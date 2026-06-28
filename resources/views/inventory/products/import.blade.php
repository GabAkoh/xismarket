@extends('layouts.app')
@section('title', 'Import products')

@section('content')
<x-page-header title="Import products" subtitle="Upload a Shopify or Odoo product-export CSV">
    <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to products</a>
</x-page-header>

{{-- After queueing, reload once so the background job's summary appears. --}}
@if (session('justQueued'))
    <script>setTimeout(function () { window.location.reload(); }, 6000);</script>
@endif

@if (! empty($lastImport))
    @php $hasIssues = ($lastImport['skipped'] ?? 0) > 0 || ! empty($lastImport['errors']); @endphp
    <div class="mb-4 rounded-md border p-4 {{ $hasIssues ? 'bg-amber-50 border-amber-200' : 'bg-green-50 border-green-200' }}">
        <p class="text-sm font-semibold text-slate-700">
            Last import result
            <span class="ml-1 text-xs font-normal text-slate-400">{{ $lastImport['finished_at'] ?? '' }}</span>
        </p>
        <div class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
            <span><strong class="text-slate-800">{{ number_format($lastImport['created'] ?? 0) }}</strong> created</span>
            @if (($lastImport['variants'] ?? 0) > 0)<span><strong class="text-slate-800">{{ number_format($lastImport['variants']) }}</strong> variants</span>@endif
            <span><strong class="text-slate-800">{{ number_format($lastImport['updated'] ?? 0) }}</strong> updated</span>
            <span><strong class="text-slate-800">{{ number_format($lastImport['images'] ?? 0) }}</strong> images</span>
            <span><strong class="text-slate-800">{{ number_format($lastImport['skipped'] ?? 0) }}</strong> skipped</span>
        </div>
        @if (! empty($lastImport['errors']))
            <ul class="mt-2 list-disc list-inside text-xs text-amber-800 space-y-0.5">
                @foreach (array_slice($lastImport['errors'], 0, 15) as $err)<li>{{ $err }}</li>@endforeach
                @if (count($lastImport['errors']) > 15)<li>… and {{ count($lastImport['errors']) - 15 }} more.</li>@endif
            </ul>
        @endif
    </div>
@endif

<form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-4"
      x-data="{ source: '{{ old('source', 'shopify') }}', mode: '{{ old('mode', 'create') }}' }">
    @csrf
    <x-card>
        <label class="block text-sm font-medium text-slate-700">Source</label>
        <select name="source" x-model="source" class="mt-1 w-full rounded-md border border-slate-300 p-2 text-sm">
            <option value="shopify">Shopify</option>
            <option value="odoo">Odoo</option>
        </select>

        <label class="mt-4 block text-sm font-medium text-slate-700">
            <span x-show="source === 'shopify'">Shopify products CSV</span>
            <span x-show="source === 'odoo'" x-cloak>Odoo products CSV</span>
        </label>
        <input type="file" name="file" accept=".csv,text/csv" required
               class="mt-2 w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
        @error('file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

        {{-- Shopify-only image options --}}
        <div x-show="source === 'shopify'">
            <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="download_images" value="1" checked>
                Download product images from Shopify
            </label>
            <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="refresh_images" value="1">
                Re-download images for products that already have one
            </label>
        </div>

        {{-- Shopify help --}}
        <div x-show="source === 'shopify'" class="mt-4 rounded-md bg-slate-50 border border-slate-100 p-3 text-xs text-slate-500 space-y-1">
            <p class="font-semibold text-slate-600">How it works</p>
            <p>In Shopify: <span class="font-medium">Products → Export → Current page / All products → Plain CSV</span>, then upload the file here.</p>
            <p>Each variant becomes its own product (matched/updated by SKU). Title, type→category, price, cost, barcode, stock and status are mapped automatically. Tax rate defaults to 0 — set it afterwards.</p>
            <p>Re-importing the same file updates existing products (it won't duplicate them or re-add stock).</p>
            <p>The import runs in the background — products appear progressively as it processes.</p>
        </div>

        {{-- Odoo mode --}}
        <div x-show="source === 'odoo'" x-cloak class="mt-4">
            <label class="block text-sm font-medium text-slate-700">What to do</label>
            <select name="mode" x-model="mode" class="mt-1 w-full rounded-md border border-slate-300 p-2 text-sm">
                <option value="create">Create new products only (skip existing)</option>
                <option value="update">Update price &amp; quantity of existing products</option>
                <option value="images">Update product images (from base64 Image column)</option>
            </select>
        </div>

        {{-- Odoo help --}}
        <div x-show="source === 'odoo'" x-cloak class="mt-4 rounded-md bg-slate-50 border border-slate-100 p-3 text-xs text-slate-500 space-y-1">
            <p class="font-semibold text-slate-600">How it works</p>
            <p>In Odoo: <span class="font-medium">Inventory or Sales → Products → select → Export</span>, choose <span class="font-medium">CSV</span> (include Name, Internal Reference, Barcode, Sales Price, Cost, Product Category, Quantity On Hand).</p>
            <p x-show="mode === 'create'"><span class="font-medium text-slate-600">Create:</span> only products not already here are added (matched by Internal Reference, then name) — existing ones are skipped. Name, category, price, cost, barcode and on-hand stock are mapped; tax rate defaults to 0.</p>
            <p x-show="mode === 'create'"><span class="font-medium text-slate-600">Variants:</span> to import grouped variants, export from <span class="font-medium">Products → Product Variants</span> and include the <span class="font-medium">Product Template</span> and <span class="font-medium">Variant Values</span> columns (e.g. "Color: Red, Size: M"). Rows are grouped into one product per template with a variant each.</p>
            <p x-show="mode === 'update'"><span class="font-medium text-slate-600">Update:</span> for products that already exist here (matched by Internal Reference, then name), the sale price and on-hand quantity are updated from the file (quantity is set to the file's value). Products not found here are skipped; nothing new is created. Prices with thousands separators (e.g. 530,000.00) are handled.</p>
            <div x-show="mode === 'images'" x-cloak class="space-y-1">
                <p><span class="font-medium text-slate-600">Images:</span> sets the main image of products already here, matched by <span class="font-medium">Internal Reference</span> (then name). Export with the <span class="font-medium">Image</span> field included — Odoo embeds it as base64 in the CSV (the public <code>/web/image</code> URL only returns a placeholder for unpublished products, so the image must travel in the file).</p>
                <p class="text-amber-700">⚠ Full-res images make a very large CSV that won't upload here. Keep it under 20&nbsp;MB, or for the whole catalogue place the file on the server and run <code>php artisan products:import-odoo-images &lt;path&gt;</code>.</p>
            </div>
            <p>The import runs in the background — the summary appears here when it finishes.</p>
        </div>
    </x-card>

    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Import products</button>
</form>
@endsection
