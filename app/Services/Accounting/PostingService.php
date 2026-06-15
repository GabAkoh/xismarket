<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PostingService
{
    /** Find a tenant-scoped account by its chart-of-accounts code. */
    public function accountByCode(string $code): ?Account
    {
        return Account::where('code', $code)->first();
    }

    /**
     * Post a balanced journal entry.
     *
     * @param  array{
     *     date: \Carbon\Carbon|string,
     *     memo: string,
     *     reference?: ?string,
     *     source?: ?Model,
     *     lines: array<int, array{account: string|Account, debit?: float, credit?: float, memo?: ?string}>
     * }  $data
     *
     * @throws \InvalidArgumentException when debits and credits do not balance
     *                                   or an account code cannot be resolved.
     */
    public function post(array $data): JournalEntry
    {
        $lines = $data['lines'] ?? [];

        if (empty($lines)) {
            throw new \InvalidArgumentException('A journal entry requires at least one line.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $resolved = [];

        foreach ($lines as $line) {
            $account = $this->resolveAccount($line['account'] ?? null);

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            $totalDebit += $debit;
            $totalCredit += $credit;

            $resolved[] = [
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
            ];
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \InvalidArgumentException(sprintf(
                'Journal entry does not balance: debits %.2f != credits %.2f.',
                $totalDebit,
                $totalCredit,
            ));
        }

        $source = $data['source'] ?? null;

        return DB::transaction(function () use ($data, $resolved, $source) {
            $entry = JournalEntry::create([
                'reference' => $data['reference'] ?? null,
                'entry_date' => $data['date'],
                'memo' => $data['memo'] ?? null,
                'source_type' => $source ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'user_id' => auth()->id(),
                'posted' => true,
            ]);

            foreach ($resolved as $line) {
                $entry->lines()->create($line);
            }

            return $entry->load('lines');
        });
    }

    /** Resolve a line's 'account' (a code string or an Account instance) to a model. */
    protected function resolveAccount(string|Account|null $account): Account
    {
        if ($account instanceof Account) {
            return $account;
        }

        if (is_string($account) && $account !== '') {
            $found = $this->accountByCode($account);

            if ($found) {
                return $found;
            }
        }

        throw new \InvalidArgumentException(
            'Unable to resolve account: '.(is_string($account) ? $account : 'null')
        );
    }
}
