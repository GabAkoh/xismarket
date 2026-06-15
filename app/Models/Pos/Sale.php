<?php

namespace App\Models\Pos;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'register_id', 'shift_id', 'customer_id', 'user_id',
        'number', 'status', 'subtotal', 'discount_total', 'tax_total',
        'total', 'paid_total', 'change_due', 'balance_due', 'note', 'completed_at',
        'wallet_used', 'loyalty_discount', 'points_earned', 'points_redeemed',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_due' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'wallet_used' => 'decimal:2',
            'loyalty_discount' => 'decimal:2',
            'points_earned' => 'integer',
            'points_redeemed' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Net of tax: subtotal minus discount (the revenue figure). */
    public function netRevenue(): float
    {
        return round((float) $this->subtotal - (float) $this->discount_total, 2);
    }

    /** A sale awaiting further payment (outstanding balance not yet settled). */
    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid';
    }
}
