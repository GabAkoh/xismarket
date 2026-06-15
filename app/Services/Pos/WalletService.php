<?php

namespace App\Services\Pos;

use App\Models\Pos\Customer;
use App\Models\Pos\WalletTransaction;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Customer store-credit ("wallet") manager.
 *
 * Every change to Customer.balance goes through here so the balance and the
 * wallet_transactions ledger never drift apart. Balance changes lock the
 * customer row for the duration of the transaction to stay race-safe.
 */
class WalletService
{
    /** Chart-of-accounts code for the store-credit liability (customer deposits). */
    public const LIABILITY_CODE = '2200';

    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Add credit to a customer's wallet.
     *
     * When $postJournal is true (a real top-up paid with cash/card) the matching
     * journal is posted: Dr 1000 Cash / Cr 2200 Customer Deposits.
     */
    public function credit(Customer $customer, float $amount, ?string $reason = null, ?Model $source = null, ?int $userId = null, bool $postJournal = false): WalletTransaction
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $amount, $reason, $source, $userId, $postJournal) {
            $locked = Customer::whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $balanceAfter = round((float) $locked->balance + $amount, 2);
            $locked->update(['balance' => $balanceAfter]);

            $txn = $this->record($locked, 'credit', $amount, $balanceAfter, $reason, $source, $userId);

            if ($postJournal) {
                $this->postTopUpJournal($amount, $reason, $source);
            }

            $customer->balance = $balanceAfter;

            return $txn;
        });
    }

    /**
     * Debit (spend) a customer's wallet. Throws if the balance is insufficient.
     * No journal is posted here — when a wallet pays for a sale, the sale's own
     * journal debits the liability account instead of cash.
     */
    public function debit(Customer $customer, float $amount, ?string $reason = null, ?Model $source = null, ?int $userId = null): WalletTransaction
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $amount, $reason, $source, $userId) {
            $locked = Customer::whereKey($customer->getKey())->lockForUpdate()->firstOrFail();

            if (round((float) $locked->balance, 2) < $amount) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $balanceAfter = round((float) $locked->balance - $amount, 2);
            $locked->update(['balance' => $balanceAfter]);

            $txn = $this->record($locked, 'debit', $amount, $balanceAfter, $reason, $source, $userId);
            $customer->balance = $balanceAfter;

            return $txn;
        });
    }

    /** A real top-up funded by cash/card — credits the wallet and posts the journal. */
    public function topUp(Customer $customer, float $amount, string $method = 'cash', ?string $reason = null, ?int $userId = null): WalletTransaction
    {
        return $this->credit(
            $customer,
            $amount,
            $reason ?? 'Wallet top-up ('.$method.')',
            null,
            $userId,
            postJournal: true,
        );
    }

    protected function record(Customer $customer, string $type, float $amount, float $balanceAfter, ?string $reason, ?Model $source, ?int $userId): WalletTransaction
    {
        return WalletTransaction::create([
            'tenant_id' => $this->tenancy->id(),
            'customer_id' => $customer->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reason' => $reason,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    protected function postTopUpJournal(float $amount, ?string $reason, ?Model $source): void
    {
        if (! class_exists(\App\Services\Accounting\PostingService::class)) {
            return;
        }

        $this->ensureLiabilityAccount();

        app(\App\Services\Accounting\PostingService::class)->post([
            'date' => now(),
            'memo' => $reason ?? 'Wallet top-up',
            'reference' => null,
            'source' => $source,
            'lines' => [
                ['account' => '1000', 'debit' => $amount, 'credit' => 0, 'memo' => 'Wallet top-up received'],
                ['account' => self::LIABILITY_CODE, 'debit' => 0, 'credit' => $amount, 'memo' => 'Customer store credit'],
            ],
        ]);
    }

    /** Make sure the customer-deposits liability account exists for this tenant. */
    public function ensureLiabilityAccount(): void
    {
        if (! class_exists(\App\Models\Accounting\Account::class)) {
            return;
        }

        \App\Models\Accounting\Account::firstOrCreate(
            ['code' => self::LIABILITY_CODE],
            [
                'name' => 'Customer Store Credit',
                'type' => 'liability',
                'subtype' => 'current',
                'is_active' => true,
            ],
        );
    }
}
