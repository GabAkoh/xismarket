<?php

namespace App\Models\Pos;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Orders\Order;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model implements Authenticatable
{
    use AuthenticatableTrait, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'identity_number', 'phone', 'address', 'notes',
        'balance', 'loyalty_points', 'password',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'loyalty_points' => 'integer',
            'password' => 'hashed',
        ];
    }

    /** Whether this customer has a storefront login (vs a POS-only record). */
    public function hasAccount(): bool
    {
        return ! empty($this->password);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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
