<?php

namespace App\Services\Storefront;

use App\Models\Orders\OrderItem;
use App\Models\Pos\SaleItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;

/**
 * Computes and caches the per-tenant "bestsellers" ranking — products ordered by
 * real units sold across completed POS sales and fulfilled online orders.
 *
 * The aggregation is expensive (two grouped joins) so it's cached per tenant and
 * invalidated explicitly whenever a sale or order completes (see SaleService /
 * OrderService). The cache key is tenant-only — the full ranked list is stored
 * and sliced to the requested size — so a single forget() clears every caller.
 */
class BestsellerService
{
    /** Safety-net TTL; the ranking is normally refreshed by explicit invalidation. */
    protected const TTL_MINUTES = 1440;

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Ranked product IDs (highest-selling first), capped to $limit.
     *
     * @return array<int, int>
     */
    public function topProductIds(int $limit): array
    {
        $all = Cache::remember(
            $this->cacheKey($this->tenancy->id()),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => $this->compute(),
        );

        return array_slice($all, 0, $limit);
    }

    /** Drop the cached ranking for a tenant (defaults to the current one). */
    public function forget(?int $tenantId = null): void
    {
        Cache::forget($this->cacheKey($tenantId ?? $this->tenancy->id()));
    }

    protected function cacheKey(?int $tenantId): string
    {
        return "storefront:bestsellers:{$tenantId}";
    }

    /**
     * Run the sales aggregation. Tenant scoping comes from the SaleItem/OrderItem
     * global scopes (which qualify tenant_id by table, so the joins stay safe).
     *
     * @return array<int, int> full ranked list of product ids
     */
    protected function compute(): array
    {
        $tally = [];

        $accumulate = function ($rows) use (&$tally) {
            foreach ($rows as $productId => $qty) {
                $tally[$productId] = ($tally[$productId] ?? 0) + (float) $qty;
            }
        };

        // Completed POS sales (exclude voided / refunded). The inner join to
        // products — restricted to active rows — drops any product that has
        // since been deleted or deactivated, so the ranking only ever contains
        // products still in the live catalogue.
        $accumulate(SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('products.is_active', true)
            ->whereNotIn('sales.status', ['void', 'refunded'])
            ->whereNotNull('sale_items.product_id')
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id as pid, SUM(sale_items.quantity - sale_items.returned_quantity) as qty')
            ->pluck('qty', 'pid'));

        // Fulfilled online orders (revenue is recognised at fulfilment == completed).
        $accumulate(OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.is_active', true)
            ->where('orders.status', 'completed')
            ->where('orders.payment_status', '!=', 'refunded')
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id as pid, SUM(order_items.quantity) as qty')
            ->pluck('qty', 'pid'));

        arsort($tally);

        return array_map('intval', array_keys($tally));
    }
}
