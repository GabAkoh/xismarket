@extends('layouts.app')
@section('title', 'New Delivery')

@section('content')
@php $symbol = $currentTenant->currency ?? ''; @endphp

<x-page-header title="New Delivery" />

<form method="POST" action="{{ route('deliveries.store') }}" class="max-w-2xl">
    @csrf
    @if ($order)
        <input type="hidden" name="order_id" value="{{ $order->id }}">
    @endif

    <x-card>
        @if ($order)
            <div class="mb-4 rounded-md bg-indigo-50 border border-indigo-100 p-3 text-sm text-indigo-700">
                Delivery for order <span class="font-semibold">{{ $order->number }}</span> ({{ $symbol }}{{ number_format($order->total, 2) }})
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Recipient name</label>
                <input name="recipient_name" value="{{ old('recipient_name', $order->contact_name ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Phone</label>
                <input name="phone" value="{{ old('phone', $order->contact_phone ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Address</label>
                <input name="address" value="{{ old('address', $order->address ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">City</label>
                <input name="city" value="{{ old('city', $order->city ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Zone</label>
                <input name="zone" value="{{ old('zone') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Fee ({{ $symbol }})</label>
                <input type="number" step="0.01" min="0" name="fee" value="{{ old('fee', $order->delivery_fee ?? 0) }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Scheduled for</label>
                <input type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Driver</label>
                <select name="driver_id" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                    <option value="">Unassigned</option>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">Notes</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-md border border-slate-300 p-2">{{ old('notes') }}</textarea>
        </div>
    </x-card>

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create delivery</button>
        <a href="{{ route('deliveries.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
