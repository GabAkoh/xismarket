<?php

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'type', 'subtype', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function journalLines(): HasMany
    {
        return $this->lines();
    }

    /**
     * Compute the account balance from its journal lines.
     *
     * Asset & expense accounts carry a natural debit balance (debit - credit);
     * liability, equity & income accounts carry a natural credit balance.
     */
    public function balance(): float
    {
        $debit = (float) $this->lines()->sum('debit');
        $credit = (float) $this->lines()->sum('credit');

        return in_array($this->type, ['asset', 'expense'], true)
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }
}
