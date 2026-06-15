<?php

namespace App\Services\Storefront;

use App\Models\Inventory\Product;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Session;

/**
 * A simple per-tenant session cart for the public storefront. Holds a map of
 * product_id => quantity; line/total maths mirror OrderService so what the
 * shopper sees matches the order that gets created.
 */
class CartService
{
    /** Flat delivery fee applied to delivery orders (pickup is free). */
    public const DELIVERY_FEE = 5.00;

    public function __construct(protected Tenancy $tenancy) {}

    protected function key(): string
    {
        return 'shop.cart.'.($this->tenancy->id() ?? 'none');
    }

    /** @return array<int,int> product_id => qty */
    public function raw(): array
    {
        return Session::get($this->key(), []);
    }

    protected function save(array $items): void
    {
        Session::put($this->key(), $items);
    }

    public function add(int $productId, int $qty = 1): void
    {
        $items = $this->raw();
        $items[$productId] = max(1, ($items[$productId] ?? 0) + $qty);
        $this->save($items);
    }

    public function setQty(int $productId, int $qty): void
    {
        $items = $this->raw();
        if ($qty <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $qty;
        }
        $this->save($items);
    }

    public function remove(int $productId): void
    {
        $items = $this->raw();
        unset($items[$productId]);
        $this->save($items);
    }

    public function clear(): void
    {
        Session::forget($this->key());
    }

    /** Total number of units in the cart. */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return empty($this->raw());
    }

    /**
     * Detailed cart lines (only active products that still exist), each with
     * product, qty, unit price, tax rate (fraction), and line totals.
     */
    public function lines(): array
    {
        $items = $this->raw();
        if (empty($items)) {
            return [];
        }

        $products = Product::whereIn('id', array_keys($items))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($items as $id => $qty) {
            $product = $products->get($id);
            if (! $product) {
                continue;
            }
            $qty = (int) $qty;
            $price = round((float) $product->sale_price, 2);
            $taxRate = (float) $product->tax_rate / 100;
            $lineNet = round($price * $qty, 2);
            $lineTax = round($lineNet * $taxRate, 2);

            $lines[] = [
                'product' => $product,
                'qty' => $qty,
                'unit_price' => $price,
                'tax_rate' => $taxRate,
                'line_net' => $lineNet,
                'line_total' => round($lineNet + $lineTax, 2),
            ];
        }

        return $lines;
    }

    /** Cart totals for a given fulfilment type. */
    public function totals(string $fulfillment = 'pickup'): array
    {
        $lines = $this->lines();
        $subtotal = round(array_sum(array_column($lines, 'line_net')), 2);
        $tax = round(array_sum(array_map(fn ($l) => round($l['line_net'] * $l['tax_rate'], 2), $lines)), 2);
        $deliveryFee = $fulfillment === 'delivery' ? self::DELIVERY_FEE : 0.0;

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'delivery_fee' => $deliveryFee,
            'total' => round($subtotal + $tax + $deliveryFee, 2),
        ];
    }
}
