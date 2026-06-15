<?php

namespace App\Providers;

use App\Support\Tenancy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request.
        $this->app->singleton(Tenancy::class, fn () => new Tenancy);
    }

    public function boot(): void
    {
        // @permission('inventory.view') ... @endpermission
        Blade::if('permission', function (string $slug) {
            $user = Auth::user();

            return $user && $user->hasPermission($slug);
        });
    }
}
