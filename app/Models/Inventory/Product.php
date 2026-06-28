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
        'tenant_id', 'category_id', 'name', 'option1_name', 'option2_name', 'option3_name',
        'sku', 'barcode', 'description',
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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position')->orderBy('id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /** Whether this product carries multiple variants / defined option axes. */
    public function hasVariants(): bool
    {
        return ! empty($this->option1_name) || $this->variants()->count() > 1;
    }

    /** The non-empty option axis names, e.g. ['Size', 'Colour']. */
    public function optionNames(): array
    {
        return array_values(array_filter([$this->option1_name, $this->option2_name, $this->option3_name]));
    }

    /** The first/default variant (a simple product has exactly one). */
    public function defaultVariant(): ?ProductVariant
    {
        return $this->relationLoaded('variants')
            ? $this->variants->first()
            : $this->variants()->first();
    }

    /**
     * Min/max active sale price across variants — for "from N" storefront pricing.
     *
     * @return array{min: float, max: float}
     */
    public function priceRange(): array
    {
        $prices = $this->variants()->where('is_active', true)->pluck('sale_price')
            ->map(fn ($p) => (float) $p);

        if ($prices->isEmpty()) {
            return ['min' => (float) $this->sale_price, 'max' => (float) $this->sale_price];
        }

        return ['min' => (float) $prices->min(), 'max' => (float) $prices->max()];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Additional gallery images (excludes the primary cover on image_path). */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Every image URL for the storefront gallery: the cover first (if any),
     * then the additional gallery images in order.
     *
     * @return array<int, string>
     */
    public function galleryUrls(): array
    {
        $urls = [];
        if ($this->image_path) {
            $urls[] = asset('storage/'.$this->image_path);
        }
        foreach ($this->images as $img) {
            $urls[] = $img->url();
        }

        return $urls;
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

    /**
     * Has this stock-tracked product sold out — on-hand <= 0 in the fulfilment
     * (default) warehouse? Mirrors the order/sale guards so the storefront can
     * hide "Add to cart". Untracked products, or setups without an Inventory
     * warehouse, are always treated as available. Read-only: unlike
     * Warehouse::default() this never creates a warehouse.
     */
    public function isOutOfStock(): bool
    {
        if (! $this->track_stock || ! class_exists(Warehouse::class)) {
            return false;
        }

        $warehouse = Warehouse::query()->where('is_default', true)->first()
            ?? Warehouse::query()->first();
        if (! $warehouse) {
            return false;
        }

        return $this->stockIn($warehouse) <= 0;
    }
}
