@extends('layouts.app')
@section('title', 'Product report')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $days = max(1, $from->diffInDays($to) + 1);
    $exportParams = array_filter([
        'category' => $filters['category'], 'status' => $filters['status'], 'stock' => $filters['stock'],
        'from' => $from->toDateString(), 'to' => $to->toDateString(),
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<x-page-header title="Product report" subtitle="Stock state and sales activity across your catalogue">
    <a href="{{ route('products.report.export', $exportParams) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export CSV</a>
    <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to products</a>
</x-page-header>

{{-- Filters --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('products.report') }}" class="flex flex-wrap items-end gap-3">
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
                <option value="">Any</option>
                <option value="in" @selected($filters['stock'] === 'in')>In stock</option>
                <option value="out" @selected($filters['stock'] === 'out')>Out of stock</option>
                <option value="reorder" @selected($filters['stock'] === 'reorder')>At/below reorder</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Sales from</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Sales to</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
    </form>
</x-card>

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Products</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary->products) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Out of stock</p>
        <p class="mt-1 text-2xl font-bold {{ $summary->out_of_stock > 0 ? 'text-red-600' : 'text-slate-800' }}">{{ number_format($summary->out_of_stock) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">At/below reorder</p>
        <p class="mt-1 text-2xl font-bold {{ $summary->reorder > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ number_format($summary->reorder) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Stock value (cost)</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary->stock_value) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Units sold</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $qty($summary->units_sold) }}</p>
        <p class="text-xs text-slate-400 mt-1">over {{ number_format($days) }} day(s)</p>
    </div>
</div>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Product</th><th>Category</th><th>Status</th>
                <th class="text-right">Stock</th><th class="text-right">Reorder</th><th>State</th>
                <th class="text-right">Units sold</th><th class="text-right">Per day</th><th>Last sold</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $p)
                @php
                    $last = collect([$p->pos_last, $p->onl_last])->filter()->max();
                    $state = (float) $p->total_stock <= 0 ? 'Out of stock'
                        : ((float) $p->reorder_level > 0 && (float) $p->total_stock <= (float) $p->reorder_level ? 'Reorder' : 'OK');
                    $rate = (float) $p->units_sold / $days;
                @endphp
                <tr>
                    <td class="py-2 font-medium text-slate-700">{{ $p->name }}<div class="text-xs text-slate-400">{{ $p->sku }}</div></td>
                    <td class="text-slate-500">{{ $p->category?->name ?? '—' }}</td>
                    <td>
                        @if ($p->is_active)<span class="text-xs text-green-600">● Active</span>
                        @else<span class="text-xs text-slate-400">● Inactive</span>@endif
                    </td>
                    <td class="text-right text-slate-700">{{ $qty($p->total_stock) }}</td>
                    <td class="text-right text-slate-400">{{ (float) $p->reorder_level > 0 ? $qty($p->reorder_level) : '—' }}</td>
                    <td>
                        @if ($state === 'Out of stock')<span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700">Out of stock</span>
                        @elseif ($state === 'Reorder')<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">Reorder</span>
                        @else<span class="text-xs text-slate-400">OK</span>@endif
                    </td>
                    <td class="text-right font-semibold text-slate-700">{{ $qty($p->units_sold) }}</td>
                    <td class="text-right text-slate-500">{{ $rate > 0 ? number_format($rate, 2) : '—' }}</td>
                    <td class="text-slate-500">{{ $last ? \Illuminate\Support\Carbon::parse($last)->format('d M Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="py-6 text-center text-slate-400">No products match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-card>
@endsection
