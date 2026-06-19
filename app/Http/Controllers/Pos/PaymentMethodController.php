<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tenant-wide POS payment methods (Cash, Card, Bank Transfer, …). Stored under
 * the tenant's settings JSON (pos.payment_methods) and read back through
 * Tenant::paymentMethods(). 'wallet' (store credit) is a reserved built-in.
 */
class PaymentMethodController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $store = $this->tenancy->current();
        $methods = $store->paymentMethods();

        return view('pos.payment-methods', compact('store', 'methods'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*.key' => ['nullable', 'string', 'max:40'],
            'methods.*.label' => ['nullable', 'string', 'max:40'],
            'methods.*.credit' => ['nullable'],
        ]);

        // Build a clean, de-duplicated list. Keep the submitted key when present
        // (preserves history/reporting on rename), else derive one from the label.
        $methods = [];
        $seen = [];
        foreach ($request->input('methods', []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = Str::slug(trim((string) ($row['key'] ?? '')) ?: $label, '_') ?: 'method';
            if ($key === 'wallet') {              // reserved for store credit
                $key = 'wallet_method';
            }
            if (isset($seen[$key])) {              // first occurrence wins
                continue;
            }

            $seen[$key] = true;
            $methods[] = [
                'key' => $key,
                'label' => $label,
                'credit' => filter_var($row['credit'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        if ($methods === []) {
            return back()->with('error', 'Add at least one payment method.');
        }

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $settings['pos'] = array_merge($settings['pos'] ?? [], ['payment_methods' => $methods]);
        $store->update(['settings' => $settings]);

        return redirect()->route('payment-methods.settings')->with('status', 'Payment methods updated.');
    }
}
