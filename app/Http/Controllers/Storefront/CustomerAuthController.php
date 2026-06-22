<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Pos\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Storefront customer accounts (Sign in / Sign up). Uses the dedicated
 * "customer" guard against the Customer model; everything is tenant-scoped by
 * the store-resolving middleware, so accounts belong to the store being browsed.
 */
class CustomerAuthController extends Controller
{
    public function showRegister($store)
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('shop.account', ['store' => $store]);
        }

        return view('storefront.auth.register');
    }

    public function register(Request $request, $store)
    {
        $tenantId = app(\App\Support\Tenancy::class)->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                // Unique among accounts (customers that already have a password) in this store.
                Rule::unique('customers', 'email')->where(fn ($q) => $q
                    ->where('tenant_id', $tenantId)->whereNotNull('password')),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Reuse a matching POS customer record (e.g. created at a prior checkout)
        // so their history links up; otherwise create a fresh one.
        $customer = Customer::whereNotNull('email')->where('email', $data['email'])->first()
            ?? new Customer;

        $customer->fill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? $customer->phone,
            'password' => $data['password'],   // hashed via the model cast
        ])->save();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('shop.account', ['store' => $store])
            ->with('status', 'Welcome to '.($customer->name ? $customer->name : 'the family').'! Your account is ready.');
    }

    public function showLogin($store)
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('shop.account', ['store' => $store]);
        }

        return view('storefront.auth.login');
    }

    public function login(Request $request, $store)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('customer')->attempt(['email' => strtolower($data['email']), 'password' => $data['password']])) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Those credentials do not match our records.');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('shop.account', ['store' => $store]));
    }

    public function logout(Request $request, $store)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.home', ['store' => $store]);
    }

    public function account($store)
    {
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            return redirect()->route('shop.login', ['store' => $store]);
        }

        $orders = $customer->orders()->latest()->take(50)->get();

        return view('storefront.account', compact('customer', 'orders'));
    }
}
