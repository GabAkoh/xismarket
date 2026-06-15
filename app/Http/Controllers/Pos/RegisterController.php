<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\Register;
use App\Models\Pos\Shift;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $registers = Register::with(['warehouse'])->orderBy('name')->get();
        $registers->each(fn (Register $r) => $r->setRelation('currentShift', $r->openShift()));

        return view('registers.index', [
            'registers' => $registers,
            'warehouses' => $this->warehouses(),
        ]);
    }

    public function create()
    {
        return view('registers.create', ['warehouses' => $this->warehouses()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        Register::create($data + ['tenant_id' => $this->tenancy->id()]);

        return redirect()->route('registers.index')->with('status', 'Register added.');
    }

    public function edit(Register $register)
    {
        $this->authorizeTenant($register);

        return view('registers.edit', [
            'register' => $register,
            'warehouses' => $this->warehouses(),
        ]);
    }

    public function update(Request $request, Register $register)
    {
        $this->authorizeTenant($register);

        $register->update($this->validateData($request, $register));

        return redirect()->route('registers.index')->with('status', 'Register updated.');
    }

    public function destroy(Register $register)
    {
        $this->authorizeTenant($register);
        $register->delete();

        return redirect()->route('registers.index')->with('status', 'Register removed.');
    }

    /** Open a shift on a register. */
    public function openShift(Request $request, Register $register)
    {
        $this->authorizeTenant($register);

        if ($register->openShift()) {
            return back()->with('error', 'This register already has an open shift.');
        }

        $data = $request->validate([
            'opening_float' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        Shift::create([
            'tenant_id' => $this->tenancy->id(),
            'register_id' => $register->id,
            'user_id' => auth()->id(),
            'opened_at' => now(),
            'opening_float' => $data['opening_float'] ?? 0,
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Shift opened on '.$register->name.'.');
    }

    /** Close the open shift on a register. */
    public function closeShift(Request $request, Register $register)
    {
        $this->authorizeTenant($register);

        $shift = $register->openShift();
        if (! $shift) {
            return back()->with('error', 'There is no open shift on this register.');
        }

        $data = $request->validate([
            'closing_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Expected = opening float + cash payments taken during the shift.
        $cashTaken = (float) $shift->sales()
            ->where('status', '!=', 'void')
            ->join('payments', 'payments.sale_id', '=', 'sales.id')
            ->where('payments.method', 'cash')
            ->sum('payments.amount');

        $expected = round((float) $shift->opening_float + $cashTaken, 2);

        $shift->update([
            'closed_at' => now(),
            'closing_amount' => $data['closing_amount'] ?? null,
            'expected_amount' => $expected,
            'status' => 'closed',
            'notes' => $data['notes'] ?? $shift->notes,
        ]);

        return back()->with('status', 'Shift closed on '.$register->name.'.');
    }

    protected function warehouses()
    {
        return class_exists(Warehouse::class)
            ? Warehouse::orderBy('name')->get(['id', 'name'])
            : collect();
    }

    protected function validateData(Request $request, ?Register $register = null): array
    {
        $tenantId = $this->tenancy->id();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('registers', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($register?->id),
            ],
            'warehouse_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    protected function authorizeTenant(Register $register): void
    {
        abort_unless($register->tenant_id === $this->tenancy->id(), 404);
    }
}
