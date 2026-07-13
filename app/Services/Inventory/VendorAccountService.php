<?php

namespace App\Services\Inventory;

use App\Models\Accounting\Account;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use App\Services\Accounting\PostingService;
use Illuminate\Support\Facades\DB;

/**
 * Vendor Accounts-Payable ledger.
 *
 * Gives every supplier its own liability account in the chart of accounts and
 * keeps it in step with purchasing: receiving a purchase order credits the
 * vendor (Cr) and debits inventory (Dr); settling a bill debits the vendor (Dr)
 * and credits the paying cash/bank account (Cr). Mirrors the customer A/R side
 * (POS credit sales + WalletService). All accounting calls are guarded with
 * class_exists so purchasing still works when the Accounting module is absent.
 */
class VendorAccountService
{
    /**
     * Ensure a per-vendor Accounts-Payable liability account exists and is linked
     * to the supplier. Codes sort right after the 2000 control account.
     */
    public function ensureAccount(Supplier $supplier): ?Account
    {
        if (! class_exists(Account::class)) {
            return null;
        }

        $account = Account::firstOrCreate(
            ['code' => '2000-'.$supplier->id],
            [
                'name' => 'Accounts Payable — '.$supplier->name,
                'type' => 'liability',
                'subtype' => 'current_liability',
                'is_active' => true,
            ],
        );

        if ((int) $supplier->account_id !== (int) $account->id) {
            $supplier->forceFill(['account_id' => $account->id])->saveQuietly();
        }

        return $account;
    }

    /** Ensure the core Inventory + Accounts-Payable control accounts exist for posting. */
    public function ensureCore(): void
    {
        if (! class_exists(Account::class)) {
            return;
        }

        Account::firstOrCreate(['code' => '1300'],
            ['name' => 'Inventory', 'type' => 'asset', 'subtype' => 'current_asset', 'is_active' => true]);
        Account::firstOrCreate(['code' => '2000'],
            ['name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'current_liability', 'is_active' => true]);
    }

    /**
     * Post the receipt of a purchase order: Dr Inventory / Cr the vendor's payable
     * (or the 2000 control account when the PO has no supplier). Untaxed, so the
     * full PO total is the payable.
     */
    public function postPurchaseReceipt(PurchaseOrder $purchase): void
    {
        if (! class_exists(PostingService::class)) {
            return;
        }

        $total = round((float) $purchase->total, 2);
        if ($total <= 0) {
            return;
        }

        $this->ensureCore();

        $creditCode = '2000';
        $vendorName = 'supplier';
        if ($purchase->supplier) {
            $account = $this->ensureAccount($purchase->supplier);
            if ($account) {
                $creditCode = $account->code;
            }
            $vendorName = $purchase->supplier->name;
        }

        app(PostingService::class)->post([
            'date' => now(),
            'memo' => 'Purchase received '.$purchase->reference,
            'reference' => $purchase->reference,
            'source' => $purchase,
            'lines' => [
                ['account' => '1300', 'debit' => $total, 'credit' => 0, 'memo' => 'Inventory received'],
                ['account' => $creditCode, 'debit' => 0, 'credit' => $total, 'memo' => 'Payable to '.$vendorName],
            ],
        ]);
    }

    /** Amount currently owed to a vendor (credit − debit on its A/P account). */
    public function outstanding(Supplier $supplier): float
    {
        $account = $this->ensureAccount($supplier);

        return $account ? round($account->balance(), 2) : 0.0;
    }

    /**
     * Settle (part of) a vendor's outstanding payable: Dr the vendor's A/P account,
     * Cr the paying asset account (cash/bank). The amount is capped to the balance
     * owed. Returns the amount applied.
     */
    public function payVendor(Supplier $supplier, float $amount, string $payingCode, ?string $reference = null): float
    {
        if (! class_exists(PostingService::class)) {
            throw new \RuntimeException('Accounting is not available.');
        }

        return DB::transaction(function () use ($supplier, $amount, $payingCode, $reference) {
            $account = $this->ensureAccount($supplier);
            if (! $account) {
                throw new \RuntimeException('Accounting is not available.');
            }

            $owed = round($account->balance(), 2);
            $applied = round(min(round($amount, 2), $owed), 2);
            if ($applied <= 0) {
                throw new \RuntimeException('Nothing is currently owed to this vendor.');
            }

            app(PostingService::class)->post([
                'date' => now(),
                'memo' => 'Payment to '.$supplier->name.($reference ? ' · '.$reference : ''),
                'reference' => 'VP-'.$supplier->id.'-'.now()->format('YmdHis'),
                'source' => $supplier,
                'lines' => [
                    ['account' => $account->code, 'debit' => $applied, 'credit' => 0, 'memo' => 'Payable settled'],
                    ['account' => $payingCode, 'debit' => 0, 'credit' => $applied, 'memo' => 'Paid to '.$supplier->name],
                ],
            ]);

            return $applied;
        });
    }
}
