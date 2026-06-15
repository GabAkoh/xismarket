<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\Account;
use App\Models\Accounting\Tax;
use App\Services\Accounting\PostingService;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class AccountingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app(Tenancy::class)->id();

        $this->seedChartOfAccounts($tenantId);
        $this->seedTaxes($tenantId);
        $this->seedOpeningEntry();
    }

    /**
     * Seed the full standard chart of accounts. POS posts to these exact codes,
     * so they must always exist.
     */
    protected function seedChartOfAccounts(int $tenantId): void
    {
        $accounts = [
            ['1000', 'Cash', 'asset', 'current_asset'],
            ['1010', 'Bank', 'asset', 'current_asset'],
            ['1200', 'Accounts Receivable', 'asset', 'current_asset'],
            ['1300', 'Inventory', 'asset', 'current_asset'],
            ['2000', 'Accounts Payable', 'liability', 'current_liability'],
            ['2100', 'Tax Payable', 'liability', 'current_liability'],
            ['3000', 'Owner Equity', 'equity', null],
            ['3900', 'Retained Earnings', 'equity', null],
            ['4000', 'Sales Revenue', 'income', 'operating_income'],
            ['4100', 'Sales Discounts', 'income', 'contra_income'],
            ['5000', 'Cost of Goods Sold', 'expense', 'cost_of_sales'],
            ['6000', 'Operating Expenses', 'expense', 'operating_expense'],
        ];

        foreach ($accounts as [$code, $name, $type, $subtype]) {
            Account::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'is_active' => true],
            );
        }
    }

    protected function seedTaxes(int $tenantId): void
    {
        $taxes = [
            ['Standard', 10.0],
            ['Reduced', 5.0],
            ['Zero', 0.0],
        ];

        foreach ($taxes as [$name, $rate]) {
            Tax::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['rate' => $rate, 'is_active' => true],
            );
        }
    }

    /**
     * An opening-balance entry (Debit Cash / Credit Owner Equity) so reports
     * have data to display.
     */
    protected function seedOpeningEntry(): void
    {
        $posting = app(PostingService::class);

        // Avoid duplicating the opening entry on repeat seeds.
        $exists = \App\Models\Accounting\JournalEntry::where('reference', 'OPENING')->exists();
        if ($exists) {
            return;
        }

        $posting->post([
            'date' => now()->startOfYear()->toDateString(),
            'memo' => 'Opening balance',
            'reference' => 'OPENING',
            'lines' => [
                ['account' => '1000', 'debit' => 5000.00, 'credit' => 0],
                ['account' => '3000', 'debit' => 0, 'credit' => 5000.00],
            ],
        ]);
    }
}
