<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Warehouse;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $warehouses = Warehouse::withCount('stocks')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(20);

        return view('inventory.warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        return view('inventory.warehouses.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['is_default'] = $request->boolean('is_default');

        $warehouse = Warehouse::create($data);
        $this->ensureSingleDefault($warehouse);

        return redirect()->route('warehouses.index')->with('status', 'Warehouse added.');
    }

    public function edit(Warehouse $warehouse)
    {
        $this->authorizeTenant($warehouse);

        return view('inventory.warehouses.edit', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $this->authorizeTenant($warehouse);
        $data = $this->validateData($request);
        $data['is_default'] = $request->boolean('is_default');

        $warehouse->update($data);
        $this->ensureSingleDefault($warehouse);

        return redirect()->route('warehouses.index')->with('status', 'Warehouse updated.');
    }

    public function destroy(Warehouse $warehouse)
    {
        $this->authorizeTenant($warehouse);

        if ($warehouse->is_default) {
            return back()->with('error', 'The default warehouse cannot be removed.');
        }

        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('status', 'Warehouse removed.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);
    }

    /** Keep at most one default warehouse per tenant. */
    protected function ensureSingleDefault(Warehouse $warehouse): void
    {
        if ($warehouse->is_default) {
            Warehouse::where('id', '!=', $warehouse->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    protected function authorizeTenant(Warehouse $warehouse): void
    {
        abort_unless($warehouse->tenant_id === $this->tenancy->id(), 404);
    }
}
