<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\Customer;
use App\Models\Pos\WalletTransaction;
use App\Services\Pos\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /** Store-credit overview across all customers. */
    public function index(Request $request)
    {
        $totalCredit = (float) Customer::sum('balance');
        $holders = Customer::where('balance', '>', 0)->count();

        $customers = Customer::query()
            ->where('balance', '>', 0)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term));
            })
            ->orderByDesc('balance')
            ->paginate(20)
            ->withQueryString();

        $recent = WalletTransaction::with('customer')
            ->latest()
            ->limit(12)
            ->get();

        return view('wallets.index', compact('totalCredit', 'holders', 'customers', 'recent'));
    }

    /** Form to top up several customers' wallets at once. */
    public function bulkForm()
    {
        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone', 'balance']);

        return view('wallets.bulk', compact('customers'));
    }

    /** Apply the same credit to every selected customer. */
    public function bulkStore(Request $request, WalletService $wallet)
    {
        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'method' => ['required', 'string', 'in:cash,card,other'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Tenant-scoped, so ids from another tenant are silently ignored.
        $customers = Customer::whereIn('id', $data['customer_ids'])->get();
        if ($customers->isEmpty()) {
            return back()->with('error', 'Select at least one customer.');
        }

        $amount = round((float) $data['amount'], 2);
        DB::transaction(function () use ($customers, $wallet, $amount, $data) {
            foreach ($customers as $customer) {
                $wallet->topUp($customer, $amount, $data['method'], $data['reason'] ?? null);
            }
        });

        $total = number_format($amount * $customers->count(), 2);

        return redirect()->route('wallets.index')->with('status',
            'Topped up '.$customers->count().' customer(s) by '.number_format($amount, 2).' each ('.$total.' total).');
    }
}
