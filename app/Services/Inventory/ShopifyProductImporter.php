<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports products from a Shopify "Products" CSV export.
 *
 * Shopify lists one row per variant (and extra rows for additional images),
 * with product-level fields (Title, Body, Type, …) only on the first row of
 * each Handle. We import one product per variant, inheriting those
 * product-level fields, and optionally download the variant/product image.
 *
 * Header matching is lenient: headers are normalised (case/space/punctuation
 * insensitive) and resolved from a list of common aliases, so exports or
 * hand-edited files using e.g. "SKU"/"Price"/"Name"/"Cost"/"Quantity" still work.
 */
class ShopifyProductImporter
{
    /**
     * Logical field => accepted header aliases (already normalised: lowercase,
     * alphanumeric only). The first alias found in the header row wins.
     *
     * @var array<string, array<int, string>>
     */
    protected const FIELDS = [
        'handle' => ['handle'],
        'title' => ['title', 'name', 'productname', 'producttitle', 'product'],
        'body' => ['bodyhtml', 'body', 'description', 'desc', 'details'],
        'type' => ['type', 'producttype', 'productcategory', 'category', 'categories', 'collection'],
        'published' => ['published', 'visible'],
        'status' => ['status'],
        'option1' => ['option1value', 'option1', 'variant', 'variantname'],
        'option2' => ['option2value', 'option2'],
        'option3' => ['option3value', 'option3'],
        'sku' => ['variantsku', 'sku', 'skucode', 'itemnumber'],
        'barcode' => ['variantbarcode', 'barcode', 'upc', 'ean', 'gtin'],
        'price' => ['variantprice', 'price', 'saleprice', 'retailprice', 'sellingprice', 'unitprice'],
        'cost' => ['costperitem', 'cost', 'costprice', 'unitcost', 'buyprice', 'purchaseprice'],
        'invqty' => ['variantinventoryqty', 'inventoryqty', 'inventoryquantity', 'quantity', 'qty', 'stock', 'stockquantity', 'inventory', 'onhand'],
        'tracker' => ['variantinventorytracker', 'inventorytracker'],
        'image' => ['imagesrc', 'image', 'imageurl', 'imagelink', 'photo'],
        'variantimage' => ['variantimage'],
    ];

