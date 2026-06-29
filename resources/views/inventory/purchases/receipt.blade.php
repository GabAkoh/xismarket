@extends('layouts.app')
@section('title', 'Purchase order '.$purchase->reference)

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Purchase order {{ $purchase->reference }}">
    <button onclick="window.print()" class="rounded-md border border-slate-300 px-4 py-2 text-sm print:hidden">Print</button>
    <a href="{{ route('purchases.show', $purchase) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 print:hidden">Back</a>
</x-page-header>

<div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-6 print:shadow-none" id="receipt">
    <div class="text-center border-b border-dashed border-slate-200 pb-4 mb-4">
        @php $logo = $currentTenant->setting('storefront.logo'); @endphp
        @if ($logo)
            <img src="{{ asset('storage/'.$logo) }}" alt="{{ $currentTenant->name }}" class="h-12 w-auto object-contain mx-auto mb-1">
        @else
            <h2 class="text-lg font-bold text-slate-800">{{ $currentTenant->name }}</h2>
        @endif
        @if ($currentTenant->address || $currentTenant->phone)
            <p class="text-xs text-slate-500 mt-1">
                @if ($currentTenant->address){{ $currentTenant->address }}@endif
                @if ($currentTenant->address && $currentTenant->phone) · @endif
                @if ($currentTenant->phone){{ $currentTenant->phone }}@endif
            </p>
        @endif
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 mt-2">Purchase Order</p>
        <p class="text-xs text-slate-500">{{ $purchase->reference }}</p>
        <p class="text-xs text-slate-400">{{ optional($purchase->order_date ?? $purchase->created_at)->format('d M Y') }}</p>
        @if ($purchase->isReceived())
            <p class="mt-2 text-xs font-semibold uppercase text-green-600">Received {{ optional($purchase->received_at)->format('d M Y') }}</p>
        @else
            <p class="mt-2 text-xs font-semibold uppercase text-amber-600">Draft</p>
        @endif
    </div>

    <div class="text-xs text-slate-500 mb-3 space-y-0.5">
        <div>Supplier: {{ $purchase->supplier?->name ?? '—' }}</div>
        <div>Warehouse: {{ $purchase->warehouse?->name ?? '—' }}</div>
    </div>

    <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
            @foreach ($purchase->items as $item)
                <tr>
                    <td class="py-2">
                        <div class="text-slate-700">{{ $item->product?->name ?? '—' }}</div>
                        <div class="text-xs text-slate-400">
                            {{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} × {{ $symbol }}{{ number_format((float) $item->unit_cost, 2) }}
                        </div>
                    </td>
                    <td class="py-2 text-right text-slate-700">{{ $symbol }}{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="border-t border-dashed border-slate-200 mt-3 pt-3 space-y-1 text-sm">
        <div class="flex justify-between font-bold text-slate-800 text-base"><span>Total</span><span>{{ $symbol }}{{ number_format((float) $purchase->total, 2) }}</span></div>
    </div>

    @if ($purchase->note)
        <div class="border-t border-dashed border-slate-200 mt-3 pt-3 text-xs text-slate-500">{{ $purchase->note }}</div>
    @endif

    <p class="text-center text-xs text-slate-400 mt-6">Purchase order · {{ $currentTenant->name }}</p>
</div>

@php
    $receiptWidth = in_array((int) $currentTenant->setting('pos.receipt_width', 80), [58, 80], true)
        ? (int) $currentTenant->setting('pos.receipt_width', 80) : 80;
@endphp

@push('head')
<style>
@media print {
    @page { size: {{ $receiptWidth }}mm auto; margin: 0; }
    html, body { background: #fff !important; }
    aside, header, .print\:hidden { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; }
    #receipt {
        width: {{ $receiptWidth }}mm !important;
        max-width: {{ $receiptWidth }}mm !important;
        margin: 0 !important;
        padding: 2mm 3mm !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        font-size: {{ $receiptWidth === 58 ? '10' : '11' }}px;
        line-height: 1.3;
    }
    #receipt * { color: #000 !important; }
}
</style>
@endpush

{{-- Opening this page is the "print" action: send straight to the default printer. --}}
@push('scripts')
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
@endpush
@endsection
