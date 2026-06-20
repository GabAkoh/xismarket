@extends('layouts.app')
@section('title', 'Products')

@section('content')
<x-page-header title="Products">
    @permission('products.manage')
        <a href="{{ route('products.import') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Import from Shopify</a>
        <a href="{{ route('products.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add product</a>
    @endpermission
</x-page-header>

@php $filter = request('filter'); @endphp
<x-card>
    {{-- Filters --}}
    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('products.index') }}"
           class="rounded-full px-3 py-1 {{ $filter ? 'text-slate-500 hover:bg-slate-100' : 'bg-slate-800 text-white' }}">All</a>
        <a href="{{ route('products.index', ['filter' => 'attention']) }}"
           class="rounded-full px-3 py-1 {{ $filter === 'attention' ? 'bg-amber-500 text-white' : 'text-amber-600 hover:bg-amber-50' }}">
            ⚠ Needs attention ({{ number_format($attentionCount) }})
        </a>
        @if ($filter === 'attention')
            <span class="text-xs text-slate-400">No cost price and no stock — set a cost, restock, or deactivate.</span>
        @endif
    </div>

    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th>SKU</th><th>Category</th><th class="text-right">Sale price</th><th class="text-right">Stock</th><th>Status</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($products as $product)
                <tr>
                    <td class="py-3 font-medium text-slate-700">
                        <div class="flex items-center gap-3">
                            @if ($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-9 w-9 rounded object-cover border border-slate-200">
                            @else
                                <span class="flex h-9 w-9 items-center justify-center rounded bg-slate-100 text-xs font-semibold text-slate-400">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                            @endif
                            <span>{{ $product->name }}</span>
                            @if ((float) $product->cost_price == 0 && (float) ($product->total_stock ?? 0) <= 0)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700 whitespace-nowrap" title="No cost price and no stock">⚠ No cost &amp; stock</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-slate-500">{{ $product->sku }}</td>
                    <td class="text-slate-500">{{ $product->category?->name ?? '—' }}</td>
                    <td class="text-right text-slate-700">{{ $currentTenant->currencySymbol() }} {{ number_format((float) $product->sale_price, 2) }}</td>
                    <td class="text-right text-slate-700">{{ rtrim(rtrim(number_format((float) ($product->total_stock ?? 0), 3), '0'), '.') }}</td>
                    <td>
                        @if ($product->is_active)
                            <span class="text-xs text-green-600">● Active</span>
                        @else
                            <span class="text-xs text-slate-400">● Inactive</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @permission('products.manage')
                            <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline" onsubmit="return confirm('Remove this product?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-400">No products yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $products->links() }}</div>
</x-card>
@endsection
