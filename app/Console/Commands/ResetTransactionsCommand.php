<?php

namespace App\Console\Commands;

use App\Models\Orders\Order;
use App\Models\Pos\Sale;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipe a tenant's transactional history — sales, orders, purchase orders,
 * deliveries and their payments, stock movements and accounting entries —
 * while keeping the catalogue, current stock levels and customers.
 *
 * Customer wallet balances and loyalty points are zeroed (they were earned on
 * the deleted transactions). Intended for clearing demo/test data before a
 * store goes properly live.
 */
class ResetTransactionsCommand extends Command
{
    protected $signature = 'data:reset-transactions
        {--tenant= : Tenant slug or id (defaults to the only tenant)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all sales, orders, purchase orders, deliveries and their payments/accounting, keeping the catalogue, stock levels and customers.';

    public function handle(): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            return self::FAILURE;
        }
        $tid = $tenant->id;

        $this->info("Tenant: {$tenant->name} (#{$tid}, {$tenant->slug})");
        $this->line('To delete:');
        foreach ([
            'sales', 'sale_items', 'orders', 'order_items', 'purchase_orders',
            'purchase_order_items', 'deliveries', 'payments', 'journal_entries',
            'loyalty_transactions', 'wallet_transactions', 'shifts',
        ] as $table) {
            $this->line(sprintf('  %-22s %d', $table, DB::table($table)->where('tenant_id', $tid)->count()));
        }
        $this->line(sprintf('  %-22s %d', 'stock_movements (tx)', DB::table('stock_movements')->where('tenant_id', $tid)
            ->whereIn('reference_type', [Order::class, Sale::class])->count()));
        $this->newLine();
        $this->warn('Kept: products, current stock levels, customers, suppliers, settings.');
        $this->warn('Customer wallet balances and loyalty points will be reset to 0.');

        if (! $this->option('force') && ! $this->confirm('Permanently delete the above? This cannot be undone.')) {
            $this->info('Aborted — nothing deleted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($tid) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            DB::table('payments')->where('tenant_id', $tid)->delete();
            DB::table('sale_items')->where('tenant_id', $tid)->delete();
            DB::table('sales')->where('tenant_id', $tid)->delete();
            DB::table('order_items')->where('tenant_id', $tid)->delete();
            DB::table('deliveries')->where('tenant_id', $tid)->delete();
            DB::table('orders')->where('tenant_id', $tid)->delete();
            DB::table('purchase_order_items')->where('tenant_id', $tid)->delete();
            DB::table('purchase_orders')->where('tenant_id', $tid)->delete();

            // Only the movements created by these transactions — keep imports/adjustments
            // so current stock levels stay explained.
            DB::table('stock_movements')->where('tenant_id', $tid)
                ->whereIn('reference_type', [Order::class, Sale::class])->delete();

            // Accounting: journal_lines hang off journal_entries (no own tenant_id).
            DB::table('journal_lines')->whereIn('journal_entry_id',
                DB::table('journal_entries')->where('tenant_id', $tid)->pluck('id'))->delete();
            DB::table('journal_entries')->where('tenant_id', $tid)->delete();

            DB::table('loyalty_transactions')->where('tenant_id', $tid)->delete();
            DB::table('wallet_transactions')->where('tenant_id', $tid)->delete();
            DB::table('shifts')->where('tenant_id', $tid)->delete();

            // Wallet balance + loyalty points are stored on the customer row.
            DB::table('customers')->where('tenant_id', $tid)->update(['balance' => 0, 'loyalty_points' => 0]);

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        });

        $this->newLine();
        $this->info('Done — transactional data cleared. Catalogue, stock and customers kept.');

        return self::SUCCESS;
    }

    protected function resolveTenant(): ?Tenant
    {
        if ($opt = $this->option('tenant')) {
            $tenant = Tenant::where('slug', $opt)
                ->orWhere('id', is_numeric($opt) ? (int) $opt : 0)
                ->first();
            if (! $tenant) {
                $this->error("Tenant not found: {$opt}");

                return null;
            }

            return $tenant;
        }

        $tenants = Tenant::all();
        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        $this->error('Multiple tenants found — specify which with --tenant=<slug>.');

        return null;
    }
}
