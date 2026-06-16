@extends('layouts.app')
@section('title', 'Delivery '.($delivery->tracking_number ?: $delivery->id))

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $badges = [
        'pending' => 'bg-slate-100 text-slate-600', 'assigned' => 'bg-blue-100 text-blue-700',
        'out_for_delivery' => 'bg-amber-100 text-amber-700', 'delivered' => 'bg-green-100 text-green-700',
        'failed' => 'bg-red-100 text-red-700',
    ];
@endphp

<x-page-header title="Delivery {{ $delivery->tracking_number ?: '#'.$delivery->id }}">
    <a href="{{ route('deliveries.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <x-card title="Details">
            <dl class="text-sm space-y-1.5">
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt>
                    <dd><span class="text-xs px-2 py-0.5 rounded-full {{ $badges[$delivery->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst(str_replace('_',' ',$delivery->status)) }}</span></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Order</dt><dd>
                    @if ($delivery->order && Route::has('orders.show'))
                        <a href="{{ route('orders.show', $delivery->order) }}" class="text-indigo-600 hover:underline">{{ $delivery->order->number }}</a>
                    @else {{ $delivery->order?->number ?? '—' }} @endif
                </dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Recipient</dt><dd>{{ $delivery->recipient_name ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd>{{ $delivery->phone ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Address</dt><dd class="text-right">{{ $delivery->address ?: '—' }}@if($delivery->city), {{ $delivery->city }}@endif</dd></div>
                @if ($delivery->zone)<div class="flex justify-between"><dt class="text-slate-500">Zone</dt><dd>{{ $delivery->zone }}</dd></div>@endif
                <div class="flex justify-between"><dt class="text-slate-500">Driver</dt><dd>{{ $delivery->driver?->name ?? 'Unassigned' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Fee</dt><dd>{{ $symbol }}{{ number_format($delivery->fee, 2) }}</dd></div>
                @if ($delivery->scheduled_for)<div class="flex justify-between"><dt class="text-slate-500">Scheduled</dt><dd>{{ $delivery->scheduled_for->format('d M Y H:i') }}</dd></div>@endif
                @if ($delivery->dispatched_at)<div class="flex justify-between"><dt class="text-slate-500">Dispatched</dt><dd>{{ $delivery->dispatched_at->format('d M Y H:i') }}</dd></div>@endif
                @if ($delivery->delivered_at)<div class="flex justify-between"><dt class="text-slate-500">Delivered</dt><dd>{{ $delivery->delivered_at->format('d M Y H:i') }}</dd></div>@endif
            </dl>
            @if ($delivery->notes)<p class="mt-3 text-sm text-slate-500 border-t pt-3 whitespace-pre-line">{{ $delivery->notes }}</p>@endif
        </x-card>
    </div>

    <div class="space-y-4">
        @permission('deliveries.manage')
            @if ($delivery->isActive())
                <x-card title="Actions">
                    {{-- Assign / change driver --}}
                    <form method="POST" action="{{ route('deliveries.assign', $delivery) }}" class="flex gap-2 mb-3">
                        @csrf
                        <select name="driver_id" required class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
                            <option value="">Select driver…</option>
                            @foreach ($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected($delivery->driver_id === $driver->id)>{{ $driver->name }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Assign</button>
                    </form>

                    @if (in_array($delivery->status, ['assigned']))
                        <form method="POST" action="{{ route('deliveries.dispatch', $delivery) }}" class="mb-2">
                            @csrf
                            <button class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Dispatch (out for delivery)</button>
                        </form>
                    @endif

                    @if ($delivery->status === 'out_for_delivery')
                        <form method="POST" action="{{ route('deliveries.deliver', $delivery) }}" class="mb-2">
                            @csrf
                            <button class="w-full rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Mark delivered</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('deliveries.fail', $delivery) }}"
                          onsubmit="return confirm('Mark this delivery as failed?')">
                        @csrf
                        <input type="text" name="reason" placeholder="Reason (optional)" class="w-full rounded-md border border-slate-300 p-2 text-sm mb-2">
                        <button class="w-full rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Mark failed</button>
                    </form>
                </x-card>
            @else
                <x-card><p class="text-sm text-slate-500">This delivery is {{ str_replace('_',' ',$delivery->status) }} — no further actions.</p></x-card>
            @endif
        @endpermission
    </div>
</div>
@endsection
