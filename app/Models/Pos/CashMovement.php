<?php

namespace App\Models\Pos;

use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual cash-drawer adjustment recorded against an open shift — the
 * Shopify-style "Cash in" (add to drawer) / "Cash out" (remove from drawer).
 * Each movement adjusts the shift's expected cash and posts a double-entry
 * journal (cash leg is always account 1000; the counterpart depends on reason).
 */
class CashMovement extends Model
{
    use BelongsToTenant;

    /** Cash-in reasons => label. Counterpart account is resolved in CashDrawerService. */
    public const IN_REASONS = [
        'bank' => 'From bank / safe',
        'owner' => 'Owner contribution',
        'other' => 'Other',
    ];

    /** Cash-out reasons => label. */
    public const OUT_REASONS = [
        'bank' => 'Bank deposit / safe drop',
        'expense' => 'Expense / petty cash',
        'owner' => 'Owner drawings',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id', 'shift_id', 'register_id', 'user_id',
        'type', 'reason', 'amount', 'note', 'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** Signed drawer effect: +amount for cash in, -amount for cash out. */
    public function signedAmount(): float
    {
        return round(($this->type === 'out' ? -1 : 1) * (float) $this->amount, 2);
    }

    /** Human label for the reason, resolved from the tenant's configured reasons. */
    public function reasonLabel(): string
    {
        $store = app(\App\Support\Tenancy::class)->current();
        $labels = $store?->cashReasonsByType($this->type) ?? [];
        if (isset($labels[$this->reason])) {
            return $labels[$this->reason];
        }

        // Fallbacks: built-in defaults, then a titleised key.
        $map = $this->type === 'out' ? self::OUT_REASONS : self::IN_REASONS;

        return $map[$this->reason] ?? \Illuminate\Support\Str::headline($this->reason);
    }
}
