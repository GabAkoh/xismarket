@extends('layouts.app')
@section('title', 'Sales report')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $maxDay = $daily->max('total') ?: 1;
@endphp

<x-page-header title="Sales report" subtitle="Totals, payment mix and trends for the selected period">
    <a href="{{ route('sales.report.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export CSV</a>
    <a href="{{ route('sales.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to sales</a>
</x-page-header>

{{-- Date filter --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('sales.report') }}" class="flex flex-wrap items-end gap-3">
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

{{-- Headline KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Net sales</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary['net']) }}</p>
        <p class="text-xs text-slate-400 mt-1">ex-tax revenue</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Gross profit</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ $money($summary['profit']) }}</p>
        <p class="text-xs text-slate-400 mt-1">net − cost of goods</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Sales</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary['count']) }}</p>
        <p class="text-xs text-slate-400 mt-1">avg {{ $money($summary['avg']) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Outstanding (credit)</p>
        <p class="mt-1 text-2xl font-bold {{ $summary['outstanding'] > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $money($summary['outstanding']) }}</p>
        <p class="text-xs text-slate-400 mt-1">collected {{ $money($summary['collected']) }}</p>
    </div>
</div>

{{-- Secondary figures --}}
<x-card class="mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
        <div><p class="text-slate-400">Gross</p><p class="font-semibold text-slate-700">{{ $money($summary['gross']) }}</p></div>
        <div><p class="text-slate-400">Discounts</p><p class="font-semibold text-slate-700">−{{ $money($summary['discounts']) }}</p></div>
        <div><p class="text-slate-400">Tax</p><p class="font-semibold text-slate-700">{{ $money($summary['tax']) }}</p></div>
        <div><p class="text-slate-400">Total billed</p><p class="font-semibold text-slate-700">{{ $money($summary['total']) }}</p></div>
        <div><p class="text-slate-400">Cost of goods</p><p class="font-semibold text-slate-700">{{ $money($summary['cogs']) }}</p></div>
        <div><p class="text-slate-400">Refunded</p><p class="font-semibold text-red-600">−{{ $money($summary['refunded']) }}</p></div>
    </div>
</x-card>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Payment mix --}}
    <x-card title="Payment methods">
        <table class="w-full text-sm">
            <tbody class="divide-y">
                @forelse ($methods as $m)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $m->label }}</td>
                        <td class="py-2 text-right text-slate-400 text-xs">{{ number_format($m->n) }}×</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($m->amount) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-400">No payments in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    {{-- Top products --}}
    <x-card title="Top products">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b">
                <tr><th class="py-2">Product</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($top as $p)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $p->name }}</td>
                        <td class="py-2 text-right text-slate-500">{{ rtrim(rtrim(number_format($p->qty, 3), '0'), '.') }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($p->revenue) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-slate-400">No sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>

{{-- Daily trend --}}
<x-card title="Daily breakdown">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Date</th><th class="text-right">Sales</th>
                <th class="text-right">Net</th><th class="text-right">Tax</th><th class="text-right">Total</th>
                <th class="pl-4 w-1/3">&nbsp;</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($daily as $d)
                <tr>
                    <td class="py-2 text-slate-600">{{ \Illuminate\Support\Carbon::parse($d->d)->format('d M Y') }}</td>
                    <td class="py-2 text-right text-slate-500">{{ number_format($d->n) }}</td>
                    <td class="py-2 text-right text-slate-600">{{ $money($d->net) }}</td>
                    <td class="py-2 text-right text-slate-500">{{ $money($d->tax) }}</td>
                    <td class="py-2 text-right font-semibold text-slate-700">{{ $money($d->total) }}</td>
                    <td class="pl-4">
                        <div class="h-2 rounded bg-indigo-100">
                            <div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $d->total / $maxDay * 100)) }}%"></div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-400">No sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection
