<?php

namespace Database\Seeders\Demo;

use App\Models\Delivery\Delivery;
use App\Models\Delivery\Driver;
use App\Services\Delivery\DeliveryService;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

class DeliveryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = app(Tenancy::class)->id();

        // Idempotent: only seed once.
        if (Driver::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        // --- Drivers ---
        $driverData = [
            ['name' => 'Dan Cruz', 'phone' => '+1-555-3001', 'vehicle' => 'Motorbike'],
            ['name' => 'Eva Stone', 'phone' => '+1-555-3002', 'vehicle' => 'Van'],
            ['name' => 'Omar Haddad', 'phone' => '+1-555-3003', 'vehicle' => 'Bicycle'],
        ];
        $drivers = collect($driverData)->map(fn ($d) => Driver::create($d + [
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]));

        $this->command?->info('DeliveryDemoSeeder: seeded '.$drivers->count().' drivers.');

        // --- Deliveries for delivery-type orders (guarded: Orders module optional) ---
        if (! class_exists(\App\Models\Orders\Order::class)) {
            return;
        }

        $orders = \App\Models\Orders\Order::query()
            ->where('tenant_id', $tenantId)
            ->where('fulfillment_type', 'delivery')
            ->whereDoesntHave('delivery')
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        $service = app(DeliveryService::class);
        $count = 0;

        foreach ($orders as $i => $order) {
            $driver = $drivers[$i % $drivers->count()];
            $delivery = $service->createForOrder($order, []);

            // Choose a delivery state that matches the order's lifecycle.
            if ($order->isCompleted()) {
                // Completed order → already delivered. Set the row directly so we
                // don't push a status back onto the (terminal) order.
                $delivery->update([
                    'driver_id' => $driver->id,
                    'status' => 'delivered',
                    'dispatched_at' => $order->completed_at?->copy()->subHours(2),
                    'delivered_at' => $order->completed_at,
                ]);
            } elseif ($order->status === 'ready') {
                $service->assign($delivery, $driver);
                $service->dispatch($delivery); // order → dispatched
            } elseif (in_array($order->status, ['confirmed', 'preparing'], true)) {
                $service->assign($delivery, $driver);
            }
            // pending orders keep an unassigned, pending delivery.

            $count++;
        }

        $this->command?->info("DeliveryDemoSeeder: seeded {$count} deliveries.");
    }
}
