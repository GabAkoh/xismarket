@extends('layouts.app')
@section('title', 'Sales')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Sales">
    <a href="{{ route('sales.returns') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Returns &amp; refunds</a>
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('sales.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Search #</label>
            <input name="q" value="{{ request('q') }}" placeholder="INV-…" class="w-full rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select name="status" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div class="flex gap-2">
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
            <a href="{{ route('sales.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Reset</a>
        </div>
    </form>
</x-card>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Number</th><th>Date</th><th>Customer</th><th>Status</th>
                <th class="text-right">Total</th><th class="text-right">Balance</th><th></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($sales as $sale)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $sale->number }}</td>
                    <td class="text-slate-500">{{ optional($sale->completed_at)->format('d M Y H:i') }}</td>
                    <td class="text-slate-500">{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                    <td>
                        @php
                            $badge = match($sale->status) {
                                'completed' => 'bg-green-100 text-green-700',
                                'partially_paid' => 'bg-amber-100 text-amber-700',
                                'refunded' => 'bg-red-100 text-red-700',
                                'partially_refunded' => 'bg-amber-100 text-amber-700',
                                default => 'bg-slate-100 text-slate-600',
                            };
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $sale->status)) }}</span>
                    </td>
                    <td class="text-right font-semibold text-slate-700">{{ $symbol }}{{ number_format($sale->total, 2) }}</td>
                    <td class="text-right {{ $sale->balance_due > 0 ? 'text-red-600 font-semibold' : 'text-slate-300' }}">
                        {{ $sale->balance_due > 0 ? $symbol.number_format($sale->balance_due, 2) : '—' }}
                    </td>
                    <td class="text-right">
                        <a href="{{ route('sales.show', $sale) }}" class="text-indigo-600 hover:underline">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-400">No sales found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $sales->links() }}</div>
</x-card>
@endsection
