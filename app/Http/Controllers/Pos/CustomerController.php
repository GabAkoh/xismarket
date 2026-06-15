<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\Customer;
use App\Models\Pos\LoyaltySetting;
use App\Services\Pos\LoyaltyService;
use App\Services\Pos\WalletService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index(Request $request)
    {
        $customers = Customer::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('identity_number', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        Customer::create($data + ['tenant_id' => $this->tenancy->id()]);

        return redirect()->route('customers.index')->with('status', 'Customer added.');
    }

    public function show(Customer $customer)
    {
        $this->authorizeTenant($customer);

        $customer->load([
            'walletTransactions' => fn ($q) => $q->limit(25),
            'loyaltyTransactions' => fn ($q) => $q->limit(25),
        ]);
        $recentSales = $customer->sales()->latest('completed_at')->limit(10)->get();
        $loyalty = LoyaltySetting::current();

        return view('customers.show', compact('customer', 'recentSales', 'loyalty'));
    }

    /**
     * A printable account statement: a running ledger of the customer's invoices
     * and payments. The closing balance equals what they currently owe.
     */
    public function statement(Request $request, Customer $customer)
    {
        $this->authorizeTenant($customer);

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : null;

        // Build a chronological list of charge (invoice) and payment events.
        $customer->load(['sales' => fn ($q) => $q->where('status', '!=', 'void')->with('payments')]);

        $events = collect();
        foreach ($customer->sales as $sale) {
            if ($sale->completed_at) {
                $events->push([
                    'date' => $sale->completed_at,
                    'order' => 0, // invoices before payments on the same instant
                    'ref' => $sale->number,
                    'sale_id' => $sale->id,
                    'description' => 'Invoice'.($sale->status === 'refunded' ? ' (refunded)' : ''),
                    'charge' => (float) $sale->total,
                    'payment' => 0.0,
                ]);
            }
            // Only the amount actually applied to the invoice counts toward the
            // account — cash change handed back is not a payment. Distribute the
            // applied total (total − balance) across the payment rows in order.
            $remaining = round((float) $sale->total - (float) $sale->balance_due, 2);
            foreach ($sale->payments->sortBy('paid_at') as $p) {
                if ($remaining <= 0) {
                    break;
                }
                $applied = round(min((float) $p->amount, $remaining), 2);
                if ($applied <= 0) {
                    continue;
                }
                $remaining = round($remaining - $applied, 2);

                $events->push([
                    'date' => $p->paid_at ?? $sale->completed_at,
                    'order' => 1,
                    'ref' => $sale->number,
                    'sale_id' => $sale->id,
                    'description' => ucfirst($p->method).' payment',
                    'charge' => 0.0,
                    'payment' => $applied,
                ]);
            }
        }

        $events = $events
            ->sort(fn ($a, $b) => [$a['date']->timestamp, $a['order']] <=> [$b['date']->timestamp, $b['order']])
            ->values();

        // Opening balance (everything before the period), then the in-period ledger.
        $opening = 0.0;
        $running = 0.0;
        $totalCharges = 0.0;
        $totalPayments = 0.0;
        $rows = [];

        foreach ($events as $e) {
            if ($from && $e['date']->lt($from)) {
                $opening += $e['charge'] - $e['payment'];
                $running = $opening;

                continue;
            }
            if ($to && $e['date']->gt($to)) {
                continue;
            }

            $running += $e['charge'] - $e['payment'];
            $totalCharges += $e['charge'];
            $totalPayments += $e['payment'];
            $rows[] = $e + ['balance' => round($running, 2)];
        }

        $opening = round($opening, 2);
        $closing = round($running, 2);
        $totalCharges = round($totalCharges, 2);
        $totalPayments = round($totalPayments, 2);

        return view('customers.statement', compact(
            'customer', 'rows', 'opening', 'closing', 'totalCharges', 'totalPayments', 'from', 'to'
        ));
    }

    public function edit(Customer $customer)
    {
        $this->authorizeTenant($customer);

        return view('customers.edit', compact('customer'));
    }

    /** Add store credit to the customer's wallet (a real, cash/card-funded top-up). */
    public function topUpWallet(Request $request, Customer $customer, WalletService $wallet)
    {
        $this->authorizeTenant($customer);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'method' => ['required', 'string', 'in:cash,card,other'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $wallet->topUp($customer, (float) $data['amount'], $data['method'], $data['reason'] ?? null);

        return back()->with('status', 'Wallet topped up by '.number_format((float) $data['amount'], 2).'.');
    }

    /** Manually adjust a customer's loyalty points (add or remove). */
    public function adjustLoyalty(Request $request, Customer $customer, LoyaltyService $loyalty)
    {
        $this->authorizeTenant($customer);

        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $loyalty->adjust($customer, (int) $data['points'], $data['reason'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Loyalty points adjusted.');
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeTenant($customer);

        $customer->update($this->validateData($request, $customer));

        return redirect()->route('customers.index')->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorizeTenant($customer);
        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Customer removed.');
    }

    protected function validateData(Request $request, ?Customer $customer = null): array
    {
        $tenantId = $this->tenancy->id();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'identity_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('customers', 'identity_number')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($customer?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'identity_number.unique' => 'Another customer already has this ID number.',
        ]);
    }

    protected function authorizeTenant(Customer $customer): void
    {
        abort_unless($customer->tenant_id === $this->tenancy->id(), 404);
    }
}
