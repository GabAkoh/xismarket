<?php

namespace App\Support;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * "Only approved devices may sign in" control. Each browser carries a long-lived
 * signed cookie with a random token; access requires a matching approved Device
 * row. Unknown devices are recorded as pending for an admin to approve.
 */
class DeviceService
{
    public const COOKIE = 'xis_device';

    public function restrictionEnabled(): bool
    {
        return (bool) app(Tenancy::class)->current()?->setting('security.device_restriction', false);
    }

    public function token(Request $request): ?string
    {
        return $request->cookie(self::COOKIE);
    }

    /** Return the device token, queuing a 2-year cookie if the browser has none. */
    public function ensureToken(Request $request): string
    {
        $token = $request->cookie(self::COOKIE);
        if (! $token) {
            $token = Str::random(48);
            Cookie::queue(self::COOKIE, $token, 60 * 24 * 365 * 2, null, null, null, true, false, 'lax');
        }

        return $token;
    }

    /** True when the current cookie maps to an approved device (touches last-seen). */
    public function isApproved(Request $request): bool
    {
        $token = $this->token($request);
        if (! $token) {
            return false;
        }

        $device = Device::where('token', $token)->where('approved', true)->first();
        if (! $device) {
            return false;
        }

        $device->forceFill(['last_seen_at' => now()])->saveQuietly();

        return true;
    }

    /** Record (or refresh) a pending device so an admin can approve this browser. */
    public function registerPending(Request $request): Device
    {
        $token = $this->ensureToken($request);
        $device = Device::firstOrNew(['token' => $token]);
        if (! $device->exists) {
            $device->approved = false;
        }
        $device->label = $device->label ?: $this->label($request);
        $device->user_agent = substr((string) $request->userAgent(), 0, 512);
        $device->last_seen_at = now();
        $device->save();

        return $device;
    }

    /** Approve the current browser (used when enabling the feature, to avoid lockout). */
    public function approveCurrent(Request $request, ?int $userId = null): Device
    {
        $token = $this->ensureToken($request);

        return Device::updateOrCreate(['token' => $token], [
            'label' => $this->label($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'approved' => true,
            'approved_by' => $userId,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * A friendly device label from the user agent, e.g. "Chrome on Windows" or,
     * where the UA exposes a model, "Chrome on Android (SM-G991B)". Browsers
     * can't reveal the OS device name/hostname, so admins can rename devices.
     */
    public function label(Request $request): string
    {
        $ua = (string) $request->userAgent();

        $browser = str_contains($ua, 'Edg') ? 'Edge'
            : (str_contains($ua, 'Chrome') ? 'Chrome'
            : (str_contains($ua, 'Firefox') ? 'Firefox'
            : (str_contains($ua, 'Safari') ? 'Safari' : 'Browser')));

        $os = str_contains($ua, 'Windows') ? 'Windows'
            : (str_contains($ua, 'Android') ? 'Android'
            : ((str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) ? 'iOS'
            : (str_contains($ua, 'Mac') ? 'Mac'
            : (str_contains($ua, 'Linux') ? 'Linux' : 'device'))));

        // Mobile UAs usually carry a model; desktops don't.
        $model = null;
        if (preg_match('/Android [\d.]+; ?([^;)]+?)(?: Build|[;)])/', $ua, $m)) {
            $model = trim($m[1]);
        } elseif (str_contains($ua, 'iPhone')) {
            $model = 'iPhone';
        } elseif (str_contains($ua, 'iPad')) {
            $model = 'iPad';
        }

        $label = $browser.' on '.$os;
        if ($model && ! str_contains($label, $model)) {
            $label .= ' ('.$model.')';
        }

        return $label;
    }
}
