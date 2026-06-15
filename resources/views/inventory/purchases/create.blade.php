@extends('layouts.app')
@section('title', 'New purchase order')

@section('content')
<x-page-header title="New purchase order" />

<form method="POST" action="{{ route('purchases.store') }}" class="max-w-4xl"
      x-data="{ items: [{ product_id: '', quantity: 1, unit_cost: 0 }] }">
    @csrf
    <x-card title="Details">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Supplier</label>
                <select name="supplier_id" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                    <option value="">— None —</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Warehouse</label>
                <select name="warehouse_id" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id || $warehouse->is_default)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Order date</label>
                <input name="order_date" type="date" value="{{ old('order_date', now()->toDateString()) }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">Note</label>
            <input name="note" value="{{ old('note') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
        </div>
    </x-card>

    <div class="mt-4">
        <x-card title="Line items">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400 border-b">
                    <tr><th class="py-2">Product</th><th class="w-32">Quantity</th><th class="w-32">Unit cost</th><th class="w-10"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <template x-for="(item, index) in items" :key="index">
                        <tr>
                            <td class="py-2 pr-2">
                                <select :name="`items[${index}][product_id]`" x-model="item.product_id" required class="w-full rounded-md border border-slate-300 p-2">
                                    <option value="">— Select product —</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pr-2">
                                <input :name="`items[${index}][quantity]`" x-model="item.quantity" type="number" step="0.001" min="0.001" required class="w-full rounded-md border border-slate-300 p-2">
                            </td>
                            <td class="py-2 pr-2">
                                <input :name="`items[${index}][unit_cost]`" x-model="item.unit_cost" type="number" step="0.01" min="0" required class="w-full rounded-md border border-slate-300 p-2">
                            </td>
                            <td class="py-2 text-right">
                                <button type="button" @click="items.splice(index, 1)" x-show="items.length > 1" class="text-red-600 hover:underline">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="items.push({ product_id: '', quantity: 1, unit_cost: 0 })" class="mt-3 text-sm text-indigo-600 hover:underline">+ Add line</button>
        </x-card>
    </div>

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create purchase order</button>
        <a href="{{ route('purchases.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
