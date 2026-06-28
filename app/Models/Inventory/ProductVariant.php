<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sellable variant of a product — its own SKU, barcode, price and option
 * values (e.g. Size = L, Colour = Red). Stock is tracked per variant. A simple
 * product (no options) still has exactly one variant.
 */
class ProductVariant extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'sku', 'barcode',
        'option1', 'option2', 'option3',
        'cost_price', 'sale_price', 'image_path', 'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class, 'variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    /** Quantity on hand for this variant in a warehouse. */
    public function stockIn(Warehouse $w): float
    {
        return (float) ($this->stocks()->where('warehouse_id', $w->id)->value('quantity') ?? 0);
    }

    public function totalStock(): float
    {
        return (float) $this->stocks()->sum('quantity');
    }

    /** "Red / L" — the non-empty option values joined. Empty for a simple variant. */
    public function optionLabel(): string
    {
        return collect([$this->option1, $this->option2, $this->option3])
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->implode(' / ');
    }

    /** Full display name: product name plus the option label when present. */
    public function displayName(): string
    {
        $label = $this->optionLabel();
        $name = $this->product?->name ?? $this->sku;

        return $label === '' ? $name : $name.' - '.$label;
    }
}
