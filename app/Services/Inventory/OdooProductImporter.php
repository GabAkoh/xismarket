<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy;
use Illuminate\Support\Str;

/**
 * Imports products from an Odoo product CSV export (Inventory/Sales → Products
 * → Export). One row per product. Products are matched on Internal Reference
 * (SKU) — falling back to name when a row has none. In "create" mode only
 * products that don't already exist here are added; existing ones are skipped.
 *
 * Header matching is lenient: headers are normalised (case/space/punctuation
 * insensitive) and resolved from a list of aliases, so both Odoo's technical
 * field names (default_code, list_price, …) and the human labels (Internal
 * Reference, Sales Price, …) are recognised.
 */
class OdooProductImporter
{
    /**
     * Logical field => accepted header aliases (normalised: lowercase, a-z0-9).
     *
     * @var array<string, array<int, string>>
     */
    protected const FIELDS = [
        'name' => ['name', 'productname', 'product', 'producttemplate'],
        'sku' => ['internalreference', 'defaultcode', 'reference', 'sku', 'code', 'itemnumber'],
        'barcode' => ['barcode', 'ean13', 'ean', 'upc', 'gtin'],
        'price' => ['salesprice', 'saleprice', 'listprice', 'price', 'unitprice', 'publicprice'],
        'cost' => ['cost', 'standardprice', 'costprice', 'purchaseprice'],
        'category' => ['productcategory', 'category', 'categid', 'internalcategory', 'productcategoryname', 'poscategory'],
        'qty' => ['quantityonhand', 'qtyavailable', 'onhand', 'quantity', 'qty', 'stock'],
        'active' => ['active', 'canbesold', 'saleok', 'status'],
        'description' => ['salesdescription', 'description', 'descriptionforcustomers'],
    ];

    /** @var array<string, int> slug => category id */
    protected array $categoryCache = [];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @param  string  $mode  'create' (only add products not already here) or
     *                        'update' (only update existing products' sale price
     *                        + on-hand quantity). Matched by Internal Reference
     *                        (SKU), falling back to name.
     * @return array{created:int, updated:int, images:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, string $mode = 'create'): array
    {
        $result = ['created' => 0, 'updated' => 0, 'images' => 0, 'skipped' => 0, 'errors' => []];

        if (! is_file($path) || ! is_readable($path)) {
            $result['errors'][] = 'Import file not found or unreadable.';

            return $result;
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            $result['errors'][] = 'Could not open the uploaded file.';

            return $result;
        }

        $header = fgetcsv($fh, 0, ',', '"', '');
        if ($header === false) {
            fclose($fh);
            $result['errors'][] = 'The file is empty.';

            return $result;
        }

        $norm = [];
        foreach ($header as $i => $h) {
            $key = $this->normalize((string) $h);
            if ($key !== '' && ! isset($norm[$key])) {
                $norm[$key] = $i;
            }
        }
        $idx = [];
        foreach (self::FIELDS as $field => $aliases) {
            $idx[$field] = null;
            foreach ($aliases as $alias) {
                if (isset($norm[$alias])) {
                    $idx[$field] = $norm[$alias];
                    break;
                }
            }
        }

        if ($idx['name'] === null) {
            fclose($fh);
            $result['errors'][] = 'No product name column found (expected "Name"). Re-export from Odoo with the Name column.';

            return $result;
        }

        $col = fn (array $row, string $field) => $idx[$field] !== null && isset($row[$idx[$field]])
            ? trim((string) $row[$idx[$field]])
            : '';

        // --- Update mode: only touch products already here (matched by name) ---
        if ($mode === 'update') {
            return $this->runUpdate($fh, $col, $idx, $result);
        }

        $warehouse = class_exists(Warehouse::class) ? Warehouse::default() : null;

        // Dedupe sets: existing Internal References (SKUs) — the primary key —
        // and names as a fallback for rows that carry no reference.
        $existingSku = Product::query()->whereNotNull('sku')->where('sku', '!=', '')
            ->pluck('sku')->map(fn ($s) => strtolower(trim($s)))->flip();
        $existingName = Product::query()->pluck('name')
            ->map(fn ($n) => $this->key($n))->flip();

        $rowNum = 1;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rowNum++;
            $name = $col($row, 'name');
            $sku = $col($row, 'sku');
            if ($name === '' && $sku === '') {
                continue; // blank line / sub-row
            }

            $skuKey = $sku !== '' ? strtolower($sku) : null;
            $nameKey = $name !== '' ? $this->key($name) : null;

            // Skip when the reference already exists; for rows without a
            // reference, fall back to matching on name.
            $exists = $skuKey !== null
                ? $existingSku->has($skuKey)
                : ($nameKey !== null && $existingName->has($nameKey));
            if ($exists) {
                $result['skipped']++;   // already available here — skip
                continue;
            }

            try {
                $this->createProduct($row, $col, $name !== '' ? $name : $sku, $warehouse, $result);
                if ($skuKey !== null) {
                    $existingSku->put($skuKey, true);  // guard against dups within the file
                }
                if ($nameKey !== null) {
                    $existingName->put($nameKey, true);
                }
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = "Row {$rowNum} (".($name !== '' ? $name : $sku)."): ".$e->getMessage();
            }
        }

        fclose($fh);

