<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Public product feed at /feed.json — a flat JSON list of the default store's
 * active products for our own use (headless front-ends, integrations, exports).
 *
 * This route lives at the site root, outside the /shop/{store} group, so there
 * is no {store} slug to resolve the tenant from. We resolve the configured
 * default store ourselves and set the Tenancy service BEFORE querying — without
 * an active tenant the BelongsToTenant scope is a no-op and the feed would leak
 * every tenant's products.
 */
class FeedController extends Controller
{
    /** How long the generated feed is cached (seconds). Keeps price/stock fresh-ish. */
    protected const CACHE_TTL = 600;

    public function __invoke(Tenancy $tenancy): JsonResponse
    {
        $slug = config('storefront.default_store');

        abort_if(! $slug, 404, 'No default store configured.');

        $store = Tenant::where('slug', $slug)->where('is_active', true)->first();

        abort_if(! $store, 404, 'Store not found.');

        // Scope every subsequent query to this store.
        $tenancy->set($store);

        $payload = Cache::remember("storefront.feed.{$store->id}", self::CACHE_TTL, function () use ($store) {
            return $this->build($store);
        });

        return response()->json($payload, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** Assemble the feed payload for the given store. */
    protected function build(Tenant $store): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['category', 'variants' => fn ($v) => $v->where('is_active', true)])
            ->withSum('stocks as stock_total', 'quantity')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => $this->product($p, $store))
            ->filter(fn (array $row) => $row['in_stock']) // in-stock items only
            ->map(fn (array $row) => Arr::except($row, 'in_stock')); // internal flag, not emitted

        return [
            'store' => $store->name,
            'currency' => $store->currency,
            'count' => $products->count(),
            'generated_at' => now()->toIso8601String(),
            'products' => $products->values()->all(),
        ];
    }

    /** One product row in the feed. */
    protected function product(Product $product, Tenant $store): array
    {
        // Min/max across active variants (from the eager-loaded relation, so no
        // per-product query), falling back to the product's own price.
        $prices = $product->variants->map(fn ($v) => (float) $v->sale_price);
        $min = $prices->isEmpty() ? (float) $product->sale_price : (float) $prices->min();
        $max = $prices->isEmpty() ? (float) $product->sale_price : (float) $prices->max();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'category' => $product->category?->name,
            'price' => $min,
            'price_max' => $max,
            'currency' => $store->currency,
            // Kept so the mapped row can be filtered to in-stock items; dropped
            // from the emitted payload below (the feed only lists in-stock items).
            'in_stock' => ! $product->track_stock || (float) $product->stock_total > 0,
            'url' => route('shop.product', ['store' => $store->slug, 'product' => $product->id]),
            'image' => $product->image_path ? asset('storage/'.$product->image_path) : null,
        ];
    }
}
