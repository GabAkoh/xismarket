<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Mail\OrderReceiptMail;
use App\Models\Inventory\Product;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\Pos\Customer;
use App\Services\Orders\OrderService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    /** Online-orders summary report (totals, status/fulfilment mix, top products, trend). */
    public function report(Request $request)
    {
        return view('orders.report', $this->reportData($request));
    }

    /** Download an orders-report breakdown as CSV (?section=daily|status|fulfilment|methods|products|all). */
    public function reportExport(Request $request)
    {
        $data = $this->reportData($request);
        $from = $data['from'];
        $to = $data['to'];

        if ($request->input('section') === 'all') {
            return $this->reportExportAll($data);
        }

        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        [$name, $header, $rows] = match ($request->input('section')) {
            'status' => ['order-status', ['Status', 'Orders', 'Value'],
                $data['statusRows']->map(fn ($s) => [$s->status, $s->n, $num($s->total)])],
            'fulfilment' => ['fulfilment', ['Fulfilment', 'Orders', 'Value'],
                $data['fulfilment']->map(fn ($f) => [$f->label, $f->n, $num($f->total)])],
            'methods' => ['payment-methods', ['Method', 'Orders', 'Amount'],
                $data['methods']->map(fn ($m) => [$m->label, $m->n, $num($m->amount)])],
            'products' => ['top-products', ['Product', 'Qty', 'Revenue'],
                $data['top']->map(fn ($p) => [$p->name, $qty($p->qty), $num($p->revenue)])],
            default => ['orders', ['Date', 'Orders', 'Net', 'Tax', 'Total'],
                $data['daily']->map(fn ($d) => [$d->d, $d->n, $num($d->net), $num($d->tax), $num($d->total)])],
        };

        $filename = $name.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** One CSV holding every section of the orders report. */
    protected function reportExportAll(array $data)
    {
        $from = $data['from'];
        $to = $data['to'];
        $s = $data['summary'];
        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        $filename = 'orders-report-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($data, $s, $from, $to, $num, $qty) {
            $out = fopen('php://output', 'w');
            $put = fn ($row) => fputcsv($out, $row, ',', '"', '');
            $section = function (string $title, array $header, $rows) use ($put) {
                $put([$title]);
                $put($header);
                foreach ($rows as $row) {
                    $put($row);
                }
                $put([]);
            };

            $put(['Online orders report', $from->toDateString().' to '.$to->toDateString()]);
            $put([]);
            $section('Summary', ['Metric', 'Value'], [
                ['Orders', $s['count']],
                ['Average order', $num($s['avg'])],
                ['Gross', $num($s['gross'])],
                ['Discounts', $num($s['discounts'])],
                ['Tax', $num($s['tax'])],
                ['Delivery income', $num($s['delivery'])],
                ['Net revenue', $num($s['net'])],
                ['Cost of goods', $num($s['cogs'])],
                ['Gross profit', $num($s['profit'])],
                ['Gross margin %', $num($s['margin'])],
                ['Refunds (count)', $s['refund_count']],
                ['Refunded revenue', $num($s['refund_net'])],
                ['Refunded total', $num($s['refund_total'])],
                ['Net sales after refunds', $num($s['net_after_returns'])],
                ['Gross profit after refunds', $num($s['profit_after_returns'])],
                ['Gross margin % after refunds', $num($s['margin_after_returns'])],
                ['Collected', $num($s['paid'])],
                ['Outstanding (unpaid)', $num($s['outstanding'])],
                ['Cancelled (count)', $s['cancelled_count']],
                ['Cancelled value', $num($s['cancelled_total'])],
            ]);
            $section('Order status', ['Status', 'Orders', 'Value'],
                $data['statusRows']->map(fn ($x) => [$x->status, $x->n, $num($x->total)]));
            $section('Fulfilment', ['Fulfilment', 'Orders', 'Value'],
                $data['fulfilment']->map(fn ($x) => [$x->label, $x->n, $num($x->total)]));
            $section('Payment methods', ['Method', 'Orders', 'Amount'],
                $data['methods']->map(fn ($x) => [$x->label, $x->n, $num($x->amount)]));
            $section('Top products', ['Product', 'Qty', 'Revenue'],
                $data['top']->map(fn ($x) => [$x->name, $qty($x->qty), $num($x->revenue)]));
            $section('Daily breakdown', ['Date', 'Orders', 'Net', 'Tax', 'Total'],
                $data['daily']->map(fn ($x) => [$x->d, $x->n, $num($x->net), $num($x->tax), $num($x->total)]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the orders report for the request's date range (orders bucketed by
     * placed_at; cancelled excluded from value totals). Refunds are full-order
     * refunds bucketed by refunded_at.
     */
    protected function reportData(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $valid = fn () => Order::query()->where('status', '!=', 'cancelled')->whereBetween('placed_at', [$from, $to]);

        $t = $valid()->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(subtotal), 0) as gross,
            COALESCE(SUM(discount_total), 0) as discounts,
            COALESCE(SUM(tax_total), 0) as tax,
            COALESCE(SUM(delivery_fee), 0) as delivery,
            COALESCE(SUM(total), 0) as total,
            COALESCE(SUM(paid_total), 0) as paid
        ')->first();

        $cogs = round((float) OrderItem::whereHas('order', fn ($q) => $q
            ->where('status', '!=', 'cancelled')->whereBetween('placed_at', [$from, $to]))
            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as c')->value('c'), 2);

        $net = round((float) $t->gross - (float) $t->discounts, 2);   // ex-tax, ex-delivery
        $profit = round($net - $cogs, 2);

        // Full-order refunds processed in the period.
        $refRange = fn () => Order::query()->where('payment_status', 'refunded')
            ->whereNotNull('refunded_at')->whereBetween('refunded_at', [$from, $to]);
        $ref = $refRange()->selectRaw('COUNT(*) as count,
            COALESCE(SUM(subtotal - discount_total), 0) as net,
            COALESCE(SUM(total), 0) as total')->first();
        $refCogs = round((float) OrderItem::whereHas('order', fn ($q) => $q
            ->where('payment_status', 'refunded')->whereNotNull('refunded_at')->whereBetween('refunded_at', [$from, $to]))
            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as c')->value('c'), 2);

        $cancelled = Order::query()->where('status', 'cancelled')->whereBetween('placed_at', [$from, $to])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')->first();

        $netAfterReturns = round($net - (float) $ref->net, 2);
        $profitAfterReturns = round($profit - ((float) $ref->net - $refCogs), 2);

        $summary = [
            'count' => (int) $t->count,
            'gross' => round((float) $t->gross, 2),
            'discounts' => round((float) $t->discounts, 2),
            'tax' => round((float) $t->tax, 2),
            'delivery' => round((float) $t->delivery, 2),
            'total' => round((float) $t->total, 2),
            'net' => $net,
            'cogs' => $cogs,
            'profit' => $profit,
            'paid' => round((float) $t->paid, 2),
            'outstanding' => round((float) $t->total - (float) $t->paid, 2),
            'avg' => (int) $t->count > 0 ? round((float) $t->total / (int) $t->count, 2) : 0.0,
            'refund_count' => (int) $ref->count,
            'refund_net' => round((float) $ref->net, 2),
            'refund_total' => round((float) $ref->total, 2),
            'net_after_returns' => $netAfterReturns,
            'profit_after_returns' => $profitAfterReturns,
            'margin' => $net > 0 ? round($profit / $net * 100, 1) : 0.0,
            'margin_after_returns' => $netAfterReturns > 0 ? round($profitAfterReturns / $netAfterReturns * 100, 1) : 0.0,
            'cancelled_count' => (int) $cancelled->count,
            'cancelled_total' => round((float) $cancelled->total, 2),
        ];

        // Status mix (every order in range, including cancelled).
        $statusRows = Order::query()->whereBetween('placed_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as n, COALESCE(SUM(total), 0) as total')
            ->groupBy('status')->orderByDesc('n')->get();

        // Fulfilment mix (valid orders).
        $fulfilment = $valid()->selectRaw('fulfillment_type, COUNT(*) as n, COALESCE(SUM(total), 0) as total')
            ->groupBy('fulfillment_type')->orderByDesc('n')->get()
            ->map(fn ($f) => (object) [
                'label' => ucfirst($f->fulfillment_type ?: 'unspecified'),
                'n' => (int) $f->n, 'total' => round((float) $f->total, 2),
            ]);

        // Payment methods (orders with money collected).
        $labels = $this->tenancy->current()->paymentMethodLabels() + ['wallet' => 'Wallet'];
        $methods = $valid()->where('paid_total', '>', 0)->whereNotNull('payment_method')
            ->selectRaw('payment_method, COUNT(*) as n, COALESCE(SUM(paid_total), 0) as amount')
            ->groupBy('payment_method')->orderByDesc('amount')->get()
            ->map(fn ($m) => (object) [
                'label' => $labels[$m->payment_method] ?? ucfirst(str_replace('_', ' ', $m->payment_method)),
                'n' => (int) $m->n, 'amount' => round((float) $m->amount, 2),
            ]);

        $top = OrderItem::whereHas('order', fn ($q) => $q
            ->where('status', '!=', 'cancelled')->whereBetween('placed_at', [$from, $to]))
            ->selectRaw('product_id, name, COALESCE(SUM(quantity), 0) as qty, COALESCE(SUM(line_total), 0) as revenue')
            ->groupBy('product_id', 'name')->orderByDesc('revenue')->limit(10)->get();

        $daily = $valid()->selectRaw('DATE(placed_at) as d, COUNT(*) as n,
            COALESCE(SUM(subtotal - discount_total), 0) as net,
            COALESCE(SUM(tax_total), 0) as tax,
            COALESCE(SUM(total), 0) as total')
            ->groupBy('d')->orderBy('d')->get();

        return compact('summary', 'statusRows', 'fulfilment', 'methods', 'top', 'daily', 'from', 'to');
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

        $shippingMethods = $this->tenancy->current()->shippingMethods();

        return view('orders.create', compact('products', 'customers', 'shippingMethods'));
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'channel' => ['nullable', 'string', 'max:50'],
            'fulfillment_type' => ['required', 'string', 'in:delivery,pickup'],
            'shipping_method' => ['nullable', 'string', 'max:255'],
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

    public function refund(Order $order, OrderService $orders)
    {
        $this->authorizeTenant($order);

        try {
            $orders->refund($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('status', 'Order '.$order->number.' refunded.');
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
