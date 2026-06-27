@extends('layouts.app')
@section('title', 'Payments summary')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
@endphp

<x-page-header title="Payments summary" subtitle="Money received and paid out for the period, grouped by source and method">
    <a href="{{ route('sales.payments-summary.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export (CSV)</a>
    <a href="{{ route('sales.report') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Sales report</a>
</x-page-header>

{{-- Date filter --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('sales.payments-summary') }}" class="flex flex-wrap items-end gap-3">
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

{{-- Headline totals --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Total received</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ $money($totalReceived) }}</p>
        <p class="text-xs text-slate-400 mt-1">POS + settlements + online + cash in</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Total paid out</p>
        <p class="mt-1 text-2xl font-bold text-rose-600">{{ $money($totalOut) }}</p>
        <p class="text-xs text-slate-400 mt-1">cash out + refunds</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Net movement</p>
        <p class="mt-1 text-2xl font-bold {{ $net >= 0 ? 'text-slate-800' : 'text-rose-600' }}">{{ $money($net) }}</p>
        <p class="text-xs text-slate-400 mt-1">received − paid out</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Received sources --}}
    <div class="space-y-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Received</h2>
        @foreach ($sources as $s)
            <x-card>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-slate-800">{{ $s['title'] }}</h3>
                    <span class="font-bold text-green-600">{{ $money($s['total']) }}</span>
                </div>
                @if ($s['rows']->isEmpty())
                    <p class="text-sm text-slate-400">No activity in this period.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-400 border-b">
                            <tr><th class="py-1.5">Method</th><th class="text-right">Count</th><th class="text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($s['rows'] as $r)
                                <tr>
                                    <td class="py-1.5 text-slate-600">{{ $r->label }}</td>
                                    <td class="text-right text-slate-400">{{ number_format($r->n) }}</td>
                                    <td class="text-right font-medium text-slate-700">{{ $money($r->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @endforeach
    </div>

    {{-- Paid out + combined --}}
    <div class="space-y-6">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Paid out</h2>
        @foreach ($outflows as $s)
            <x-card>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-slate-800">{{ $s['title'] }}</h3>
                    <span class="font-bold text-rose-600">{{ $money($s['total']) }}</span>
                </div>
                @if ($s['rows']->isEmpty())
                    <p class="text-sm text-slate-400">No activity in this period.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-400 border-b">
                            <tr><th class="py-1.5">{{ $s['key'] === 'cash_out' ? 'Reason' : 'Method' }}</th><th class="text-right">Count</th><th class="text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($s['rows'] as $r)
                                <tr>
                                    <td class="py-1.5 text-slate-600">{{ $r->label }}</td>
                                    <td class="text-right text-slate-400">{{ $r->n ? number_format($r->n) : '—' }}</td>
                                    <td class="text-right font-medium text-slate-700">{{ $money($r->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @endforeach

        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 pt-2">All received by method</h2>
        <x-card>
            @if ($combined->isEmpty())
                <p class="text-sm text-slate-400">No payments received in this period.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400 border-b">
                        <tr><th class="py-1.5">Method</th><th class="text-right">Count</th><th class="text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($combined as $r)
                            <tr>
                                <td class="py-1.5 text-slate-600">{{ $r->label }}</td>
                                <td class="text-right text-slate-400">{{ number_format($r->n) }}</td>
                                <td class="text-right font-medium text-slate-700">{{ $money($r->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-semibold">
                            <td class="py-2">Total received by method</td>
                            <td></td>
                            <td class="text-right text-green-600">{{ $money($combined->sum('amount')) }}</td>
                        </tr>
                    </tfoot>
                </table>
                <p class="mt-2 text-xs text-slate-400">Excludes cash-in (recorded by reason, not a tender). Wallet means store credit redeemed, not new cash.</p>
            @endif
        </x-card>
    </div>
</div>
@endsection
