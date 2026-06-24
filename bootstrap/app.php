<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            foreach (glob(__DIR__.'/../routes/modules/*.php') as $moduleRoutes) {
                require $moduleRoutes;
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind the production TLS terminator (Caddy → nginx), trust the proxy
        // so Laravel honours X-Forwarded-Proto/For and builds https:// URLs.
        // Only Caddy can reach nginx on the internal network, so trusting all
        // forwarders is safe here.
        $middleware->trustProxies(at: '*');

        // Resolve the active tenant on every web request (after auth).
        $middleware->web(append: [
            IdentifyTenant::class,
        ]);

        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
