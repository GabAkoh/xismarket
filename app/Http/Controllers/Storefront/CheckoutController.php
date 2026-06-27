<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Mail\OrderReceiptMail;
use App\Models\Orders\Order;
use App\Models\Pos\Customer;
use App\Services\Orders\OrderService;
use App\Services\Storefront\CartService;
use App\Services\Storefront\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function show()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.home')->with('status', 'Your cart is empty.');
        }

        $shipping = app(\App\Support\Tenancy::class)->current()->shippingMethods();
        $firstFee = (float) ($shipping[0]['fee'] ?? 0);

        return view('storefront.checkout', [
            'lines' => $this->cart->lines(),
            'totals' => $this->cart->totals($firstFee),
            'shippingMethods' => $shipping,
        ]);
    }

    public function place(Request $request, OrderService $orders, PaymentGateway $gateway)
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.home')->with('status', 'Your cart is empty.');
        }

        $shipping = app(\App\Support\Tenancy::class)->current()->shippingMethods();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'shipping_method' => ['required', 'integer', 'min:0', 'max:'.max(0, count($shipping) - 1)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'in:card'],   // card is the only online option
            // Card details are required (and never stored).
            'card_number' => ['required', 'string', 'max:30'],
            'card_name' => ['required', 'string', 'max:255'],
            'card_expiry' => ['required', 'string', 'max:10'],
            'card_cvc' => ['required', 'string', 'max:4'],
        ]);

        $method = $shipping[$data['shipping_method']];
        // A delivery method needs an address.
        if (! $method['pickup'] && blank($data['address'] ?? null)) {
            return back()->withInput()->withErrors(['address' => 'A delivery address is required for '.$method['label'].'.']);
        }
        $fee = (float) $method['fee'];

        // Build the order lines up front so we can vet stock before taking payment.
        $items = [];
        foreach ($this->cart->raw() as $productId => $qty) {
            $items[] = ['product_id' => (int) $productId, 'quantity' => (int) $qty];
        }

        // Reject sold-out products BEFORE charging the card (no order = no charge).
        $soldOut = $orders->outOfStock($items);
        if ($soldOut !== []) {
            return back()->with('error',
                'Out of stock: '.implode(', ', $soldOut).'. Please remove '
                .(count($soldOut) === 1 ? 'it' : 'them').' from your cart to checkout.'
            );
        }

        $payByCard = true;

        // --- Authorise the card BEFORE creating the order (decline = no order). ---
        $charge = null;
        if ($payByCard) {
            $total = $this->cart->totals($fee)['total'];
            $charge = $gateway->charge([
                'number' => $request->input('card_number'),
                'name' => $request->input('card_name'),
                'expiry' => $request->input('card_expiry'),
                'cvc' => $request->input('card_cvc'),
            ], $total);

            if (! $charge['success']) {
                // Re-populate everything EXCEPT the card details.
                return back()
                    ->withInput($request->except(['card_number', 'card_cvc', 'card_expiry']))
                    ->with('error', $charge['message'] ?? 'Payment could not be processed.');
            }
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
            'user_id' => null, // placed by the customer, not staff
            'items' => $items,
        ]);

        // Paid online → arrives in the back office already paid (staff just fulfil).
        if ($payByCard && $charge) {
            $orders->markPaid($order, 'card', $charge['brand'].' ····'.$charge['last4'].' · '.$charge['reference']);
        }

        // Email the customer their receipt (best-effort — never fail the order on this).
        if (! empty($customer->email)) {
            try {
                Mail::to($customer->email)->send(new OrderReceiptMail($order->fresh()->load('items')));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Alert the store (owner + admins) that a new order arrived — email now,
        // SMS once a provider is configured. Best-effort; never fail the order.
        try {
            app(\App\Services\Orders\OrderAlertService::class)->notifyNewOrder($order->fresh());
        } catch (\Throwable $e) {
            report($e);
        }

        // Remember which orders this browser placed so only they can see the
        // confirmation (prevents enumerating other people's orders).
        $placed = $request->session()->get('shop.orders', []);
        $placed[] = $order->id;
        $request->session()->put('shop.orders', $placed);
        $request->session()->put('shop.last_order', $order->id);

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

        $order = Order::with('items')->find($lastId);
        if (! $order) {
            return redirect()->route('shop.home');
        }

        return view('storefront.confirmation', compact('order'));
    }
}
