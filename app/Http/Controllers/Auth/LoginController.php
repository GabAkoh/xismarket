<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // Set the tenant now (middleware ran as guest) so the checks below use
        // the tenant's timezone / device list.
        app(\App\Support\Tenancy::class)->set(\App\Models\Tenant::find($user->tenant_id));

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        if (! \App\Support\AccessHours::allows($user)) {
            Auth::logout();
            $window = \App\Support\AccessHours::label($user);
            throw ValidationException::withMessages([
                'email' => 'Access is closed right now.'.($window ? ' Allowed hours: '.$window.'.' : ''),
            ]);
        }

        // Device allowlist (owners/super-admins bypass so they can approve devices).
        $devices = app(\App\Support\DeviceService::class);
        if ($devices->restrictionEnabled() && ! $user->is_owner && ! $user->is_super_admin && ! $devices->isApproved($request)) {
            $devices->registerPending($request);
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'This device isn’t approved yet. An administrator must approve it before you can sign in.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
