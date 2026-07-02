@extends('layouts.app')
@section('title', 'Branding')

@section('content')
@php
    $name = $store->setting('branding.app_name', '');
    $icon = $store->setting('branding.icon');
@endphp

<x-page-header title="Branding" subtitle="Set the app name and icon shown in the sidebar, browser tab and favicon." />

@if (session('status'))
    <div class="max-w-2xl mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('branding.settings.update') }}" enctype="multipart/form-data" class="max-w-2xl">
    @csrf @method('PUT')

    <x-card>
        <div>
            <label class="block text-sm font-medium text-slate-700">App name</label>
            <input name="app_name" value="{{ old('app_name', $name) }}" maxlength="60" placeholder="xismarket"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2">
            <p class="mt-1 text-xs text-slate-400">Shown in the sidebar and the browser tab. Leave blank to use the default.</p>
            @error('app_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-5">
            <label class="block text-sm font-medium text-slate-700">App icon</label>
            @if ($icon)
                <div class="mt-2 flex items-center gap-3">
                    <img src="{{ asset('storage/'.$icon) }}" alt="App icon" class="h-12 w-12 rounded border border-slate-200 object-contain bg-slate-50">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remove_icon" value="1" class="rounded border-slate-300">
                        Remove
                    </label>
                </div>
            @endif
            <input type="file" name="icon" accept="image/png,image/jpeg,image/webp"
                   class="mt-2 w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
            <p class="mt-1 text-xs text-slate-400">Used as the sidebar logo and browser favicon. Square PNG works best (e.g. 128×128). PNG/JPG/WebP, up to 1&nbsp;MB.</p>
            @error('icon')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-5 border-t pt-4">
            <label class="block text-sm font-medium text-slate-700">Timezone</label>
            <input name="timezone" list="tz-list" value="{{ old('timezone', $store->setting('general.timezone', config('app.timezone'))) }}"
                   placeholder="Africa/Lagos" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            <datalist id="tz-list">
                <option value="Africa/Lagos"><option value="Africa/Accra"><option value="Africa/Nairobi">
                <option value="Africa/Johannesburg"><option value="UTC"><option value="Europe/London"><option value="America/New_York">
            </datalist>
            <p class="mt-1 text-xs text-slate-400">Used for staff access-hours and local-time displays. e.g. <span class="font-mono">Africa/Lagos</span>.</p>
            @error('timezone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </x-card>

    <div class="mt-4">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
    </div>
</form>
@endsection
