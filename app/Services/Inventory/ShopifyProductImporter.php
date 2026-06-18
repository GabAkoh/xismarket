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
 */
class ShopifyProductImporter
{
    /** @var array<string, int> slug => category id */
    protected array $categoryCache = [];

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * @return array{created:int, updated:int, images:int, skipped:int, errors:array<int,string>}
     */
    public function import(string $path, bool $downloadImages = true): array
    {
        $result = ['created' => 0, 'updated' => 0, 'images' => 0, 'skipped' => 0, 'errors' => []];

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

        $map = [];
        foreach ($header as $i => $h) {
            $h = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)); // strip UTF-8 BOM
            $map[$h] = $i;
        }
        if (! isset($map['Handle']) && ! isset($map['Title'])) {
            fclose($fh);
            $result['errors'][] = 'This file does not look like a Shopify product export (no Handle/Title columns).';

            return $result;
        }

        $col = fn (array $row, string $name) => isset($map[$name], $row[$map[$name]]) ? trim((string) $row[$map[$name]]) : '';

        $warehouse = class_exists(Warehouse::class) ? Warehouse::default() : null;
        $context = [];
        $rowNum = 1;

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rowNum++;
            $handle = $col($row, 'Handle');
            if ($handle === '' && $col($row, 'Title') === '') {
                continue;
            }

            // First row of a Handle carries the product-level fields.
            if ($col($row, 'Title') !== '') {
                $context[$handle] = [
                    'title' => $col($row, 'Title'),
                    'description' => trim(strip_tags($col($row, 'Body (HTML)'))),
                    'category' => $col($row, 'Type') ?: $col($row, 'Product Category'),
                    'active' => $this->isActive($col($row, 'Status'), $col($row, 'Published')),
                    'image' => $col($row, 'Image Src'),
                ];
            }
            $ctx = $context[$handle] ?? null;

            // Only variant rows (a SKU or price) become products; skip image-only rows.
            if (! $ctx || ($col($row, 'Variant SKU') === '' && $col($row, 'Variant Price') === '')) {
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

    protected function importVariant(array $row, callable $col, array $ctx, string $handle, int $rowNum, ?Warehouse $warehouse, bool $downloadImages, array &$result): void
    {
        // Distinguish variants by their option values (skip Shopify's "Default Title").
        $opts = array_values(array_filter(
            [$col($row, 'Option1 Value'), $col($row, 'Option2 Value'), $col($row, 'Option3 Value')],
            fn ($v) => $v !== '' && strtolower($v) !== 'default title',
        ));
        $name = $ctx['title'].($opts ? ' - '.implode(' / ', $opts) : '');

        $sku = $col($row, 'Variant SKU');
        if ($sku === '') {
            $sku = strtoupper(Str::slug($handle.'-'.implode('-', $opts))) ?: 'SHOPIFY-'.$rowNum;
        }

        $cost = (float) ($col($row, 'Cost per item') ?: 0);
        $invQty = $col($row, 'Variant Inventory Qty');
        $trackStock = $col($row, 'Variant Inventory Tracker') === 'shopify' || $invQty !== '';

        $values = [
            'name' => $name !== '' ? $name : $sku,
            'barcode' => $col($row, 'Variant Barcode') ?: null,
            'description' => $ctx['description'] ?: null,
            'category_id' => $this->categoryId($ctx['category']),
            'cost_price' => $cost,
            'sale_price' => (float) ($col($row, 'Variant Price') ?: 0),
            'tax_rate' => 0,
            'track_stock' => $trackStock,
            'is_active' => $ctx['active'],
        ];

        if ($downloadImages) {
            $stored = $this->fetchImage($col($row, 'Variant Image') ?: $ctx['image']);
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
            return strtolower($status) === 'active';
        }

        return in_array(strtolower($published), ['true', '1'], true);
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
