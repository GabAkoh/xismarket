@extends('layouts.app')
@section('title', 'Stock valuation')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $base = array_filter([
        'category' => $filters['category'], 'status' => $filters['status'],
        'stock' => $filters['stock'], 'q' => $filters['q'],
    ], fn ($v) => $v !== null && $v !== '');
    $exportUrl = fn ($section) => route('products.valuation.export', $base + ['section' => $section]);
    $profit = (float) $summary->retail_value - (float) $summary->cost_value;
    $margin = (float) $summary->retail_value > 0 ? $profit / (float) $summary->retail_value * 100 : 0;
    $maxCat = max(0.01, (float) ($byCategory->max('cost_value') ?? 0));
@endphp

<x-page-header title="Stock valuation" subtitle="Current worth of on-hand inventory, as of {{ now()->format('d M Y') }}">
    <a href="{{ $exportUrl('all') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export all (CSV)</a>
    <a href="{{ route('products.report') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Product report</a>
</x-page-header>

{{-- Filters --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('products.valuation') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
            <select name="category" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All categories</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected((string) $filters['category'] === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select name="status" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Stock</label>
            <select name="stock" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="in" @selected($filters['stock'] === 'in')>In stock</option>
                <option value="any" @selected($filters['stock'] === 'any')>Any (incl. zero)</option>
                <option value="out" @selected($filters['stock'] === 'out')>Out of stock</option>
                <option value="reorder" @selected($filters['stock'] === 'reorder')>At/below reorder</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Product</label>
            <input name="q" value="{{ $filters['q'] }}" placeholder="Name, SKU or barcode" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
        <a href="{{ route('products.valuation') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Reset</a>
    </form>
</x-card>

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Products</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary->products) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Units on hand</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $qty($summary->units) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Stock value (cost)</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary->cost_value) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Retail value</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary->retail_value) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Potential profit</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ $money($profit) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Margin</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($margin, 1) }}%</p>
    </div>
</div>

{{-- By category --}}
<x-card title="Value by category" class="mb-6">
    <x-slot:actions><a href="{{ $exportUrl('category') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Category</th><th class="text-right">Products</th><th class="text-right">Units</th>
                <th class="text-right">Cost value</th><th class="text-right">Retail value</th><th class="text-right">Margin</th><th class="pl-4 w-1/5">&nbsp;</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($byCategory as $c)
                <tr>
                    <td class="py-2 text-slate-700">{{ $c->name }}</td>
                    <td class="py-2 text-right text-slate-500">{{ number_format($c->products) }}</td>
                    <td class="py-2 text-right text-slate-500">{{ $qty($c->units) }}</td>
                    <td class="py-2 text-right font-semibold text-slate-700">{{ $money($c->cost_value) }}</td>
                    <td class="py-2 text-right text-slate-500">{{ $money($c->retail_value) }}</td>
                    <td class="py-2 text-right text-slate-500">{{ number_format($c->margin, 1) }}%</td>
                    <td class="pl-4"><div class="h-2 rounded bg-indigo-100"><div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $c->cost_value / $maxCat * 100)) }}%"></div></div></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-4 text-center text-slate-400">No stock matches these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>

{{-- By product --}}
<x-card title="Valuation by product">
    <x-slot:actions><a href="{{ $exportUrl('detailed') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Product</th><th>Category</th>
                <th class="text-right">On hand</th><th class="text-right">Unit cost</th><th class="text-right">Cost value</th>
                <th class="text-right">Unit price</th><th class="text-right">Retail value</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $p)
                <tr>
                    <td class="py-2 font-medium text-slate-700">{{ $p->name }}<div class="text-xs text-slate-400">{{ $p->sku }}</div></td>
                    <td class="text-slate-500">{{ $p->category?->name ?? '—' }}</td>
                    <td class="text-right text-slate-700">{{ $qty($p->on_hand) }}</td>
                    <td class="text-right text-slate-500">{{ $money($p->cost_price) }}</td>
                    <td class="text-right font-semibold text-slate-700">{{ $money($p->cost_value) }}</td>
                    <td class="text-right text-slate-500">{{ $money($p->sale_price) }}</td>
                    <td class="text-right text-slate-500">{{ $money($p->retail_value) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-400">No stock matches these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-card>
@endsection
