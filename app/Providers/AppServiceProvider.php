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

        // Outbound SMS driver. Defaults to a no-op logger until a real provider
        // is configured (set SMS_PROVIDER and add the matching arm + class).
        $this->app->singleton(\App\Contracts\SmsSender::class, function () {
            return match (config('services.sms.provider')) {
                // 'termii' => new \App\Services\Sms\TermiiSmsSender,
                // 'twilio' => new \App\Services\Sms\TwilioSmsSender,
                default => new \App\Services\Sms\LogSmsSender,
            };
        });
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
