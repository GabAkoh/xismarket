{{-- Allowed sign-in hours editor. Expects $schedule (array|null). Fields post
     as access_hours[enabled|start|end|days][]. --}}
@php
    $s = $schedule ?? [];
    $enabled = (bool) ($s['enabled'] ?? false);
    $days = array_map('intval', $s['days'] ?? []);
@endphp
<div x-data="{ on: {{ $enabled ? 'true' : 'false' }} }">
    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
        <input type="checkbox" name="access_hours[enabled]" value="1" x-model="on" class="rounded border-slate-300 text-indigo-600">
        Restrict sign-in to certain hours
    </label>

    <div x-show="on" x-cloak class="mt-3 space-y-3 pl-6">
        <div class="flex flex-wrap items-center gap-4">
            <label class="text-sm text-slate-600">From
                <input type="time" name="access_hours[start]" value="{{ $s['start'] ?? '08:00' }}" class="ml-1 rounded-md border border-slate-300 p-1.5">
            </label>
            <label class="text-sm text-slate-600">To
                <input type="time" name="access_hours[end]" value="{{ $s['end'] ?? '18:00' }}" class="ml-1 rounded-md border border-slate-300 p-1.5">
            </label>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            @foreach (['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '0' => 'Sun'] as $d => $name)
                <label class="flex items-center gap-1 rounded border border-slate-200 px-2 py-1 cursor-pointer">
                    <input type="checkbox" name="access_hours[days][]" value="{{ $d }}" @checked(in_array((int) $d, $days, true)) class="rounded border-slate-300">
                    {{ $name }}
                </label>
            @endforeach
        </div>

        <p class="text-xs text-slate-400">Outside these hours sign-in is refused and any open session is logged out. Leave all days ticked-off to allow every day. Owners &amp; super-admins are never restricted.</p>
    </div>
</div>
