<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Online-payment (Paystack) configuration, stored in the tenant's settings so
 * keys can be managed from the admin UI instead of .env. The secret key is
 * encrypted at rest and never re-displayed (enter a new one to change it).
 */
class PaymentSettingsController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $store = $this->tenancy->current();

        return view('settings.payments', [
            'store' => $store,
            'enabled' => (bool) $store->setting('payments.enabled', false),
            'publicKey' => (string) $store->setting('payments.paystack_public', ''),
            'hasSecret' => filled($store->setting('payments.paystack_secret')),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['nullable'],
            'paystack_public' => ['nullable', 'string', 'max:255'],
            'paystack_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $payments = $settings['payments'] ?? [];

        $payments['enabled'] = $request->boolean('enabled');
        $payments['paystack_public'] = trim((string) ($data['paystack_public'] ?? '')) ?: null;

        // Only replace the secret when a new value is entered (it's masked otherwise).
        if (filled($data['paystack_secret'])) {
            $payments['paystack_secret'] = Crypt::encryptString(trim($data['paystack_secret']));
        }

        $settings['payments'] = $payments;
        $store->update(['settings' => $settings]);

        return redirect()->route('payments.settings')->with('status', 'Payment settings saved.');
    }
}
