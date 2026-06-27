@extends('storefront.layout')
@section('title', 'Cart')

@section('content')
@php $symbol = $store->currencySymbol() ?? ''; @endphp

<h1 class="text-2xl font-bold text-slate-800 mb-5">Your cart</h1>

@if (empty($lines))
    <div class="bg-white rounded-lg border border-slate-200 p-12 text-center">
        <p class="text-slate-400 mb-4">Your cart is empty.</p>
        <a href="{{ route('shop.home') }}" class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Browse products</a>
    </div>
@else
    @php $hasOutOfStock = collect($lines)->contains(fn ($l) => $l['product']->isOutOfStock()); @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-lg border border-slate-200 divide-y">
            @foreach ($lines as $line)
                <div class="p-4 flex items-center gap-4">
                    <div class="h-14 w-14 rounded bg-slate-100 flex items-center justify-center text-slate-300 font-bold shrink-0">{{ strtoupper(substr($line['product']->name, 0, 1)) }}</div>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('shop.product', ['product' => $line['product']->id]) }}" class="font-medium text-slate-700 hover:text-indigo-600">{{ $line['product']->name }}</a>
                        <div class="text-sm text-slate-400">{{ $symbol }} {{ number_format($line['unit_price'], 2) }} each</div>
                        @if ($line['product']->isOutOfStock())
                            <span class="mt-0.5 inline-block rounded bg-rose-100 px-1.5 py-0.5 text-[11px] font-semibold text-rose-600">Out of stock — remove to checkout</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('shop.cart.update') }}" class="flex items-center gap-1">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $line['product']->id }}">
                        <input type="number" name="qty" value="{{ $line['qty'] }}" min="0" max="999" class="w-16 rounded-md border border-slate-300 p-1.5 text-sm text-center">
                        <button class="text-xs text-indigo-600 hover:underline px-1">Update</button>
                    </form>
                    <div class="w-24 text-right font-semibold text-slate-700">{{ $symbol }} {{ number_format($line['line_total'], 2) }}</div>
                    <form method="POST" action="{{ route('shop.cart.remove') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $line['product']->id }}">
                        <button class="text-slate-300 hover:text-red-500" title="Remove">✕</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-lg border border-slate-200 p-5 h-fit">
            <h2 class="font-semibold text-slate-800 mb-3">Summary</h2>
            <dl class="text-sm space-y-1.5">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ $symbol }} {{ number_format($totals['subtotal'], 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd>{{ $symbol }} {{ number_format($totals['tax'], 2) }}</dd></div>
                <div class="flex justify-between font-bold text-slate-800 pt-2 border-t"><dt>Total</dt><dd>{{ $symbol }} {{ number_format($totals['total'], 2) }}</dd></div>
            </dl>
            <p class="text-xs text-slate-400 mt-1">Delivery fee (if any) added at checkout.</p>
            @if ($hasOutOfStock)
                <button type="button" disabled class="mt-4 block w-full text-center rounded-md bg-slate-100 px-5 py-2.5 text-sm font-semibold text-slate-400 cursor-not-allowed">Checkout</button>
                <p class="mt-2 text-xs text-rose-500">Remove out-of-stock items to checkout.</p>
            @else
                <a href="{{ route('shop.checkout') }}" class="mt-4 block text-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Checkout</a>
            @endif
            <a href="{{ route('shop.home') }}" class="mt-2 block text-center text-sm text-slate-500 hover:underline">Continue shopping</a>
        </div>
    </div>
@endif
@endsection
