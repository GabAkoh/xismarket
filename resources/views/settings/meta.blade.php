@extends('layouts.app')
@section('title', 'Meta Pixel')

@section('content')
<x-page-header title="Meta Pixel" subtitle="Track storefront conversions in Meta Ads with the Pixel + Conversions API." />

@if (session('status'))
    <div class="max-w-2xl mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('meta.settings.update') }}" class="max-w-2xl">
    @csrf @method('PUT')

    <x-card>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="enabled" value="1" @checked($enabled) class="mt-0.5 rounded border-slate-300 text-indigo-600">
            <span>
                <span class="font-medium">Enable Meta tracking</span>
                <span class="block text-xs text-slate-500">When on (and a Pixel ID + token are set), the storefront fires the Pixel in the browser and sends matching events server-side via the Conversions API. When off, nothing is loaded or sent.</span>
            </span>
        </label>

        <div class="mt-5">
            <label class="block text-sm font-medium text-slate-700">Pixel ID (dataset ID)</label>
            <input name="pixel_id" value="{{ old('pixel_id', $pixelId) }}" placeholder="e.g. 1234567890123456" inputmode="numeric"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-sm">
            @error('pixel_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">Conversions API access token</label>
            <input name="access_token" type="password" autocomplete="off"
                   placeholder="{{ $hasToken ? '•••••••• (saved — leave blank to keep)' : 'EAAG… (system-user token)' }}"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-sm">
            <p class="mt-1 text-xs text-slate-400">Stored encrypted and never shown again. Enter a new value only to change it.</p>
            @error('access_token')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">Test event code <span class="font-normal text-slate-400">(optional)</span></label>
            <input name="test_event_code" value="{{ old('test_event_code', $testEventCode) }}" placeholder="TEST12345"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-sm">
            <p class="mt-1 text-xs text-slate-400">Set while verifying in Events Manager → Test Events. Clear it for live traffic.</p>
            @error('test_event_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-5 rounded-md bg-slate-50 border border-slate-100 p-3 text-xs text-slate-500 space-y-1">
            <p class="font-semibold text-slate-600">Setup</p>
            <p>In <span class="font-medium">Meta Events Manager</span>, create (or open) a dataset to get the <span class="font-medium">Pixel ID</span>. Generate a <span class="font-medium">Conversions API access token</span> under the dataset's Settings.</p>
            <p>Tracked events: <span class="font-mono">PageView</span>, <span class="font-mono">ViewContent</span>, <span class="font-mono">AddToCart</span>, <span class="font-mono">InitiateCheckout</span> and <span class="font-mono">Purchase</span>. Purchase is sent both from the browser and server-side (deduplicated by event ID) so paid orders are always attributed.</p>
        </div>
    </x-card>

    <div class="mt-4">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
    </div>
</form>
@endsection
