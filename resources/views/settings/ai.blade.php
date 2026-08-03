@extends('layouts.app')
@section('title', 'AI Tools')

@section('content')
@php
    $key = $store->setting('ai.gemini_key');
    $envKey = config('services.image_ai.key');
    $configured = $key || $envKey;
    $masked = $key ? (substr($key, 0, 4).str_repeat('•', max(4, strlen($key) - 8)).substr($key, -4)) : null;
@endphp

<x-page-header title="AI Tools" subtitle="Set the Google Gemini API key that powers AI product images and semantic search." />

@if (session('status'))
    <div class="max-w-2xl mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('ai.settings.update') }}" class="max-w-2xl">
    @csrf @method('PUT')

    <x-card>
        <div class="flex items-center gap-2 text-sm">
            <span class="inline-flex h-2.5 w-2.5 rounded-full {{ $configured ? 'bg-green-500' : 'bg-slate-300' }}"></span>
            @if ($key)
                <span class="text-slate-700">Using a store key <span class="font-mono text-slate-500">{{ $masked }}</span></span>
            @elseif ($envKey)
                <span class="text-slate-700">Using the server key (set in the environment)</span>
            @else
                <span class="text-slate-500">Not configured — AI image tools and semantic search are disabled</span>
            @endif
        </div>

        <div class="mt-5">
            <label class="block text-sm font-medium text-slate-700">Gemini API key</label>
            <input name="gemini_key" type="password" autocomplete="off" value="" maxlength="200"
                   placeholder="{{ $key ? 'Leave blank to keep the current key' : 'AIza…' }}"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono">
            <p class="mt-1 text-xs text-slate-400">
                Get a key from <span class="font-mono">aistudio.google.com/apikey</span>. Powers AI product-image
                generation and semantic product search. This key is stored for your store only and overrides the
                server default.
            </p>
            @error('gemini_key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        @if ($key)
            <div class="mt-4">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_key" value="1" class="rounded border-slate-300">
                    Remove the store key (fall back to the server default, if any)
                </label>
            </div>
        @endif
    </x-card>

    <div class="mt-4">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
    </div>
</form>
@endsection
