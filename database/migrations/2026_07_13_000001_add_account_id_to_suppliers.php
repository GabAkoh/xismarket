<?php

use App\Models\Accounting\Account;
use App\Models\Inventory\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded so a re-run after a partial failure (DDL auto-commits in MySQL)
        // doesn't error on an already-added column. The backfill below is idempotent.
        if (! Schema::hasColumn('suppliers', 'account_id')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->foreignId('account_id')->nullable()->after('address')
                    ->constrained('accounts')->nullOnDelete();
            });
        }

        // Give every existing vendor its own Accounts-Payable account in the chart
        // of accounts (starting at a zero balance). This is going-forward only — no
        // journals are posted for already-received purchase orders.
        Supplier::withoutGlobalScopes()->whereNull('account_id')->cursor()->each(function (Supplier $supplier) {
            $account = Account::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $supplier->tenant_id, 'code' => '2000-'.$supplier->id],
                [
                    'name' => 'Accounts Payable — '.$supplier->name,
                    'type' => 'liability',
                    'subtype' => 'current_liability',
                    'is_active' => true,
                ],
            );

            Supplier::withoutGlobalScopes()->whereKey($supplier->id)->update(['account_id' => $account->id]);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
    }
};
