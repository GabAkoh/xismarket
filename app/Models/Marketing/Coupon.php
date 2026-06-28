<?php

namespace App\Models\Marketing;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A discount code applied at storefront checkout or the POS register. Either a
 * percentage or a fixed amount off the order, with optional active flag, expiry,
 * total usage limit and minimum order amount.
 */
class Coupon extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'type', 'value', 'min_order',
        'usage_limit', 'used_count', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** The discount amount this coupon yields on a given pre-tax subtotal. */
    public function discountFor(float $subtotal): float
    {
        $discount = $this->type === 'percent'
            ? round($subtotal * (float) $this->value / 100, 2)
            : round((float) $this->value, 2);

        // Never discount more than the subtotal.
        return round(min($discount, max(0, $subtotal)), 2);
    }

    public function hasReachedLimit(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->endOfDay()->isPast();
    }

    /** Short human label, e.g. "10% off" or "N 500 off". */
    public function label(?string $symbol = null): string
    {
        return $this->type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% off'
            : trim(($symbol ? $symbol.' ' : '').number_format((float) $this->value, 2)).' off';
    }
}
