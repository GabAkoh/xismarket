<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'sku', 'barcode', 'description',
        'cost_price', 'sale_price', 'tax_rate', 'track_stock', 'is_active', 'is_featured', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Quantity on hand for this product in the given warehouse. */
    public function stockIn(Warehouse $w): float
    {
        return (float) ($this->stocks()
            ->where('warehouse_id', $w->id)
            ->value('quantity') ?? 0);
    }

    /** Total quantity on hand across all warehouses. */
    public function totalStock(): float
    {
        return (float) $this->stocks()->sum('quantity');
    }
}
