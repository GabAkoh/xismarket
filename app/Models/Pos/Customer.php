<?php

namespace App\Models\Pos;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'identity_number', 'phone', 'address', 'notes',
        'balance', 'loyalty_points',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'loyalty_points' => 'integer',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest();
    }
}
