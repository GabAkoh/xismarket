@extends('layouts.app')
@section('title', 'Devices')

@section('content')
<x-page-header title="Devices" subtitle="Approve the devices allowed to sign in. Applies when 'only approved devices' is on.">
    <a href="{{ route('branding.settings') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Settings</a>
</x-page-header>

@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
@endif

<x-card>
    @if ($devices->isEmpty())
        <p class="text-sm text-slate-400 py-6 text-center">No devices yet. They appear here the first time someone signs in from a browser (after enabling the device allowlist).</p>
    @else
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b">
                <tr><th class="py-2">Device</th><th>Status</th><th>Last seen</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($devices as $device)
                    <tr>
                        <td class="py-3">
                            <form method="POST" action="{{ route('devices.rename', $device) }}" class="flex items-center gap-1">
                                @csrf @method('PUT')
                                <input name="label" value="{{ $device->label }}" class="w-48 rounded border border-slate-200 p-1 text-sm">
                                <button class="text-xs text-indigo-600 hover:underline">Rename</button>
                            </form>
                            <div class="text-xs text-slate-400 mt-0.5 truncate max-w-xs" title="{{ $device->user_agent }}">{{ \Illuminate\Support\Str::limit($device->user_agent, 60) }}</div>
                        </td>
                        <td>
                            @if ($device->approved)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Approved</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Pending</span>
                            @endif
                        </td>
                        <td class="text-slate-500">{{ $device->last_seen_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @unless ($device->approved)
                                <form method="POST" action="{{ route('devices.approve', $device) }}" class="inline">
                                    @csrf
                                    <button class="text-sm text-green-600 hover:underline">Approve</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('devices.revoke', $device) }}" class="inline">
                                    @csrf
                                    <button class="text-sm text-amber-600 hover:underline">Revoke</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('devices.destroy', $device) }}" class="inline ml-2" onsubmit="return confirm('Remove this device?')">
                                @csrf @method('DELETE')
                                <button class="text-sm text-red-500 hover:underline">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-card>
@endsection
