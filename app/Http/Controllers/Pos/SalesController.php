<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\Sale;
use App\Services\Pos\SaleService;
use App\Support\Tenancy;
use Illuminate\Http\Request;

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
