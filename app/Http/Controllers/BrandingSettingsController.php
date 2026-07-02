<?php

namespace App\Http\Controllers;

use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * White-label branding for the admin app: the name shown in the sidebar/title
 * and the icon used as the sidebar logo + browser favicon. Stored under the
 * tenant's settings (branding.*) and read back by the app layout.
 */
class BrandingSettingsController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        return view('settings.branding', ['store' => $this->tenancy->current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name' => ['nullable', 'string', 'max:60'],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_icon' => ['nullable'],
        ]);

        $store = $this->tenancy->current();
        $settings = $store->settings ?? [];
        $branding = $settings['branding'] ?? [];

        $branding['app_name'] = trim((string) ($data['app_name'] ?? '')) ?: null;

        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('branding', 'public');
            if (! empty($branding['icon'])) {
                Storage::disk('public')->delete($branding['icon']);
            }
            $branding['icon'] = $path;
        } elseif ($request->boolean('remove_icon') && ! empty($branding['icon'])) {
            Storage::disk('public')->delete($branding['icon']);
            $branding['icon'] = null;
        }

        $settings['branding'] = $branding;
        $store->update(['settings' => $settings]);

        return redirect()->route('branding.settings')->with('status', 'Branding updated.');
    }
}
