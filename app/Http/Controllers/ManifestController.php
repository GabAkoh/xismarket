<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Dynamic PWA web app manifest, so "Add to home screen" uses the tenant's
 * branded name + icon. Public (the browser fetches it without a session), so
 * branding is resolved from the default store / first tenant.
 */
class ManifestController extends Controller
{
    public function show()
    {
        $slug = config('storefront.default_store');
        $tenant = $slug ? Tenant::where('slug', $slug)->first() : Tenant::query()->first();

        $name = $tenant?->setting('branding.app_name') ?: 'xismarket';
        $icon = $tenant?->setting('branding.icon');
        $iconUrl = $icon ? asset('storage/'.$icon) : null;
        $type = 'image/'.str_replace('jpg', 'jpeg', strtolower(pathinfo((string) $icon, PATHINFO_EXTENSION) ?: 'png'));

        $icons = $iconUrl ? [
            ['src' => $iconUrl, 'sizes' => '192x192', 'type' => $type, 'purpose' => 'any'],
            ['src' => $iconUrl, 'sizes' => '512x512', 'type' => $type, 'purpose' => 'any maskable'],
        ] : [];

        return response()->json([
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#0f172a',
            'icons' => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json'])
            ->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
