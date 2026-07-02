<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Decides whether a staff user may access the app right now, based on allowed
 * sign-in windows. A per-user schedule (if enabled) overrides the user's roles;
 * otherwise any role without a schedule grants unrestricted access, and roles
 * with schedules allow access when the current time falls in any of them.
 * Owners and super-admins always have access.
 */
class AccessHours
{
    /** Build a stored schedule from the access_hours.* form fields (null if off). */
    public static function fromRequest(\Illuminate\Http\Request $request): ?array
    {
        if (! $request->boolean('access_hours.enabled')) {
            return null;
        }

        $h = $request->input('access_hours', []);

        return [
            'enabled' => true,
            'start' => $h['start'] ?? '00:00',
            'end' => $h['end'] ?? '23:59',
            'days' => array_values(array_unique(array_map('intval', $h['days'] ?? []))),
        ];
    }

    public static function allows(User $user): bool
    {
        if ($user->is_owner || $user->is_super_admin) {
            return true;
        }

        $now = Carbon::now(static::timezone());

        // Per-user override wins outright.
        $override = $user->access_hours;
        if (is_array($override) && ($override['enabled'] ?? false)) {
            return static::within($override, $now);
        }

        // Otherwise: unrestricted if any role has no schedule; else within-any.
        $roles = $user->roles;
        if ($roles->isEmpty()) {
            return true;
        }
        foreach ($roles as $role) {
            $sched = $role->access_hours;
            if (! (is_array($sched) && ($sched['enabled'] ?? false))) {
                return true;   // a role with no window = anytime access
            }
            if (static::within($sched, $now)) {
                return true;
            }
        }

        return false;   // every role is scheduled and none is open now
    }

    /** Human label of a user's effective window, for messages (or null if none). */
    public static function label(User $user): ?string
    {
        $sched = null;
        $override = $user->access_hours;
        if (is_array($override) && ($override['enabled'] ?? false)) {
            $sched = $override;
        } else {
            foreach ($user->roles as $role) {
                if (is_array($role->access_hours) && ($role->access_hours['enabled'] ?? false)) {
                    $sched = $role->access_hours;
                    break;
                }
            }
        }
        if (! $sched) {
            return null;
        }

        $names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $days = collect($sched['days'] ?? [])->sort()->map(fn ($d) => $names[$d] ?? '')->filter()->implode(', ');

        return trim(($days ? $days.' ' : '').($sched['start'] ?? '00:00').'–'.($sched['end'] ?? '23:59'));
    }

    protected static function within(array $sched, Carbon $now): bool
    {
        $days = array_map('intval', $sched['days'] ?? []);
        if ($days && ! in_array($now->dayOfWeek, $days, true)) {
            return false;
        }

        $cur = $now->format('H:i');
        $start = $sched['start'] ?? '00:00';
        $end = $sched['end'] ?? '23:59';

        // Same-day window, or an overnight window that wraps past midnight.
        return $start <= $end
            ? ($cur >= $start && $cur <= $end)
            : ($cur >= $start || $cur <= $end);
    }

    protected static function timezone(): string
    {
        $tenant = app(Tenancy::class)->current();

        return $tenant?->setting('general.timezone') ?: config('app.timezone', 'UTC');
    }
}
