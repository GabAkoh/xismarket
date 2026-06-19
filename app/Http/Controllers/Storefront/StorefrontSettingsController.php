<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-side editor for the public storefront's landing-page content. Values are
 * persisted under the tenant's settings JSON (settings.storefront.*) and read
 * back by the storefront views via $store->setting('storefront.*', <fallback>).
 */
class StorefrontSettingsController extends Controller
{
    public function __construct(protected Tenancy $tenancy) {}

    public function edit()
    {
        $store = $this->tenancy->current();

        return view('settings.storefront', compact('store'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'promo' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:150'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],
            'story_title' => ['nullable', 'string', 'max:150'],
            'story_body' => ['nullable', 'string', 'max:2000'],
            'hero_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
            'testimonials' => ['nullable', 'array', 'max:12'],
            'testimonials.*.name' => ['nullable', 'string', 'max:100'],
            'testimonials.*.text' => ['nullable', 'string', 'max:500'],
        ]);

        $store = $this->tenancy->current();
        $existing = $store->setting('storefront', []);

        // Text fields — drop blanks so the storefront's built-in fallbacks apply.
        $storefront = array_filter([
            'promo' => $data['promo'] ?? null,
            'hero_title' => $data['hero_title'] ?? null,
            'hero_subtitle' => $data['hero_subtitle'] ?? null,
            'story_title' => $data['story_title'] ?? null,
            'story_body' => $data['story_body'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $storefront['promo_enabled'] = $request->boolean('promo_enabled');

        // Hero image: a new upload replaces the old, the checkbox clears it,
        // otherwise the existing image is kept.
        $heroImage = $existing['hero_image'] ?? null;
        if ($request->hasFile('hero_image')) {
            $heroImage = $request->file('hero_image')->store('storefront', 'public');
            if (! empty($existing['hero_image'])) {
                Storage::disk('public')->delete($existing['hero_image']);
            }
        } elseif ($request->boolean('remove_hero_image') && ! empty($existing['hero_image'])) {
            Storage::disk('public')->delete($existing['hero_image']);
            $heroImage = null;
        }
        if ($heroImage) {
            $storefront['hero_image'] = $heroImage;
        }

        // Logo — same replace/remove handling as the hero image.
        $logo = $existing['logo'] ?? null;
        if ($request->hasFile('logo')) {
            $logo = $request->file('logo')->store('storefront', 'public');
            if (! empty($existing['logo'])) {
                Storage::disk('public')->delete($existing['logo']);
            }
        } elseif ($request->boolean('remove_logo') && ! empty($existing['logo'])) {
            Storage::disk('public')->delete($existing['logo']);
            $logo = null;
        }
        if ($logo) {
            $storefront['logo'] = $logo;
        }

        // Testimonials — keep only rows that have both a name and a quote.
        $testimonials = collect($data['testimonials'] ?? [])
            ->map(fn ($t) => ['name' => trim($t['name'] ?? ''), 'text' => trim($t['text'] ?? '')])
            ->filter(fn ($t) => $t['name'] !== '' && $t['text'] !== '')
            ->values()
            ->all();
        if (! empty($testimonials)) {
            $storefront['testimonials'] = $testimonials;
        }

        $settings = $store->settings ?? [];
        $settings['storefront'] = $storefront;
        $store->update([
            'settings' => $settings,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        return redirect()->route('storefront.settings')->with('status', 'Storefront content updated.');
    }
}
