@extends('storefront.layout')
@section('title', 'Order confirmed')

@section('content')
@php $symbol = $store->currencySymbol() ?? ''; @endphp

<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-lg border border-slate-200 p-8 text-center">
        <div class="mx-auto h-14 w-14 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-3xl">✓</div>
        <h1 class="mt-4 text-2xl font-bold text-slate-800">Thank you!</h1>
        <p class="mt-1 text-slate-500">Your order <span class="font-semibold text-slate-700">{{ $order->number }}</span> has been received.</p>
        @if ($order->payment_status === 'paid')
            <p class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-green-700 bg-green-50 rounded-full px-3 py-1">✓ Paid{{ $order->payment_reference ? ' · '.\Illuminate\Support\Str::before($order->payment_reference, ' · ') : '' }}</p>
            <p class="mt-2 text-sm text-slate-400">We'll confirm and prepare your order shortly.</p>
        @elseif ($order->payment_status === 'partial')
            <p class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-amber-700 bg-amber-50 rounded-full px-3 py-1">Deposit paid · {{ $symbol }} {{ number_format($order->paid_total, 2) }}</p>
            <p class="mt-2 text-sm text-slate-400">Balance {{ $symbol }} {{ number_format($order->balanceDue(), 2) }} is due on {{ $order->fulfillment_type === 'delivery' ? 'delivery' : 'pickup' }}.</p>
        @else
            <p class="mt-1 text-sm text-slate-400">We'll confirm it shortly. Payment is collected on {{ $order->fulfillment_type === 'delivery' ? 'delivery' : 'pickup' }}.</p>
        @endif
    </div>

    <div class="mt-4 bg-white rounded-lg border border-slate-200 p-6">
        <h2 class="font-semibold text-slate-800 mb-3">Order summary</h2>
        <ul class="text-sm divide-y mb-3">
            @foreach ($order->items as $item)
                <li class="py-2 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-10 w-10 flex-shrink-0 rounded-md bg-slate-100 overflow-hidden flex items-center justify-center text-sm font-semibold text-slate-400">
                            @if ($item->product?->image_path)
                                <img src="{{ asset('storage/'.$item->product->image_path) }}" alt="{{ $item->name }}" class="h-full w-full object-cover">
                            @else
                                {{ strtoupper(substr($item->name, 0, 1)) }}
                            @endif
                        </div>
                        <span class="text-slate-600 truncate">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }} × {{ $item->name }}</span>
                    </div>
                    <span class="text-slate-700 whitespace-nowrap">{{ $symbol }} {{ number_format($item->line_total, 2) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="text-sm space-y-1.5 border-t pt-2">
            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ $symbol }} {{ number_format($order->subtotal - $order->discount_total, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd>{{ $symbol }} {{ number_format($order->tax_total, 2) }}</dd></div>
            @if ($order->fulfillment_type === 'delivery')
                <div class="flex justify-between"><dt class="text-slate-500">{{ $order->shipping_method ?: 'Delivery' }}</dt><dd>{{ $symbol }} {{ number_format($order->delivery_fee, 2) }}</dd></div>
            @endif
            <div class="flex justify-between font-bold text-slate-800 pt-2 border-t"><dt>Total</dt><dd>{{ $symbol }} {{ number_format($order->total, 2) }}</dd></div>
            @if ($order->hasBalance() && $order->paid_total > 0)
                <div class="flex justify-between text-slate-500"><dt>Paid</dt><dd>{{ $symbol }} {{ number_format($order->paid_total, 2) }}</dd></div>
                <div class="flex justify-between font-semibold text-amber-700"><dt>Balance due</dt><dd>{{ $symbol }} {{ number_format($order->balanceDue(), 2) }}</dd></div>
            @endif
        </dl>
        <div class="mt-3 text-sm text-slate-500">
            <span class="capitalize">{{ $order->fulfillment_type }}</span>@if ($order->shipping_method) · {{ $order->shipping_method }}@endif
            @if ($order->fulfillment_type === 'delivery' && $order->address) · {{ $order->address }}@if($order->city), {{ $order->city }}@endif @endif
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="{{ route('shop.home') }}" class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Continue shopping</a>
    </div>
</div>

<script>
    // Meta Purchase (browser). event_id 'order-<id>' matches the server-side
    // Conversions API event so Meta de-duplicates the two into one conversion.
    window.xisMetaTrack && xisMetaTrack('Purchase', {
        content_ids: @js($order->items->pluck('product_id')->filter()->map(fn ($id) => (string) $id)->values()),
        content_type: 'product',
        num_items: {{ (int) $order->items->sum('quantity') }},
        currency: @js($store->currency),
        value: {{ (float) $order->total }},
        order_id: @js((string) $order->number),
    }, 'order-{{ $order->id }}');
</script>
@endsection
