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
@php $canManage = auth()->user()?->hasPermission('products.manage'); @endphp
<div x-data="{
        sel: [],
        pageIds: @js($products->pluck('id')->map(fn ($i) => (string) $i)->values()),
        action: '', price: '', qty: '', reorder: '', coverDays: '',
        toggleAll(e) { this.sel = e.target.checked ? [...this.pageIds] : []; },
        run(a) {
            if (a === 'price' && this.price === '') return;
            if (a === 'restock' && (this.qty === '' || Number(this.qty) === 0)) return;
            if (a === 'reorder' && this.reorder === '') return;
            if (a === 'reorder_demand' && (this.coverDays === '' || Number(this.coverDays) < 1)) return;
            this.action = a;
            this.$nextTick(() => this.$refs.bulkForm.submit());
        },
     }">
<x-card>
    {{-- Filters --}}
    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
        <a href="{{ route('products.index') }}"
           class="rounded-full px-3 py-1 {{ $filter ? 'text-slate-500 hover:bg-slate-100' : 'bg-slate-800 text-white' }}">All</a>
        <a href="{{ route('products.index', ['filter' => 'attention']) }}"
           class="rounded-full px-3 py-1 {{ $filter === 'attention' ? 'bg-amber-500 text-white' : 'text-amber-600 hover:bg-amber-50' }}">
            ⚠ Needs attention ({{ number_format($attentionCount) }})
        </a>
        <a href="{{ route('products.index', ['filter' => 'unsellable']) }}"
           class="rounded-full px-3 py-1 {{ $filter === 'unsellable' ? 'bg-red-500 text-white' : 'text-red-600 hover:bg-red-50' }}">
            No price / out of stock ({{ number_format($sellableCount) }})
        </a>
        @if ($filter === 'attention')
            <span class="text-xs text-slate-400">No cost price and no stock — set a cost, restock, or deactivate.</span>
        @elseif ($filter === 'unsellable')
            <span class="text-xs text-slate-400">Zero sale price or nothing on hand — these can't be sold as-is.</span>
        @endif
    </div>

    @if ($canManage)
        {{-- Bulk action bar (appears once products are selected) --}}
        <div x-show="sel.length" x-cloak class="mb-3 flex flex-wrap items-center gap-3 rounded-md border border-indigo-200 bg-indigo-50 p-3">
            <form method="POST" action="{{ route('products.bulk') }}" x-ref="bulkForm" class="contents">
                @csrf
                <template x-for="id in sel" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <input type="hidden" name="action" :value="action">

                <span class="text-sm font-semibold text-indigo-700"><span x-text="sel.length"></span> selected</span>
                <button type="button" @click="run('deactivate')" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Deactivate</button>
                <button type="button" @click="run('activate')" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Activate</button>

                <div class="flex items-center gap-1">
                    <span class="text-xs text-slate-400">{{ $currentTenant->currencySymbol() }}</span>
                    <input type="number" name="price" x-model="price" min="0" step="0.01" placeholder="price"
                           class="w-24 rounded-md border border-slate-300 p-1.5 text-sm text-right">
                    <button type="button" @click="run('price')" :disabled="price===''" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40">Set price</button>
                </div>

                <div class="flex items-center gap-1">
                    <input type="number" name="quantity" x-model="qty" step="1" placeholder="qty"
                           class="w-20 rounded-md border border-slate-300 p-1.5 text-sm text-right">
                    <button type="button" @click="run('restock')" :disabled="qty==='' || Number(qty)===0" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40">Add stock</button>
                </div>

                <div class="flex items-center gap-1">
                    <input type="number" name="reorder" x-model="reorder" min="0" step="1" placeholder="reorder"
                           class="w-20 rounded-md border border-slate-300 p-1.5 text-sm text-right">
                    <button type="button" @click="run('reorder')" :disabled="reorder===''" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40">Set reorder</button>
                </div>

                <div class="flex items-center gap-1">
                    <input type="number" name="cover_days" x-model="coverDays" min="1" step="1" placeholder="days"
                           class="w-16 rounded-md border border-slate-300 p-1.5 text-sm text-right">
                    <button type="button" @click="run('reorder_demand')" :disabled="coverDays==='' || Number(coverDays) < 1"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50 disabled:opacity-40"
                            title="Set reorder level to this many days of average demand (last 90 days)">Reorder ≈ demand</button>
                </div>

                <button type="button" @click="sel = []" class="ml-auto text-sm text-slate-500 hover:underline">Clear</button>
            </form>
        </div>
    @endif

    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                @if ($canManage)<th class="py-2 w-8"><input type="checkbox" @change="toggleAll($event)" :checked="sel.length && sel.length === pageIds.length" class="rounded border-slate-300"></th>@endif
                <th class="py-2">Name</th><th>SKU</th><th>Category</th><th class="text-right">Sale price</th><th class="text-right">Stock</th><th>Status</th><th></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($products as $product)
                <tr>
                    @if ($canManage)<td class="align-top pt-4"><input type="checkbox" value="{{ $product->id }}" x-model="sel" class="rounded border-slate-300"></td>@endif
                    <td class="py-3 font-medium text-slate-700">
                        <div class="flex items-center gap-3">
                            @if ($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-9 w-9 rounded object-cover border border-slate-200">
                            @else
                                <span class="flex h-9 w-9 items-center justify-center rounded bg-slate-100 text-xs font-semibold text-slate-400">{{ strtoupper(substr($product->name, 0, 1)) }}</span>
                            @endif
                            <span>{{ $product->name }}</span>
                            @if ((float) $product->sale_price == 0)
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700 whitespace-nowrap" title="No sale price set">No sale price</span>
                            @endif
                            @if ((float) ($product->total_stock ?? 0) <= 0)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700 whitespace-nowrap" title="No stock on hand">Out of stock</span>
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
                <tr><td colspan="8" class="py-6 text-center text-slate-400">No products match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $products->links() }}</div>
</x-card>
</div>
@endsection
