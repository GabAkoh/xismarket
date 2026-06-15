@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php $currency = $currentTenant?->currency ?? 'USD'; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Sales today</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $currency }} {{ number_format($stats['sales_today_total'] ?? 0, 2) }}</p>
        <p class="text-xs text-slate-400">{{ $stats['sales_today_count'] ?? 0 }} transactions</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Products</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($stats['products'] ?? 0) }}</p>
        <p class="text-xs text-slate-400">in catalog</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Low stock</p>
        <p class="mt-1 text-2xl font-bold {{ ($stats['low_stock'] ?? 0) > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ number_format($stats['low_stock'] ?? 0) }}</p>
        <p class="text-xs text-slate-400">items at/below reorder level</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Staff</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($stats['staff'] ?? 0) }}</p>
        <p class="text-xs text-slate-400">user accounts</p>
    </div>
</div>

@include('dashboard._sales-chart', [
    'salesByDay' => $salesByDay ?? [],
    'salesByMonth' => $salesByMonth ?? [],
    'currency' => $currency,
])

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-5">
        <h2 class="font-semibold text-slate-800 mb-4">Recent sales</h2>
        @if (count($recentSales))
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400 border-b">
                    <tr><th class="py-2">Reference</th><th>Status</th><th class="text-right">Total</th><th class="text-right">When</th></tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($recentSales as $sale)
                        <tr>
                            <td class="py-2 font-medium text-slate-700">{{ $sale->number }}</td>
                            <td><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100">{{ $sale->status }}</span></td>
                            <td class="text-right">{{ $currency }} {{ number_format($sale->total, 2) }}</td>
                            <td class="text-right text-slate-400">{{ $sale->completed_at ? \Illuminate\Support\Carbon::parse($sale->completed_at)->diffForHumans() : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-sm text-slate-400">No sales yet. Open the <a href="{{ route('pos.index') }}" class="text-indigo-600">register</a> to make your first sale.</p>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow-sm p-5">
        <h2 class="font-semibold text-slate-800 mb-4">Quick actions</h2>
        <div class="space-y-2 text-sm">
            @if (Route::has('shop.home') && $currentTenant)<a href="{{ route('shop.home', ['store' => $currentTenant->slug]) }}" target="_blank" class="block rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-indigo-700 hover:bg-indigo-100">🛍️ View storefront ↗</a>@endif
            @if (Route::has('pos.index'))<a href="{{ route('pos.index') }}" class="block rounded-md border border-slate-200 px-4 py-3 hover:bg-slate-50">🧾 New sale (POS)</a>@endif
            @if (Route::has('products.create'))<a href="{{ route('products.create') }}" class="block rounded-md border border-slate-200 px-4 py-3 hover:bg-slate-50">📦 Add product</a>@endif
            @if (Route::has('purchases.create'))<a href="{{ route('purchases.create') }}" class="block rounded-md border border-slate-200 px-4 py-3 hover:bg-slate-50">🚚 New purchase order</a>@endif
            @if (Route::has('reports.index'))<a href="{{ route('reports.index') }}" class="block rounded-md border border-slate-200 px-4 py-3 hover:bg-slate-50">📊 Financial reports</a>@endif
            @if (Route::has('users.create'))<a href="{{ route('users.create') }}" class="block rounded-md border border-slate-200 px-4 py-3 hover:bg-slate-50">👤 Invite staff</a>@endif
        </div>
    </div>
</div>
@endsection
