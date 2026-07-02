<?php

namespace App\Http\Middleware;

use App\Support\DeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the tenant requires approved devices, log a signed-in staff user out if
 * their browser isn't an approved device (checked every request, so revoking a
 * device ends its sessions). Guests/customers and owners/super-admins bypass;
 * login attempts are gated in the LoginController.
 */
class EnforceDeviceRestriction
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && ! $user->is_owner && ! $user->is_super_admin) {
            $devices = app(DeviceService::class);
            if ($devices->restrictionEnabled() && ! $devices->isApproved($request)) {
                $devices->registerPending($request);
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'This device is not approved. Ask an administrator to approve it.',
                ]);
            }
        }

        return $next($request);
    }
}
