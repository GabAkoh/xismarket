<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the PUBLIC storefront from the {store} slug in
 * the URL (there is no authenticated user to identify it). Sets the Tenancy
 * service so BelongsToTenant scoping applies, shares the store to views, and
 * registers the slug as a default route parameter so shop.* links don't each
 * have to pass it.
 */
class ResolveStoreTenant
{
    public function __construct(protected Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('store');

        $tenant = Tenant::where('slug', $slug)->where('is_active', true)->first();

        abort_if(! $tenant, 404, 'Store not found.');

        $this->tenancy->set($tenant);

        // Re-share: the global IdentifyTenant middleware already shared a (null)
        // currentTenant for this guest request before we resolved the store.
        view()->share('currentTenant', $tenant);
        view()->share('store', $tenant);

        // So route('shop.cart') etc. auto-fill {store}.
        URL::defaults(['store' => $tenant->slug]);

        return $next($request);
    }
}
