<?php

namespace App\Models\Inventory;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An additional gallery image for a product. The product's primary/cover image
 * stays on products.image_path; these are the extra angles, variants and
 * lifestyle shots shown in the storefront gallery.
 */
class ProductImage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'path', 'position', 'source', 'label',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Public URL for the stored image. */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
