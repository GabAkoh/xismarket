@php $symbol = $store->currencySymbol() ?? ''; @endphp
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col hover:shadow-md transition">
    <a href="{{ route('shop.product', ['product' => $product->id]) }}" class="block">
        <div class="aspect-square bg-gradient-to-br from-slate-100 to-slate-200 flex items-center justify-center text-4xl text-slate-300">
            @if ($product->image_path)
                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            @else
                {{ strtoupper(substr($product->name, 0, 1)) }}
            @endif
        </div>
    </a>
    <div class="p-3 flex flex-col flex-1">
        @if ($product->category)
            <span class="text-[11px] uppercase tracking-wide text-slate-400">{{ $product->category->name }}</span>
        @endif
        <a href="{{ route('shop.product', ['product' => $product->id]) }}" class="text-sm font-medium text-slate-700 hover:text-indigo-600 leading-tight">{{ $product->name }}</a>
        <div class="mt-1 font-bold text-indigo-600">{{ $symbol }} {{ number_format($product->sale_price, 2) }}</div>
        <form method="POST" action="{{ route('shop.cart.add') }}" class="mt-3">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add to cart</button>
        </form>
    </div>
</div>
