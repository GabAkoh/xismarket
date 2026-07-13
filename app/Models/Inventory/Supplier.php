<?php

namespace App\Models\Inventory;

use App\Models\Accounting\Account;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Inventory\VendorAccountService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'address', 'account_id',
    ];

    protected static function booted(): void
    {
        // Give every new vendor its own Accounts-Payable account in the chart of
        // accounts. Covers both the normal create and the PO quick-add path.
        static::created(function (Supplier $supplier) {
            app(VendorAccountService::class)->ensureAccount($supplier);
        });
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** This vendor's Accounts-Payable account in the chart of accounts. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** Amount currently owed to this vendor (credit − debit on its A/P account). */
    public function balanceOwed(): float
    {
        return $this->account?->balance() ?? 0.0;
    }
}
