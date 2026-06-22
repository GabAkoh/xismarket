@extends('storefront.layout')
@section('title', $product->name)

@section('content')
@php $symbol = $store->currencySymbol() ?? ''; @endphp

<a href="{{ route('shop.home') }}" class="text-sm text-indigo-600 hover:underline">← Back to shop</a>

@php $gallery = $product->galleryUrls(); @endphp
<div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-8 bg-white rounded-lg border border-slate-200 p-6">
    @if (count($gallery))
        <div x-data="{ active: @js($gallery[0]) }">
            <div class="aspect-square bg-gradient-to-br from-slate-100 to-slate-200 rounded-lg overflow-hidden">
                <img :src="active" alt="{{ $product->name }}" class="w-full h-full object-cover">
            </div>
            @if (count($gallery) > 1)
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($gallery as $url)
                        <button type="button" @click="active = @js($url)"
                                class="h-16 w-16 rounded-md overflow-hidden border-2"
                                :class="active === @js($url) ? 'border-indigo-500' : 'border-slate-200'">
                            <img src="{{ $url }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="aspect-square bg-gradient-to-br from-slate-100 to-slate-200 rounded-lg flex items-center justify-center text-7xl text-slate-300">
            {{ strtoupper(substr($product->name, 0, 1)) }}
        </div>
    @endif

    <div class="flex flex-col">
        <h1 class="text-2xl font-bold text-slate-800">{{ $product->name }}</h1>
        @if ($product->category)<p class="text-sm text-slate-400 mt-1">{{ $product->category->name }}</p>@endif
        <div class="mt-3 text-2xl font-bold text-indigo-600">{{ $symbol }} {{ number_format($product->sale_price, 2) }}</div>

        @if ($product->description)
            <p class="mt-4 text-sm text-slate-600 leading-relaxed">{{ $product->description }}</p>
        @endif

        <form method="POST" action="{{ route('shop.cart.add') }}" class="mt-6 flex items-center gap-3">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <input type="number" name="qty" value="1" min="1" max="999" class="w-20 rounded-md border border-slate-300 p-2 text-sm text-center">
            <button class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Add to cart</button>
        </form>

        <p class="mt-3 text-xs text-slate-400">SKU: {{ $product->sku }}</p>
    </div>
</div>
@endsection
