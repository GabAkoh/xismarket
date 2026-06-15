@extends('layouts.app')
@section('title', 'Stock Levels')

@section('content')
<x-page-header title="Stock Levels">
    @permission('stock.adjust')
        <button type="button" onclick="document.getElementById('adjust-form').classList.toggle('hidden')"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Adjust stock</button>
    @endpermission
</x-page-header>

@permission('stock.adjust')
    <div id="adjust-form" class="hidden mb-6">
        <x-card title="Stock adjustment">
            <form method="POST" action="{{ route('stock.adjust') }}">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Product</label>
                        <select name="product_id" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Warehouse</label>
                        <select name="warehouse_id" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Quantity (+/-)</label>
                        <input name="quantity" type="number" step="0.001" required class="mt-1 w-full rounded-md border border-slate-300 p-2" placeholder="e.g. 10 or -5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Note</label>
                        <input name="note" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                    </div>
                </div>
                <div class="mt-4">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply adjustment</button>
                </div>
            </form>
        </x-card>
    </div>
@endpermission

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Product</th><th>SKU</th><th>Warehouse</th><th class="text-right">On hand</th><th class="text-right">Reorder level</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($stocks as $stock)
                <tr @class(['bg-red-50' => (float) $stock->quantity <= (float) $stock->reorder_level && (float) $stock->reorder_level > 0])>
                    <td class="py-3 font-medium text-slate-700">{{ $stock->product?->name }}</td>
                    <td class="text-slate-500">{{ $stock->product?->sku }}</td>
                    <td class="text-slate-500">{{ $stock->warehouse?->name }}</td>
                    <td class="text-right text-slate-700">{{ rtrim(rtrim(number_format((float) $stock->quantity, 3), '0'), '.') }}</td>
                    <td class="text-right text-slate-500">{{ rtrim(rtrim(number_format((float) $stock->reorder_level, 3), '0'), '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-400">No stock records yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection
