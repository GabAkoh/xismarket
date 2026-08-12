<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Orders\Order;
use App\Models\Pos\Customer;
use App\Services\Orders\OnlinePaymentService;
use App\Services\Orders\OrderService;
use App\Services\Storefront\CartService;
use App\Services\Storefront\MetaConversionsService;
use App\Services\Storefront\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    use \App\Http\Controllers\Storefront\Concerns\BuildsMetaUserIdentity;

    public function __construct(protected CartService $cart) {}

    public function show(PaystackGateway $gateway, Request $request, MetaConversionsService $meta)
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.home')->with('status', 'Your cart is empty.');
        }

        $store = app(\App\Support\Tenancy::class)->current();
        $shipping = $store->shippingMethods();
        $firstFee = (float) ($shipping[0]['fee'] ?? 0);
        $lines = $this->cart->lines();
        $totals = $this->cart->totals($firstFee);

        // Meta InitiateCheckout — fired here (server, CAPI) and in the view
        // (browser Pixel) under one event_id so Meta de-duplicates the pair.
        $metaEventId = 'ic-'.Str::uuid();
        $meta->track($request, 'InitiateCheckout', [
            'event_id' => $metaEventId,
            'identity' => $this->customerIdentity(),
            'custom_data' => [
                'currency' => (string) $store->currency,
                'value' => (float) $totals['total'],
                'content_type' => 'product',
                'content_ids' => collect($lines)->pluck('product_id')->map(fn ($id) => (string) $id)->values()->all(),
                'num_items' => (int) collect($lines)->sum('qty'),
            ],
        ]);

        return view('storefront.checkout', [
            'lines' => $lines,
            'totals' => $totals,
            'shippingMethods' => $shipping,
            'onlinePayment' => $gateway->configured(),
            'requireFull' => (bool) $store->setting('payments.require_full_payment', false),
            'minDepositPercent' => (int) $store->setting('payments.min_deposit_percent', 0),
            'metaEventId' => $metaEventId,
        ]);
    }

    public function place(Request $request, OrderService $orders, PaystackGateway $gateway, OnlinePaymentService $payments)
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.home')->with('status', 'Your cart is empty.');
        }

        $store = app(\App\Support\Tenancy::class)->current();
        $shipping = $store->shippingMethods();
        $online = $gateway->configured();
        $requireFull = (bool) $store->setting('payments.require_full_payment', false);

        // Which payment methods this checkout offers, given the gateway and the
        // full-payment policy: 'online' (pay in full by card), 'deposit' (pay
        // part now by card), 'offline' (pay on delivery / pickup).
        $allowed = [];
        if ($online) {
            $allowed[] = 'online';
            if (! $requireFull) {
                $allowed[] = 'deposit';
            }
        }
        if (! $online || ! $requireFull) {
            $allowed[] = 'offline';
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            // Paystack needs a customer email; pay-on-delivery doesn't.
            'email' => [$online ? 'required' : 'nullable', 'email', 'max:255'],
            'shipping_method' => ['required', 'integer', 'min:0', 'max:'.max(0, count($shipping) - 1)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'in:'.implode(',', $allowed)],
            // Only present/validated when the deposit option is chosen.
            'deposit_amount' => ['exclude_unless:payment_method,deposit', 'required', 'numeric', 'min:0.01'],
        ]);

        $payOnline = in_array($data['payment_method'], ['online', 'deposit'], true);
        if ($payOnline && ! $online) {
            return back()->withInput()->with('error', 'Online payment is not available right now — please choose pay on delivery.');
        }

        $method = $shipping[$data['shipping_method']];
        if (! $method['pickup'] && blank($data['address'] ?? null)) {
            return back()->withInput()->withErrors(['address' => 'A delivery address is required for '.$method['label'].'.']);
        }
        $fee = (float) $method['fee'];

        // Build the order lines and vet stock before creating the order.
        $items = [];
        foreach ($this->cart->lines() as $line) {
            $items[] = [
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'quantity' => $line['qty'],
            ];
        }

        $soldOut = $orders->outOfStock($items);
        if ($soldOut !== []) {
            return back()->with('error',
                'Out of stock: '.implode(', ', $soldOut).'. Please remove '
                .(count($soldOut) === 1 ? 'it' : 'them').' from your cart to checkout.'
            );
        }

        // A signed-in shopper's orders attach to their account; otherwise match an
        // existing customer by email, or create one from the checkout details.
        $customer = auth('customer')->user();
        if (! $customer && ! empty($data['email'])) {
            $customer = Customer::where('email', $data['email'])->first();
        }
        if (! $customer) {
            $customer = Customer::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
            ]);
        }

        $order = $orders->create([
            'customer_id' => $customer->id,
            'channel' => 'online',
            'fulfillment_type' => $method['pickup'] ? 'pickup' : 'delivery',
            'shipping_method' => $method['label'],
            'delivery_fee' => $method['pickup'] ? 0 : $fee,
            'contact_name' => $data['name'],
            'contact_phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => null,   // placed by the customer, not staff
            'coupon_code' => $this->cart->couponCode(),
            'items' => $items,
        ]);

        // --- Online: hand off to Paystack, settle on the callback/webhook ---
        if ($payOnline) {
            // A deposit charges only the chosen part now; the balance is owed on
            // delivery. The order total is authoritative, so cap the deposit to it.
            $charge = (float) $order->total;
            if ($data['payment_method'] === 'deposit') {
                $charge = round(min((float) $data['deposit_amount'], (float) $order->total), 2);
                if ($charge <= 0) {
                    return redirect()->route('shop.checkout')->with('error', 'Enter a deposit greater than zero.');
                }
                // Enforce the store's minimum deposit (a % of the order total).
                $minPercent = (int) $store->setting('payments.min_deposit_percent', 0);
                $minCharge = $minPercent > 0 ? round((float) $order->total * $minPercent / 100, 2) : 0.0;
                if ($minCharge > 0 && $charge + 0.001 < $minCharge) {
                    return redirect()->route('shop.checkout')->with('error',
                        'The minimum deposit is '.$minPercent.'% of your order ('.$store->currencySymbol().number_format($minCharge, 2).').');
                }
            }

            $reference = 'PSK-'.$order->number.'-'.strtoupper(Str::random(6));
            $order->update(['payment_reference' => $reference]);

            try {
                $init = $gateway->initialize((string) $customer->email, $charge, $reference, route('shop.checkout.callback'));
            } catch (\Throwable $e) {
                report($e);

                return redirect()->route('shop.checkout')->with('error', 'Could not start the payment. Please try again.');
            }

            // Remember the order so the confirmation page shows it after return.
            // Keep the cart until payment succeeds (cleared in the callback).
            $this->rememberOrder($request, $order);

            return redirect()->away($init['authorization_url']);
        }

        // --- Pay on delivery / pickup: placed unpaid; notify now ---
        $payments->notify($order);
        $this->rememberOrder($request, $order);
        $this->cart->clear();

        return redirect()->route('shop.confirmation');
    }

    /** Paystack redirects the customer back here after payment. */
    public function paymentCallback(Request $request, PaystackGateway $gateway, OnlinePaymentService $payments)
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref') ?: '');
        if ($reference === '') {
            return redirect()->route('shop.home');
        }

        $order = Order::where('payment_reference', $reference)->first();
        if (! $order) {
            return redirect()->route('shop.home')->with('error', 'We could not find that order.');
        }

        // Settle unless the webhook already did.
        if (! $order->isPaid()) {
            $result = $gateway->verify($reference);
            // When full payment is required the capture must cover the total;
            // otherwise any positive capture (a deposit) is accepted.
            $requireFull = (bool) app(\App\Support\Tenancy::class)->current()->setting('payments.require_full_payment', false);
            $ok = $result['success'] && ($requireFull
                ? round($result['amount'], 2) + 0.01 >= round((float) $order->total, 2)
                : $result['amount'] > 0);
            if (! $ok) {
                return redirect()->route('shop.checkout')->with('error', 'Your payment was not completed — you can try again.');
            }
            $payments->settle($order, $reference, 'paystack', (float) $result['amount']);
        }

        $this->rememberOrder($request, $order);
        $this->cart->clear();

        return redirect()->route('shop.confirmation');
    }

    public function confirmation(Request $request)
    {
        $lastId = $request->session()->get('shop.last_order');
        $placed = $request->session()->get('shop.orders', []);

        if (! $lastId || ! in_array($lastId, $placed, true)) {
            return redirect()->route('shop.home');
        }

        $order = Order::with('items.product')->find($lastId);
        if (! $order) {
            return redirect()->route('shop.home');
        }

        return view('storefront.confirmation', compact('order'));
    }

    /** Record which orders this browser placed (so only they can see the confirmation). */
    protected function rememberOrder(Request $request, Order $order): void
    {
        $placed = $request->session()->get('shop.orders', []);
        if (! in_array($order->id, $placed, true)) {
            $placed[] = $order->id;
        }
        $request->session()->put('shop.orders', $placed);
        $request->session()->put('shop.last_order', $order->id);
    }
}
