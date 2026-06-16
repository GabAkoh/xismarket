@extends('layouts.app')
@section('title', 'Accounts Receivable')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $labels = ['current' => 'Current (0–30)', 'd31_60' => '31–60 days', 'd61_90' => '61–90 days', 'd90' => 'Over 90 days'];
    $reconciles = abs($grandTotal - $arBalance) < 0.01;
@endphp

<x-page-header title="Accounts Receivable" subtitle="Outstanding customer balances by age">
    <button onclick="window.print()" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Print</button>
    <a href="{{ route('reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to reports</a>
</x-page-header>

{{-- Summary tiles --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    @foreach ($labels as $key => $label)
        <div class="bg-white rounded-lg shadow-sm p-4">
            <p class="text-xs text-slate-500">{{ $label }}</p>
            <p class="mt-1 text-lg font-bold {{ $key === 'd90' && $totalsByBucket[$key] > 0 ? 'text-red-600' : 'text-slate-800' }}">
                {{ $symbol }} {{ number_format($totalsByBucket[$key], 2) }}
            </p>
        </div>
    @endforeach
    <div class="bg-indigo-600 rounded-lg shadow-sm p-4 text-white">
        <p class="text-xs text-indigo-100">Total outstanding</p>
        <p class="mt-1 text-lg font-bold">{{ $symbol }} {{ number_format($grandTotal, 2) }}</p>
    </div>
</div>

{{-- Aging summary by customer --}}
<x-card title="Aging by customer" class="mb-6">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Customer</th>
                <th class="text-right">Current</th>
                <th class="text-right">31–60</th>
                <th class="text-right">61–90</th>
                <th class="text-right">90+</th>
                <th class="text-right">Total due</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($customers as $row)
                <tr>
                    <td class="py-3 font-medium text-slate-700">
                        @if ($row['customer'])
                            @permission('customers.view')
                                <a href="{{ route('customers.show', $row['customer']) }}" class="text-indigo-600 hover:underline">{{ $row['customer']->name }}</a>
                                <a href="{{ route('customers.statement', $row['customer']) }}" class="ml-2 text-xs text-slate-400 hover:underline">statement</a>
                            @else
                                {{ $row['customer']->name }}
                            @endpermission
                            @if ($row['customer']->identity_number)<span class="block text-xs font-normal text-slate-400">ID: {{ $row['customer']->identity_number }}</span>@endif
                        @else
                            <span class="text-slate-400">Unknown customer</span>
                        @endif
                    </td>
                    <td class="text-right tabular-nums text-slate-600">{{ $row['buckets']['current'] > 0 ? number_format($row['buckets']['current'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums text-slate-600">{{ $row['buckets']['d31_60'] > 0 ? number_format($row['buckets']['d31_60'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums text-slate-600">{{ $row['buckets']['d61_90'] > 0 ? number_format($row['buckets']['d61_90'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums {{ $row['buckets']['d90'] > 0 ? 'text-red-600 font-medium' : 'text-slate-600' }}">{{ $row['buckets']['d90'] > 0 ? number_format($row['buckets']['d90'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums font-semibold text-slate-800">{{ $symbol }} {{ number_format($row['total'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-400">🎉 No outstanding balances — every sale is paid in full.</td></tr>
            @endforelse
        </tbody>
        @if ($customers->isNotEmpty())
            <tfoot>
                <tr class="font-semibold text-slate-800 border-t">
                    <td class="py-3">Totals</td>
                    <td class="text-right tabular-nums">{{ number_format($totalsByBucket['current'], 2) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($totalsByBucket['d31_60'], 2) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($totalsByBucket['d61_90'], 2) }}</td>
                    <td class="text-right tabular-nums">{{ number_format($totalsByBucket['d90'], 2) }}</td>
                    <td class="text-right tabular-nums">{{ $symbol }} {{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-card>

{{-- Invoice-level detail --}}
@if ($customers->isNotEmpty())
    <x-card title="Outstanding invoices">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b">
                <tr>
                    <th class="py-2">Sale</th><th>Customer</th><th>Date</th>
                    <th class="text-right">Age (days)</th><th class="text-right">Total</th>
                    <th class="text-right">Paid</th><th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($customers as $row)
                    @foreach ($row['items'] as $it)
                        <tr>
                            <td class="py-2 font-medium text-slate-700">
                                @permission('sales.view')
                                    <a href="{{ route('sales.show', $it['id']) }}" class="text-indigo-600 hover:underline">{{ $it['number'] }}</a>
                                @else
                                    {{ $it['number'] }}
                                @endpermission
                            </td>
                            <td class="text-slate-500">{{ $row['customer']?->name ?? '—' }}</td>
                            <td class="text-slate-500">{{ $it['date']->format('d M Y') }}</td>
                            <td class="text-right tabular-nums {{ $it['days'] > 90 ? 'text-red-600 font-medium' : 'text-slate-600' }}">{{ $it['days'] }}</td>
                            <td class="text-right tabular-nums text-slate-600">{{ number_format($it['total'], 2) }}</td>
                            <td class="text-right tabular-nums text-slate-600">{{ number_format($it['paid'], 2) }}</td>
                            <td class="text-right tabular-nums font-semibold text-slate-800">{{ $symbol }} {{ number_format($it['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </x-card>
@endif

{{-- Reconciliation note --}}
<p class="mt-4 text-xs {{ $reconciles ? 'text-slate-400' : 'text-red-500' }}">
    @if ($reconciles)
        ✓ Reconciled with the Accounts Receivable control account (1200): {{ $symbol }} {{ number_format($arBalance, 2) }}.
    @else
        ⚠ Outstanding total ({{ $symbol }} {{ number_format($grandTotal, 2) }}) does not match the A/R control account 1200 ({{ $symbol }} {{ number_format($arBalance, 2) }}).
    @endif
</p>

@push('head')
<style>@media print { aside, header, .print\:hidden, button { display:none !important; } main { padding:0 !important; } }</style>
@endpush
@endsection
