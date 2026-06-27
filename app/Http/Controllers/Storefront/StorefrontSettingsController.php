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
        $categories = \App\Models\Inventory\Category::orderBy('name')->get(['id', 'name']);

        return view('settings.storefront', compact('store', 'categories'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'order_alert_emails' => ['nullable', 'array', 'max:20'],
            'order_alert_emails.*' => ['nullable', 'email', 'max:255'],
            'order_alert_phones' => ['nullable', 'array', 'max:20'],
            'order_alert_phones.*' => ['nullable', 'string', 'max:50'],
            'promo' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['nullable', 'string', 'max:150'],
            'hero_subtitle' => ['nullable', 'string', 'max:500'],
            'story_title' => ['nullable', 'string', 'max:150'],
            'story_body' => ['nullable', 'string', 'max:2000'],
            'hero_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'story_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
            'testimonials' => ['nullable', 'array', 'max:12'],
            'testimonials.*.name' => ['nullable', 'string', 'max:100'],
            'testimonials.*.text' => ['nullable', 'string', 'max:500'],
            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'string', 'max:255'],
            'shipping_methods' => ['nullable', 'array'],
            'shipping_methods.*.label' => ['nullable', 'string', 'max:100'],
            'shipping_methods.*.fee' => ['nullable', 'numeric', 'min:0'],
            'shipping_methods.*.pickup' => ['nullable'],
            'featured_collections' => ['nullable', 'array', 'max:6'],
            'featured_collections.*.category_id' => ['nullable', 'integer'],
            'featured_collections.*.subtitle' => ['nullable', 'string', 'max:120'],
            'featured_collections.*.image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'featured_collections.*.existing_image' => ['nullable', 'string', 'max:255'],
            'featured_collections.*.remove_image' => ['nullable'],
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

        // Brand-story image — same replace/remove handling as the hero image.
        $storyImage = $existing['story_image'] ?? null;
        if ($request->hasFile('story_image')) {
            $storyImage = $request->file('story_image')->store('storefront', 'public');
            if (! empty($existing['story_image'])) {
                Storage::disk('public')->delete($existing['story_image']);
            }
        } elseif ($request->boolean('remove_story_image') && ! empty($existing['story_image'])) {
            Storage::disk('public')->delete($existing['story_image']);
            $storyImage = null;
        }
        if ($storyImage) {
            $storefront['story_image'] = $storyImage;
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

        // Social handles — keep only the platforms that were filled in.
        $social = collect($data['social'] ?? [])
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->all();
        if (! empty($social)) {
            $storefront['social'] = $social;
        }

        // Order alert recipients — trimmed, de-duped. Empty lists are omitted so
        // OrderAlertService falls back to the store contact + owner accounts.
        $alertEmails = collect($data['order_alert_emails'] ?? [])
            ->map(fn ($e) => trim((string) $e))
            ->filter()
            ->unique()
            ->values()->all();
        if (! empty($alertEmails)) {
            $storefront['order_alert_emails'] = $alertEmails;
        }

        $alertPhones = collect($data['order_alert_phones'] ?? [])
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->unique()
            ->values()->all();
        if (! empty($alertPhones)) {
            $storefront['order_alert_phones'] = $alertPhones;
        }

        // Shipping methods — keep rows that have a label.
        $shipping = collect($data['shipping_methods'] ?? [])
            ->map(fn ($m) => [
                'label' => trim((string) ($m['label'] ?? '')),
                'fee' => round((float) ($m['fee'] ?? 0), 2),
                'pickup' => filter_var($m['pickup'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->filter(fn ($m) => $m['label'] !== '')
            ->values()->all();
        if (! empty($shipping)) {
            $storefront['shipping_methods'] = $shipping;
        }

        // Featured collections — keep rows that point at a category, with a
        // per-row image (new upload replaces, checkbox removes, else kept).
        $collections = [];
        foreach (($data['featured_collections'] ?? []) as $i => $c) {
            $catId = (int) ($c['category_id'] ?? 0);
            if ($catId <= 0) {
                continue;
            }

            $image = $c['existing_image'] ?? null;
            if ($file = $request->file("featured_collections.$i.image")) {
                $image = $file->store('storefront', 'public');
            } elseif (! empty($c['remove_image'])) {
                $image = null;
            }

            $collections[] = [
                'category_id' => $catId,
                'subtitle' => trim((string) ($c['subtitle'] ?? '')),
                'image' => $image,
            ];
        }
        if (! empty($collections)) {
            $storefront['featured_collections'] = $collections;
        }

        // Delete any collection images that are no longer referenced.
        $keptImages = collect($collections)->pluck('image')->filter()->all();
        foreach (($existing['featured_collections'] ?? []) as $old) {
            if (! empty($old['image']) && ! in_array($old['image'], $keptImages, true)) {
                Storage::disk('public')->delete($old['image']);
            }
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
