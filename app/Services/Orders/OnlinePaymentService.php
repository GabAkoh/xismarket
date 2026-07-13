<?php

namespace App\Services\Orders;

use App\Mail\OrderReceiptMail;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Post-checkout side effects for storefront orders: mark an order paid from an
 * online reference (idempotently) and send the customer receipt + store alert.
 * Shared by the checkout callback, the Paystack webhook, and pay-on-delivery.
 */
class OnlinePaymentService
{
    public function __construct(protected OrderService $orders) {}

    /**
     * Idempotently record an online payment against an order and notify.
     * Returns true only if this call settled it (so callback + webhook don't
     * double-send / double-record).
     *
     * $amount is the amount actually captured by the gateway (a deposit may be
     * less than the total); null records the full total. Because a single
     * gateway transaction owns one reference — pre-set on the order at
     * checkout — a second call for the same reference (paid_total already moved)
     * is a no-op.
     */
    public function settle(Order $order, string $reference, string $method = 'paystack', ?float $amount = null): bool
    {
        // Lock the row so a concurrent callback + webhook for the same charge
        // can't both apply it — recordPayment increments paid_total, so the
        // idempotency check and the increment must be atomic.
        $settled = DB::transaction(function () use ($order, $reference, $method, $amount) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked
                || $locked->isPaid()
                || ((float) $locked->paid_total > 0 && $locked->payment_reference === $reference)) {
                return false;
            }

            $this->orders->recordPayment($locked, $amount ?? (float) $locked->total, $method, $reference);

            return true;
        });

        if ($settled) {
            $this->notify($order);
        }

        return $settled;
    }

    /** Email the customer their receipt and alert the store — best-effort. */
    public function notify(Order $order): void
    {
        $order = $order->fresh()->load('items', 'customer');

        if (! empty($order->customer?->email)) {
            try {
                Mail::to($order->customer->email)->send(new OrderReceiptMail($order));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        try {
            app(OrderAlertService::class)->notifyNewOrder($order);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
