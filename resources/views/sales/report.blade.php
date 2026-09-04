@extends('layouts.app')
@section('title', 'Sales report')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $maxDay = $daily->max('total') ?: 1;
    $exportUrl = fn ($section) => route('sales.report.export', [
        'section' => $section, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
        'product_id' => $productId, 'method' => $method,
    ]);
@endphp

<x-page-header title="Sales report" subtitle="Totals, payment mix and trends for the selected period">
    <a href="{{ route('sales.report.export', ['section' => 'all', 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'product_id' => $productId, 'method' => $method]) }}"
       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export all (CSV)</a>
    <a href="{{ route('sales.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to sales</a>
</x-page-header>

{{-- Filters: date range, product and payment method --}}
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
        <div class="w-56">
            <label class="block text-xs font-medium text-slate-500 mb-1">Product</label>
            <x-searchable-select name="product_id" :options="$products" :selected="(string) $productId"
                placeholder="All products" class="p-2 text-sm" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Payment method</label>
            <select name="method" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All methods</option>
                @foreach ($methodOptions as $key => $label)
                    <option value="{{ $key }}" @selected($method === (string) $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
        @if ($filtered)
            <a href="{{ route('sales.report', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Clear filters</a>
        @endif
    </form>
    @if ($filtered)
        @php
            $activeProduct = $productId ? optional($products->firstWhere('id', $productId))->name : null;
            $activeMethod = $method ? ($methodOptions[$method] ?? $method) : null;
        @endphp
        <p class="mt-3 text-xs text-slate-500">
            Showing sales
            @if ($activeProduct) that include <span class="font-semibold text-slate-700">{{ $activeProduct }}</span>@endif
            @if ($activeProduct && $activeMethod) and @endif
            @if ($activeMethod) paid with <span class="font-semibold text-slate-700">{{ $activeMethod }}</span>@endif.
        </p>
        <p class="mt-1 text-xs text-slate-400">
            @if ($revenueSliced && $revenueApportioned)
                Revenue and profit reflect <span class="font-medium text-slate-500">{{ $activeProduct }}</span>’s lines, apportioned to each sale’s <span class="font-medium text-slate-500">{{ $activeMethod }}</span> share.
            @elseif ($revenueSliced)
                Revenue, profit and the daily / cashier / register figures reflect <span class="font-medium text-slate-500">{{ $activeProduct }}</span>’s lines only.
            @elseif ($revenueApportioned)
                Revenue and profit are apportioned to each sale’s <span class="font-medium text-slate-500">{{ $activeMethod }}</span> share (split payments counted pro-rata).
            @endif
            @if ($paymentSliced)
                Collected and the payment mix reflect <span class="font-medium text-slate-500">{{ $activeMethod }}</span>.
            @elseif ($revenueSliced)
                Collected, outstanding and the payment mix are marked <span class="font-medium text-slate-500">whole invoices</span> — they can’t be split by product and cover the full matching sales.
            @endif
        </p>
    @endif
</x-card>
@php
    // A figure family isn't reflected by the active filter → mark it "whole
    // invoices". Revenue now reflects any filter (product → lines, method →
    // apportioned), so it's never whole once filtered; payment receipts still
    // can't be split by a product.
    $revenueWhole = $filtered && ! $revenueSliced && ! $revenueApportioned;
    $paymentWhole = $filtered && ! $paymentSliced;   // product-only filter
    $wholeTag = '<span class="ml-1 text-xs font-normal text-slate-400">· whole invoices</span>';
@endphp

{{-- Headline KPIs (net of returns) --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Net sales</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ $money($summary['net_after_returns']) }}</p>
        <p class="text-xs text-slate-400 mt-1">ex-tax, after returns{!! $revenueWhole ? $wholeTag : '' !!}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Gross profit</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ $money($summary['profit_after_returns']) }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ number_format($summary['margin_after_returns'], 1) }}% margin · after returns{!! $revenueWhole ? $wholeTag : '' !!}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Sales</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary['count']) }}</p>
        <p class="text-xs text-slate-400 mt-1">avg {{ $money($summary['avg']) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Outstanding (credit)</p>
        <p class="mt-1 text-2xl font-bold {{ $summary['outstanding'] > 0 ? 'text-amber-600' : 'text-slate-800' }}">{{ $money($summary['outstanding']) }}</p>
        <p class="text-xs text-slate-400 mt-1">collected {{ $money($summary['collected']) }}{!! $paymentWhole ? $wholeTag : '' !!}</p>
    </div>
</div>

{{-- Sales vs returns breakdown --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="Sales (this period)">
        @if ($revenueWhole)<p class="mb-2 text-xs text-slate-400">Whole invoices — revenue can’t be split by payment method.</p>@endif
        <dl class="text-sm space-y-1.5">
            <div class="flex justify-between"><dt class="text-slate-500">Gross</dt><dd class="font-medium text-slate-700">{{ $money($summary['gross']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Discounts</dt><dd class="font-medium text-slate-700">−{{ $money($summary['discounts']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd class="font-medium text-slate-700">{{ $money($summary['tax']) }}</dd></div>
            <div class="flex justify-between border-t border-dashed border-slate-200 pt-1.5"><dt class="text-slate-600">Net revenue</dt><dd class="font-semibold text-slate-800">{{ $money($summary['net']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Cost of goods</dt><dd class="font-medium text-slate-700">−{{ $money($summary['cogs']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Gross profit <span class="text-xs font-normal text-slate-400">({{ number_format($summary['margin'], 1) }}%)</span></dt><dd class="font-semibold text-green-600">{{ $money($summary['profit']) }}</dd></div>
        </dl>
    </x-card>

    <x-card title="Returns (this period)">
        @if ($filtered)
        <p class="text-sm text-slate-400 py-6 text-center">Returns aren’t broken down by product or payment method, so they’re shown only in the unfiltered report.</p>
        @else
        <dl class="text-sm space-y-1.5">
            <div class="flex justify-between"><dt class="text-slate-500">Revenue reversed</dt><dd class="font-medium text-red-600">−{{ $money($summary['returns_net']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tax reversed</dt><dd class="font-medium text-red-600">−{{ $money($summary['returns_tax']) }}</dd></div>
            <div class="flex justify-between border-t border-dashed border-slate-200 pt-1.5"><dt class="text-slate-600">Total refunded</dt><dd class="font-semibold text-red-600">−{{ $money($summary['returns_total']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Cost of goods recovered</dt><dd class="font-medium text-slate-700">+{{ $money($summary['returns_cogs']) }}</dd></div>
            <div class="flex justify-between border-t border-dashed border-slate-200 pt-1.5"><dt class="text-slate-600">Net sales after returns</dt><dd class="font-semibold text-slate-800">{{ $money($summary['net_after_returns']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-600">Gross profit after returns <span class="text-xs font-normal text-slate-400">({{ number_format($summary['margin_after_returns'], 1) }}%)</span></dt><dd class="font-semibold text-green-600">{{ $money($summary['profit_after_returns']) }}</dd></div>
        </dl>
        @endif
    </x-card>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Payment mix --}}
    <x-card title="Payment methods">
        <x-slot:actions><a href="{{ $exportUrl('methods') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        @if ($paymentWhole)<p class="mb-2 text-xs text-slate-400">Whole invoices — payments can’t be split by product.</p>@endif
        <table class="w-full text-sm">
            <tbody class="divide-y">
                @forelse ($methods as $m)
                    @php
                        $methodBreakdownUrl = route('sales.report.method-breakdown', array_filter([
                            'method' => $m->method,
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                            'product_id' => $productId,
                        ]));
                    @endphp
                    <tr>
                        <td class="py-2 text-slate-700">{{ $m->label }}</td>
                        <td class="py-2 text-right text-slate-400 text-xs">{{ number_format($m->n) }}×</td>
                        <td class="py-2 text-right font-semibold">
                            <a href="{{ $methodBreakdownUrl }}" class="text-indigo-600 hover:underline"
                               title="Break down the {{ $m->label }} payments in this period">{{ $money($m->amount) }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td class="py-4 text-center text-slate-400">No payments in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($creditExtended)
            {{-- Kept out of the method mix above: credit is a receivable raised at
                 the point of sale, not money received. Shows up as a settlement
                 (under its actual tender) once the customer pays it down. --}}
            <div class="mt-3 border-t border-dashed pt-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">On credit <span class="text-xs text-slate-400">(receivable, not cash)</span></span>
                    <span class="flex items-center gap-3">
                        <span class="text-slate-400 text-xs">{{ number_format($creditExtended->n) }}×</span>
                        <span class="font-semibold text-amber-600">{{ $money($creditExtended->amount) }}</span>
                    </span>
                </div>
            </div>
        @endif
    </x-card>

    {{-- Top products --}}
    <x-card title="Top products">
        <x-slot:actions><a href="{{ $exportUrl('products') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
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

{{-- Sales by cashier / register --}}
@php
    $maxCashier = $cashiers->max('total') ?: 1;
    $maxRegister = $registers->max('total') ?: 1;
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-card title="Sales by cashier">
        <x-slot:actions><a href="{{ $exportUrl('cashiers') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        @if ($revenueWhole)<p class="mb-2 text-xs text-slate-400">Whole invoices — revenue can’t be split by payment method.</p>@endif
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b">
                <tr>
                    <th class="py-2">Cashier</th><th class="text-right">Sales</th>
                    <th class="text-right">Net</th><th class="text-right">Total</th>
                    <th class="pl-4 w-1/4">&nbsp;</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($cashiers as $c)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $c->name }}</td>
                        <td class="py-2 text-right text-slate-500">{{ number_format($c->n) }}</td>
                        <td class="py-2 text-right text-slate-600">{{ $money($c->net) }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($c->total) }}</td>
                        <td class="pl-4">
                            <div class="h-2 rounded bg-indigo-100">
                                <div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $c->total / $maxCashier * 100)) }}%"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">No sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <x-card title="Sales by register">
        <x-slot:actions><a href="{{ $exportUrl('registers') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
        @if ($revenueWhole)<p class="mb-2 text-xs text-slate-400">Whole invoices — revenue can’t be split by payment method.</p>@endif
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b">
                <tr>
                    <th class="py-2">Register</th><th class="text-right">Sales</th>
                    <th class="text-right">Net</th><th class="text-right">Total</th>
                    <th class="pl-4 w-1/4">&nbsp;</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($registers as $r)
                    <tr>
                        <td class="py-2 text-slate-700">{{ $r->name }}</td>
                        <td class="py-2 text-right text-slate-500">{{ number_format($r->n) }}</td>
                        <td class="py-2 text-right text-slate-600">{{ $money($r->net) }}</td>
                        <td class="py-2 text-right font-semibold text-slate-700">{{ $money($r->total) }}</td>
                        <td class="pl-4">
                            <div class="h-2 rounded bg-indigo-100">
                                <div class="h-2 rounded bg-indigo-500" style="width: {{ max(2, round((float) $r->total / $maxRegister * 100)) }}%"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">No sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>

{{-- Daily trend --}}
<x-card title="Daily breakdown">
    <x-slot:actions><a href="{{ $exportUrl('daily') }}" class="text-xs text-indigo-600 hover:underline">Export CSV</a></x-slot:actions>
    @if ($revenueWhole)<p class="mb-2 text-xs text-slate-400">Whole invoices — revenue can’t be split by payment method.</p>@endif
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
