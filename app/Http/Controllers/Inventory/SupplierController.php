<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Supplier;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate(20);

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
