<?php

namespace Database\Seeders\Demo;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\Customer;
use App\Models\Pos\LoyaltySetting;
use App\Models\Pos\Register;
use App\Models\Pos\Shift;
use App\Services\Pos\SaleService;
use App\Services\Pos\WalletService;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app(Tenancy::class)->id();

        // Defensive: if there are no products, there is nothing to sell.
        if (! class_exists(Product::class) || Product::query()->doesntExist()) {
            $this->command?->warn('PosDemoSeeder: no products found — skipping.');

            return;
        }

        // The chart of accounts must exist before SaleService posts journals.
        // PosDemoSeeder may run before AccountingDemoSeeder, so ensure it here.
        $this->ensureChartOfAccounts();

        $warehouse = class_exists(Warehouse::class) ? Warehouse::default() : null;

        // --- Activate the loyalty program so historical sales earn points ---
        $loyalty = LoyaltySetting::current();
        $loyalty->update([
            'is_active' => true,
            'earn_rate' => 1,       // 1 point per 1 unit of net spend
            'redeem_value' => 0.05, // 100 points = 5.00 off
            'min_redeem_points' => 0,
        ]);

        // --- Customers ---
        $customers = [];
        $customerData = [
            ['name' => 'John Buyer', 'email' => 'john.buyer@demo.test', 'phone' => '+1-555-1001', 'identity_number' => 'NID-100001'],
            ['name' => 'Sara Shopper', 'email' => 'sara.shopper@demo.test', 'phone' => '+1-555-1002', 'identity_number' => 'NID-100002'],
            ['name' => 'Mike Regular', 'email' => 'mike.regular@demo.test', 'phone' => '+1-555-1003', 'identity_number' => 'NID-100003'],
            ['name' => 'Lisa Loyal', 'email' => 'lisa.loyal@demo.test', 'phone' => '+1-555-1004', 'identity_number' => 'NID-100004'],
            ['name' => 'Tom Walker', 'email' => 'tom.walker@demo.test', 'phone' => '+1-555-1005', 'identity_number' => 'NID-100005'],
        ];
        foreach ($customerData as $data) {
            $customers[] = Customer::firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $data['email']],
                $data,
            );
        }

        // --- Seed some wallet (store-credit) balances ---
        $wallet = app(WalletService::class);
        $topUps = ['lisa.loyal@demo.test' => 50.00, 'john.buyer@demo.test' => 25.00];
        foreach ($customers as $customer) {
            if (isset($topUps[$customer->email]) && $customer->walletTransactions()->doesntExist()) {
                $wallet->topUp($customer, $topUps[$customer->email], 'cash', 'Opening store credit');
            }
        }

        // --- Registers (bound to the default warehouse) ---
        $registerMain = Register::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'REG-01'],
            ['name' => 'Front Counter', 'warehouse_id' => $warehouse?->id, 'is_active' => true],
        );
        Register::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'REG-02'],
            ['name' => 'Express Lane', 'warehouse_id' => $warehouse?->id, 'is_active' => true],
        );

        // --- An open shift on the main register ---
        $shift = $registerMain->openShift();
        if (! $shift) {
            $shift = Shift::create([
                'tenant_id' => $tenantId,
                'register_id' => $registerMain->id,
                'user_id' => $this->cashierId(),
                'opened_at' => now()->subHours(4),
                'opening_float' => 100.00,
                'status' => 'open',
                'notes' => 'Demo shift',
            ]);
        }

        // --- Historical sales (idempotent: skip if we already seeded them) ---
        if (\App\Models\Pos\Sale::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $sales = app(SaleService::class);
        $products = Product::where('is_active', true)->get();
        $userId = $this->cashierId();

        $count = random_int(14, 18);
        for ($i = 0; $i < $count; $i++) {
            $when = Carbon::now()
                ->subDays(random_int(0, 29))
                ->setTime(random_int(8, 19), random_int(0, 59));

            // 1–4 distinct line items.
            $lineItems = $products->random(min(random_int(1, 4), $products->count()));
            $items = [];
            foreach ($lineItems as $product) {
                $qty = random_int(1, 5);
                $discount = random_int(0, 10) === 0 ? round($product->sale_price * 0.10, 2) : 0;
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => (float) $product->sale_price,
                    'discount' => $discount,
                ];
            }

            // Pre-compute the exact total using the SAME per-line rounding as
            // SaleService, so the tendered amount always covers it in full
            // (completed sales now require full payment).
            $net = 0.0;
            $tax = 0.0;
            foreach ($lineItems as $idx => $product) {
                $line = $items[$idx];
                $gross = round($product->sale_price * $line['quantity'], 2);
                $lineNet = round($gross - $line['discount'], 2);
                $tax += round($lineNet * ((float) $product->tax_rate / 100), 2);
                $net += $lineNet;
            }
            $estimate = round($net + $tax, 2);

            $method = ['cash', 'cash', 'card', 'other'][random_int(0, 3)];
            $tendered = $method === 'cash'
                ? ceil($estimate / 5) * 5   // round cash up to nearest 5
                : $estimate;

            // Most sales are walk-ins; some attach a customer.
            $customerId = random_int(0, 1) === 1
                ? $customers[array_rand($customers)]->id
                : null;

            // Tie sales within the open shift's window to the shift.
            $inShift = $when->greaterThanOrEqualTo($shift->opened_at);

            $sales->complete([
                'register_id' => $registerMain->id,
                'shift_id' => $inShift ? $shift->id : null,
                'customer_id' => $customerId,
                'user_id' => $userId,
                'completed_at' => $when,
                'items' => $items,
                'payments' => [[
                    'method' => $method,
                    'amount' => $tendered,
                ]],
            ]);
        }

        $this->command?->info("PosDemoSeeder: generated {$count} historical sales.");
    }

    /** Ensure the standard chart of accounts exists (POS journals depend on it). */
    protected function ensureChartOfAccounts(): void
    {
        if (! class_exists(\App\Models\Accounting\Account::class)) {
            return;
        }

        if (\App\Models\Accounting\Account::query()->exists()) {
            return;
        }

        if (class_exists(AccountingDemoSeeder::class)) {
            $this->callOnce(AccountingDemoSeeder::class);
        }
    }

    /** Prefer the demo cashier, fall back to any user, then the owner. */
    protected function cashierId(): ?int
    {
        $cashier = \App\Models\User::query()->where('email', 'cashier@demo.test')->value('id');

        return $cashier ?? \App\Models\User::query()->value('id');
    }
}
