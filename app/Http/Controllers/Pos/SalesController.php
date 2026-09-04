<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Orders\Order;
use App\Models\Pos\CashMovement;
use App\Models\Pos\Payment;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleItem;
use App\Services\Pos\SaleService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        // Filter by the STORE-local date of the sale (completed_at is stored UTC),
        // so the list agrees with the sales/payments reports at the day boundary.
        $tzOffset = Carbon::now($this->tenancy->current()->timezone())->format('P');
        $localDate = "DATE(CONVERT_TZ(completed_at, '+00:00', ?))";

        $method = $request->string('method');
        $productId = $request->integer('product_id');

        // Shared filtered query, reused for the page rows and the footer total so
        // both agree on exactly which sales match.
        $base = Sale::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereRaw("$localDate >= ?", [$tzOffset, $request->date('from')->toDateString()]))
            ->when($request->filled('to'), fn ($q) => $q->whereRaw("$localDate <= ?", [$tzOffset, $request->date('to')->toDateString()]))
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('product_id'), fn ($q) => $q->whereHas('items',
                fn ($i) => $i->where('product_id', $productId)))
            // A sale can have several payments in different methods (split pay,
            // later credit settlements), so match any positive payment with the
            // chosen method — mirrors the sales report's method filter.
            ->when($request->filled('method'), fn ($q) => $q->whereHas('payments',
                fn ($p) => $p->where('method', $method)->where('amount', '>', 0)));

        // Which slice the amount column shows: a method filter narrows it to that
        // method's payments; else a product filter narrows it to that product's
        // lines; else the full invoice total. Method wins when both are set.
        $amountMode = $request->filled('method') ? 'method'
            : ($request->filled('product_id') ? 'product' : 'invoice');

        $sales = (clone $base)->with('customer', 'user');
        if ($amountMode === 'method') {
            $sales->withSum(['payments as filtered_amount' => fn ($p) => $p
                ->where('method', $method)->where('amount', '>', 0)], 'amount');
        } elseif ($amountMode === 'product') {
            $sales->withSum(['items as filtered_amount' => fn ($i) => $i
                ->where('product_id', $productId)], 'line_total');
        }
        $sales = $sales
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Footer total across ALL matching sales (not just the current page), in
        // the same slice the rows show.
        $filteredTotal = match ($amountMode) {
            'method' => (float) Payment::whereIn('sale_id', (clone $base)->select('id'))
                ->where('method', $method)->where('amount', '>', 0)->sum('amount'),
            'product' => (float) SaleItem::whereIn('sale_id', (clone $base)->select('id'))
                ->where('product_id', $productId)->sum('line_total'),
            default => (float) (clone $base)->sum('total'),
        };

        $statuses = ['completed', 'partially_paid', 'refunded', 'partially_refunded', 'void'];

        // Product filter options: only products that have actually sold, keeping
        // the dropdown relevant and bounded (mirrors the sales report).
        $soldProductIds = SaleItem::whereNotNull('product_id')->distinct()->pluck('product_id');
        $products = \App\Models\Inventory\Product::whereIn('id', $soldProductIds)
            ->orderBy('name')->get(['id', 'name']);

        // Payment method options: the tenant's configured methods plus wallet.
        $methodOptions = $this->tenancy->current()->paymentMethodLabels() + ['wallet' => 'Wallet'];

        return view('sales.index', compact('sales', 'statuses', 'products', 'methodOptions', 'amountMode', 'filteredTotal'));
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
        // Store-timezone day boundaries; query on the UTC instants (stored UTC).
        $tz = $this->tenancy->current()->timezone();
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        $fromU = $from->copy()->utc();
        $toU = $to->copy()->utc();

        // POS sale returns — negative payment rows grouped by return reference.
        $posRows = Payment::query()
            ->where('amount', '<', 0)
            ->whereBetween('paid_at', [$fromU, $toU])
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
            ->whereBetween('refunded_at', [$fromU, $toU])
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
     * Drill-down behind a single payment-method total: the individual payments
     * that make it up, each showing that sale's portion for the method (a split
     * tender records one payment per method per sale), so the rows sum EXACTLY
     * to the figure the user clicked — unlike the sales list, which shows whole
     * invoice totals.
     *
     * ?source lets the same page reconcile with two different totals:
     *   - report (default): the Sales report "Payment methods" card — every
     *     payment of this method on sales COMPLETED in the period (optionally
     *     product-filtered), matching reportData()'s $methods query.
     *   - pos-receipts: the Payments summary "POS sales" section — checkout
     *     receipts (kind = sale) PAID in the period, matching paymentsSummaryData().
     */
    public function reportMethodBreakdown(Request $request)
    {
        $tz = $this->tenancy->current()->timezone();
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        $fromU = $from->copy()->utc();
        $toU = $to->copy()->utc();

        $method = (string) $request->input('method', '');
        abort_if($method === '', 404);
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;
        $source = $request->input('source') === 'pos-receipts' ? 'pos-receipts' : 'report';

        $labels = $this->tenancy->current()->paymentMethodLabels() + ['wallet' => 'Wallet'];
        $label = $labels[$method] ?? ucfirst(str_replace('_', ' ', $method));

        $query = Payment::query()->where('method', $method)
            ->with(['sale:id,number,completed_at,customer_id,total', 'sale.customer:id,name']);

        if ($source === 'pos-receipts') {
            $query->where('kind', 'sale')
                ->whereBetween('paid_at', [$fromU, $toU])
                ->whereHas('sale', fn ($s) => $s->whereNotIn('status', ['void', 'refunded']));
        } else {
            $query->whereHas('sale', function ($s) use ($fromU, $toU, $productId) {
                $s->whereNotIn('status', ['void', 'refunded'])->whereBetween('completed_at', [$fromU, $toU]);
                if ($productId !== null) {
                    $s->whereHas('items', fn ($i) => $i->where('product_id', $productId));
                }
            });
        }

        $payments = $query->orderByDesc('paid_at')->get()->map(fn ($p) => (object) [
            'sale_id' => $p->sale?->id,
            'number' => $p->sale?->number,
            'at' => $p->paid_at,
            'customer' => $p->sale?->customer?->name,
            'kind' => $p->kind,
            'amount' => round((float) $p->amount, 2),
        ]);
        $total = round($payments->sum('amount'), 2);

        return view('sales.method-breakdown', compact('from', 'to', 'tz', 'method', 'label', 'productId', 'source', 'payments', 'total'));
    }

    /**
     * Payments summary: every money movement in the period grouped by source —
     * POS sales, customer settlements, online sales, cash in (received) and cash
     * out + refunds (paid out) — each broken down by payment method.
     */
    public function paymentsSummary(Request $request)
    {
        return view('sales.payments-summary', $this->paymentsSummaryData($request));
    }

    /** CSV of the payments summary (every source + the combined method mix). */
    public function paymentsSummaryExport(Request $request)
    {
        $data = $this->paymentsSummaryData($request);
        $num = fn ($v) => number_format((float) $v, 2, '.', '');
        $filename = 'payments-summary-'.$data['from']->toDateString().'-to-'.$data['to']->toDateString().'.csv';

        return response()->streamDownload(function () use ($data, $num) {
            $out = fopen('php://output', 'w');
            $put = fn ($row) => fputcsv($out, $row, ',', '"', '');

            $put(['Payments summary', $data['from']->toDateString().' to '.$data['to']->toDateString()]);
            $put([]);

            $section = function (string $title, $rows, float $total) use ($put, $num) {
                $put([$title, 'Count', 'Amount']);
                foreach ($rows as $r) {
                    $put([$r->label, $r->n, $num($r->amount)]);
                }
                $put(['Subtotal', '', $num($total)]);
                $put([]);
            };

            foreach ($data['sources'] as $s) {
                $section($s['title'].' (received)', $s['rows'], $s['total']);
            }
            foreach ($data['outflows'] as $s) {
                $section($s['title'].' (paid out)', $s['rows'], $s['total']);
            }
            $section('All received by method', $data['combined'], $data['totalReceived']);

            if ($data['creditExtended']) {
                $put(['On credit (receivable, not cash)', 'Count', 'Amount']);
                $put(['Sold on credit', $data['creditExtended']->n, $num($data['creditExtended']->amount)]);
                $put([]);
            }

            $put(['Totals']);
            $put(['Total received', '', $num($data['totalReceived'])]);
            $put(['Total paid out', '', $num($data['totalOut'])]);
            $put(['Net movement', '', $num($data['net'])]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Assemble the payments summary for the request's date range.
     *
     * @return array{from: Carbon, to: Carbon, sources: array, outflows: array, combined: \Illuminate\Support\Collection, totalReceived: float, totalOut: float, net: float}
     */
    protected function paymentsSummaryData(Request $request): array
    {
        // Day boundaries in the STORE's timezone (e.g. Africa/Lagos), so "today"
        // and the month-to-date range line up with the trading day rather than
        // UTC. Stored timestamps are UTC, so query on the UTC instants ($fromU/$toU).
        $tz = $this->tenancy->current()->timezone();
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        $fromU = $from->copy()->utc();
        $toU = $to->copy()->utc();

        $labels = $this->tenancy->current()->paymentMethodLabels() + ['wallet' => 'Wallet'];
        $methodLabel = fn ($m) => $labels[$m] ?? ucfirst((string) ($m ?: 'other'));
        $row = fn ($method, $label, $n, $amount) => (object) [
            'method' => $method, 'label' => $label, 'n' => (int) $n, 'amount' => round((float) $amount, 2),
        ];

        // POS payments table: sales (checkout), settlements (later), refunds (negative).
        // Fully-refunded sales are excluded: their receipts and the offsetting
        // refund wash out, so they'd only overstate both the received and paid-out
        // sides (net movement is unchanged). Partial refunds stay — the sale is
        // still 'partially_refunded', so its receipts and refund both show.
        $pos = Payment::query()
            ->whereHas('sale', fn ($q) => $q->whereNotIn('status', ['void', 'refunded']))
            ->whereBetween('paid_at', [$fromU, $toU])
            ->selectRaw('kind, method, SUM(amount) as amount, COUNT(*) as n')
            ->groupBy('kind', 'method')
            ->get();

        $posByKind = fn (string $kind, bool $abs = false) => $pos
            ->where('kind', $kind)
            ->map(fn ($r) => $row($r->method, $methodLabel($r->method), $r->n, $abs ? abs((float) $r->amount) : $r->amount))
            ->sortByDesc('amount')->values();

        $posSales = $posByKind('sale');
        $settlements = $posByKind('settlement');
        $posRefunds = $posByKind('refund', abs: true);

        // Online order payments (paid in the period).
        $online = collect();
        $onlineRefunds = 0.0;
        if (class_exists(Order::class)) {
            $online = Order::query()
                ->whereIn('payment_status', ['paid', 'refunded'])
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$fromU, $toU])
                ->selectRaw('payment_method, SUM(paid_total) as amount, COUNT(*) as n')
                ->groupBy('payment_method')
                ->get()
                ->map(fn ($r) => $row($r->payment_method ?: 'card', $methodLabel($r->payment_method ?: 'card'), $r->n, $r->amount))
                ->sortByDesc('amount')->values();

            $onlineRefunds = round((float) Order::where('payment_status', 'refunded')
                ->whereBetween('refunded_at', [$fromU, $toU])->sum('total'), 2);
        }

        // Cash drawer movements, grouped by reason.
        $cashIn = collect();
        $cashOut = collect();
        if (class_exists(CashMovement::class)) {
            $cm = CashMovement::query()
                ->whereBetween('created_at', [$fromU, $toU])
                ->selectRaw('type, reason, SUM(amount) as amount, COUNT(*) as n')
                ->groupBy('type', 'reason')->get();

            $mapCm = fn (string $type) => $cm->where('type', $type)
                ->map(fn ($r) => $row($r->reason, ucfirst(str_replace('_', ' ', (string) $r->reason)), $r->n, $r->amount))
                ->sortByDesc('amount')->values();

            $cashIn = $mapCm('in');
            $cashOut = $mapCm('out');
        }

        // Online refunds folded into the POS-refunds section as a single line.
        if ($onlineRefunds > 0) {
            $posRefunds = $posRefunds->push($row('online', 'Online refunds', 0, $onlineRefunds))->values();
        }

        $sum = fn ($c) => round($c->sum('amount'), 2);

        // Combined "received by method" across POS sales + settlements + online.
        $combined = collect()->concat($posSales)->concat($settlements)->concat($online)
            ->groupBy('method')
            ->map(fn ($g, $m) => $row($m, $methodLabel($m), $g->sum('n'), $g->sum('amount')))
            ->sortByDesc('amount')->values();

        $sources = [
            ['key' => 'pos', 'title' => 'POS sales', 'rows' => $posSales, 'total' => $sum($posSales)],
            ['key' => 'settlements', 'title' => 'Customer settlements', 'rows' => $settlements, 'total' => $sum($settlements)],
            ['key' => 'online', 'title' => 'Online sales', 'rows' => $online, 'total' => $sum($online)],
            ['key' => 'cash_in', 'title' => 'Cash in', 'rows' => $cashIn, 'total' => $sum($cashIn)],
        ];
        $outflows = [
            ['key' => 'cash_out', 'title' => 'Cash out', 'rows' => $cashOut, 'total' => $sum($cashOut)],
            ['key' => 'refunds', 'title' => 'Refunds', 'rows' => $posRefunds, 'total' => $sum($posRefunds)],
        ];

        // Sold on credit in the period — new receivables, shown as a line kept
        // OUT of the received totals (it isn't money in hand). Scoped by the
        // SALE's completed_at, since a credit tender has no payment/paid_at.
        $creditExtended = $this->creditExtendedRow(
            fn ($q) => $q->whereNotIn('status', ['void', 'refunded'])->whereBetween('completed_at', [$fromU, $toU])
        );

        $totalReceived = round($sum($posSales) + $sum($settlements) + $sum($online) + $sum($cashIn), 2);
        $totalOut = round($sum($cashOut) + $sum($posRefunds), 2);
        $net = round($totalReceived - $totalOut, 2);

        return compact('from', 'to', 'sources', 'outflows', 'combined', 'totalReceived', 'totalOut', 'net', 'creditExtended');
    }

    /**
     * "Sold on credit" for a set of sales, as a single pseudo-method row — the
     * new accounts receivable those sales created in the period.
     *
     * A credit tender is deliberately NOT a Payment (SaleService captures it as
     * balance_due), so it never appears in the payment-method tables. This
     * derives it from each sale's total minus what was actually tendered at
     * checkout (kind='sale' payments), i.e. the receivable ORIGINALLY raised.
     * Settlements (kind='settlement') are excluded, so the figure never drifts
     * as the balance is paid down and never double-counts the settlement
     * payments that DO show as money received.
     *
     * @param  \Closure  $scope  applies the caller's row filters (date, status, product) to a Sale query
     * @return object{n:int, amount:float}|null  null when nothing was sold on credit
     */
    protected function creditExtendedRow(\Closure $scope): ?object
    {
        // Per-sale credit = total − Σ(kind='sale' payments). Keep only the sales
        // that were left with a balance owing at checkout.
        $perSale = Sale::query()->tap($scope)
            ->leftJoin('payments as cp', fn ($j) => $j->on('cp.sale_id', '=', 'sales.id')->where('cp.kind', '=', 'sale'))
            ->groupBy('sales.id')
            ->havingRaw('COALESCE(SUM(cp.amount), 0) < sales.total - 0.005')
            ->selectRaw('sales.total - COALESCE(SUM(cp.amount), 0) as credit');

        $agg = DB::query()->fromSub($perSale, 'c')
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(credit), 0) as amount')
            ->first();

        if (! $agg || (float) $agg->amount <= 0.005) {
            return null;
        }

        return (object) ['n' => (int) $agg->n, 'amount' => round((float) $agg->amount, 2)];
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
                $data['methods']->map(fn ($m) => [$m->label, $m->n, $num($m->amount)])
                    ->when($data['creditExtended'], fn ($rows) => $rows->push([
                        'On credit (receivable, not cash)', $data['creditExtended']->n, $num($data['creditExtended']->amount),
                    ]))],
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
                ['Gross margin %', $num($s['margin'])],
                ['Revenue reversed (returns)', $num($s['returns_net'])],
                ['Tax reversed (returns)', $num($s['returns_tax'])],
                ['Total refunded', $num($s['returns_total'])],
                ['COGS recovered (returns)', $num($s['returns_cogs'])],
                ['Net sales after returns', $num($s['net_after_returns'])],
                ['Gross profit after returns', $num($s['profit_after_returns'])],
                ['Gross margin % after returns', $num($s['margin_after_returns'])],
                ['Collected', $num($s['collected'])],
                ['Outstanding (credit)', $num($s['outstanding'])],
            ]);

            $section('Payment methods', ['Method', 'Count', 'Amount'],
                $data['methods']->map(fn ($m) => [$m->label, $m->n, $num($m->amount)])
                    ->when($data['creditExtended'], fn ($rows) => $rows->push([
                        'On credit (receivable, not cash)', $data['creditExtended']->n, $num($data['creditExtended']->amount),
                    ])));
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
        // Day boundaries in the STORE's timezone so the range and the per-day
        // breakdown follow the trading day, not UTC. Stored timestamps are UTC:
        // query on the UTC instants ($fromU/$toU), and bucket the daily rows by
        // the store-local date via CONVERT_TZ using the store's UTC offset.
        $tz = $this->tenancy->current()->timezone();
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        $fromU = $from->copy()->utc();
        $toU = $to->copy()->utc();
        $tzOffset = Carbon::now($tz)->format('P');   // e.g. "+01:00"

        // Optional filters: narrow the whole report to sales that include a given
        // product and/or were paid (at least partly) with a given method. Baking
        // them into $inRange means every table below inherits them.
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;
        $method = $request->filled('method') ? (string) $request->input('method') : null;
        $filtered = $productId !== null || $method !== null;

        // Fully-refunded sales are treated like voids here — the whole sale was
        // reversed, so it shouldn't count as a sale in any operational table.
        // (Partial refunds stay: the sale is 'partially_refunded', still real,
        // and its returned portion is subtracted via the returns figures below.)
        $inRange = function ($q) use ($fromU, $toU, $productId, $method) {
            $q->whereNotIn('status', ['void', 'refunded'])->whereBetween('completed_at', [$fromU, $toU]);
            if ($productId !== null) {
                $q->whereHas('items', fn ($i) => $i->where('product_id', $productId));
            }
            if ($method !== null) {
                $q->whereHas('payments', fn ($p) => $p->where('method', $method)->where('amount', '>', 0));
            }

            return $q;
        };

        // A method filter apportions revenue/profit to each sale's share paid via
        // that method (a ₦1,000 sale paid ₦600 by card contributes 60% of its
        // net & profit to "card"). Split payments are attributed fairly and the
        // shares across methods sum back to the sale. Computed in PHP over the
        // matching sales; when a product filter is also active, the per-sale base
        // is that product's lines rather than the whole invoice.
        $apportionByMethod = $method !== null;
        $ap = $apportionByMethod
            ? $this->methodApportionedRevenue($inRange, $method, $productId, $tz)
            : null;

        // Headline revenue totals. With a product filter active these come from
        // that product's SALE ITEM lines, so every revenue figure below reflects
        // the product rather than the whole invoice; otherwise from the sale
        // invoices as before. Payment-/invoice-level figures (collected,
        // outstanding) can't be attributed to a single product, so they stay
        // whole-invoice — the view labels them "whole invoices" when filtered.
        $sliceByProduct = $productId !== null;

        if ($apportionByMethod) {
            $count = $ap['count'];
            $gross = $ap['gross'];
            $discounts = $ap['discounts'];
            $tax = $ap['tax'];
            $net = $ap['net'];
            $total = $ap['total'];
            $cogs = $ap['cogs'];
            // Outstanding is an unpaid balance — no payment method — so it stays
            // whole-invoice for the matching sales.
            $outstanding = round((float) Sale::query()->tap($inRange)->sum('balance_due'), 2);
        } elseif ($sliceByProduct) {
            // Line economics: net = gross − line discount, line_total = net + tax.
            // (Cart-level/loyalty discounts are sale-wide and not attributed here.)
            $r = SaleItem::whereHas('sale', $inRange)->where('product_id', $productId)
                ->selectRaw('
                    COALESCE(SUM(unit_price * quantity), 0) as gross,
                    COALESCE(SUM(discount), 0) as discounts,
                    COALESCE(SUM(unit_price * quantity - discount), 0) as net,
                    COALESCE(SUM(line_total), 0) as total,
                    COALESCE(SUM(unit_cost * quantity), 0) as cogs
                ')->first();
            $count = (int) Sale::query()->tap($inRange)->count();
            $gross = round((float) $r->gross, 2);
            $discounts = round((float) $r->discounts, 2);
            $net = round((float) $r->net, 2);
            $total = round((float) $r->total, 2);
            $cogs = round((float) $r->cogs, 2);
            $tax = round(max(0, $total - $net), 2);   // line_total already carries line tax
            $outstanding = round((float) Sale::query()->tap($inRange)->sum('balance_due'), 2);
        } else {
            $t = Sale::query()->tap($inRange)->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(subtotal), 0) as gross,
                COALESCE(SUM(discount_total), 0) as discounts,
                COALESCE(SUM(tax_total), 0) as tax,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(balance_due), 0) as outstanding
            ')->first();
            $count = (int) $t->count;
            $gross = round((float) $t->gross, 2);
            $discounts = round((float) $t->discounts, 2);
            $tax = round((float) $t->tax, 2);
            $total = round((float) $t->total, 2);
            $net = round($gross - $discounts, 2);   // ex-tax revenue
            $cogs = round((float) SaleItem::whereHas('sale', $inRange)
                ->selectRaw('COALESCE(SUM(unit_cost * quantity), 0) as cogs')
                ->value('cogs'), 2);
            $outstanding = round((float) $t->outstanding, 2);
        }
        $profit = round($net - $cogs, 2);

        // Returns processed in this period, taken from the reversing journal entries
        // (both POS returns and online-order refunds debit revenue / credit COGS,
        // reference "…-R"). Sourcing returns here keeps the net-of-returns figures
        // consistent with the P&L. Guarded — the Accounting module is optional.
        // Returns come from reversing journal entries, which aren't broken down by
        // product or payment method — so we only show them for the unfiltered view.
        $returnsNet = $returnsTax = $returnsCogs = 0.0;
        $accountClass = \App\Models\Accounting\Account::class;
        $lineClass = \App\Models\Accounting\JournalLine::class;
        if (! $filtered && class_exists($accountClass) && class_exists($lineClass)) {
            // Reversal lines (reference "…-R…") posted in the period for the three
            // accounts we reverse: 4000 revenue (debit), 2100 tax (debit),
            // 5000 COGS (credit).
            $accountIds = $accountClass::whereIn('code', ['4000', '2100', '5000'])->pluck('id', 'code');
            $codeById = $accountIds->flip();

            // entry_date is a plain DATE (accounting date, no timezone) — match on
            // the store-local calendar dates, not UTC instants.
            $lines = $lineClass::whereIn('account_id', $accountIds->values())
                ->whereHas('entry', fn ($e) => $e
                    ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
                    ->where('reference', 'like', '%-R%'))
                ->with('entry:id,reference')
                ->get();

            // A fully-refunded sale is already excluded from gross, so its reversal
            // must not be counted as a return as well (that would subtract it
            // twice). Strip the "-Rn" suffix to get the originating sale number.
            $base = fn ($ref) => preg_replace('/-R\d*$/', '', (string) $ref);
            $washedOut = \App\Models\Pos\Sale::whereIn('number', $lines->map(fn ($l) => $base($l->entry->reference))->unique())
                ->where('status', 'refunded')->pluck('number')->flip();

            foreach ($lines as $l) {
                if ($washedOut->has($base($l->entry->reference))) {
                    continue;
                }
                $code = $codeById[$l->account_id] ?? null;
                if ($code === '4000') {
                    $returnsNet += (float) $l->debit;
                } elseif ($code === '2100') {
                    $returnsTax += (float) $l->debit;
                } elseif ($code === '5000') {
                    $returnsCogs += (float) $l->credit;
                }
            }
            $returnsNet = round($returnsNet, 2);
            $returnsTax = round($returnsTax, 2);
            $returnsCogs = round($returnsCogs, 2);
        }
        $returnsTotal = round($returnsNet + $returnsTax, 2);

        $netAfterReturns = round($net - $returnsNet, 2);
        $profitAfterReturns = round($profit - ($returnsNet - $returnsCogs), 2);

        // Money actually kept = every payment net of refunds (a refund is a
        // negative payment row), so a refunded sale nets to zero rather than
        // showing its receipts as collected. A method filter narrows this to that
        // method's receipts (it's the one payment figure that IS method-sliceable).
        $collected = round((float) Payment::whereHas('sale', $inRange)
            ->when($method !== null, fn ($q) => $q->where('method', $method))
            ->sum('amount'), 2);

        $summary = [
            'count' => $count,
            'gross' => $gross,
            'discounts' => $discounts,
            'tax' => $tax,
            'total' => $total,
            'net' => $net,
            'cogs' => $cogs,
            'profit' => $profit,
            // Money actually kept, net of refunds (see $collected above).
            'collected' => $collected,
            'outstanding' => $outstanding,
            'avg' => $count > 0 ? round($total / $count, 2) : 0.0,
            // Returns in the period + net-of-returns bottom lines.
            'returns_net' => $returnsNet,
            'returns_tax' => $returnsTax,
            'returns_cogs' => $returnsCogs,
            'returns_total' => $returnsTotal,
            'net_after_returns' => $netAfterReturns,
            'profit_after_returns' => $profitAfterReturns,
            // Gross profit as a % of net revenue.
            'margin' => $net > 0 ? round($profit / $net * 100, 1) : 0.0,
            'margin_after_returns' => $netAfterReturns > 0 ? round($profitAfterReturns / $netAfterReturns * 100, 1) : 0.0,
        ];

        // Payment mix: net receipts by method (a refund is a negative payment,
        // so it reduces its method's take). Fully-refunded sales are already
        // excluded by $inRange — their receipts washed out.
        $labels = $this->tenancy->current()->paymentMethodLabels() + ['wallet' => 'Wallet'];
        $methods = Payment::whereHas('sale', $inRange)
            ->when($method !== null, fn ($q) => $q->where('method', $method))
            ->selectRaw('method, COALESCE(SUM(amount), 0) as amount, COUNT(*) as n')
            ->groupBy('method')
            ->havingRaw('COALESCE(SUM(amount), 0) <> 0')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($m) => (object) [
                'method' => $m->method,
                'label' => $labels[$m->method] ?? ucfirst(str_replace('_', ' ', $m->method)),
                'amount' => round((float) $m->amount, 2),
                'n' => (int) $m->n,
            ]);

        // Per-day breakdown, bucketed by the store-local date (completed_at is
        // stored UTC; CONVERT_TZ with the store offset shifts it before DATE()).
        // Sliced to the product's lines (product filter) or apportioned to the
        // method's share (method filter).
        if ($apportionByMethod) {
            $daily = $ap['daily'];
        } elseif ($sliceByProduct) {
            $daily = SaleItem::query()
                ->where('sale_items.product_id', $productId)
                ->whereHas('sale', $inRange)
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->selectRaw("DATE(CONVERT_TZ(sales.completed_at, '+00:00', ?)) as d,
                    COUNT(DISTINCT sales.id) as n,
                    COALESCE(SUM(sale_items.unit_price * sale_items.quantity - sale_items.discount), 0) as net,
                    COALESCE(SUM(sale_items.line_total - (sale_items.unit_price * sale_items.quantity - sale_items.discount)), 0) as tax,
                    COALESCE(SUM(sale_items.line_total), 0) as total", [$tzOffset])
                ->groupBy('d')
                ->orderBy('d')
                ->get();
        } else {
            $daily = Sale::query()->tap($inRange)
                ->selectRaw("DATE(CONVERT_TZ(completed_at, '+00:00', ?)) as d, COUNT(*) as n,
                    COALESCE(SUM(subtotal - discount_total), 0) as net,
                    COALESCE(SUM(tax_total), 0) as tax,
                    COALESCE(SUM(total), 0) as total", [$tzOffset])
                ->groupBy('d')
                ->orderBy('d')
                ->get();
        }

        // Top products by revenue (apportioned to the method's share when a method
        // filter is active; otherwise each product's own line revenue).
        if ($apportionByMethod) {
            $top = $ap['top'];
        } else {
            $top = SaleItem::whereHas('sale', $inRange)
                ->selectRaw('product_id, name, COALESCE(SUM(quantity), 0) as qty, COALESCE(SUM(line_total), 0) as revenue')
                ->groupBy('product_id', 'name')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get();
        }

        // Per-cashier breakdown (product-sliced, or method-apportioned).
        if ($apportionByMethod) {
            $cashiers = $ap['cashiers'];
        } elseif ($sliceByProduct) {
            $cashiers = SaleItem::query()
                ->where('sale_items.product_id', $productId)
                ->whereHas('sale', $inRange)
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->selectRaw('sales.user_id as user_id, COUNT(DISTINCT sales.id) as n,
                    COALESCE(SUM(sale_items.unit_price * sale_items.quantity - sale_items.discount), 0) as net,
                    COALESCE(SUM(sale_items.line_total), 0) as total')
                ->groupBy('sales.user_id')
                ->orderByDesc('total')
                ->get();
        } else {
            $cashiers = Sale::query()->tap($inRange)
                ->selectRaw('user_id, COUNT(*) as n,
                    COALESCE(SUM(subtotal - discount_total), 0) as net,
                    COALESCE(SUM(total), 0) as total')
                ->groupBy('user_id')
                ->orderByDesc('total')
                ->get();
        }
        $names = \App\Models\User::whereIn('id', $cashiers->pluck('user_id')->filter())->pluck('name', 'id');
        $cashiers->each(fn ($c) => $c->name = $names[$c->user_id] ?? 'Unknown');

        // Per-register breakdown (product-sliced, or method-apportioned).
        if ($apportionByMethod) {
            $registers = $ap['registers'];
        } elseif ($sliceByProduct) {
            $registers = SaleItem::query()
                ->where('sale_items.product_id', $productId)
                ->whereHas('sale', $inRange)
                ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                ->selectRaw('sales.register_id as register_id, COUNT(DISTINCT sales.id) as n,
                    COALESCE(SUM(sale_items.unit_price * sale_items.quantity - sale_items.discount), 0) as net,
                    COALESCE(SUM(sale_items.line_total), 0) as total')
                ->groupBy('sales.register_id')
                ->orderByDesc('total')
                ->get();
        } else {
            $registers = Sale::query()->tap($inRange)
                ->selectRaw('register_id, COUNT(*) as n,
                    COALESCE(SUM(subtotal - discount_total), 0) as net,
                    COALESCE(SUM(total), 0) as total')
                ->groupBy('register_id')
                ->orderByDesc('total')
                ->get();
        }
        $regNames = \App\Models\Pos\Register::whereIn('id', $registers->pluck('register_id')->filter())->pluck('name', 'id');
        $registers->each(fn ($r) => $r->name = $regNames[$r->register_id] ?? 'No register');

        // Filter option lists: only products that have actually sold (keeps the
        // dropdown relevant and bounded), and the tenant's payment methods.
        $soldProductIds = SaleItem::whereNotNull('product_id')->distinct()->pluck('product_id');
        $products = \App\Models\Inventory\Product::whereIn('id', $soldProductIds)
            ->orderBy('name')->get(['id', 'name']);
        $methodOptions = $labels;

        // What each family of figures reflects, so the view can label the rest as
        // "whole invoices": revenue is sliced by the product filter and/or
        // apportioned by the method filter; payment receipts by the method filter.
        $revenueSliced = $sliceByProduct;
        $revenueApportioned = $apportionByMethod;
        $paymentSliced = $method !== null;

        // Amount sold on credit in the period (new receivables), shown as a line
        // separate from the money-received method mix. Omitted under a method
        // filter — that view is scoped to one tender, and credit isn't one.
        $creditExtended = $method === null ? $this->creditExtendedRow($inRange) : null;

        return compact('summary', 'methods', 'daily', 'top', 'cashiers', 'registers', 'from', 'to',
            'products', 'methodOptions', 'productId', 'method', 'filtered',
            'revenueSliced', 'revenueApportioned', 'paymentSliced', 'creditExtended');
    }

    /**
     * Revenue for a payment-method filter, apportioned to each matching sale's
     * share paid via that method: share = (that method's net payments on the
     * sale) ÷ (sale total), clamped to [0,1]. Every revenue figure below is the
     * sale's value × its share, so split payments are attributed fairly and a
     * sale counts once, split across its methods. When a product filter is also
     * active the per-sale base is that product's lines instead of the whole
     * invoice. Done in PHP over the matching sales (bounded by the report's date
     * range) rather than one big apportioning SQL query.
     *
     * @return array{count:int,gross:float,discounts:float,tax:float,net:float,total:float,cogs:float,daily:\Illuminate\Support\Collection,cashiers:\Illuminate\Support\Collection,registers:\Illuminate\Support\Collection,top:\Illuminate\Support\Collection}
     */
    protected function methodApportionedRevenue(\Closure $inRange, string $method, ?int $productId, $tz): array
    {
        $sales = Sale::query()->tap($inRange)
            ->select('id', 'completed_at', 'user_id', 'register_id', 'subtotal', 'discount_total', 'tax_total', 'total')
            ->withSum(['payments as method_paid' => fn ($p) => $p->where('method', $method)], 'amount')
            ->get();

        $ids = $sales->pluck('id');

        // Per-sale COGS (all lines) — the whole-invoice base.
        $cogsBySale = SaleItem::whereIn('sale_id', $ids)
            ->selectRaw('sale_id, COALESCE(SUM(unit_cost * quantity), 0) as cogs')
            ->groupBy('sale_id')->pluck('cogs', 'sale_id');

        // Per-sale product-line base, when a product filter is also active.
        $lineBySale = collect();
        if ($productId !== null) {
            $lineBySale = SaleItem::whereIn('sale_id', $ids)->where('product_id', $productId)
                ->selectRaw('sale_id,
                    COALESCE(SUM(unit_price * quantity), 0) as gross,
                    COALESCE(SUM(discount), 0) as discounts,
                    COALESCE(SUM(unit_price * quantity - discount), 0) as net,
                    COALESCE(SUM(line_total), 0) as total,
                    COALESCE(SUM(unit_cost * quantity), 0) as cogs')
                ->groupBy('sale_id')->get()->keyBy('sale_id');
        }

        $sum = ['gross' => 0.0, 'discounts' => 0.0, 'tax' => 0.0, 'net' => 0.0, 'total' => 0.0, 'cogs' => 0.0];
        $daily = $byUser = $byReg = [];
        $shareBySale = [];

        foreach ($sales as $s) {
            $saleTotal = (float) $s->total;
            $share = $saleTotal > 0 ? min(1.0, max(0.0, (float) $s->method_paid / $saleTotal)) : 0.0;
            $shareBySale[$s->id] = $share;

            if ($productId !== null) {
                $l = $lineBySale->get($s->id);
                $bGross = (float) ($l->gross ?? 0);
                $bDisc = (float) ($l->discounts ?? 0);
                $bNet = (float) ($l->net ?? 0);
                $bTotal = (float) ($l->total ?? 0);
                $bCogs = (float) ($l->cogs ?? 0);
                $bTax = $bTotal - $bNet;
            } else {
                $bGross = (float) $s->subtotal;
                $bDisc = (float) $s->discount_total;
                $bTax = (float) $s->tax_total;
                $bNet = $bGross - $bDisc;
                $bTotal = (float) $s->total;
                $bCogs = (float) ($cogsBySale[$s->id] ?? 0);
            }

            $sum['gross'] += $bGross * $share;
            $sum['discounts'] += $bDisc * $share;
            $sum['tax'] += $bTax * $share;
            $sum['net'] += $bNet * $share;
            $sum['total'] += $bTotal * $share;
            $sum['cogs'] += $bCogs * $share;

            $d = Carbon::parse($s->completed_at)->setTimezone($tz)->toDateString();
            $daily[$d] ??= ['n' => 0, 'net' => 0.0, 'tax' => 0.0, 'total' => 0.0];
            $daily[$d]['n']++;
            $daily[$d]['net'] += $bNet * $share;
            $daily[$d]['tax'] += $bTax * $share;
            $daily[$d]['total'] += $bTotal * $share;

            $byUser[$s->user_id] ??= ['n' => 0, 'net' => 0.0, 'total' => 0.0];
            $byUser[$s->user_id]['n']++;
            $byUser[$s->user_id]['net'] += $bNet * $share;
            $byUser[$s->user_id]['total'] += $bTotal * $share;

            $byReg[$s->register_id] ??= ['n' => 0, 'net' => 0.0, 'total' => 0.0];
            $byReg[$s->register_id]['n']++;
            $byReg[$s->register_id]['net'] += $bNet * $share;
            $byReg[$s->register_id]['total'] += $bTotal * $share;
        }

        // Top products: each line apportioned by its sale's share (co-purchased
        // products kept, each at its own apportioned line revenue).
        $topAgg = [];
        foreach (SaleItem::whereIn('sale_id', $ids)->select('sale_id', 'product_id', 'name', 'quantity', 'line_total')->cursor() as $it) {
            $f = $shareBySale[$it->sale_id] ?? 0.0;
            $key = $it->product_id.'|'.$it->name;
            $topAgg[$key] ??= ['product_id' => $it->product_id, 'name' => $it->name, 'qty' => 0.0, 'revenue' => 0.0];
            $topAgg[$key]['qty'] += (float) $it->quantity * $f;
            $topAgg[$key]['revenue'] += (float) $it->line_total * $f;
        }

        ksort($daily);

        return [
            'count' => $sales->count(),
            'gross' => round($sum['gross'], 2),
            'discounts' => round($sum['discounts'], 2),
            'tax' => round(max(0.0, $sum['tax']), 2),
            'net' => round($sum['net'], 2),
            'total' => round($sum['total'], 2),
            'cogs' => round($sum['cogs'], 2),
            'daily' => collect($daily)->map(fn ($v, $d) => (object) [
                'd' => $d, 'n' => $v['n'], 'net' => round($v['net'], 2), 'tax' => round($v['tax'], 2), 'total' => round($v['total'], 2),
            ])->values(),
            'cashiers' => collect($byUser)->map(fn ($v, $id) => (object) [
                'user_id' => $id ?: null, 'n' => $v['n'], 'net' => round($v['net'], 2), 'total' => round($v['total'], 2),
            ])->sortByDesc('total')->values(),
            'registers' => collect($byReg)->map(fn ($v, $id) => (object) [
                'register_id' => $id ?: null, 'n' => $v['n'], 'net' => round($v['net'], 2), 'total' => round($v['total'], 2),
            ])->sortByDesc('total')->values(),
            'top' => collect($topAgg)->map(fn ($v) => (object) [
                'product_id' => $v['product_id'], 'name' => $v['name'], 'qty' => round($v['qty'], 3), 'revenue' => round($v['revenue'], 2),
            ])->sortByDesc('revenue')->take(10)->values(),
        ];
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
