@extends('layouts.app')
@section('title', 'Receipt '.$sale->number)

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Receipt {{ $sale->number }}">
    <button onclick="window.print()" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Print</button>
    <a href="{{ route('pos.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New sale</a>
</x-page-header>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-6 print:shadow-none" id="receipt">
    <div class="text-center border-b border-dashed border-slate-200 pb-4 mb-4">
        <h2 class="text-lg font-bold text-slate-800">{{ $currentTenant->name }}</h2>
        <p class="text-xs text-slate-500 mt-1">Receipt {{ $sale->number }}</p>
        <p class="text-xs text-slate-400">{{ optional($sale->completed_at)->format('d M Y H:i') }}</p>
        @if ($sale->status !== 'completed')
            <p class="mt-2 text-xs font-semibold uppercase text-red-600">{{ str_replace('_', ' ', $sale->status) }}</p>
        @endif
    </div>

    <div class="text-xs text-slate-500 mb-3 space-y-0.5">
        <div>Cashier: {{ $sale->user?->name ?? '—' }}</div>
        <div>Customer: {{ $sale->customer?->name ?? 'Walk-in' }}</div>
        @if ($sale->register)<div>Register: {{ $sale->register->name }}</div>@endif
    </div>

    <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
            @foreach ($sale->items as $item)
                <tr>
                    <td class="py-2">
                        <div class="text-slate-700">{{ $item->name }}</div>
                        <div class="text-xs text-slate-400">
                            {{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }} × {{ $symbol }}{{ number_format($item->unit_price, 2) }}
                            @if ($item->discount > 0) · −{{ $symbol }}{{ number_format($item->discount, 2) }}@endif
                        </div>
                    </td>
                    <td class="py-2 text-right text-slate-700">{{ $symbol }}{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="border-t border-dashed border-slate-200 mt-3 pt-3 space-y-1 text-sm">
        <div class="flex justify-between text-slate-500"><span>Subtotal</span><span>{{ $symbol }}{{ number_format($sale->subtotal, 2) }}</span></div>
        <div class="flex justify-between text-slate-500"><span>Discount</span><span>−{{ $symbol }}{{ number_format($sale->discount_total, 2) }}</span></div>
        <div class="flex justify-between text-slate-500"><span>Tax</span><span>{{ $symbol }}{{ number_format($sale->tax_total, 2) }}</span></div>
        <div class="flex justify-between font-bold text-slate-800 text-base"><span>Total</span><span>{{ $symbol }}{{ number_format($sale->total, 2) }}</span></div>
    </div>

    <div class="border-t border-dashed border-slate-200 mt-3 pt-3 space-y-1 text-sm">
        @foreach ($sale->payments as $payment)
            <div class="flex justify-between text-slate-500">
                <span class="capitalize">{{ $payment->method }}</span>
                <span>{{ $symbol }}{{ number_format($payment->amount, 2) }}</span>
            </div>
        @endforeach
        <div class="flex justify-between text-slate-500"><span>Paid</span><span>{{ $symbol }}{{ number_format($sale->paid_total, 2) }}</span></div>
        @if ($sale->balance_due > 0)
            <div class="flex justify-between font-semibold text-red-600"><span>Balance due</span><span>{{ $symbol }}{{ number_format($sale->balance_due, 2) }}</span></div>
        @else
            <div class="flex justify-between text-slate-500"><span>Change</span><span>{{ $symbol }}{{ number_format($sale->change_due, 2) }}</span></div>
        @endif
    </div>

    @if ($sale->customer && ($sale->points_earned || $sale->points_redeemed || $sale->wallet_used > 0))
        <div class="border-t border-dashed border-slate-200 mt-3 pt-3 space-y-1 text-xs text-slate-500">
            @if ($sale->wallet_used > 0)
                <div class="flex justify-between"><span>Paid from wallet</span><span>{{ $symbol }}{{ number_format($sale->wallet_used, 2) }}</span></div>
            @endif
            @if ($sale->points_redeemed)
                <div class="flex justify-between"><span>Points redeemed</span><span>−{{ number_format($sale->points_redeemed) }}</span></div>
            @endif
            @if ($sale->points_earned)
                <div class="flex justify-between text-amber-600"><span>Points earned</span><span>+{{ number_format($sale->points_earned) }}</span></div>
            @endif
            <div class="flex justify-between"><span>Points balance</span><span>{{ number_format($sale->customer->loyalty_points) }}</span></div>
        </div>
    @endif

    <p class="text-center text-xs text-slate-400 mt-6">Thank you for your purchase!</p>
</div>

@push('head')
<style>@media print { aside, header, .print\:hidden { display:none !important; } main { padding:0 !important; } }</style>
@endpush
@endsection
