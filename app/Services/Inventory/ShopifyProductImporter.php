<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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

    /** How many image downloads to run at once. */
    protected const IMAGE_CONCURRENCY = 20;

    /** @var array<string, int> slug => category id */
    protected array $categoryCache = [];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @return array{created:int, updated:int, images:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, bool $downloadImages = true, bool $refreshImages = false): array
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
        // A header row always carries a Handle (Shopify) or a SKU column. If neither
        // is present the file is almost certainly missing its header row (e.g. a split
        // chunk whose first line is data) — refuse it rather than import nothing.
        if ($idx['handle'] === null && $idx['sku'] === null) {
            fclose($fh);
            $result['errors'][] = 'Could not find a Handle or SKU column — the header row may be missing. Re-export from Shopify (Products → Export) and upload the file with its header row intact.';

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

        // image URL => [product ids that should use it]. Downloads run concurrently
        // after the rows are processed, and the same URL is fetched only once even
        // when several variants of a product share it.
        $pendingImages = [];

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
                $this->importVariant($row, $col, $ctx, $handle, $rowNum, $warehouse, $downloadImages, $refreshImages, $pendingImages, $result);
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($fh);

        if ($downloadImages && $pendingImages) {
            $this->downloadImages($pendingImages, $result);
        }

        return $result;
    }

    /** Strip Shopify's leading apostrophe (a spreadsheet text-format guard) from a code. */
    protected function cleanCode(string $value): string
    {
        return ltrim(trim($value), "'");
    }

    /** Lowercase + strip everything but a-z0-9 (and the UTF-8 BOM) for header matching. */
    protected function normalize(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return strtolower(preg_replace('/[^a-z0-9]/i', '', $header));
    }

    protected function importVariant(array $row, callable $col, array $ctx, string $handle, int $rowNum, ?Warehouse $warehouse, bool $downloadImages, bool $refreshImages, array &$pendingImages, array &$result): void
    {
        // Distinguish variants by their option values (skip Shopify's "Default Title").
        $opts = array_values(array_filter(
            [$col($row, 'option1'), $col($row, 'option2'), $col($row, 'option3')],
            fn ($v) => $v !== '' && strtolower($v) !== 'default title',
        ));
        $name = $ctx['title'].($opts ? ' - '.implode(' / ', $opts) : '');

        // Shopify prefixes numeric SKUs/barcodes with an apostrophe to force text
        // format in spreadsheets — strip it so SKUs are clean and don't duplicate.
        $sku = $this->cleanCode($col($row, 'sku'));
        if ($sku === '') {
            $sku = strtoupper(Str::slug($handle.'-'.implode('-', $opts))) ?: 'SHOPIFY-'.$rowNum;
        }

        $cost = (float) ($col($row, 'cost') ?: 0);
        $invQty = $col($row, 'invqty');
        $trackStock = $col($row, 'tracker') === 'shopify' || $invQty !== '';

        $values = [
            'name' => $name !== '' ? $name : $sku,
            'barcode' => $this->cleanCode($col($row, 'barcode')) ?: null,
            'description' => $ctx['description'] ?: null,
            'category_id' => $this->categoryId($ctx['category']),
            'cost_price' => $cost,
            'sale_price' => (float) ($col($row, 'price') ?: 0),
            'tax_rate' => 0,
            'track_stock' => $trackStock,
            'is_active' => $ctx['active'],
        ];

        $product = Product::updateOrCreate(['sku' => $sku], $values);
        $result[$product->wasRecentlyCreated ? 'created' : 'updated']++;

        // Queue the image for a concurrent download pass after all rows are read.
        // Skip products that already have an image (re-imports keep their existing
        // image) unless a refresh was requested.
        if ($downloadImages && ($refreshImages || ! $product->image_path)) {
            $url = trim((string) ($col($row, 'variantimage') ?: $ctx['image']));
            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                $pendingImages[$url][] = $product->id;
            }
        }

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

    /**
     * Download all queued images concurrently (in batches) and attach each stored
     * image to every product that referenced its URL.
     *
     * @param  array<string, array<int, int>>  $pending  url => product ids
     */
    protected function downloadImages(array $pending, array &$result): void
    {
        foreach (array_chunk(array_keys($pending), self::IMAGE_CONCURRENCY) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $url) => $pool->as($url)
                    ->timeout(15)
                    ->withUserAgent('xismarket-importer')
                    ->get($url),
                $batch,
            ));

            foreach ($batch as $url) {
                $response = $responses[$url] ?? null;
                if (! $response instanceof Response || ! $response->successful()) {
                    continue;
                }

                $bytes = $response->body();
                if ($bytes === '') {
                    continue;
                }

                $stored = $this->storeImageBytes($url, $bytes);
                if ($stored === null) {
                    continue;
                }

                // One download serves every variant/product that shared the URL.
                $ids = $pending[$url];
                $replaced = Product::whereIn('id', $ids)->pluck('image_path')->filter()->unique();
                Product::whereIn('id', $ids)->update(['image_path' => $stored]);
                $result['images'] += count($ids);

                // Drop any image files we just replaced (a --refresh-images re-run).
                foreach ($replaced as $old) {
                    if ($old !== $stored) {
                        Storage::disk('public')->delete($old);
                    }
                }
            }
        }
    }

    /** Persist downloaded image bytes to the public disk; returns the stored path. */
    protected function storeImageBytes(string $url, string $bytes): ?string
    {
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $name = 'products/shopify-'.Str::random(32).'.'.$ext;

        return Storage::disk('public')->put($name, $bytes) ? $name : null;
    }
}
