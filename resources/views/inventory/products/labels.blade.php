@extends('layouts.app')
@section('title', 'Barcode labels')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    // Label dimensions (mm) per preset.
    $dims = ['50x25' => [50, 25], '40x30' => [40, 30], '38x25' => [38, 25], '50x30' => [50, 30], '60x40' => [60, 40], '30x40' => [30, 40]];
    [$lw, $lh] = $dims[$size] ?? $dims['50x25'];
    // Flatten the items into one entry per printed label (respecting qty).
    $labels = $items->flatMap(fn ($it) => array_fill(0, $qty, $it));
@endphp

<x-page-header title="{{ $currentTenant->name }}" subtitle="{{ $items->count() }} item(s) · {{ $qty }} label(s) each">
    <button onclick="window.print()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 print:hidden">Print</button>
    <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm print:hidden">Back</a>
</x-page-header>

{{-- Controls (size / quantity) — re-loads the page with new options. --}}
<form method="GET" action="{{ route('products.labels') }}" class="mb-4 flex flex-wrap items-end gap-3 print:hidden">
    <input type="hidden" name="ids" value="{{ $idsParam }}">
    <input type="hidden" name="variants" value="{{ $variantsParam }}">
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Label size</label>
        <select name="size" class="rounded-md border border-slate-300 p-2 text-sm">
            <option value="50x25" @selected($size === '50x25')>50 × 25 mm</option>
            <option value="40x30" @selected($size === '40x30')>40 × 30 mm</option>
            <option value="38x25" @selected($size === '38x25')>38 × 25 mm</option>
            <option value="50x30" @selected($size === '50x30')>50 × 30 mm (bigger)</option>
            <option value="60x40" @selected($size === '60x40')>60 × 40 mm (biggest)</option>
            <option value="30x40" @selected($size === '30x40')>30 × 40 mm</option>
            <option value="40x30" @selected($size === '40x30')>40 × 30 mm</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Labels per product</label>
        <input type="number" name="qty" value="{{ $qty }}" min="1" max="50" class="w-24 rounded-md border border-slate-300 p-2 text-sm">
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-600 pb-2" title="One label per page, sized to the label — for roll/thermal label printers">
        <input type="checkbox" name="roll" value="1" @checked($roll) class="rounded border-slate-300">
        Roll printer (one label per page)
    </label>
    <button class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Update</button>
</form>

@if ($items->isEmpty())
    <x-card><p class="text-center text-slate-400 py-8">No items selected. Pick products in the list and choose “Print labels”, or use the 🏷 link on a variant.</p></x-card>
@else
    <div id="labels" class="label-sheet" style="--lw: {{ $lw }}mm; --lh: {{ $lh }}mm;">
        @foreach ($labels as $p)
            <div class="label">
                <div class="label-name">{{ \Illuminate\Support\Str::limit($p['name'], 22) }}</div>
                @php $code = $p['barcode'] ?: $p['sku']; @endphp
                @if ($code)
                    <div class="label-bc-wrap"><svg class="label-barcode" data-value="{{ $code }}"></svg></div>
                    <div class="label-code">{{ $code }}</div>
                @else
                    <div class="label-nobc">No barcode / SKU</div>
                @endif
                @if ($p['sale_price'])<div class="label-price">{{ $symbol }} {{ number_format($p['sale_price'], 2) }}</div>@endif
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
        padding: 0.5mm; gap: 0.3mm; overflow: hidden; box-sizing: border-box; text-align: center;
    }
    /* Keep the name to one line so the barcode gets most of the label. */
    .label-name { font-size: 7px; line-height: 1.1; font-weight: 600; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 0 0 auto; }
    .label-price { font-size: 10px; font-weight: 700; flex: 0 0 auto; }
    /* Barcode fills the remaining height; horizontal padding = scanner quiet zone. */
    .label-bc-wrap { width: 100%; flex: 1 1 auto; min-height: 8mm; display: flex; padding: 0 2mm; box-sizing: border-box; }
    .label-barcode { width: 100%; height: 100%; display: block; }
    .label-code { font-size: 9px; font-weight: 600; letter-spacing: 0.5px; font-family: 'Courier New', monospace; line-height: 1; flex: 0 0 auto; }
    .label-nobc { font-size: 8px; color: #ef4444; }

    @media print {
        aside, header, .print\:hidden { display: none !important; }
        main { padding: 0 !important; }
        .label { border: none; }
        @if ($roll)
        /* Roll/label printer: each label is its own page, sized exactly to the label. */
        @page { size: {{ $lw }}mm {{ $lh }}mm; margin: 0; }
        html, body { margin: 0 !important; padding: 0 !important; }
        .label-sheet { display: block !important; gap: 0 !important; }
        .label { width: {{ $lw }}mm !important; height: {{ $lh }}mm !important; page-break-after: always; break-after: page; }
        .label:last-child { page-break-after: avoid; break-after: avoid; }
        @else
        @page { margin: 4mm; }
        @endif
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
                // Render bars only (the code is shown separately as text), then make
                // the SVG stretch to fill the label box so the bars are large.
                JsBarcode(el, v, { format: 'CODE128', width: 2, height: 100, margin: 0, displayValue: false });
                var w = el.getAttribute('width'), h = el.getAttribute('height');
                if (w && h) el.setAttribute('viewBox', '0 0 ' + w + ' ' + h);
                el.setAttribute('preserveAspectRatio', 'none');
                el.setAttribute('width', '100%');
                el.setAttribute('height', '100%');
            } catch (e) { /* unrenderable value — leave blank */ }
        });
    }
    window.addEventListener('load', renderBarcodes);
</script>
@endpush
@endsection
