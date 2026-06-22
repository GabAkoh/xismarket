@extends('layouts.app')
@section('title', 'Order '.$order->number)

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $statusBadge = [
        'pending' => 'bg-slate-100 text-slate-600', 'confirmed' => 'bg-blue-100 text-blue-700',
        'preparing' => 'bg-indigo-100 text-indigo-700', 'ready' => 'bg-indigo-100 text-indigo-700',
        'dispatched' => 'bg-amber-100 text-amber-700', 'delivered' => 'bg-teal-100 text-teal-700',
        'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-red-100 text-red-700',
    ][$order->status] ?? 'bg-slate-100 text-slate-600';
    $payBadge = ['paid' => 'bg-green-100 text-green-700', 'refunded' => 'bg-red-100 text-red-700'][$order->payment_status] ?? 'bg-amber-100 text-amber-700';
@endphp

<x-page-header title="Order {{ $order->number }}">
    @if ($order->customer?->email)
        <form method="POST" action="{{ route('orders.email-receipt', $order) }}">
            @csrf
            <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">✉ Email receipt</button>
        </form>
    @endif
    <a href="{{ route('orders.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <x-card title="Items">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400 border-b">
                    <tr><th class="py-2">Product</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Disc</th><th class="text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="py-2"><div class="text-slate-700">{{ $item->name }}</div><div class="text-xs text-slate-400">{{ $item->sku }}</div></td>
                            <td class="text-right text-slate-600">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                            <td class="text-right text-slate-600">{{ $symbol }}{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-right text-slate-600">{{ $symbol }}{{ number_format($item->discount, 2) }}</td>
                            <td class="text-right font-medium text-slate-700">{{ $symbol }}{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="text-sm">
                    <tr><td colspan="4" class="pt-3 text-right text-slate-500">Subtotal</td><td class="pt-3 text-right">{{ $symbol }}{{ number_format($order->subtotal, 2) }}</td></tr>
                    <tr><td colspan="4" class="text-right text-slate-500">Discount</td><td class="text-right">−{{ $symbol }}{{ number_format($order->discount_total, 2) }}</td></tr>
                    <tr><td colspan="4" class="text-right text-slate-500">Tax</td><td class="text-right">{{ $symbol }}{{ number_format($order->tax_total, 2) }}</td></tr>
                    @if ($order->fulfillment_type === 'delivery')
                        <tr><td colspan="4" class="text-right text-slate-500">{{ $order->shipping_method ?: 'Delivery fee' }}</td><td class="text-right">{{ $symbol }}{{ number_format($order->delivery_fee, 2) }}</td></tr>
                    @endif
                    <tr class="font-bold text-slate-800"><td colspan="4" class="text-right">Total</td><td class="text-right">{{ $symbol }}{{ number_format($order->total, 2) }}</td></tr>
                </tfoot>
            </table>
            @if ($order->notes)<p class="mt-3 text-sm text-slate-500 border-t pt-3">{{ $order->notes }}</p>@endif
        </x-card>

        @if ($order->fulfillment_type === 'delivery')
            <x-card title="Delivery">
                @if ($order->delivery)
                    <div class="flex items-center justify-between text-sm">
                        <div>
                            <div class="text-slate-700 font-medium">{{ $order->delivery->tracking_number }}</div>
                            <div class="text-slate-500 capitalize">{{ str_replace('_', ' ', $order->delivery->status) }}</div>
                        </div>
                        @if (Route::has('deliveries.show'))
                            <a href="{{ route('deliveries.show', $order->delivery) }}" class="text-indigo-600 hover:underline">View delivery →</a>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-slate-500 mb-3">No delivery created yet.</p>
                    @if (Route::has('deliveries.create'))
                        <a href="{{ route('deliveries.create', ['order' => $order->id]) }}" class="inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create delivery</a>
                    @endif
                @endif
            </x-card>
        @endif
    </div>

    <div class="space-y-4">
        <x-card title="Status">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs px-2 py-0.5 rounded-full capitalize {{ $statusBadge }}">{{ str_replace('_', ' ', $order->status) }}</span>
                <span class="text-xs px-2 py-0.5 rounded-full capitalize {{ $payBadge }}">{{ $order->payment_status }}</span>
            </div>
            @if ($order->payment_status === 'paid' && $order->payment_method)
                <p class="text-xs text-slate-500 mb-3">Paid by <span class="font-medium capitalize">{{ $order->payment_method }}</span>@if($order->payment_reference) · {{ $order->payment_reference }}@endif @if($order->paid_at)<span class="text-slate-400">({{ $order->paid_at->format('d M Y H:i') }})</span>@endif</p>
            @endif

            @if (! $order->isCompleted() && ! $order->isCancelled())
                @permission('orders.manage')
                    <form method="POST" action="{{ route('orders.status', $order) }}" class="flex gap-2 mb-3">
                        @csrf
                        <select name="status" class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
                            @foreach (['confirmed','preparing','ready','dispatched','delivered'] as $s)
                                <option value="{{ $s }}" @selected($order->status === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Update</button>
                    </form>
                @endpermission

                @permission('orders.manage')
                    @unless ($order->isPaid())
                        <form method="POST" action="{{ route('orders.pay', $order) }}" class="mb-2">
                            @csrf
                            <button class="w-full rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Mark as paid</button>
                        </form>
                    @endunless
                @endpermission

                @permission('orders.fulfill')
                    <form method="POST" action="{{ route('orders.fulfill', $order) }}" class="mb-2"
                          onsubmit="return confirm('Fulfil this order? Stock will be deducted and the sale posted to the books.')">
                        @csrf
                        <button class="w-full rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-40"
                                @disabled(! $order->isPaid())>Fulfil order</button>
                    </form>
                    @unless ($order->isPaid())<p class="text-xs text-amber-600 mb-2">Mark the order paid before fulfilling.</p>@endunless

                    <form method="POST" action="{{ route('orders.cancel', $order) }}"
                          onsubmit="return confirm('Cancel this order?')">
                        @csrf
                        <button class="w-full rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Cancel order</button>
                    </form>
                @endpermission
            @elseif ($order->isCompleted())
                <p class="text-sm text-green-700">✓ Fulfilled on {{ optional($order->completed_at)->format('d M Y H:i') }}.</p>
            @endif

            {{-- Refund: available for any paid order (reverses stock + books if it was fulfilled) --}}
            @permission('orders.fulfill')
                @if ($order->isPaid())
                    <form method="POST" action="{{ route('orders.refund', $order) }}" class="mt-2"
                          onsubmit="return confirm('Refund this order? {{ $order->isCompleted() ? 'Stock will be restored and the sale reversed in the books.' : 'The payment will be reversed.' }}')">
                        @csrf
                        <button class="w-full rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Refund order</button>
                    </form>
                @elseif ($order->payment_status === 'refunded')
                    <p class="mt-2 text-sm text-red-600">↩ Refunded.</p>
                @endif
            @endpermission
        </x-card>

        <x-card title="Details">
            <dl class="text-sm space-y-1.5">
                <div class="flex justify-between"><dt class="text-slate-500">Channel</dt><dd class="capitalize">{{ $order->channel }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Fulfilment</dt><dd class="capitalize">{{ $order->fulfillment_type }}</dd></div>
                @if ($order->shipping_method)
                    <div class="flex justify-between"><dt class="text-slate-500">Shipping method</dt><dd>{{ $order->shipping_method }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-slate-500">Placed</dt><dd>{{ optional($order->placed_at)->format('d M Y H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Customer</dt><dd>{{ $order->customer?->name ?? 'Guest' }}</dd></div>
            </dl>
        </x-card>

        @if ($order->contact_name || $order->address)
            <x-card title="Contact">
                <div class="text-sm text-slate-600 space-y-0.5">
                    @if ($order->contact_name)<div>{{ $order->contact_name }}</div>@endif
                    @if ($order->contact_phone)<div>{{ $order->contact_phone }}</div>@endif
                    @if ($order->address)<div>{{ $order->address }}@if($order->city), {{ $order->city }}@endif</div>@endif
                </div>
            </x-card>
        @endif
    </div>
</div>
@endsection
