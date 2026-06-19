<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Orders\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleItem;
use App\Services\Pos\SaleService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalesController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        $sales = Sale::query()
            ->with('customer', 'user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('completed_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('completed_at', '<=', $request->date('to')))
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $statuses = ['completed', 'partially_paid', 'refunded', 'partially_refunded', 'void'];

        return view('sales.index', compact('sales', 'statuses'));
    }

    /**
     * Returns & refunds report. Each return is recorded as negative payment
     * rows sharing a reference ({number}-R{n}); grouping them gives one row per
     * return event with its cash/store-credit split.
     */
    public function returns(Request $request)
    {
        return view('sales.returns', $this->returnsData($request));
    }

    /** Download the returns report (current filter) as CSV. */
    public function returnsExport(Request $request)
    {
        ['rows' => $rows, 'from' => $from, 'to' => $to] = $this->returnsData($request);

        $filename = 'returns-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // Explicit args (incl. $escape) — PHP 8.4 deprecates omitting them,
            // and the warning would otherwise corrupt the streamed CSV.
            fputcsv($out, ['Date', 'Source', 'Number', 'Reference', 'Customer', 'Cash', 'Store credit', 'Total'], ',', '"', '');
            foreach ($rows as $r) {
                fputcsv($out, [
                    optional($r->date)->format('Y-m-d H:i'),
                    $r->type,
                    $r->number,
                    $r->reference,
                    $r->customer,
                    number_format($r->cash, 2, '.', ''),
                    number_format($r->wallet, 2, '.', ''),
                    number_format($r->total, 2, '.', ''),
                ], ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the returns report data for the request's date range.
     *
     * @return array{rows: \Illuminate\Support\Collection, summary: array, from: Carbon, to: Carbon}
     */
    protected function returnsData(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        // POS sale returns — negative payment rows grouped by return reference.
        $posRows = Payment::query()
            ->where('amount', '<', 0)
            ->whereBetween('paid_at', [$from, $to])
            ->with('sale.customer')
            ->get()
            ->groupBy('reference')
            ->map(function ($group) {
                $total = round(abs((float) $group->sum('amount')), 2);
                $wallet = round(abs((float) $group->where('method', 'wallet')->sum('amount')), 2);
                $sale = $group->first()->sale;

                return (object) [
                    'type' => 'POS',
                    'date' => $group->max('paid_at'),
                    'number' => $sale?->number ?? '—',
                    'reference' => $group->first()->reference,
                    'customer' => $sale?->customer?->name ?? 'Walk-in',
                    'url' => $sale ? route('sales.show', $sale) : null,
                    'cash' => round($total - $wallet, 2),
                    'wallet' => $wallet,
                    'total' => $total,
                ];
            })
            ->values();

        // Online-order refunds (full-order; no store-credit component).
        $orderRows = Order::query()
            ->where('payment_status', 'refunded')
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$from, $to])
            ->with('customer')
            ->get()
            ->map(function ($order) {
                $total = round((float) $order->total, 2);

                return (object) [
                    'type' => 'Online',
                    'date' => $order->refunded_at,
                    'number' => $order->number,
                    'reference' => $order->number.'-R',
                    'customer' => $order->customer?->name ?? 'Guest',
                    'url' => route('orders.show', $order),
                    'cash' => $total,
                    'wallet' => 0.0,
                    'total' => $total,
                ];
            });

        $rows = $posRows->concat($orderRows)->sortByDesc('date')->values();

        $summary = [
            'count' => $rows->count(),
            'total' => round((float) $rows->sum('total'), 2),
            'cash' => round((float) $rows->sum('cash'), 2),
            'wallet' => round((float) $rows->sum('wallet'), 2),
        ];

        return compact('rows', 'summary', 'from', 'to');
    }

    /** Sales summary report (totals, payment mix, daily trend, top products). */
    public function report(Request $request)
    {
        return view('sales.report', $this->reportData($request));
    }

    /**
     * Download a report breakdown (current filter) as CSV. The ?section= param
     * selects which table: daily (default), methods, products, cashiers, registers.
     */
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
            'methods' => ['payment-methods', ['Method', 'Count', 'Amount'],
                $data['methods']->map(fn ($m) => [$m->label, $m->n, $num($m->amount)])],
            'products' => ['top-products', ['Product', 'Qty', 'Revenue'],
                $data['top']->map(fn ($p) => [$p->name, $qty($p->qty), $num($p->revenue)])],
            'cashiers' => ['cashiers', ['Cashier', 'Sales', 'Net', 'Total'],
                $data['cashiers']->map(fn ($c) => [$c->name, $c->n, $num($c->net), $num($c->total)])],
            'registers' => ['registers', ['Register', 'Sales', 'Net', 'Total'],
                $data['registers']->map(fn ($r) => [$r->name, $r->n, $num($r->net), $num($r->total)])],
            default => ['sales', ['Date', 'Sales', 'Net', 'Tax', 'Total'],
                $data['daily']->map(fn ($d) => [$d->d, $d->n, $num($d->net), $num($d->tax), $num($d->total)])],
        };

        $filename = $name.'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            // Explicit args (incl. $escape) — PHP 8.4 deprecates omitting them.
            fputcsv($out, $header, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** One CSV holding every section of the report (summary + each breakdown). */
    protected function reportExportAll(array $data)
    {
        $from = $data['from'];
        $to = $data['to'];
        $s = $data['summary'];

        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');

        $filename = 'sales-report-'.$from->toDateString().'-to-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($data, $s, $from, $to, $num, $qty) {
            $out = fopen('php://output', 'w');
            $put = fn ($row) => fputcsv($out, $row, ',', '"', '');
            // Title row, header row, data rows, then a blank separator.
            $section = function (string $title, array $header, $rows) use ($put) {
                $put([$title]);
                $put($header);
                foreach ($rows as $row) {
                    $put($row);
                }
                $put([]);
            };

            $put(['Sales report', $from->toDateString().' to '.$to->toDateString()]);
            $put([]);

            $section('Summary', ['Metric', 'Value'], [
                ['Sales', $s['count']],
                ['Average sale', $num($s['avg'])],
                ['Gross', $num($s['gross'])],
                ['Discounts', $num($s['discounts'])],
                ['Tax', $num($s['tax'])],
                ['Net revenue', $num($s['net'])],
                ['Cost of goods', $num($s['cogs'])],
                ['Gross profit', $num($s['profit'])],
                ['Revenue reversed (returns)', $num($s['returns_net'])],
                ['Tax reversed (returns)', $num($s['returns_tax'])],
                ['Total refunded', $num($s['returns_total'])],
                ['COGS recovered (returns)', $num($s['returns_cogs'])],
                ['Net sales after returns', $num($s['net_after_returns'])],
                ['Gross profit after returns', $num($s['profit_after_returns'])],
                ['Collected', $num($s['collected'])],
                ['Outstanding (credit)', $num($s['outstanding'])],
            ]);

            $section('Payment methods', ['Method', 'Count', 'Amount'],
                $data['methods']->map(fn ($m) => [$m->label, $m->n, $num($m->amount)]));
            $section('Top products', ['Product', 'Qty', 'Revenue'],
                $data['top']->map(fn ($p) => [$p->name, $qty($p->qty), $num($p->revenue)]));
            $section('Sales by cashier', ['Cashier', 'Sales', 'Net', 'Total'],
                $data['cashiers']->map(fn ($c) => [$c->name, $c->n, $num($c->net), $num($c->total)]));
            $section('Sales by register', ['Register', 'Sales', 'Net', 'Total'],
                $data['registers']->map(fn ($r) => [$r->name, $r->n, $num($r->net), $num($r->total)]));
            $section('Daily breakdown', ['Date', 'Sales', 'Net', 'Tax', 'Total'],
                $data['daily']->map(fn ($d) => [$d->d, $d->n, $num($d->net), $num($d->tax), $num($d->total)]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Build the sales report for the request's date range. Excludes voided sales;
     * sales are bucketed by completed_at.
     *
     * @return array{summary: array, methods: \Illuminate\Support\Collection, daily: \Illuminate\Support\Collection, top: \Illuminate\Support\Collection, from: Carbon, to: Carbon}
     */
    protected function reportData(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $inRange = fn ($q) => $q->where('status', '!=', 'void')->whereBetween('completed_at', [$from, $to]);

        // Headline totals.
        $t = Sale::query()->tap($inRange)->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(subtotal), 0) as gross,
            COALESCE(SUM(discount_total), 0) as discounts,
            COALESCE(SUM(tax_total), 0) as tax,
            COALESCE(SUM(total), 0) as total,
            COALESCE(SUM(balance_due), 0) as outstanding
        ')->first();

        $cogs = round((float) SaleItem::whereHas('sale', $inRange)
            ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as cogs')
            ->value('cogs'), 2);

        $net = round((float) $t->gross - (float) $t->discounts, 2);   // ex-tax revenue
        $profit = round($net - $cogs, 2);

        // Returns processed in this period, taken from the reversing journal entries
        // (both POS returns and online-order refunds debit revenue / credit COGS,
        // reference "…-R"). Sourcing returns here keeps the net-of-returns figures
        // consistent with the P&L. Guarded — the Accounting module is optional.
        $returnsNet = $returnsTax = $returnsCogs = 0.0;
        $accountClass = \App\Models\Accounting\Account::class;
        $lineClass = \App\Models\Accounting\JournalLine::class;
        if (class_exists($accountClass) && class_exists($lineClass)) {
            $returnSum = function (string $code, string $side) use ($from, $to, $accountClass, $lineClass) {
                $account = $accountClass::where('code', $code)->first();
                if (! $account) {
                    return 0.0;
                }

                return (float) $lineClass::where('account_id', $account->id)
                    ->whereHas('entry', fn ($e) => $e
                        ->whereBetween('entry_date', [$from, $to])
                        ->where('reference', 'like', '%-R%'))
                    ->sum($side);
            };
            $returnsNet = round($returnSum('4000', 'debit'), 2);    // revenue reversed
            $returnsTax = round($returnSum('2100', 'debit'), 2);    // tax reversed
            $returnsCogs = round($returnSum('5000', 'credit'), 2);  // COGS recovered
        }
        $returnsTotal = round($returnsNet + $returnsTax, 2);

        $summary = [
            'count' => (int) $t->count,
            'gross' => round((float) $t->gross, 2),
            'discounts' => round((float) $t->discounts, 2),
            'tax' => round((float) $t->tax, 2),
            'total' => round((float) $t->total, 2),
            'net' => $net,
            'cogs' => $cogs,
            'profit' => $profit,
            // Money actually kept = billed minus what's still owed (excludes change).
            'collected' => round((float) $t->total - (float) $t->outstanding, 2),
            'outstanding' => round((float) $t->outstanding, 2),
            'avg' => (int) $t->count > 0 ? round((float) $t->total / (int) $t->count, 2) : 0.0,
            // Returns in the period + net-of-returns bottom lines.
            'returns_net' => $returnsNet,
            'returns_tax' => $returnsTax,
            'returns_cogs' => $returnsCogs,
            'returns_total' => $returnsTotal,
            'net_after_returns' => round($net - $returnsNet, 2),
            'profit_after_returns' => round($profit - ($returnsNet - $returnsCogs), 2),
        ];

        // Payment mix (positive payments only; refunds are negative).
        $labels = $this->tenancy->current()->paymentMethodLabels() + ['wallet' => 'Wallet'];
        $methods = Payment::whereHas('sale', $inRange)
            ->where('amount', '>', 0)
            ->selectRaw('method, COALESCE(SUM(amount), 0) as amount, COUNT(*) as n')
            ->groupBy('method')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($m) => (object) [
                'label' => $labels[$m->method] ?? ucfirst(str_replace('_', ' ', $m->method)),
                'amount' => round((float) $m->amount, 2),
                'n' => (int) $m->n,
            ]);

        // Per-day breakdown.
        $daily = Sale::query()->tap($inRange)
            ->selectRaw('DATE(completed_at) as d, COUNT(*) as n,
                COALESCE(SUM(subtotal - discount_total), 0) as net,
                COALESCE(SUM(tax_total), 0) as tax,
                COALESCE(SUM(total), 0) as total')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        // Top products by revenue.
        $top = SaleItem::whereHas('sale', $inRange)
            ->selectRaw('product_id, name, COALESCE(SUM(quantity), 0) as qty, COALESCE(SUM(line_total), 0) as revenue')
            ->groupBy('product_id', 'name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Per-cashier breakdown.
        $cashiers = Sale::query()->tap($inRange)
            ->selectRaw('user_id, COUNT(*) as n,
                COALESCE(SUM(subtotal - discount_total), 0) as net,
                COALESCE(SUM(total), 0) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->get();
        $names = \App\Models\User::whereIn('id', $cashiers->pluck('user_id')->filter())->pluck('name', 'id');
        $cashiers->each(fn ($c) => $c->name = $names[$c->user_id] ?? 'Unknown');

        // Per-register breakdown.
        $registers = Sale::query()->tap($inRange)
            ->selectRaw('register_id, COUNT(*) as n,
                COALESCE(SUM(subtotal - discount_total), 0) as net,
                COALESCE(SUM(total), 0) as total')
            ->groupBy('register_id')
            ->orderByDesc('total')
            ->get();
        $regNames = \App\Models\Pos\Register::whereIn('id', $registers->pluck('register_id')->filter())->pluck('name', 'id');
        $registers->each(fn ($r) => $r->name = $regNames[$r->register_id] ?? 'No register');

        return compact('summary', 'methods', 'daily', 'top', 'cashiers', 'registers', 'from', 'to');
    }

    public function show(Sale $sale)
    {
        $this->authorizeTenant($sale);
        $sale->load('items', 'payments', 'customer', 'register', 'shift', 'user');

        return view('sales.show', compact('sale'));
    }

    /** Record a follow-up payment that settles part/all of a credit sale's balance. */
    public function addPayment(Request $request, Sale $sale, SaleService $sales)
    {
        $this->authorizeTenant($sale);

        $tenant = $this->tenancy->current();
        // Settle a credit sale with real money (or wallet) — not another credit method.
        $allowed = array_merge(
            array_values(array_diff($tenant->paymentMethodKeys(), $tenant->creditPaymentMethodKeys())),
            ['wallet'],
        );
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', \Illuminate\Validation\Rule::in($allowed)],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $sale = $sales->addPayment($sale, $data);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $sale->status === 'completed'
            ? 'Payment recorded — sale '.$sale->number.' is now fully paid.'
            : 'Payment recorded. Balance due: '.number_format((float) $sale->balance_due, 2).'.';

        return redirect()->route('sales.show', $sale)->with('status', $msg);
    }

    public function refund(Request $request, Sale $sale, SaleService $sales)
    {
        $this->authorizeTenant($sale);

        $data = $request->validate([
            'returns' => ['required', 'array'],
            'returns.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $sale = $sales->processReturn($sale, $data['returns']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $sale->status === 'refunded'
            ? 'Sale '.$sale->number.' fully returned.'
            : 'Return processed for sale '.$sale->number.'.';

        return redirect()->route('sales.show', $sale)->with('status', $msg);
    }

    protected function authorizeTenant(Sale $sale): void
    {
        abort_unless($sale->tenant_id === $this->tenancy->id(), 404);
    }
}
