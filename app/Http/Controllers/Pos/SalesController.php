<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Orders\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\Sale;
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

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,card,other,wallet'],
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
