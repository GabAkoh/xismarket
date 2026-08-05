@extends('layouts.app')
@section('title', 'Shipping methods')

@section('content')
<x-page-header title="Shipping methods" subtitle="Delivery & pickup options for online orders." />

<form method="POST" action="{{ route('shipping-methods.update') }}" class="max-w-3xl">
    @csrf @method('PUT')

    {{-- Shipping methods --}}
    @php $shippingRows = old('shipping_methods', $store->shippingMethods()); @endphp
    <x-card>
        <div id="shipping-methods" x-data="{ rows: @js(array_values($shippingRows)) }">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-sm font-semibold text-slate-700">Shipping methods</h2>
                <button type="button" @click="rows.push({ label: '', fee: '0', pickup: false })" class="text-sm font-medium text-indigo-600 hover:underline">+ Add method</button>
            </div>
            <p class="text-xs text-slate-400 mb-3">The options shoppers choose at online checkout — and that staff pick when creating an online order. Mark a method as “Pickup” if it needs no delivery address.</p>

            <div class="flex items-center gap-2 px-1 pb-1 text-xs font-medium uppercase tracking-wider text-slate-400">
                <span class="flex-1">Method name</span>
                <span class="w-28">Fee ({{ $store->currencySymbol() }})</span>
                <span class="w-20 text-center">Pickup</span>
                <span class="w-5"></span>
            </div>
            <template x-for="(row, i) in rows" :key="i">
                <div class="mb-2 flex items-center gap-2">
                    <input type="text" :name="`shipping_methods[${i}][label]`" x-model="row.label" maxlength="100"
                           placeholder="e.g. Express Delivery" class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
                    <input type="hidden" :name="`shipping_methods[${i}][pickup]`" :value="row.pickup ? 1 : 0">
                    <input type="number" :name="`shipping_methods[${i}][fee]`" x-model="row.fee" min="0" step="0.01"
                           class="w-28 rounded-md border border-slate-300 p-2 text-sm text-right">
                    <span class="w-20 flex justify-center">
                        <input type="checkbox" x-model="row.pickup" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    </span>
                    <button type="button" @click="rows.splice(i, 1)" class="w-5 text-slate-300 hover:text-red-500 text-sm" title="Remove">✕</button>
                </div>
            </template>
            <p class="mt-2 text-xs text-slate-400">Leave all rows empty to fall back to the defaults (Standard Delivery + Store Pickup).</p>
        </div>
    </x-card>

    <div class="mt-4">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
    </div>
</form>
@endsection
