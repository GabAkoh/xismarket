<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Meta (Facebook) Pixel + Conversions API configuration, stored in the tenant's
 * settings (meta.*) so it's managed from the admin UI instead of .env. The
 * access token is encrypted at rest and never re-displayed (enter a new one to
 * change it), mirroring the Paystack secret in PaymentSettingsController.
 */
class MetaSettingsController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $store = $this->tenancy->current();

        return view('settings.meta', [
            'store' => $store,
            'enabled' => (bool) $store->setting('meta.enabled', false),
            'pixelId' => (string) $store->setting('meta.pixel_id', ''),
            'testEventCode' => (string) $store->setting('meta.test_event_code', ''),
            'hasToken' => filled($store->setting('meta.access_token')),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['nullable'],
            'pixel_id' => ['nullable', 'string', 'max:32', 'regex:/^\d*$/'],
            'access_token' => ['nullable', 'string', 'max:512'],
            'test_event_code' => ['nullable', 'string', 'max:64'],
        ]);

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $meta = $settings['meta'] ?? [];

        $meta['enabled'] = $request->boolean('enabled');
        $meta['pixel_id'] = trim((string) ($data['pixel_id'] ?? '')) ?: null;
        $meta['test_event_code'] = trim((string) ($data['test_event_code'] ?? '')) ?: null;

        // Only replace the token when a new value is entered (it's masked otherwise).
        if (filled($data['access_token'])) {
            $meta['access_token'] = Crypt::encryptString(trim($data['access_token']));
        }

        $settings['meta'] = $meta;
        $store->update(['settings' => $settings]);

        return redirect()->route('meta.settings')->with('status', 'Meta Pixel settings saved.');
    }
}
