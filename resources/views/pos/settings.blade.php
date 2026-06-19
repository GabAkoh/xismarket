@extends('layouts.app')
@section('title', 'Register display')

@section('content')
@php $current = max(2, min(8, (int) $store->setting('pos.grid_columns', 6))); @endphp

<x-page-header title="Register display" subtitle="Control how the POS register shows products." />

<form method="POST" action="{{ route('pos.settings.update') }}" class="max-w-2xl" x-data="{ cols: {{ $current }} }">
    @csrf @method('PUT')

    <x-card>
        <label class="block text-sm font-medium text-slate-600 mb-1">Products per row (on a wide screen)</label>
        <div class="flex items-center gap-4">
            <input type="range" name="grid_columns" min="2" max="8" step="1" x-model.number="cols" class="flex-1 accent-indigo-600">
            <span class="w-10 text-center text-sm font-semibold text-indigo-600" x-text="cols"></span>
        </div>
        <p class="text-xs text-slate-400 mt-1">More columns = smaller images and more products visible at once. Fewer = larger images.</p>

        {{-- Live preview: a grid of placeholder cards at the chosen density. --}}
        <div class="mt-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Preview</p>
            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
                <div class="grid gap-2" :style="`grid-template-columns: repeat(${cols}, minmax(0, 1fr))`">
                    <template x-for="i in cols * 2" :key="i">
                        <div class="rounded border border-slate-200 bg-white p-2">
                            <div class="aspect-square w-full rounded bg-slate-100 flex items-center justify-center text-slate-300 text-lg">📦</div>
                            <div class="mt-1 h-2 rounded bg-slate-200"></div>
                            <div class="mt-1 h-2 w-2/3 rounded bg-slate-100"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </x-card>

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('pos.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Open register</a>
    </div>
</form>
@endsection
