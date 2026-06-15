<?php

namespace App\Services\Delivery;

use App\Models\Delivery\Delivery;
use App\Models\Delivery\Driver;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Drives the delivery lifecycle: pending → assigned → out_for_delivery →
 * delivered (plus the failed branch).
 *
 * State changes that touch the linked order are wrapped in a DB transaction and
 * guarded so this module compiles & runs even when the Orders module is absent.
 */
class DeliveryService
{
    public function __construct(protected Tenancy $tenancy) {}

    /**
     * Create a delivery for an order, prefilling recipient details and fee from
     * the order. The Order is type-hinted loosely (FQCN) so this compiles
     * without the Orders module present at build time.
     *
     * @param  \App\Models\Orders\Order  $order
     */
    public function createForOrder($order, array $data = []): Delivery
    {
        $delivery = Delivery::create([
            'tenant_id' => $this->tenancy->id(),
            'order_id' => $order->id,
            'driver_id' => $data['driver_id'] ?? null,
            'recipient_name' => $data['recipient_name'] ?? $order->contact_name,
            'phone' => $data['phone'] ?? $order->contact_phone,
            'address' => $data['address'] ?? $order->address,
            'city' => $data['city'] ?? $order->city,
            'zone' => $data['zone'] ?? null,
            'fee' => $data['fee'] ?? $order->delivery_fee ?? 0,
            'status' => 'pending',
            'scheduled_for' => $data['scheduled_for'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $delivery->update(['tracking_number' => $this->makeTrackingNumber($delivery, $order)]);

        return $delivery;
    }

    /** Assign a driver. Only valid while pending or already assigned. */
    public function assign(Delivery $delivery, Driver $driver): Delivery
    {
        if (! in_array($delivery->status, ['pending', 'assigned'], true)) {
            throw new \RuntimeException('Only a pending delivery can be assigned a driver.');
        }

        $delivery->update([
            'driver_id' => $driver->id,
            'status' => 'assigned',
        ]);

        return $delivery;
    }

    /** Send the delivery out and mark the linked order as dispatched. */
    public function dispatch(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery) {
            $delivery->update([
                'status' => 'out_for_delivery',
                'dispatched_at' => now(),
            ]);

            $this->syncOrderStatus($delivery, 'dispatched');

            return $delivery;
        });
    }

    /** Complete the delivery and mark the linked order as delivered. */
    public function markDelivered(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery) {
            $delivery->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            $this->syncOrderStatus($delivery, 'delivered');

            return $delivery;
        });
    }

    /** Mark the delivery as failed, appending the reason to its notes. */
    public function markFailed(Delivery $delivery, ?string $reason = null): Delivery
    {
        $notes = $delivery->notes;
        if ($reason) {
            $stamp = 'Failed: '.$reason;
            $notes = $notes ? $notes."\n".$stamp : $stamp;
        }

        $delivery->update([
            'status' => 'failed',
            'notes' => $notes,
        ]);

        return $delivery;
    }

    /**
     * Push a status onto the linked order. Guarded against the Orders module
     * being absent and against the delivery having no linked order.
     */
    protected function syncOrderStatus(Delivery $delivery, string $status): void
    {
        if (! class_exists(\App\Models\Orders\Order::class)) {
            return;
        }

        $order = $delivery->order;
        if ($order) {
            $order->update(['status' => $status]);
        }
    }

    /** Build a human-friendly tracking code from the order number or the id. */
    protected function makeTrackingNumber(Delivery $delivery, $order): string
    {
        if (! empty($order->number)) {
            return 'DLV-'.$order->number;
        }

        return 'DLV-'.str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT);
    }
}
