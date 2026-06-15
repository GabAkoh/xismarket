<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Mail\OrderReceiptMail;
use App\Models\Inventory\Product;
use App\Models\Orders\Order;
use App\Models\Pos\Customer;
use App\Services\Orders\OrderService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        $orders = Order::query()
            ->with('customer', 'user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $statuses = OrderService::STATUSES;
        $paymentStatuses = ['unpaid', 'paid', 'refunded'];

        return view('orders.index', compact('orders', 'statuses', 'paymentStatuses'));
    }

    public function create()
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'price' => (float) $p->sale_price,
                // Stored as a percent (e.g. 8.0); expose as a fraction for the cart math.
                'tax_rate' => (float) $p->tax_rate / 100,
            ])
            ->values();

        $customers = Customer::orderBy('name')
            ->get(['id', 'name', 'phone', 'address'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'address' => $c->address,
            ])
            ->values();

        return view('orders.create', compact('products', 'customers'));
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'channel' => ['nullable', 'string', 'max:50'],
            'fulfillment_type' => ['required', 'string', 'in:delivery,pickup'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $order = $orders->create($data);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order '.$order->number.' created.');
    }

    public function show(Order $order)
    {
        $this->authorizeTenant($order);
        $order->load('items', 'customer', 'user', 'delivery');

        $statuses = OrderService::STATUSES;

        return view('orders.show', compact('order', 'statuses'));
    }

    public function updateStatus(Request $request, Order $order, OrderService $orders)
    {
        $this->authorizeTenant($order);

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        try {
            $orders->updateStatus($order, $data['status']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order '.$order->number.' is now '.str_replace('_', ' ', $order->status).'.');
    }

    public function pay(Request $request, Order $order, OrderService $orders)
    {
        $this->authorizeTenant($order);

        $data = $request->validate([
            'method' => ['nullable', 'string', 'in:cash,card,other'],
        ]);

        if ($order->isPaid()) {
            return back()->with('error', 'This order is already paid.');
        }
        if ($order->isCancelled()) {
            return back()->with('error', 'A cancelled order cannot be paid.');
        }

        $orders->markPaid($order, $data['method'] ?? 'cash');

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order '.$order->number.' marked as paid.');
    }

    public function fulfill(Order $order, OrderService $orders)
    {
        $this->authorizeTenant($order);

        try {
            $orders->fulfill($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order '.$order->number.' fulfilled.');
    }

    public function cancel(Order $order, OrderService $orders)
    {
        $this->authorizeTenant($order);

        try {
            $orders->cancel($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order '.$order->number.' cancelled.');
    }

    /** (Re)send the order receipt to the customer's email. */
    public function emailReceipt(Order $order)
    {
        $this->authorizeTenant($order);

        $email = $order->customer?->email;
        if (! $email) {
            return back()->with('error', 'This order has no customer email on file.');
        }

        try {
            Mail::to($email)->send(new OrderReceiptMail($order->load('items')));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not send the receipt. Please try again later.');
        }

        return back()->with('status', 'Receipt emailed to '.$email.'.');
    }

    protected function authorizeTenant(Order $order): void
    {
        abort_unless($order->tenant_id === $this->tenancy->id(), 404);
    }
}
