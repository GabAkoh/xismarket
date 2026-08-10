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

{{-- Screen-only header. It must NOT print: on a roll printer the store name +
     "N item(s)/label(s)" subtitle would otherwise land on the first label. --}}
<div class="print:hidden">
    <x-page-header title="{{ $currentTenant->name }}" subtitle="{{ $items->count() }} item(s) · {{ $qty }} label(s) each">
        <button onclick="window.print()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Print</button>
        <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
    </x-page-header>
</div>

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
    /* Barcode area. The scanner quiet zone is baked INTO the SVG (11 modules each
       side, via JsBarcode margins), so this wrapper adds no side padding — every
       mm of label width goes to the code, and the bars stay centred. */
    .label-bc-wrap { width: 100%; flex: 1 1 auto; min-height: 8mm; display: flex; justify-content: center; align-items: stretch; box-sizing: border-box; }
    .label-barcode { height: 100%; display: block; }   /* width set in mm by JS to lock the X-dimension */
    .label-code { font-size: 9px; font-weight: 600; letter-spacing: 0.5px; font-family: 'Courier New', monospace; line-height: 1; flex: 0 0 auto; }
    .label-nobc { font-size: 8px; color: #ef4444; }
    /* A code too dense to scan reliably at this label size — flag it so staff
       pick a bigger label instead of printing an unscannable sticker. */
    .label.bc-too-dense { outline: 1px solid #ef4444; }
    .bc-warn { font-size: 6px; line-height: 1; color: #ef4444; flex: 0 0 auto; }

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
    // Physical label width (mm) for this page, from the size preset.
    var LABEL_W_MM = {{ $lw }};
    // Quiet zone in modules on each side. EAN-13 requires >= 11 (left); a scanner
    // will not read a code whose quiet zone is too small even when it looks fine.
    var QUIET = 11;
    // X-dimension = width of the narrowest bar, in mm. We aim for X_PREF and never
    // go below X_FLOOR (~0.25mm is the practical floor for retail scanners); below
    // that the label is simply too small for the code and we flag it.
    var X_PREF = 0.40, X_FLOOR = 0.25;
    // Source raster resolution: px per module in the off-screen canvas. High enough
    // (~10) that the printer gets a crisp bitmap at any DPI up to laser (600+).
    var SRC = 10;

    // True only for a 13-digit string whose last digit is a valid EAN-13 check
    // digit (matches App\Services\Inventory\BarcodeService::checkDigit).
    function isEan13(v) {
        if (!/^\d{13}$/.test(v)) return false;
        var sum = 0;
        for (var i = 0; i < 12; i++) {
            sum += (+v[i]) * (i % 2 === 0 ? 1 : 3);
        }
        return (10 - (sum % 10)) % 10 === (+v[12]);
    }

    function renderBarcodes() {
        if (typeof JsBarcode === 'undefined') { setTimeout(renderBarcodes, 100); return; }
        // Usable width for the whole symbol (bars + both quiet zones). Keep 1mm of
        // safety so the printer's own edge margin can never clip a quiet zone.
        var usableW = Math.max(10, LABEL_W_MM - 1);

        document.querySelectorAll('svg.label-barcode').forEach(function (el) {
            var v = el.getAttribute('data-value');
            if (!v) return;

            // Render to an OFF-SCREEN CANVAS, not the SVG. An SVG scaled down to a few
            // mm rasterises to ~1-3px bars that anti-alias and merge together in both
            // screen AND print — that is what made every code unscannable. A high-res
            // canvas raster downsamples cleanly (the browser averages), so the bars
            // survive. Quiet zone (>=11 modules) is baked in; no top/bottom waste.
            var canvas = document.createElement('canvas');
            try {
                JsBarcode(canvas, v, {
                    format: isEan13(v) ? 'EAN13' : 'CODE128',
                    width: SRC, height: 200, displayValue: false,
                    marginTop: 0, marginBottom: 0,
                    marginLeft: QUIET * SRC, marginRight: QUIET * SRC
                });
            } catch (e) { return; } // unrenderable value — leave the placeholder blank

            var modules = canvas.width / SRC;        // total modules incl. both quiet zones
            // Lock a real-world X-dimension instead of stretching to fill the label.
            // Uniform horizontal scaling preserves the bar/space RATIOS a scanner reads;
            // the height stretches independently (harmless for 1-D codes).
            var X = Math.min(X_PREF, usableW / modules);
            var symW = modules * X;                  // always <= usableW, so it never clips

            // Swap the placeholder <svg> for the raster.
            var img = document.createElement('img');
            img.className = 'label-barcode';
            img.alt = v;
            img.src = canvas.toDataURL('image/png');
            img.style.width = symW.toFixed(2) + 'mm';
            img.style.height = '100%';
            el.replaceWith(img);

            // Too dense to scan reliably at this label size — warn rather than print junk.
            if (X < X_FLOOR) {
                var label = img.closest('.label');
                if (label) {
                    label.classList.add('bc-too-dense');
                    label.title = 'Barcode too dense to scan at this label size (bar width '
                        + X.toFixed(2) + 'mm). Choose a bigger label.';
                    var warn = document.createElement('div');
                    warn.className = 'bc-warn';
                    warn.textContent = '⚠ too small — use bigger label';
                    label.appendChild(warn);
                }
            }
        });
    }
    window.addEventListener('load', renderBarcodes);
</script>
@endpush
@endsection
