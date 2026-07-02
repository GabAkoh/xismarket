<?php

namespace App\Http\Middleware;

use App\Support\AccessHours;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lock a signed-in staff user out the moment their allowed access window
 * closes (checked on every web request). Guests and customers are unaffected;
 * owners/super-admins bypass. Login attempts are checked separately in the
 * LoginController.
 */
class EnforceAccessHours
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && ! AccessHours::allows($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $window = AccessHours::label($user);

            return redirect()->route('login')->withErrors([
                'email' => 'Access is closed right now.'.($window ? ' Allowed hours: '.$window.'.' : ''),
            ]);
        }

        return $next($request);
    }
}
