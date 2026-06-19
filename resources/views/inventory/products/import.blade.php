@extends('layouts.app')
@section('title', 'Import products')

@section('content')
<x-page-header title="Import from Shopify" subtitle="Upload a Shopify product-export CSV">
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

<form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="max-w-2xl space-y-4">
    @csrf
    <x-card>
        <label class="block text-sm font-medium text-slate-700">Shopify products CSV</label>
        <input type="file" name="file" accept=".csv,text/csv" required
               class="mt-2 w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
        @error('file')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror

        <label class="mt-4 flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="download_images" value="1" checked>
            Download product images from Shopify
        </label>

        <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="refresh_images" value="1">
            Re-download images for products that already have one
        </label>

        <div class="mt-4 rounded-md bg-slate-50 border border-slate-100 p-3 text-xs text-slate-500 space-y-1">
            <p class="font-semibold text-slate-600">How it works</p>
            <p>In Shopify: <span class="font-medium">Products → Export → Current page / All products → Plain CSV</span>, then upload the file here.</p>
            <p>Each variant becomes its own product (matched/updated by SKU). Title, type→category, price, cost, barcode, stock and status are mapped automatically. Tax rate defaults to 0 — set it afterwards.</p>
            <p>Re-importing the same file updates existing products (it won't duplicate them or re-add stock).</p>
            <p>The import runs in the background — products appear progressively as it processes.</p>
        </div>
    </x-card>

    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Import products</button>
</form>
@endsection
