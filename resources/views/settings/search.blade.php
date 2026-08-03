@extends('layouts.app')
@section('title', 'Search')

@section('content')
@php
    $fuzzy = (bool) $store->setting('search.fuzzy_enabled', true);
    $semantic = (bool) $store->setting('search.semantic_enabled', true);
    $pct = $productCount > 0 ? min(100, round($embeddedCount / $productCount * 100)) : 0;
@endphp

<x-page-header title="Search" subtitle="Tune how products are matched in the POS Register and storefront." />

@if (session('status'))
    <div class="max-w-2xl mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('search.settings.update') }}" class="max-w-2xl">
    @csrf @method('PUT')

    <x-card>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="fuzzy_enabled" value="1" @checked($fuzzy) class="mt-0.5 rounded border-slate-300 text-indigo-600">
            <span>
                <span class="font-medium">Fuzzy search</span>
                <span class="block text-xs text-slate-500">Typo-tolerant matching on name and SKU — "colgte" still finds "Colgate". Fast, no API calls. Exact barcode/SKU scans are always matched first.</span>
            </span>
        </label>

        <div class="mt-5 border-t pt-4">
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="semantic_enabled" value="1" @checked($semantic) class="mt-0.5 rounded border-slate-300 text-indigo-600">
                <span>
                    <span class="font-medium">Smart search <span class="text-xs font-normal text-slate-400">(AI, meaning-based)</span></span>
                    <span class="block text-xs text-slate-500">Matches by meaning, not just spelling — "kids juice" surfaces Ribena and Chivita even when those words aren't in the name. On the register this runs on every search. Uses the Gemini key from <a href="{{ route('ai.settings') }}" class="text-indigo-600 hover:underline">AI Tools</a> and per-product embeddings (below).</span>
                </span>
            </label>

            @unless ($keyConfigured)
                <div class="mt-3 ml-6 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-700">
                    No Gemini key is set, so smart search can't run yet. Add one in <a href="{{ route('ai.settings') }}" class="font-medium underline">Settings → AI Tools</a> — fuzzy and exact search keep working without it.
                </div>
            @endunless
        </div>
    </x-card>

    <div class="mt-4">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <span class="ml-2 text-xs text-slate-400">Turning smart search on automatically embeds any products that aren't indexed yet.</span>
    </div>
</form>

<div class="max-w-2xl mt-8">
    <h2 class="text-sm font-semibold text-slate-700">Product embeddings</h2>
    <p class="mt-1 text-xs text-slate-500">Smart search ranks against a vector per product. New and edited products are embedded automatically; use re-embed after a bulk import or an AI-key/model change.</p>

    <x-card class="mt-3">
        <div class="flex items-center justify-between text-sm">
            <span class="text-slate-700">{{ number_format($embeddedCount) }} of {{ number_format($productCount) }} products embedded</span>
            <span class="text-slate-400">{{ $pct }}%</span>
        </div>
        <div class="mt-2 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
            <div class="h-full rounded-full bg-indigo-500" style="width: {{ $pct }}%"></div>
        </div>

        <form method="POST" action="{{ route('search.reembed') }}" class="mt-4"
              onsubmit="return confirm('Re-embed all {{ $productCount }} products in the background?');">
            @csrf
            <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Re-embed all products
            </button>
            <span class="ml-2 text-xs text-slate-400">Runs in the background; already-current products are skipped without an API call.</span>
        </form>
    </x-card>
</div>
@endsection
