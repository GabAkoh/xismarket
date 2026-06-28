<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Coupon;
use App\Support\Tenancy;

/**
 * Validates and applies coupon codes. The single source of truth used by the
 * storefront cart, online checkout and the POS so a code behaves identically
 * everywhere.
 */
class CouponService
{
    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Look up + validate a code against a pre-tax subtotal.
     *
     * @return array{coupon: ?Coupon, discount: float, error: ?string}
     */
    public function evaluate(?string $code, float $subtotal): array
    {
        $code = strtoupper(trim((string) $code));
        $none = ['coupon' => null, 'discount' => 0.0, 'error' => null];

        if ($code === '') {
            return $none;
        }

        $coupon = Coupon::where('code', $code)->first();

        $fail = fn (string $msg) => ['coupon' => null, 'discount' => 0.0, 'error' => $msg];

        if (! $coupon) {
            return $fail('That coupon code is not valid.');
        }
        if (! $coupon->is_active) {
            return $fail('This coupon is no longer active.');
        }
        if ($coupon->isExpired()) {
            return $fail('This coupon has expired.');
        }
        if ($coupon->hasReachedLimit()) {
            return $fail('This coupon has reached its usage limit.');
        }
        if ((float) $coupon->min_order > 0 && $subtotal + 0.001 < (float) $coupon->min_order) {
            return $fail('Spend at least '.number_format((float) $coupon->min_order, 2).' to use this coupon.');
        }

        return ['coupon' => $coupon, 'discount' => $coupon->discountFor($subtotal), 'error' => null];
    }

    /** Count a redemption (call once a coupon-carrying order/sale is committed). */
    public function redeem(string $code): void
    {
        $code = strtoupper(trim($code));
        if ($code !== '') {
            Coupon::where('code', $code)->increment('used_count');
        }
    }
}
