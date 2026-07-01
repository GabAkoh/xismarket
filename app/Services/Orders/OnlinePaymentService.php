<?php

namespace App\Services\Orders;

use App\Mail\OrderReceiptMail;
use App\Models\Orders\Order;
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
     * Idempotently mark an order paid via an online gateway reference and notify.
     * Returns true only if this call settled it (so callback + webhook don't
     * double-send).
     */
    public function settle(Order $order, string $reference, string $method = 'paystack'): bool
    {
        if ($order->isPaid()) {
            return false;
        }

        $this->orders->markPaid($order, $method, $reference);
        $this->notify($order);

        return true;
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
