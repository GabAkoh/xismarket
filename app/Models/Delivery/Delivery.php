<?php

namespace App\Models\Delivery;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'order_id', 'driver_id', 'recipient_name', 'phone',
        'address', 'city', 'zone', 'fee', 'status', 'tracking_number',
        'scheduled_for', 'dispatched_at', 'delivered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'scheduled_for' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * The order this delivery fulfils. Referenced by FQCN so the Delivery module
     * compiles even when the Orders module's files are not yet present.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Orders\Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /** A delivery still in flight (not yet delivered or failed). */
    public function isActive(): bool
    {
        return ! in_array($this->status, ['delivered', 'failed'], true);
    }
}
