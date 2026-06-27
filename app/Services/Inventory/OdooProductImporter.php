<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy;
use Illuminate\Support\Str;

/**
 * Imports products from an Odoo product CSV export (Inventory/Sales → Products
 * → Export). One row per product. Only products whose NAME does not already
 * exist here are created — existing ones are skipped, never updated.
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
     * @return array{created:int, updated:int, images:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path): array
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

        $warehouse = class_exists(Warehouse::class) ? Warehouse::default() : null;

        // Names already present in this store — the dedupe set (lower-cased).
        $existing = Product::query()->pluck('name')
            ->map(fn ($n) => $this->key($n))->flip();

        $rowNum = 1;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rowNum++;
            $name = $col($row, 'name');
            if ($name === '') {
                continue; // blank line / sub-row
            }

            $nameKey = $this->key($name);
            if ($existing->has($nameKey)) {
                $result['skipped']++;   // already available here — skip
                continue;
            }

            try {
                $this->createProduct($row, $col, $name, $warehouse, $result);
                $existing->put($nameKey, true);  // guard against duplicate names within the file
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
        $cost = (float) ($col($row, 'cost') ?: 0);
        $qty = $col($row, 'qty');
        $trackStock = is_numeric($qty);

        $product = Product::create([
            'name' => $name,
            'sku' => $this->uniqueSku($col($row, 'sku'), $name),
            'barcode' => $col($row, 'barcode') ?: null,
            'description' => $col($row, 'description') ?: null,
            'category_id' => $this->categoryId($col($row, 'category')),
            'cost_price' => $cost,
            'sale_price' => (float) ($col($row, 'price') ?: 0),
            'tax_rate' => 0,
            'track_stock' => $trackStock,
            'is_active' => $this->isActive($col($row, 'active')),
        ]);
        $result['created']++;

        // Opening stock from "Quantity On Hand".
        if ($trackStock && $warehouse && (float) $qty != 0 && class_exists(StockService::class)) {
            app(StockService::class)->recordMovement(
                $product, $warehouse, 'import', (float) $qty, $cost, null, 'Odoo import',
            );
        }
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
