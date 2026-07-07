@extends('layouts.app')
@section('title', $title)

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $exportParams = array_filter([
        'category' => $filters['category'], 'status' => $filters['status'],
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<x-page-header :title="$title" :subtitle="$subtitle">
    <a href="{{ route($exportRoute, $exportParams) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export CSV</a>
    <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to products</a>
</x-page-header>

{{-- Filters --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route($reportRoute) }}" class="flex flex-wrap items-end gap-3">
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
                <option value="active" @selected($filters['status'] === 'active')>Active only</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive only</option>
                <option value="all" @selected($filters['status'] === 'all')>All</option>
            </select>
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
    </form>
</x-card>

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Products</p>
        <p class="mt-1 text-2xl font-bold {{ $summary->products > 0 ? ($mode === 'out' ? 'text-red-600' : 'text-amber-600') : 'text-slate-800' }}">{{ number_format($summary->products) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Suggested order (units)</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $qty($summary->shortfall) }}</p>
        <p class="text-xs text-slate-400 mt-1">shortfall to reorder level</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Units sold</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $qty($summary->units_sold) }}</p>
        <p class="text-xs text-slate-400 mt-1">last {{ number_format($lookback) }} day(s)</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Stock value (cost)</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary->stock_value) }}</p>
    </div>
</div>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Product</th><th>Category</th><th>Status</th>
                <th class="text-right">Stock</th><th class="text-right">Reorder</th>
                <th class="text-right">Suggested order</th>
                <th class="text-right">Units sold</th><th class="text-right">Per day</th><th>Last sold</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $p)
                @php
                    $last = collect([$p->pos_last, $p->onl_last])->filter()->max();
                    $rate = (float) $p->units_sold / max(1, $lookback);
                @endphp
                <tr>
                    <td class="py-2 font-medium text-slate-700">{{ $p->name }}<div class="text-xs text-slate-400">{{ $p->sku }}</div></td>
                    <td class="text-slate-500">{{ $p->category?->name ?? '—' }}</td>
                    <td>
                        @if ($p->is_active)<span class="text-xs text-green-600">● Active</span>
                        @else<span class="text-xs text-slate-400">● Inactive</span>@endif
                    </td>
                    <td class="text-right {{ $mode === 'out' ? 'text-red-600 font-semibold' : 'text-amber-600 font-semibold' }}">{{ $qty($p->total_stock) }}</td>
                    <td class="text-right text-slate-400">{{ (float) $p->reorder_level > 0 ? $qty($p->reorder_level) : '—' }}</td>
                    <td class="text-right font-semibold text-slate-700">{{ (float) $p->shortfall > 0 ? $qty($p->shortfall) : '—' }}</td>
                    <td class="text-right text-slate-700">{{ $qty($p->units_sold) }}</td>
                    <td class="text-right text-slate-500">{{ $rate > 0 ? number_format($rate, 2) : '—' }}</td>
                    <td class="text-slate-500">{{ $last ? \Illuminate\Support\Carbon::parse($last)->format('d M Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-400">
                    @if ($mode === 'out') Nothing is out of stock. @else Nothing is below its reorder level. @endif
                </td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-card>
@endsection
