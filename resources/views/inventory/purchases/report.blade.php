@extends('layouts.app')
@section('title', 'Purchase report')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $maxDay = $daily->max('total') ?: 1;
    $maxSupplier = $suppliers->max('total') ?: 1;
    $exportUrl = fn ($section) => route('purchases.report.export', [
        'section' => $section, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
    ]);
@endphp

<x-page-header title="Purchase report" subtitle="Procurement spend, suppliers and trends for the selected period">
    <a href="{{ $exportUrl('all') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export all (CSV)</a>
    <a href="{{ route('purchases.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to purchases</a>
</x-page-header>

{{-- Date filter --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('purchases.report') }}" class="flex flex-wrap items-end gap-3">
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
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Purchase value</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary['total']) }}</p>
        <p class="text-xs text-slate-400 mt-1">total ordered</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Received</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ $money($summary['received_total']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ number_format($summary['received_count']) }} order(s) into stock</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Pending</p>
        <p class="mt-1 text-2xl font-bold {{ $summary['pending_total'] > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $money($summary['pending_total']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ number_format($summary['pending_count']) }} not yet received</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Purchase orders</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary['count']) }}</p>
        <p class="text-xs text-slate-400 mt-1">avg {{ $money($summary['avg']) }}</p>
    </div>
</div>

{{-- Status + suppliers --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="Status">
        <x-slot:actions><a href="{{ $exportUrl('status') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <tbody class="divide-y">
                @forelse ($statusRows as $st)
                    <tr>
                        <td class="py-2 text-slate-700 capitalize">{{ $st->status }}</td>
                        <td class="py-2 text-right text-slate-400 text-xs">{{ number_format($st->n) }} order(s)</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($st->total) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-400">No purchase orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="By supplier">
        <x-slot:actions><a href="{{ $exportUrl('suppliers') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b"><tr><th class="py-2">Supplier</th><th class="text-right">Orders</th><th class="text-right">Value</th><th class="pl-4 w-1/4">&nbsp;</th></tr></thead>
            <tbody class="divide-y">
                @forelse ($suppliers as $s)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $s->label }}</td>
                        <td class="py-2 text-right text-slate-500">{{ number_format($s->n) }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($s->total) }}</td>
                        <td class="pl-4"><div class="h-2 rounded bg-indigo-100"><div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $s->total / $maxSupplier * 100)) }}%"></div></div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-400">No purchase orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>

{{-- Warehouses + top items --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="By warehouse">
        <x-slot:actions><a href="{{ $exportUrl('warehouses') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <tbody class="divide-y">
                @forelse ($warehouses as $w)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $w->label }}</td>
                        <td class="py-2 text-right text-slate-400 text-xs">{{ number_format($w->n) }} order(s)</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($w->total) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-400">No purchase orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Top items purchased">
        <x-slot:actions><a href="{{ $exportUrl('products') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b"><tr><th class="py-2">Product</th><th class="text-right">Qty</th><th class="text-right">Cost</th></tr></thead>
            <tbody class="divide-y">
                @forelse ($top as $p)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $p->name }}</td>
                        <td class="py-2 text-right text-slate-500">{{ rtrim(rtrim(number_format($p->qty, 3), '0'), '.') }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($p->cost) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-slate-400">No purchase orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>

{{-- Daily trend --}}
<x-card title="Daily breakdown">
    <x-slot:actions><a href="{{ $exportUrl('daily') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Date</th><th class="text-right">Orders</th><th class="text-right">Value</th><th class="pl-4 w-1/3">&nbsp;</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($daily as $d)
                <tr>
                    <td class="py-2 text-slate-600">{{ \Illuminate\Support\Carbon::parse($d->d)->format('d M Y') }}</td>
                    <td class="py-2 text-right text-slate-500">{{ number_format($d->n) }}</td>
                    <td class="py-2 text-right font-semibold text-slate-700">{{ $money($d->total) }}</td>
                    <td class="pl-4"><div class="h-2 rounded bg-indigo-100"><div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $d->total / $maxDay * 100)) }}%"></div></div></td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-slate-400">No purchase orders in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection
