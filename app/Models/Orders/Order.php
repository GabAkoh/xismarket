<?php

namespace App\Models\Orders;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Pos\Customer;
use App\Models\User;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'customer_id', 'channel', 'fulfillment_type',
        'status', 'payment_status', 'payment_method', 'payment_reference', 'paid_at',
        'subtotal', 'discount_total', 'tax_total',
        'delivery_fee', 'total', 'paid_total', 'contact_name', 'contact_phone',
        'address', 'city', 'notes', 'user_id', 'placed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The delivery assignment (Delivery module, built in parallel — referenced by FQCN). */
    public function delivery(): HasOne
    {
        return $this->hasOne(\App\Models\Delivery\Delivery::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
