<?php

namespace App\Models\Pos;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'is_active', 'earn_rate', 'redeem_value', 'min_redeem_points',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'earn_rate' => 'decimal:4',
            'redeem_value' => 'decimal:4',
            'min_redeem_points' => 'integer',
        ];
    }

    /** The current tenant's loyalty settings, creating a default (inactive) row if none. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'is_active' => false,
            'earn_rate' => 1,
            'redeem_value' => 0.05,
            'min_redeem_points' => 0,
        ]);
    }

    /** Currency value of a given number of points. */
    public function valueOf(int $points): float
    {
        return round($points * (float) $this->redeem_value, 2);
    }

    /** Points earned for a given amount of net revenue. */
    public function pointsFor(float $netRevenue): int
    {
        return (int) floor(max(0, $netRevenue) * (float) $this->earn_rate);
    }
}
