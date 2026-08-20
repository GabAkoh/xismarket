@extends('layouts.app')
@section('title', $label.' payments')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
    $backUrl = $source === 'pos-receipts'
        ? route('sales.payments-summary', ['from' => $from->toDateString(), 'to' => $to->toDateString()])
        : route('sales.report', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'product_id' => $productId]));
    // Human label for the payment "kind" (checkout vs later settlement vs refund).
    $kindLabel = fn ($k) => match ($k) {
        'sale' => 'Sale',
        'settlement' => 'Settlement',
        'refund' => 'Refund',
        default => ucfirst((string) ($k ?: '—')),
    };
@endphp

<x-page-header title="{{ $label }} payments"
    subtitle="Each sale's {{ $label }} portion for {{ $from->toDateString() }} to {{ $to->toDateString() }}{{ $source === 'pos-receipts' ? ' — POS checkout receipts' : '' }}">
    <a href="{{ $backUrl }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to {{ $source === 'pos-receipts' ? 'payments summary' : 'report' }}</a>
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Date</th>
                <th>Sale</th>
                <th>Customer</th>
                <th>Type</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($payments as $p)
                <tr>
                    <td class="py-2 text-slate-600">
                        {{ $p->at ? \Illuminate\Support\Carbon::parse($p->at)->timezone($tz)->format('d M Y, H:i') : '—' }}
                    </td>
                    <td class="py-2">
                        @if ($p->sale_id)
                            <a href="{{ route('sales.show', $p->sale_id) }}" class="text-indigo-600 hover:underline">{{ $p->number }}</a>
                        @else
                            <span class="text-slate-400">{{ $p->number ?? '—' }}</span>
                        @endif
                    </td>
                    <td class="py-2 text-slate-600">{{ $p->customer ?? 'Walk-in' }}</td>
                    <td class="py-2 text-slate-500">{{ $kindLabel($p->kind) }}</td>
                    <td class="py-2 text-right font-medium {{ $p->amount < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ $money($p->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-400">No {{ $label }} payments in this period.</td></tr>
            @endforelse
        </tbody>
        @if ($payments->isNotEmpty())
            <tfoot>
                <tr class="border-t font-semibold">
                    <td class="py-2" colspan="4">Total {{ $label }} ({{ number_format($payments->count()) }})</td>
                    <td class="py-2 text-right text-slate-800">{{ $money($total) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
    <p class="mt-3 text-xs text-slate-400">
        A sale split across methods contributes only its {{ $label }} portion here, so this total matches the {{ $label }} figure on the {{ $source === 'pos-receipts' ? 'payments summary' : 'report' }}. Refunds appear as negative amounts.
    </p>
</x-card>
@endsection
