<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Pos\Register;
use App\Services\Pos\CashDrawerService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashDrawerController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    /** Record a cash-in/out movement against a register's open shift. */
    public function store(Request $request, CashDrawerService $drawer)
    {
        $data = $request->validate([
            'register_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $register = Register::find($data['register_id']);
        abort_unless($register && $register->tenant_id === $this->tenancy->id(), 404);

        $shift = $register->openShift();
        if (! $shift) {
            return back()->with('error', 'Open a shift before recording cash movements.');
        }

        try {
            $movement = $drawer->record(
                $shift,
                $data['type'],
                (float) $data['amount'],
                $data['reason'],
                $data['note'] ?? null,
            );
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $verb = $movement->type === 'in' ? 'Cash in' : 'Cash out';

        return back()->with('status', $verb.' of '.number_format((float) $movement->amount, 2).' recorded.');
    }
}
