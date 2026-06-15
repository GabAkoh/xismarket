<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'code', 'address', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Return the tenant's default warehouse, creating one if none exists. */
    public static function default(): Warehouse
    {
        $warehouse = static::query()->where('is_default', true)->first()
            ?? static::query()->first();

        if (! $warehouse) {
            $warehouse = static::create([
                'name' => 'Main Warehouse',
                'code' => 'MAIN',
                'is_default' => true,
            ]);
        }

        return $warehouse;
    }
}
