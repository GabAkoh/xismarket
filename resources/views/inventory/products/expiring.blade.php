@extends('layouts.app')
@section('title', 'Expiring Soon')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3), '0'), '.');
    $exportParams = array_filter([
        'category' => $filters['category'], 'status' => $filters['status'], 'days' => $days,
    ], fn ($v) => $v !== null && $v !== '');
@endphp

<x-page-header title="Expiring Soon"
    subtitle="Products expiring within {{ $days }} day(s) — and anything already past its date">
    <a href="{{ route('products.expiring.export', $exportParams) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export CSV</a>
    <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to products</a>
</x-page-header>

{{-- Filters --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('products.expiring') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Within (days)</label>
            <input type="number" name="days" min="1" max="365" value="{{ $days }}" class="w-24 rounded-md border border-slate-300 p-2 text-sm">
        </div>
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
        <p class="mt-1 text-2xl font-bold {{ $summary->products > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ number_format($summary->products) }}</p>
        <p class="text-xs text-slate-400 mt-1">within {{ $days }} day(s)</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Already expired</p>
        <p class="mt-1 text-2xl font-bold {{ $summary->expired > 0 ? 'text-red-600' : 'text-slate-800' }}">{{ number_format($summary->expired) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Expiring soon</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary->expiring) }}</p>
        <p class="text-xs text-slate-400 mt-1">not yet past</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Stock value (cost)</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary->stock_value) }}</p>
        <p class="text-xs text-slate-400 mt-1">at risk</p>
    </div>
</div>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Product</th><th>Category</th><th>Status</th>
                <th>Expiry date</th><th class="text-right">Days left</th>
                <th class="text-right">Stock</th><th class="text-right">Stock value</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $p)
                @php
                    $daysLeft = (int) $today->diffInDays($p->expiry_date->copy()->startOfDay(), false);
                    $expired = $daysLeft < 0;
                    $urgent = $daysLeft >= 0 && $daysLeft <= 7;
                    if ($expired) {
                        $badge = 'text-red-600 font-semibold';
                        $label = abs($daysLeft).'d ago';
                    } elseif ($daysLeft === 0) {
                        $badge = 'text-red-600 font-semibold';
                        $label = 'Today';
                    } else {
                        $badge = $urgent ? 'text-amber-600 font-semibold' : 'text-slate-600';
                        $label = 'in '.$daysLeft.'d';
                    }
                @endphp
                <tr>
                    <td class="py-2 font-medium text-slate-700">{{ $p->name }}<div class="text-xs text-slate-400">{{ $p->sku }}</div></td>
                    <td class="text-slate-500">{{ $p->category?->name ?? '—' }}</td>
                    <td>
                        @if ($p->is_active)<span class="text-xs text-green-600">● Active</span>
                        @else<span class="text-xs text-slate-400">● Inactive</span>@endif
                    </td>
                    <td class="{{ $expired ? 'text-red-600 font-medium' : 'text-slate-700' }}">{{ $p->expiry_date->format('d M Y') }}</td>
                    <td class="text-right {{ $badge }}">{{ $label }}</td>
                    <td class="text-right text-slate-700">{{ $qty($p->total_stock) }}</td>
                    <td class="text-right text-slate-500">{{ $money((float) $p->total_stock * (float) $p->cost_price) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-400">No products are expiring within {{ $days }} day(s).</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-card>
@endsection
