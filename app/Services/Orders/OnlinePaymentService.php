<?php

namespace App\Services\Orders;

use App\Jobs\SendMetaEvent;
use App\Mail\OrderReceiptMail;
use App\Models\Orders\Order;
use App\Services\Storefront\MetaConversionsService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

        $this->trackPurchase($order);
    }

    /**
     * Queue a server-side Meta Purchase (Conversions API). This is the reliable
     * conversion signal — it fires from the single settlement point hit by the
     * browser callback, the Paystack webhook AND pay-on-delivery, so every order
     * is attributed even when the shopper never lands on the confirmation page.
     * The event_id ('order-<id>') matches the browser Pixel's Purchase so Meta
     * de-duplicates the pair. PII is hashed here (never queued in the clear).
     */
    protected function trackPurchase(Order $order): void
    {
        try {
            $tenant = app(Tenancy::class)->current();
            $meta = app(MetaConversionsService::class);
            if (! $tenant || ! $meta->enabled($tenant)) {
                return;   // Meta not configured for this store — nothing to send.
            }

            [$first, $last] = $this->splitName($order->contact_name ?: $order->customer?->name);

            $contents = $order->items->map(fn ($item) => [
                'id' => (string) $item->product_id,
                'quantity' => (int) $item->quantity,
                'item_price' => round((float) $item->unit_price, 2),
            ])->values()->all();

            SendMetaEvent::dispatch($tenant->id, 'Purchase', [
                'event_id' => 'order-'.$order->id,
                'action_source' => 'website',
                'user_data' => $meta->userData(null, [
                    'email' => $order->customer?->email,
                    'phone' => $order->contact_phone ?: $order->customer?->phone,
                    'first_name' => $first,
                    'last_name' => $last,
                ]),
                'custom_data' => [
                    'currency' => (string) $tenant->currency,
                    'value' => round((float) $order->total, 2),
                    'content_type' => 'product',
                    'content_ids' => $order->items->pluck('product_id')->filter()->map(fn ($id) => (string) $id)->values()->all(),
                    'contents' => $contents,
                    'num_items' => (int) $order->items->sum('quantity'),
                    'order_id' => (string) $order->number,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);   // never let tracking break settlement
        }
    }

    /** Split a full name into [first, last] for Meta user_data matching. */
    protected function splitName(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return [null, null];
        }
        $first = Str::before($name, ' ');
        $last = trim(Str::after($name, ' '));

        return [$first, $last !== '' ? $last : null];
    }
}
