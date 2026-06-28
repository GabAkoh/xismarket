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

    protected function couponKey(): string
    {
        return 'shop.coupon.'.($this->tenancy->id() ?? 'none');
    }

    /** The coupon code applied to this cart, if any. */
    public function couponCode(): ?string
    {
        return Session::get($this->couponKey());
    }

    public function setCoupon(string $code): void
    {
        Session::put($this->couponKey(), strtoupper(trim($code)));
    }

    public function clearCoupon(): void
    {
        Session::forget($this->couponKey());
    }

    /** Discount the applied coupon yields against the current subtotal (0 if invalid). */
    public function couponDiscount(float $subtotal): float
    {
        $code = $this->couponCode();
        if (! $code) {
            return 0.0;
        }

        return (float) app(\App\Services\Marketing\CouponService::class)->evaluate($code, $subtotal)['discount'];
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

    public function add(int $productId, ?int $variantId = null, int $qty = 1): void
    {
        $items = $this->raw();
        $k = $this->lineKey($productId, $variantId);
        $items[$k] = max(1, ($items[$k] ?? 0) + $qty);
        $this->save($items);
    }

    /** Cart key for a product+variant ("pid:vid"; vid 0 means the default variant). */
    protected function lineKey(int $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?: 0);
    }

    public function setQty(int $productId, ?int $variantId, int $qty): void
    {
        $items = $this->raw();
        $k = $this->lineKey($productId, $variantId);
        if ($qty <= 0) {
            unset($items[$k]);
        } else {
            $items[$k] = $qty;
        }
        $this->save($items);
    }

    public function remove(int $productId, ?int $variantId): void
    {
        $items = $this->raw();
        unset($items[$this->lineKey($productId, $variantId)]);
        $this->save($items);
    }

    public function clear(): void
    {
        Session::forget($this->key());
        Session::forget($this->couponKey());
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

        $productIds = array_map(fn ($k) => (int) explode(':', $k)[0], array_keys($items));
        $products = Product::whereIn('id', $productIds)
            ->where('is_active', true)
            ->with('variants')
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($items as $key => $qty) {
            [$pid, $vid] = array_pad(explode(':', (string) $key), 2, 0);
            $product = $products->get((int) $pid);
            if (! $product) {
                continue;
            }
            // A specific variant, else the product's default variant.
            $variant = (int) $vid
                ? $product->variants->firstWhere('id', (int) $vid)
                : $product->variants->first();
            if (! $variant) {
                continue;   // variant no longer exists
            }

            $qty = (int) $qty;
            $price = round((float) $variant->sale_price, 2);
            $taxRate = (float) $product->tax_rate / 100;
            $lineNet = round($price * $qty, 2);
            $lineTax = round($lineNet * $taxRate, 2);

            $lines[] = [
                'product' => $product,
                'variant' => $variant,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'label' => $variant->optionLabel(),
                'qty' => $qty,
                'unit_price' => $price,
                'tax_rate' => $taxRate,
                'line_net' => $lineNet,
                'line_total' => round($lineNet + $lineTax, 2),
            ];
        }

        return $lines;
    }

    /** Cart totals with a given shipping fee added. */
    public function totals(float $shippingFee = 0.0): array
    {
        $lines = $this->lines();
        $subtotal = round(array_sum(array_column($lines, 'line_net')), 2);
        $tax = round(array_sum(array_map(fn ($l) => round($l['line_net'] * $l['tax_rate'], 2), $lines)), 2);
        $shippingFee = round(max(0, $shippingFee), 2);
        $discount = round($this->couponDiscount($subtotal), 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon_code' => $discount > 0 ? $this->couponCode() : null,
            'tax' => $tax,
            'delivery_fee' => $shippingFee,
            'total' => round($subtotal - $discount + $tax + $shippingFee, 2),
        ];
    }
}
