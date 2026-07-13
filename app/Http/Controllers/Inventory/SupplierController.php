<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Inventory\Supplier;
use App\Services\Inventory\VendorAccountService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        // Eager-load the A/P account so each row's balanceOwed() has it to hand.
        $suppliers = Supplier::with('account')->orderBy('name')->paginate(20);

        return view('inventory.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('inventory.suppliers.create');
    }

    public function store(Request $request)
    {
        Supplier::create($this->validateData($request));

        return redirect()->route('suppliers.index')->with('status', 'Supplier added.');
    }

    /** Create (or reuse) a supplier by name and return it as JSON — for inline creation. */
    public function quickStore(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $name = trim($data['name']);

        $supplier = Supplier::whereRaw('LOWER(name) = ?', [strtolower($name)])->first()
            ?? Supplier::create(['name' => $name]);

        return response()->json(['id' => $supplier->id, 'name' => $supplier->name]);
    }

    public function edit(Supplier $supplier)
    {
        $this->authorizeTenant($supplier);

        return view('inventory.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeTenant($supplier);
        $supplier->update($this->validateData($request));

        return redirect()->route('suppliers.index')->with('status', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorizeTenant($supplier);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('status', 'Supplier removed.');
    }

    /** Show the "pay vendor" form: outstanding balance + which account to pay from. */
    public function payForm(Supplier $supplier, VendorAccountService $vendors)
    {
        $this->authorizeTenant($supplier);

        $outstanding = $vendors->outstanding($supplier);
        $accounts = $this->payableFromAccounts();

        return view('inventory.suppliers.pay', compact('supplier', 'outstanding', 'accounts'));
    }

    /** Settle (part of) a vendor's payable: Dr vendor A/P, Cr the chosen cash/bank account. */
    public function pay(Request $request, Supplier $supplier, VendorAccountService $vendors)
    {
        $this->authorizeTenant($supplier);

        $codes = $this->payableFromAccounts()->pluck('code')->all();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'account' => ['required', 'string', Rule::in($codes)],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $applied = $vendors->payVendor($supplier, (float) $data['amount'], $data['account'], $data['reference'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('suppliers.index')
            ->with('status', 'Paid '.number_format($applied, 2).' to '.$supplier->name.'.');
    }

    /** Asset accounts a vendor bill can be paid from (cash/bank/custom — not A/R or Inventory). */
    protected function payableFromAccounts()
    {
        return Account::where('type', 'asset')
            ->where('is_active', true)
            ->whereNotIn('code', ['1200', '1300'])
            ->orderBy('code')
            ->get();
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function authorizeTenant(Supplier $supplier): void
    {
        abort_unless($supplier->tenant_id === $this->tenancy->id(), 404);
    }
}
