@extends('storefront.layout')
@section('title', 'My account')

@section('content')
@php $symbol = $store->currencySymbol() ?? ''; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Hello, {{ $customer->name }}</h1>
        <p class="text-sm text-slate-500">{{ $customer->email }}</p>
    </div>
    <form method="POST" action="{{ route('shop.logout') }}">
        @csrf
        <button class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Sign out</button>
    </form>
</div>

@if ((float) $customer->balance > 0 || (int) $customer->loyalty_points > 0)
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Store credit</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">{{ $symbol }} {{ number_format((float) $customer->balance, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">Loyalty points</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ number_format((int) $customer->loyalty_points) }}</p>
        </div>
    </div>
@endif

<h2 class="text-lg font-semibold text-slate-800 mb-3">Your orders</h2>
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2 px-4">Order</th><th>Date</th><th>Status</th><th class="text-right px-4">Total</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($orders as $order)
                <tr>
                    <td class="py-3 px-4 font-medium text-slate-700">{{ $order->number }}</td>
                    <td class="text-slate-500">{{ optional($order->placed_at)->format('d M Y') }}</td>
                    <td><span class="text-xs rounded-full bg-slate-100 px-2 py-0.5 text-slate-600 capitalize">{{ str_replace('_', ' ', $order->status) }}</span></td>
                    <td class="text-right px-4 text-slate-700">{{ $symbol }} {{ number_format((float) $order->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-slate-400">No orders yet — <a href="{{ route('shop.home') }}" class="text-indigo-600 hover:underline">start shopping</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
