@extends('layouts.app')
@section('title', 'New purchase order')

@section('content')
<x-page-header title="New purchase order" />

<form method="POST" action="{{ route('purchases.store') }}" class="max-w-4xl" x-data="poForm()">
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
                            <td class="py-2 pr-2 relative">
                                <input type="hidden" :name="`items[${index}][product_id]`" :value="item.product_id">
                                <input x-model="item.search" @input.debounce.300ms="search(item)" @focus="search(item)"
                                       placeholder="Search name, SKU, barcode or description…" autocomplete="off"
                                       class="w-full rounded-md border p-2"
                                       :class="item.product_id ? 'border-slate-300' : 'border-amber-300'">
                                <div x-show="item.results.length" x-cloak @click.outside="item.results = []"
                                     class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg">
                                    <template x-for="p in item.results" :key="p.id">
                                        <button type="button" @click="pick(item, p)"
                                                class="block w-full text-left px-3 py-1.5 text-sm hover:bg-indigo-50">
                                            <span x-text="p.name"></span>
                                            <span class="text-slate-400" x-text="'· ' + p.sku + (p.barcode ? ' · ' + p.barcode : '')"></span>
                                        </button>
                                    </template>
                                </div>
                            </td>
                            <td class="py-2 pr-2">
                                <input :name="`items[${index}][quantity]`" x-model="item.quantity" type="number" step="0.001" min="0.001" required class="w-full rounded-md border border-slate-300 p-2">
                            </td>
                            <td class="py-2 pr-2">
                                <input :name="`items[${index}][unit_cost]`" x-model="item.unit_cost" type="number" step="0.01" min="0" required class="w-full rounded-md border border-slate-300 p-2">
                            </td>
                            <td class="py-2 text-right">
                                <button type="button" @click="removeLine(index)" x-show="items.length > 1" class="text-red-600 hover:underline">✕</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" @click="addLine()" class="mt-3 text-sm text-indigo-600 hover:underline">+ Add line</button>
        </x-card>
    </div>

    @push('scripts')
    <script>
    function poForm() {
        const blank = () => ({ product_id: '', search: '', results: [], quantity: 1, unit_cost: 0 });
        return {
            items: [blank()],
            searchUrl: '{{ route('purchases.product-search') }}',
            async search(item) {
                const q = item.search.trim();
                // If the field no longer matches the picked product, clear the selection.
                if (item.product_id && q !== item.label) item.product_id = '';
                if (q.length < 2) { item.results = []; return; }
                try {
                    const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                    });
                    item.results = res.ok ? await res.json() : [];
                } catch (e) { item.results = []; }
            },
            pick(item, p) {
                item.product_id = p.id;
                item.label = p.name + ' (' + p.sku + ')';
                item.search = item.label;
                if (!item.unit_cost || Number(item.unit_cost) <= 0) item.unit_cost = p.cost_price || 0;
                item.results = [];
            },
            addLine() { this.items.push(blank()); },
            removeLine(i) { this.items.splice(i, 1); if (!this.items.length) this.addLine(); },
        };
    }
    </script>
    @endpush

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create purchase order</button>
        <a href="{{ route('purchases.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
