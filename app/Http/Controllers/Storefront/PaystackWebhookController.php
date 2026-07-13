<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Orders\Order;
use App\Services\Orders\OnlinePaymentService;
use App\Services\Storefront\PaystackGateway;
use Illuminate\Http\Request;

/**
 * Server-to-server payment confirmation from Paystack. This is the reliable
 * settlement path (the browser callback can be missed if the customer closes
 * the tab). Verifies the signature, then settles the matching order idempotently.
 */
class PaystackWebhookController extends Controller
{
    public function handle(Request $request, PaystackGateway $gateway, OnlinePaymentService $payments)
    {
        $payload = $request->getContent();

        if (! $gateway->validSignature($payload, $request->header('x-paystack-signature'))) {
            abort(401);
        }

        $event = json_decode($payload, true) ?: [];

        if (($event['event'] ?? null) === 'charge.success') {
            $reference = (string) ($event['data']['reference'] ?? '');
            $amount = ((int) ($event['data']['amount'] ?? 0)) / 100;

            $order = $reference !== '' ? Order::where('payment_reference', $reference)->first() : null;
            if ($order && ! $order->isPaid()) {
                // When full payment is required the capture must cover the total;
                // otherwise any positive capture (a deposit) settles the order.
                $requireFull = (bool) app(\App\Support\Tenancy::class)->current()->setting('payments.require_full_payment', false);
                $ok = $requireFull
                    ? round($amount, 2) + 0.01 >= round((float) $order->total, 2)
                    : $amount > 0;
                if ($ok) {
                    $payments->settle($order, $reference, 'paystack', (float) $amount);
                }
            }
        }

        // Always 200 so Paystack stops retrying (we've recorded what we can).
        return response()->json(['status' => true]);
    }
}