        return $result;
    }

    /**
     * Update the sale price and/or on-hand quantity of products that already
     * exist here (matched by Internal Reference, then name). Quantity is set to
     * the file's value via a stock adjustment (delta from the current on-hand),
     * keeping the inventory ledger consistent. Rows with no match are skipped.
     *
     * @param  resource  $fh
     */
    protected function runUpdate($fh, callable $col, array $idx, array $result): array
    {
        if ($idx['price'] === null && $idx['qty'] === null) {
            fclose($fh);
            $result['errors'][] = 'No Sales Price or Quantity On Hand column found — nothing to update.';

            return $result;
        }

        // Match on Internal Reference (SKU) first — stable and encoding-safe —
        // then fall back to name. (last occurrence wins)
        $bySku = [];
        $byName = [];
        foreach (Product::query()->get(['id', 'sku', 'name']) as $p) {
            if (trim((string) $p->sku) !== '') {
                $bySku[strtolower(trim($p->sku))] = $p->id;
            }
            $byName[$this->key($p->name)] = $p->id;
        }

        $warehouse = ($idx['qty'] !== null && class_exists(Warehouse::class)) ? Warehouse::default() : null;
        $stock = ($warehouse && class_exists(StockService::class)) ? app(StockService::class) : null;

        $rowNum = 1;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rowNum++;
            $name = $col($row, 'name');
            $sku = $col($row, 'sku');
            if ($name === '' && $sku === '') {
                continue;
            }

            $id = ($sku !== '' ? ($bySku[strtolower($sku)] ?? null) : null)
                ?? ($name !== '' ? ($byName[$this->key($name)] ?? null) : null);
            if (! $id) {
                $result['skipped']++;   // not the same product — leave it alone
                continue;
            }

            try {
                $changed = false;

                // Sale price.
                $price = $this->number($col($row, 'price'));
                if ($idx['price'] !== null && $price !== null) {
                    Product::where('id', $id)->update(['sale_price' => $price]);
                    $changed = true;
                }

                // On-hand quantity → set to the file value via an adjustment.
                $target = $this->number($col($row, 'qty'));
                if ($idx['qty'] !== null && $target !== null && $stock && $warehouse) {
                    $product = Product::find($id);
                    if ($product) {
                        $current = round($product->stockIn($warehouse), 3);
                        $delta = round($target - $current, 3);
                        if ($delta != 0.0) {
                            $stock->recordMovement(
                                $product, $warehouse, 'adjustment', $delta,
                                (float) $product->cost_price, null, 'Odoo update',
                            );
                        }
                        if (! $product->track_stock) {
                            $product->update(['track_stock' => true]);
                        }
                        $changed = true;
                    }
                }

                $changed ? $result['updated']++ : $result['skipped']++;
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = "Row {$rowNum} ({$name}): ".$e->getMessage();
            }
        }

        fclose($fh);

        return $result;
    }

    protected function createProduct(array $row, callable $col, string $name, ?Warehouse $warehouse, array &$result): void
    {
        $cost = $this->number($col($row, 'cost')) ?? 0.0;
        $qty = $this->number($col($row, 'qty'));
        $trackStock = $qty !== null;

        $product = Product::create([
            'name' => $name,
            'sku' => $this->uniqueSku($col($row, 'sku'), $name),
            'barcode' => $col($row, 'barcode') ?: null,
            'description' => $col($row, 'description') ?: null,
            'category_id' => $this->categoryId($col($row, 'category')),
            'cost_price' => $cost,
            'sale_price' => $this->number($col($row, 'price')) ?? 0.0,
            'tax_rate' => 0,
            'track_stock' => $trackStock,
            'is_active' => $this->isActive($col($row, 'active')),
        ]);
        $result['created']++;

        // Opening stock from "Quantity On Hand".
        if ($trackStock && $warehouse && $qty != 0.0 && class_exists(StockService::class)) {
            app(StockService::class)->recordMovement(
                $product, $warehouse, 'import', $qty, $cost, null, 'Odoo import',
            );
        }
    }

    /**
     * Parse a numeric cell tolerant of currency symbols and thousands
     * separators — e.g. Odoo's "530,000.00" or "N 1,200.50" → 530000.0 / 1200.5.
     * Returns null when there's no usable number.
     */
    protected function number(string $value): ?float
    {
        $v = str_replace(',', '', trim($value));     // drop thousands separators
        $v = preg_replace('/[^0-9.\-]/', '', $v);    // strip currency symbols/spaces

        return ($v !== '' && is_numeric($v)) ? (float) $v : null;
    }

    /** A SKU that's unique per tenant: the Internal Reference, else a slug of the name. */
    protected function uniqueSku(string $reference, string $name): string
    {
        $base = $reference !== ''
            ? $reference
            : (strtoupper(Str::slug($name)) ?: 'ODOO');

        $sku = $base;
        $n = 1;
        while (Product::where('sku', $sku)->exists()) {
            $sku = $base.'-'.(++$n);
        }

        return $sku;
    }

    /** Odoo "Active"/"Can be Sold" is truthy; default to active when absent. */
    protected function isActive(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'active', 'enabled', 'published'], true);
    }

    protected function categoryId(string $name): ?int
    {
        // Odoo categories are paths like "All / Saleable / Office" — keep the leaf.
        $name = trim((string) Str::of($name)->afterLast('/'));
        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name) ?: 'cat-'.md5($name);
        if (isset($this->categoryCache[$slug])) {
            return $this->categoryCache[$slug];
        }

        return $this->categoryCache[$slug] = Category::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
    }

    /** Normalised name key for dedupe (trim + lowercase + collapse whitespace). */
    protected function key(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    /** Lowercase + strip everything but a-z0-9 (and the UTF-8 BOM) for header matching. */
    protected function normalize(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return strtolower(preg_replace('/[^a-z0-9]/i', '', $header));
    }
}
