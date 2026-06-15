<?php

namespace Database\Seeders\Demo;

use App\Models\Inventory\Product;
use App\Models\Orders\Order;
use App\Models\Pos\Customer;
use App\Services\Orders\OrderService;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OrdersDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app(Tenancy::class)->id();

        // Nothing to sell, nothing to order.
        if (! class_exists(Product::class) || Product::query()->doesntExist()) {
            $this->command?->warn('OrdersDemoSeeder: no products — skipping.');

            return;
        }

        // Idempotent: don't re-seed orders.
        if (Order::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $orders = app(OrderService::class);
        $products = Product::where('is_active', true)->get();
        $customers = Customer::all();

        if ($customers->isEmpty()) {
            $this->command?->warn('OrdersDemoSeeder: no customers — skipping.');

            return;
        }

        // A spread of demo orders: [status, paid, fulfillment, daysAgo].
        $plan = [
            ['pending', false, 'delivery', 1],
            ['pending', false, 'pickup', 1],
            ['confirmed', false, 'delivery', 2],
            ['ready', true, 'delivery', 3],
            ['preparing', false, 'delivery', 1],
            ['completed', true, 'delivery', 5],
            ['completed', true, 'delivery', 7],
            ['completed', true, 'pickup', 6],
        ];

        $contacts = [
            ['Alex Rivera', '+1-555-2001', '14 Maple Street', 'Springfield'],
            ['Priya Patel', '+1-555-2002', '208 Oak Avenue', 'Riverside'],
            ['Sam Okoro', '+1-555-2003', '76 Birch Lane', 'Lakeview'],
            ['Mei Chen', '+1-555-2004', '9 Cedar Court', 'Hillcrest'],
        ];

        $created = 0;
        foreach ($plan as $i => [$targetStatus, $paid, $fulfillment, $daysAgo]) {
            $customer = $customers[$i % $customers->count()];
            $contact = $contacts[$i % count($contacts)];

            // 1–3 distinct line items.
            $picks = $products->random(min(random_int(1, 3), $products->count()));
            $items = [];
            foreach ($picks as $product) {
                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => random_int(1, 4),
                    'unit_price' => (float) $product->sale_price,
                ];
            }

            $order = $orders->create([
                'customer_id' => $customer->id,
                'channel' => 'online',
                'fulfillment_type' => $fulfillment,
                'delivery_fee' => $fulfillment === 'delivery' ? (float) [5, 7.5, 10][$i % 3] : 0,
                'contact_name' => $contact[0],
                'contact_phone' => $contact[1],
                'address' => $fulfillment === 'delivery' ? $contact[2] : null,
                'city' => $fulfillment === 'delivery' ? $contact[3] : null,
                'notes' => null,
                'placed_at' => Carbon::now()->subDays($daysAgo)->setTime(random_int(9, 18), random_int(0, 59)),
                'items' => $items,
            ]);

            if ($paid) {
                $orders->markPaid($order, 'card');
            }

            if ($targetStatus === 'completed') {
                // Fulfilment decrements stock and posts the journal.
                $orders->fulfill($order);
            } elseif (! in_array($targetStatus, ['pending'], true)) {
                $orders->updateStatus($order, $targetStatus);
            }

            $created++;
        }

        $this->command?->info("OrdersDemoSeeder: seeded {$created} orders.");
    }
}