    /** @var array<string, int> slug => category id */
    protected array $categoryCache = [];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @return array{created:int, updated:int, images:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, bool $downloadImages = true): array
    {
        $result = ['created' => 0, 'updated' => 0, 'images' => 0, 'skipped' => 0, 'errors' => []];

        if (! is_file($path) || ! is_readable($path)) {
            $result['errors'][] = 'Import file not found or unreadable: '.$path;

            return $result;
        }

        $fh = fopen($path, 'r');
        if ($fh === false) {
            $result['errors'][] = 'Could not open the uploaded file.';

            return $result;
        }

        // RFC-4180 parsing (empty $escape) — matches Shopify's quoting.
        $header = fgetcsv($fh, 0, ',', '"', '');
        if ($header === false) {
            fclose($fh);
            $result['errors'][] = 'The file is empty.';

            return $result;
        }

        // Resolve each logical field to a column index via normalised aliases.
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

        if ($idx['title'] === null) {
            fclose($fh);
            $result['errors'][] = 'No product name column found (expected Title, Name, or similar).';

            return $result;
        }
        if ($idx['sku'] === null && $idx['price'] === null) {
            fclose($fh);
            $result['errors'][] = 'No SKU or Price column found — nothing to import.';

            return $result;
        }

        $col = fn (array $row, string $field) => $idx[$field] !== null && isset($row[$idx[$field]])
            ? trim((string) $row[$idx[$field]])
            : '';

        $warehouse = class_exists(Warehouse::class) ? Warehouse::default() : null;
        $context = [];
        $rowNum = 1;

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rowNum++;
            $handle = $col($row, 'handle');
            if ($handle === '' && $col($row, 'title') === '') {
                continue;
            }

            // First row of a Handle carries the product-level fields.
            if ($col($row, 'title') !== '') {
                $context[$handle] = [
                    'title' => $col($row, 'title'),
                    'description' => trim(strip_tags($col($row, 'body'))),
                    'category' => $col($row, 'type'),
                    'active' => $this->isActive($col($row, 'status'), $col($row, 'published')),
                    'image' => $col($row, 'image'),
                ];
            }
            $ctx = $context[$handle] ?? null;

            // Only variant rows (a SKU or price) become products; skip image-only rows.
            if (! $ctx || ($col($row, 'sku') === '' && $col($row, 'price') === '')) {
                continue;
            }

            try {
                $this->importVariant($row, $col, $ctx, $handle, $rowNum, $warehouse, $downloadImages, $result);
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($fh);

        return $result;
    }

    /** Lowercase + strip everything but a-z0-9 (and the UTF-8 BOM) for header matching. */
    protected function normalize(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return strtolower(preg_replace('/[^a-z0-9]/i', '', $header));
    }

    protected function importVariant(array $row, callable $col, array $ctx, string $handle, int $rowNum, ?Warehouse $warehouse, bool $downloadImages, array &$result): void
    {
        // Distinguish variants by their option values (skip Shopify's "Default Title").
        $opts = array_values(array_filter(
            [$col($row, 'option1'), $col($row, 'option2'), $col($row, 'option3')],
            fn ($v) => $v !== '' && strtolower($v) !== 'default title',
        ));
        $name = $ctx['title'].($opts ? ' - '.implode(' / ', $opts) : '');

        $sku = $col($row, 'sku');
        if ($sku === '') {
            $sku = strtoupper(Str::slug($handle.'-'.implode('-', $opts))) ?: 'SHOPIFY-'.$rowNum;
        }

        $cost = (float) ($col($row, 'cost') ?: 0);
        $invQty = $col($row, 'invqty');
        $trackStock = $col($row, 'tracker') === 'shopify' || $invQty !== '';

        $values = [
            'name' => $name !== '' ? $name : $sku,
            'barcode' => $col($row, 'barcode') ?: null,
            'description' => $ctx['description'] ?: null,
            'category_id' => $this->categoryId($ctx['category']),
            'cost_price' => $cost,
            'sale_price' => (float) ($col($row, 'price') ?: 0),
            'tax_rate' => 0,
            'track_stock' => $trackStock,
            'is_active' => $ctx['active'],
        ];

        if ($downloadImages) {
            $stored = $this->fetchImage($col($row, 'variantimage') ?: $ctx['image']);
            if ($stored) {
                $values['image_path'] = $stored;
                $result['images']++;
            }
        }

        $product = Product::updateOrCreate(['sku' => $sku], $values);
        $result[$product->wasRecentlyCreated ? 'created' : 'updated']++;

        // Opening stock — only on first import to avoid double-counting on re-runs.
        if ($product->wasRecentlyCreated && $trackStock && $warehouse && is_numeric($invQty) && (float) $invQty != 0
            && class_exists(StockService::class)) {
            app(StockService::class)->recordMovement(
                $product, $warehouse, 'import', (float) $invQty, $cost, null, 'Shopify import',
            );
        }
    }

    protected function isActive(string $status, string $published): bool
    {
        if ($status !== '') {
            return in_array(strtolower($status), ['active', 'published', 'enabled', 'visible', '1', 'true', 'yes'], true);
        }

        return in_array(strtolower($published), ['true', '1', 'yes'], true);
    }

    protected function categoryId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $slug = Str::slug($name) ?: 'cat-'.md5($name);
        if (isset($this->categoryCache[$slug])) {
            return $this->categoryCache[$slug];
        }

        return $this->categoryCache[$slug] = Category::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
    }

    /** Download an image URL to the public disk; returns the stored path or null. */
    protected function fetchImage(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'xismarket-importer']]);
        $bytes = @file_get_contents($url, false, $ctx);
        if ($bytes === false || strlen($bytes) === 0) {
            return null;
        }

        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $name = 'products/shopify-'.Str::random(32).'.'.$ext;
        Storage::disk('public')->put($name, $bytes);

        return $name;
    }
}
