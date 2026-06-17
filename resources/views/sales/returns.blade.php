@extends('layouts.app')
@section('title', 'Returns & refunds')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Returns &amp; refunds" subtitle="Refunds processed across sales">
    <a href="{{ route('sales.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to sales</a>
</x-page-header>

{{-- Date filter --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('sales.returns') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
    </form>
</x-card>

{{-- Summary --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Total refunded</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{{ $symbol }} {{ number_format($summary['total'], 2) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Returns</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary['count']) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Cash refunded</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $symbol }} {{ number_format($summary['cash'], 2) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Store credit refunded</p>
        <p class="mt-1 text-2xl font-bold text-indigo-600">{{ $symbol }} {{ number_format($summary['wallet'], 2) }}</p>
    </div>
</div>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">When</th><th>Sale</th><th>Customer</th>
                <th class="text-right">Cash</th><th class="text-right">Store credit</th><th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $r)
                <tr>
                    <td class="py-3 text-slate-500">{{ optional($r->date)->format('d M Y H:i') }}</td>
                    <td>
                        @if ($r->sale)
                            <a href="{{ route('sales.show', $r->sale) }}" class="font-medium text-indigo-600 hover:underline">{{ $r->sale->number }}</a>
                            <span class="text-xs text-slate-400">· {{ $r->reference }}</span>
                        @else
                            <span class="text-slate-500">{{ $r->reference ?: '—' }}</span>
                        @endif
                    </td>
                    <td class="text-slate-500">{{ $r->sale?->customer?->name ?? 'Walk-in' }}</td>
                    <td class="text-right text-slate-600">{{ $symbol }} {{ number_format($r->cash, 2) }}</td>
                    <td class="text-right text-slate-600">{{ $symbol }} {{ number_format($r->wallet, 2) }}</td>
                    <td class="text-right font-semibold text-red-600">{{ $symbol }} {{ number_format($r->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-400">No returns in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection
