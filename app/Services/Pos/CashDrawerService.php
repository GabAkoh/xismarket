<?php

namespace App\Services\Pos;

use App\Models\Pos\CashMovement;
use App\Models\Pos\Shift;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Records Shopify-style cash-drawer adjustments against an open shift and posts
 * the matching double-entry journal. The cash leg is always account 1000; the
 * counterpart is chosen from the movement's reason. The accounting call is
 * guarded so POS keeps working when the Accounting module is absent.
 *
 *   Cash in  : Dr 1000 Cash        / Cr <counterpart>
 *   Cash out : Dr <counterpart>    / Cr 1000 Cash
 */
class CashDrawerService
{
    /** Chart-of-accounts code for the physical drawer. */
    protected const CASH = '1000';

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Record a cash-in/out movement on an open shift.
     *
     * @throws \RuntimeException          when the shift is closed or amount invalid
     * @throws \InvalidArgumentException  from the accounting layer if it can't balance
     */
    public function record(Shift $shift, string $type, float $amount, string $reason, ?string $note = null): CashMovement
    {
        $type = $type === 'out' ? 'out' : 'in';
        $amount = round($amount, 2);

        if (! $shift->isOpen()) {
            throw new \RuntimeException('Cash movements can only be recorded on an open shift.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Enter a cash amount greater than zero.');
        }

        // Normalise the reason to one valid for this direction.
        $valid = $type === 'out' ? CashMovement::OUT_REASONS : CashMovement::IN_REASONS;
        if (! array_key_exists($reason, $valid)) {
            $reason = 'other';
        }

        return DB::transaction(function () use ($shift, $type, $amount, $reason, $note) {
            $movement = CashMovement::create([
                'tenant_id' => $this->tenancy->id(),
                'shift_id' => $shift->id,
                'register_id' => $shift->register_id,
                'user_id' => auth()->id(),
                'type' => $type,
                'reason' => $reason,
                'amount' => $amount,
                'note' => $note,
            ]);

            $entry = $this->postJournal($movement, $shift);
            if ($entry) {
                $movement->update(['journal_entry_id' => $entry->id]);
            }

            return $movement;
        });
    }

    /**
     * Counterpart chart-of-accounts code for a (type, reason) pair.
     * Falls back to a sensible default per direction.
     */
    protected function counterpartCode(string $type, string $reason): string
    {
        return match ([$type, $reason]) {
            ['in', 'bank'] => '1010',     // Bank / safe
            ['in', 'owner'] => '3000',    // Owner Equity
            ['out', 'bank'] => '1010',    // Bank deposit / safe drop
            ['out', 'owner'] => '3000',   // Owner drawings
            ['out', 'expense'] => '6000', // Operating Expenses
            default => $type === 'in' ? '3000' : '6000',
        };
    }

    /**
     * Post the double-entry journal for a movement. Guarded so it no-ops when the
     * Accounting module isn't installed; ensures the accounts it needs exist.
     */
    protected function postJournal(CashMovement $movement, Shift $shift): ?\App\Models\Accounting\JournalEntry
    {
        if (! class_exists(\App\Services\Accounting\PostingService::class)) {
            return null;
        }

        $counterpart = $this->counterpartCode($movement->type, $movement->reason);
        $this->ensureAccount(self::CASH);
        $this->ensureAccount($counterpart);

        $amount = (float) $movement->amount;
        $label = $movement->reasonLabel();
        $register = $shift->register?->name ?? 'Register';

        $lines = $movement->type === 'in'
            ? [
                ['account' => self::CASH, 'debit' => $amount, 'credit' => 0, 'memo' => 'Cash in'],
                ['account' => $counterpart, 'debit' => 0, 'credit' => $amount, 'memo' => $label],
            ]
            : [
                ['account' => $counterpart, 'debit' => $amount, 'credit' => 0, 'memo' => $label],
                ['account' => self::CASH, 'debit' => 0, 'credit' => $amount, 'memo' => 'Cash out'],
            ];

        $memo = ($movement->type === 'in' ? 'Cash in' : 'Cash out').' — '.$register
            .' · '.$label.($movement->note ? ' · '.$movement->note : '');

        return app(\App\Services\Accounting\PostingService::class)->post([
            'date' => now(),
            'memo' => $memo,
            'reference' => 'CASH-'.$movement->id,
            'source' => $movement,
            'lines' => $lines,
        ]);
    }

    /** Make sure a chart-of-accounts code exists (creates a standard one if not). */
    protected function ensureAccount(string $code): void
    {
        if (! class_exists(\App\Models\Accounting\Account::class)) {
            return;
        }

        $defaults = [
            '1000' => ['Cash', 'asset', 'current_asset'],
            '1010' => ['Bank', 'asset', 'current_asset'],
            '3000' => ['Owner Equity', 'equity', null],
            '6000' => ['Operating Expenses', 'expense', 'operating_expense'],
        ];

        [$name, $accType, $subtype] = $defaults[$code] ?? [$code, 'asset', null];

        \App\Models\Accounting\Account::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'type' => $accType, 'subtype' => $subtype, 'is_active' => true],
        );
    }
}
