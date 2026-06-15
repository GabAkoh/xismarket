<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Tax;
use App\Support\Tenancy;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $taxes = Tax::orderBy('name')->get();

        return view('accounting.taxes.index', compact('taxes'));
    }

    public function create()
    {
        return view('accounting.taxes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        Tax::create($data + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('taxes.index')->with('status', 'Tax rate created.');
    }

    public function edit(Tax $tax)
    {
        $this->authorizeTenant($tax);

        return view('accounting.taxes.edit', compact('tax'));
    }

    public function update(Request $request, Tax $tax)
    {
        $this->authorizeTenant($tax);

        $data = $this->validateData($request);

        $tax->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('taxes.index')->with('status', 'Tax rate updated.');
    }

    public function destroy(Tax $tax)
    {
        $this->authorizeTenant($tax);

        $tax->delete();

        return redirect()->route('taxes.index')->with('status', 'Tax rate deleted.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    protected function authorizeTenant(Tax $tax): void
    {
        abort_unless($tax->tenant_id === $this->tenancy->id(), 404);
    }
}
