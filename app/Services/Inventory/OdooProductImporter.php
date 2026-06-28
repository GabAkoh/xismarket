<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVariant;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;
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
        // Variant-level export: the template groups variants; variant values hold
        // the option axes, e.g. "Color: Red, Size: M".
        'template' => ['producttemplate', 'producttmpl', 'producttemplatename', 'template'],
        'variantvalues' => ['variantvalues', 'attributevalues', 'variantvalue', 'attributevalue', 'variantattributes', 'productvariantvalues'],
        // Base64 image data (Odoo image/binary fields export inline). Larger first.
        'image' => ['image', 'image1920', 'image1024', 'image512', 'image256', 'image128', 'productimage'],
    ];

    /** @var array<string, int> slug => category id */
    protected array $categoryCache = [];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @param  string  $mode  'create' (only add products not already here),
     *                        'update' (sale price + on-hand quantity of existing
     *                        products), or 'images' (set images from a base64
     *                        Image column). Matched by Internal Reference (SKU),
     *                        falling back to name.
     * @param  bool  $replaceImages  in 'images' mode, overwrite an existing image
     *                               instead of only filling products that have none.
     * @return array{created:int, updated:int, variants:int, images:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, string $mode = 'create', bool $replaceImages = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'variants' => 0, 'images' => 0, 'skipped' => 0, 'errors' => []];

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

        // Images mode matches by SKU, so a Name column isn't required there.
        if ($idx['name'] === null && $mode !== 'images') {
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

        // --- Images mode: set product images from a base64 Image column ---
        if ($mode === 'images') {
            return $this->runImageUpdate($fh, $col, $idx, $result, $replaceImages);
        }

        $warehouse = class_exists(Warehouse::class) ? Warehouse::default() : null;

        // Dedupe sets: existing Internal References (SKUs) — the primary key —
        // and names as a fallback for rows that carry no reference.
        $existingSku = Product::query()->whereNotNull('sku')->where('sku', '!=', '')
            ->pluck('sku')->map(fn ($s) => strtolower(trim($s)))->flip();
        $existingName = Product::query()->pluck('name')
            ->map(fn ($n) => $this->key($n))->flip();

        // A variant-level export (with a Variant Values column) groups rows into
        // products with real variants; otherwise each row is its own product.
        if ($idx['variantvalues'] !== null) {
            return $this->runVariantCreate($fh, $col, $idx, $result, $existingSku, $existingName, $warehouse);
        }

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
                // The default variant carries the price/stock for a single-variant product.
                $variant = ProductVariant::where('product_id', $id)->orderBy('position')->first();

                // Sale price — on the product (denormalised) and its default variant.
                $price = $this->number($col($row, 'price'));
                if ($idx['price'] !== null && $price !== null) {
                    Product::where('id', $id)->update(['sale_price' => $price]);
                    $variant?->update(['sale_price' => $price]);
                    $changed = true;
                }

                // On-hand quantity → set to the file value via a variant stock adjustment.
                $target = $this->number($col($row, 'qty'));
                if ($idx['qty'] !== null && $target !== null && $stock && $warehouse && $variant) {
                    $current = round($variant->stockIn($warehouse), 3);
                    $delta = round($target - $current, 3);
                    if ($delta != 0.0) {
                        $stock->recordMovement(
                            Product::find($id), $warehouse, 'adjustment', $delta,
                            (float) $variant->cost_price, null, 'Odoo update', $variant->id,
                        );
                    }
                    Product::where('id', $id)->where('track_stock', false)->update(['track_stock' => true]);
                    $changed = true;
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

    /**
     * Reconstruct grouped products from an Odoo variant-level export. Rows are
     * grouped by product template (or the variant name with its parenthetical
     * stripped); option axes come from the "Variant Values" column (e.g.
     * "Color: Red, Size: M"). Only products whose name isn't already here are
     * created — so this adds Odoo-only products on top of an existing catalogue.
     *
     * @param  resource  $fh
     */
    protected function runVariantCreate($fh, callable $col, array $idx, array $result, $existingSku, $existingName, ?Warehouse $warehouse): array
    {
        // Group all rows by their product template.
        $groups = [];
        $order = [];
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $template = $col($row, 'template');
            $groupName = $template !== '' ? $template : $this->stripVariantSuffix($col($row, 'name'));
            if ($groupName === '') {
                continue;
            }
            $gk = $this->key($groupName);
            if (! isset($groups[$gk])) {
                $groups[$gk] = ['name' => $groupName, 'category' => $col($row, 'category'), 'rows' => []];
                $order[] = $gk;
            }
            $groups[$gk]['rows'][] = [
                'sku' => $col($row, 'sku'),
                'barcode' => $col($row, 'barcode'),
                'price' => $this->number($col($row, 'price')),
                'cost' => $this->number($col($row, 'cost')),
                'qty' => $this->number($col($row, 'qty')),
                'active' => $col($row, 'active'),
                'pairs' => $this->parseVariantValues($col($row, 'variantvalues')),
            ];
        }
        fclose($fh);

        foreach ($order as $gk) {
            $g = $groups[$gk];

            if ($existingName->has($this->key($g['name']))) {
                $result['skipped'] += count($g['rows']);   // already here — skip
                continue;
            }

            try {
                // Option axis names = union of attribute keys across the variants (max 3).
                $optionNames = [];
                foreach ($g['rows'] as $r) {
                    foreach (array_keys($r['pairs']) as $attr) {
                        if (! in_array($attr, $optionNames, true)) {
                            $optionNames[] = $attr;
                        }
                    }
                }
                $optionNames = array_slice($optionNames, 0, 3);

                $product = Product::create([
                    'name' => $g['name'],
                    'option1_name' => $optionNames[0] ?? null,
                    'option2_name' => $optionNames[1] ?? null,
                    'option3_name' => $optionNames[2] ?? null,
                    'category_id' => $this->categoryId($g['category']),
                    'tax_rate' => 0,
                    'track_stock' => true,
                    'is_active' => $this->isActive($g['rows'][0]['active']),
                    'sku' => 'TMP-'.Str::random(10),
                    'sale_price' => 0,
                    'cost_price' => 0,
                ]);
                $result['created']++;

                foreach ($g['rows'] as $i => $r) {
                    $opt = [];
                    foreach ([0, 1, 2] as $j) {
                        $opt[$j] = isset($optionNames[$j]) ? ($r['pairs'][$optionNames[$j]] ?? null) : null;
                    }
                    $sku = $r['sku'] !== ''
                        ? $r['sku']
                        : (strtoupper(Str::slug($g['name'].'-'.implode('-', array_filter($opt)))) ?: 'VAR');
                    $sku = $this->makeUniqueSku($sku);

                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $sku,
                        'barcode' => $r['barcode'] ?: null,
                        'option1' => $opt[0], 'option2' => $opt[1], 'option3' => $opt[2],
                        'cost_price' => $r['cost'] ?? 0,
                        'sale_price' => $r['price'] ?? 0,
                        'position' => $i,
                    ]);
                    $result['variants'] = ($result['variants'] ?? 0) + 1;

                    if ($i === 0) {
                        $product->update([
                            'sku' => $sku, 'barcode' => $variant->barcode,
                            'sale_price' => $variant->sale_price, 'cost_price' => $variant->cost_price,
                        ]);
                    }

                    if ($warehouse && $r['qty'] !== null && $r['qty'] != 0.0 && class_exists(StockService::class)) {
                        app(StockService::class)->recordMovement(
                            $product, $warehouse, 'import', $r['qty'], $r['cost'] ?? 0, null, 'Odoo import', $variant->id,
                        );
                    }
                }

                $existingName->put($this->key($g['name']), true);
            } catch (\Throwable $e) {
                $result['skipped'] += count($g['rows']);
                $result['errors'][] = $g['name'].': '.$e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Set product images from a base64 Image column. Each row is matched to an
     * existing product by SKU (Internal Reference) first, then by name/template;
     * the decoded image becomes that product's main image (and the variant's).
     * Used for an export where the URL route only serves placeholders.
     *
     * @param  resource  $fh
     */
    protected function runImageUpdate($fh, callable $col, array $idx, array $result, bool $replace = false): array
    {
        if ($idx['image'] === null) {
            fclose($fh);
            $result['errors'][] = 'No Image column found. Re-export from Odoo including the "Image" field (base64).';

            return $result;
        }

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $raw = isset($row[$idx['image']]) ? trim((string) $row[$idx['image']]) : '';
            if ($raw === '') {
                $result['skipped']++;

                continue;
            }

            // Match an existing product: variant SKU first, then name/template.
            $sku = $col($row, 'sku');
            $variant = $sku !== '' ? ProductVariant::where('sku', $sku)->first() : null;
            $product = $variant?->product;
            if (! $product) {
                $name = $col($row, 'template') !== '' ? $col($row, 'template') : $this->stripVariantSuffix($col($row, 'name'));
                $product = $name !== '' ? Product::whereRaw('LOWER(name) = ?', [strtolower($name)])->first() : null;
            }
            if (! $product) {
                $result['skipped']++;

                continue;
            }

            // By default only fill products that have no image yet, so an existing
            // (e.g. higher-res Shopify) image is never clobbered. With $replace,
            // overwrite it and remove the old file.
            if ($product->image_path && ! $replace) {
                $result['skipped']++;

                continue;
            }

            $bytes = $this->decodeBase64Image($raw);
            $stored = $bytes !== null ? $this->storeImageData($bytes) : null;
            if (! $stored) {
                $result['skipped']++;

                continue;
            }

            $old = $product->image_path;
            $product->update(['image_path' => $stored]);
            $variant?->update(['image_path' => $stored]);
            if ($old && $old !== $stored) {
                Storage::disk('public')->delete($old);
            }
            $result['images']++;
        }
        fclose($fh);

        return $result;
    }

    /** Decode a base64 image cell (handles a data: URI prefix and whitespace). */
    protected function decodeBase64Image(string $raw): ?string
    {
        if (str_contains($raw, 'base64,')) {
            $raw = substr($raw, strpos($raw, 'base64,') + 7);
        }
        $raw = preg_replace('/\s+/', '', $raw);
        $bytes = base64_decode($raw, true);

        // Reject empty/invalid data (a real image is well over a few hundred bytes).
        return ($bytes !== false && strlen($bytes) > 200) ? $bytes : null;
    }

    /** Save decoded image bytes to the public disk, returning the stored path. */
    protected function storeImageData(string $bytes): ?string
    {
        $ext = 'jpg';
        if (str_starts_with($bytes, "\x89PNG")) {
            $ext = 'png';
        } elseif (str_starts_with($bytes, "GIF8")) {
            $ext = 'gif';
        } elseif (str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP')) {
            $ext = 'webp';
        }

        $name = 'products/odoo-'.Str::random(32).'.'.$ext;

        return Storage::disk('public')->put($name, $bytes) ? $name : null;
    }

    /** Parse Odoo "Variant Values" like "Color: Red, Size: M" → ['Color'=>'Red','Size'=>'M']. */
    protected function parseVariantValues(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $pairs = [];
        $pos = 0;
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_contains($part, ':')) {
                [$attr, $val] = explode(':', $part, 2);
                $attr = trim($attr);
                $val = trim($val);
                if ($attr !== '' && $val !== '') {
                    $pairs[$attr] = $val;
                }
            } else {
                // A bare value with no attribute name → positional option.
                $pairs['Option '.(++$pos)] = $part;
            }
        }

        return $pairs;
    }

    /** Strip a trailing variant parenthetical: "Tee (Red, M)" → "Tee". */
    protected function stripVariantSuffix(string $name): string
    {
        return trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $name));
    }

    /** A SKU unique across both products and variants for this tenant. */
    protected function makeUniqueSku(string $base): string
    {
        $sku = $base;
        $n = 1;
        while (ProductVariant::where('sku', $sku)->exists() || Product::where('sku', $sku)->exists()) {
            $sku = $base.'-'.(++$n);
        }

        return $sku;
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

        // Odoo rows are single products → one default variant mirroring the product.
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'sale_price' => $product->sale_price,
            'cost_price' => $product->cost_price,
            'position' => 0,
        ]);

        // Opening stock from "Quantity On Hand" — on the variant.
        if ($trackStock && $warehouse && $qty != 0.0 && class_exists(StockService::class)) {
            app(StockService::class)->recordMovement(
                $product, $warehouse, 'import', $qty, $cost, null, 'Odoo import', $variant->id,
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
