<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Coupon;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function index()
    {
        $coupons = Coupon::orderByDesc('id')->paginate(20);

        return view('coupons.index', compact('coupons'));
    }

    public function create()
    {
        return view('coupons.create', ['coupon' => new Coupon(['type' => 'percent', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        Coupon::create($this->validateData($request) + ['tenant_id' => $this->tenancy->id()]);

        return redirect()->route('coupons.index')->with('status', 'Coupon created.');
    }

    public function edit(Coupon $coupon)
    {
        $this->authorizeTenant($coupon);

        return view('coupons.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeTenant($coupon);
        $coupon->update($this->validateData($request, $coupon));

        return redirect()->route('coupons.index')->with('status', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorizeTenant($coupon);
        $coupon->delete();

        return redirect()->route('coupons.index')->with('status', 'Coupon deleted.');
    }

    protected function validateData(Request $request, ?Coupon $coupon = null): array
    {
        $tenantId = $this->tenancy->id();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('coupons', 'code')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($coupon?->id),
            ],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0', $request->input('type') === 'percent' ? 'max:100' : 'max:100000000'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable'],
        ], [
            'code.unique' => 'A coupon with this code already exists.',
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['min_order'] = $data['min_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function authorizeTenant(Coupon $coupon): void
    {
        abort_unless($coupon->tenant_id === $this->tenancy->id(), 404);
    }
}
