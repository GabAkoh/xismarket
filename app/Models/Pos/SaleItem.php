<?php

namespace App\Models\Pos;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'sale_id', 'product_id', 'name', 'sku',
        'quantity', 'returned_quantity', 'unit_price', 'unit_cost', 'tax_rate', 'discount', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** Quantity still eligible to be returned on this line. */
    public function returnableQuantity(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->returned_quantity, 3));
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
