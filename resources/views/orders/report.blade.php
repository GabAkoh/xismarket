@extends('layouts.app')
@section('title', 'Orders report')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $maxDay = $daily->max('total') ?: 1;
    $maxStatus = $statusRows->max('total') ?: 1;
    $exportUrl = fn ($section) => route('orders.report.export', [
        'section' => $section, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
    ]);
@endphp

<x-page-header title="Orders report" subtitle="Online-order totals, status mix and trends for the selected period">
    <a href="{{ $exportUrl('all') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export all (CSV)</a>
    <a href="{{ route('orders.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to orders</a>
</x-page-header>

{{-- Date filter --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('orders.report') }}" class="flex flex-wrap items-end gap-3">
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

{{-- Headline KPIs (net of refunds) --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Net sales</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary['net_after_returns']) }}</p>
        <p class="text-xs text-slate-400 mt-1">ex-tax, after refunds</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Gross profit</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ $money($summary['profit_after_returns']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ number_format($summary['margin_after_returns'], 1) }}% margin · after refunds</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Orders</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary['count']) }}</p>
        <p class="text-xs text-slate-400 mt-1">avg {{ $money($summary['avg']) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Outstanding (unpaid)</p>
        <p class="mt-1 text-2xl font-bold {{ $summary['outstanding'] > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $money($summary['outstanding']) }}</p>
        <p class="text-xs text-slate-400 mt-1">collected {{ $money($summary['paid']) }}</p>
    </div>
</div>

{{-- Orders vs refunds breakdown --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="Orders (this period)">
        <dl class="text-sm space-y-1.5">
            <div class="flex justify-between"><dt class="text-slate-500">Gross</dt><dd class="font-medium text-slate-700">{{ $money($summary['gross']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Discounts</dt><dd class="font-medium text-slate-700">−{{ $money($summary['discounts']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="font-medium text-slate-700">{{ $money($summary['tax']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Delivery income</dt><dd class="font-medium text-slate-700">{{ $money($summary['delivery']) }}</dd></div>
            <div class="flex justify-between border-t border-dashed border-slate-200 pt-1.5"><dt class="text-slate-600">Net revenue</dt><dd class="font-semibold text-slate-800">{{ $money($summary['net']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Cost of goods</dt><dd class="font-medium text-slate-700">−{{ $money($summary['cogs']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Gross profit <span class="text-xs font-normal text-slate-400">({{ number_format($summary['margin'], 1) }}%)</span></dt><dd class="font-semibold text-green-600">{{ $money($summary['profit']) }}</dd></div>
        </dl>
    </x-card>

    <x-card title="Refunds &amp; cancellations">
        <dl class="text-sm space-y-1.5">
            <div class="flex justify-between"><dt class="text-slate-500">Refunded orders</dt><dd class="font-medium text-slate-700">{{ number_format($summary['refund_count']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Revenue reversed</dt><dd class="font-medium text-red-600">−{{ $money($summary['refund_net']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Total refunded</dt><dd class="font-medium text-red-600">−{{ $money($summary['refund_total']) }}</dd></div>
            <div class="flex justify-between border-t border-dashed border-slate-200 pt-1.5"><dt class="text-slate-600">Net sales after refunds</dt><dd class="font-semibold text-slate-800">{{ $money($summary['net_after_returns']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Gross profit after refunds <span class="text-xs font-normal text-slate-400">({{ number_format($summary['margin_after_returns'], 1) }}%)</span></dt><dd class="font-semibold text-green-600">{{ $money($summary['profit_after_returns']) }}</dd></div>
            <div class="flex justify-between border-t border-dashed border-slate-200 pt-1.5"><dt class="text-slate-500">Cancelled orders</dt><dd class="font-medium text-slate-700">{{ number_format($summary['cancelled_count']) }} · {{ $money($summary['cancelled_total']) }}</dd></div>
        </dl>
    </x-card>
</div>

{{-- Status + fulfilment --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="Order status">
        <x-slot:actions><a href="{{ $exportUrl('status') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b"><tr><th class="py-2">Status</th><th class="text-right">Orders</th><th class="text-right">Value</th><th class="pl-4 w-1/4">&nbsp;</th></tr></thead>
            <tbody class="divide-y">
                @forelse ($statusRows as $st)
                    <tr>
                        <td class="py-2 text-slate-700 capitalize">{{ $st->status }}</td>
                        <td class="py-2 text-right text-slate-500">{{ number_format($st->n) }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($st->total) }}</td>
                        <td class="pl-4"><div class="h-2 rounded bg-indigo-100"><div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $st->total / $maxStatus * 100)) }}%"></div></div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-400">No orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Fulfilment">
        <x-slot:actions><a href="{{ $exportUrl('fulfilment') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <tbody class="divide-y">
                @forelse ($fulfilment as $f)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $f->label }}</td>
                        <td class="py-2 text-right text-slate-400 text-xs">{{ number_format($f->n) }} orders</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($f->total) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-400">No orders in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>

{{-- Shipping methods --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="Shipping methods">
        <x-slot:actions><a href="{{ $exportUrl('shipping') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <tbody class="divide-y">
                @forelse ($shipping as $s)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $s->label }}</td>
                        <td class="py-2 text-right text-slate-400 text-xs">{{ number_format($s->n) }} orders</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($s->total) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-400">No shipping methods recorded in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>

{{-- Payment methods + top products --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="Payment methods">
        <x-slot:actions><a href="{{ $exportUrl('methods') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
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

    <x-card title="Top products">
        <x-slot:actions><a href="{{ $exportUrl('products') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b"><tr><th class="py-2">Product</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr></thead>
            <tbody class="divide-y">
                @forelse ($top as $p)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $p->name }}</td>
                        <td class="py-2 text-right text-slate-500">{{ rtrim(rtrim(number_format($p->qty, 3), '0'), '.') }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($p->revenue) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-slate-400">No orders in this period.</td></tr>
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
            <tr><th class="py-2">Date</th><th class="text-right">Orders</th><th class="text-right">Net</th><th class="text-right">Tax</th><th class="text-right">Total</th><th class="pl-4 w-1/3">&nbsp;</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($daily as $d)
                <tr>
                    <td class="py-2 text-slate-600">{{ \Illuminate\Support\Carbon::parse($d->d)->format('d M Y') }}</td>
                    <td class="py-2 text-right text-slate-500">{{ number_format($d->n) }}</td>
                    <td class="py-2 text-right text-slate-600">{{ $money($d->net) }}</td>
                    <td class="py-2 text-right text-slate-500">{{ $money($d->tax) }}</td>
                    <td class="py-2 text-right font-semibold text-slate-700">{{ $money($d->total) }}</td>
                    <td class="pl-4"><div class="h-2 rounded bg-indigo-100"><div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $d->total / $maxDay * 100)) }}%"></div></div></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-400">No orders in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection
