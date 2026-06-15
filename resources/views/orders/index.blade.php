@extends('layouts.app')
@section('title', 'Online Orders')

@section('content')
@php
    $symbol = $currentTenant->currency ?? '';
    $statusBadge = fn ($s) => [
        'pending' => 'bg-slate-100 text-slate-600',
        'confirmed' => 'bg-blue-100 text-blue-700',
        'preparing' => 'bg-indigo-100 text-indigo-700',
        'ready' => 'bg-purple-100 text-purple-700',
        'dispatched' => 'bg-cyan-100 text-cyan-700',
        'delivered' => 'bg-teal-100 text-teal-700',
        'completed' => 'bg-green-100 text-green-700',
        'cancelled' => 'bg-red-100 text-red-700',
    ][$s] ?? 'bg-slate-100 text-slate-600';
    $payBadge = fn ($p) => [
        'paid' => 'bg-green-100 text-green-700',
        'unpaid' => 'bg-amber-100 text-amber-700',
        'refunded' => 'bg-red-100 text-red-700',
    ][$p] ?? 'bg-slate-100 text-slate-600';
@endphp

<x-page-header title="Online Orders">
    @permission('orders.manage')
        <a href="{{ route('orders.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New order</a>
    @endpermission
</x-page-header>

@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
@endif

<x-card class="mb-4">
    <form method="GET" action="{{ route('orders.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Search #</label>
            <input name="q" value="{{ request('q') }}" placeholder="ORD-…" class="w-full rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select name="status" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Payment</label>
            <select name="payment_status" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All</option>
                @foreach ($paymentStatuses as $ps)
                    <option value="{{ $ps }}" @selected(request('payment_status') === $ps)>{{ ucfirst($ps) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
            <a href="{{ route('orders.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Reset</a>
        </div>
    </form>
</x-card>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Number</th><th>Date</th><th>Customer</th><th>Fulfilment</th>
                <th>Status</th><th>Payment</th><th class="text-right">Total</th><th></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($orders as $order)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $order->number }}</td>
                    <td class="text-slate-500">{{ optional($order->placed_at)->format('d M Y H:i') }}</td>
                    <td class="text-slate-500">{{ $order->customer?->name ?? $order->contact_name ?? '—' }}</td>
                    <td class="text-slate-500 capitalize">{{ $order->fulfillment_type }}</td>
                    <td>
                        <span class="text-xs px-2 py-0.5 rounded-full capitalize {{ $statusBadge($order->status) }}">{{ str_replace('_', ' ', $order->status) }}</span>
                    </td>
                    <td>
                        <span class="text-xs px-2 py-0.5 rounded-full capitalize {{ $payBadge($order->payment_status) }}">{{ $order->payment_status }}</span>
                    </td>
                    <td class="text-right font-semibold text-slate-700">{{ $symbol }}{{ number_format($order->total, 2) }}</td>
                    <td class="text-right">
                        <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:underline">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-400">No orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-card>
@endsection
