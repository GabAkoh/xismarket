@extends('layouts.app')
@section('title', 'Barcode labels')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    // Label dimensions (mm) per preset.
    $dims = ['50x25' => [50, 25], '40x30' => [40, 30], '38x25' => [38, 25]];
    [$lw, $lh] = $dims[$size] ?? $dims['50x25'];
    // Flatten products into one entry per printed label (respecting qty).
    $labels = $products->flatMap(fn ($p) => array_fill(0, $qty, $p));
@endphp

<x-page-header title="Barcode labels" subtitle="{{ $products->count() }} product(s) · {{ $qty }} label(s) each">
    <button onclick="window.print()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 print:hidden">Print</button>
    <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm print:hidden">Back</a>
</x-page-header>

{{-- Controls (size / quantity) — re-loads the page with new options. --}}
<form method="GET" action="{{ route('products.labels') }}" class="mb-4 flex flex-wrap items-end gap-3 print:hidden">
    <input type="hidden" name="ids" value="{{ $products->pluck('id')->implode(',') }}">
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Label size</label>
        <select name="size" class="rounded-md border border-slate-300 p-2 text-sm">
            <option value="50x25" @selected($size === '50x25')>50 × 25 mm</option>
            <option value="40x30" @selected($size === '40x30')>40 × 30 mm</option>
            <option value="38x25" @selected($size === '38x25')>38 × 25 mm</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Labels per product</label>
        <input type="number" name="qty" value="{{ $qty }}" min="1" max="50" class="w-24 rounded-md border border-slate-300 p-2 text-sm">
    </div>
    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Update</button>
</form>

@if ($products->isEmpty())
    <x-card><p class="text-center text-slate-400 py-8">No products selected. Pick products in the list and choose “Print labels”.</p></x-card>
@else
    <div id="labels" class="label-sheet" style="--lw: {{ $lw }}mm; --lh: {{ $lh }}mm;">
        @foreach ($labels as $p)
            <div class="label">
                <div class="label-name">{{ \Illuminate\Support\Str::limit($p->name, 40) }}</div>
                @if ($p->sale_price)<div class="label-price">{{ $symbol }} {{ number_format($p->sale_price, 2) }}</div>@endif
                @php $code = $p->barcode ?: $p->sku; @endphp
                @if ($code)
                    <svg class="label-barcode" data-value="{{ $code }}"></svg>
                @else
                    <div class="label-nobc">No barcode / SKU</div>
                @endif
            </div>
        @endforeach
    </div>
@endif

@push('head')
<style>
    .label-sheet { display: flex; flex-wrap: wrap; gap: 2mm; }
    .label {
        width: var(--lw); height: var(--lh);
        border: 1px dashed #cbd5e1;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 1mm; overflow: hidden; box-sizing: border-box; text-align: center;
    }
    .label-name { font-size: 8px; line-height: 1.1; font-weight: 600; }
    .label-price { font-size: 9px; font-weight: 700; }
    .label-barcode { width: 100%; height: auto; max-height: 55%; }
    .label-nobc { font-size: 8px; color: #ef4444; }

    @media print {
        @page { margin: 4mm; }
        aside, header, .print\:hidden { display: none !important; }
        main { padding: 0 !important; }
        .label { border: none; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
    function renderBarcodes() {
        if (typeof JsBarcode === 'undefined') { setTimeout(renderBarcodes, 100); return; }
        document.querySelectorAll('svg.label-barcode').forEach(function (el) {
            var v = el.getAttribute('data-value');
            if (!v) return;
            try {
                JsBarcode(el, v, { format: 'CODE128', width: 1.4, height: 34, fontSize: 11, margin: 0, displayValue: true });
            } catch (e) { /* unrenderable value — leave blank */ }
        });
    }
    window.addEventListener('load', renderBarcodes);
</script>
@endpush
@endsection
