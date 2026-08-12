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
        @php
            $wh = \App\Models\Inventory\Warehouse::default();
            $vs = $product->variants->where('is_active', true)->values();
            $variantPayload = [
                'variants' => $vs->map(fn ($v) => [
                    'id' => $v->id, 'o1' => $v->option1, 'o2' => $v->option2, 'o3' => $v->option3,
                    'price' => (float) $v->sale_price,
                    'out' => ($product->track_stock && $wh) ? ($v->stockIn($wh) <= 0) : false,
                ])->values(),
                'names' => array_values($product->optionNames()),
                'symbol' => $symbol,
            ];
        @endphp
        <div x-data="variantBuy(@js($variantPayload))" class="contents">
            <div class="mt-3 text-2xl font-bold text-indigo-600" x-text="priceLabel"></div>

            @if ($product->description)
                <p class="mt-4 text-sm text-slate-600 leading-relaxed">{{ $product->description }}</p>
            @endif

            {{-- Option selectors --}}
            <template x-if="hasOptions">
                <div class="mt-4 space-y-3">
                    <template x-for="(name, i) in names" :key="i">
                        <div>
                            <label class="block text-sm font-medium text-slate-700" x-text="name"></label>
                            <select x-model="sel[i + 1]" class="mt-1 w-full sm:w-56 rounded-md border border-slate-300 p-2 text-sm">
                                <option value="">Choose…</option>
                                <template x-for="val in optionValues(i + 1)" :key="val">
                                    <option :value="val" x-text="val"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>
            </template>

            <form method="POST" action="{{ route('shop.cart.add') }}" class="mt-6 flex items-center gap-3"
                  @submit="if (!canAdd) { $event.preventDefault() } else {
                      // Share one event_id between the browser Pixel and the
                      // server-side (CAPI) AddToCart so Meta de-duplicates them.
                      const eid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('atc-' + Date.now() + '-' + Math.random().toString(36).slice(2));
                      $refs.metaEventId.value = eid;
                      window.xisMetaTrack && xisMetaTrack('AddToCart', { content_ids: [@js((string) $product->id)], content_name: @js($product->name), content_type: 'product', currency: @js($store->currency), value: current.price }, eid);
                  }">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="variant_id" :value="current ? current.id : ''">
                <input type="hidden" name="meta_event_id" x-ref="metaEventId">
                <input type="number" name="qty" value="1" min="1" max="999" class="w-20 rounded-md border border-slate-300 p-2 text-sm text-center">
                <button :disabled="!canAdd"
                        class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed"
                        x-text="(current && current.out) ? 'Sold out' : 'Add to cart'"></button>
            </form>
            <p x-show="hasOptions && !current" x-cloak class="mt-2 text-xs text-slate-500">Select your options to add to cart.</p>
            <p x-show="current && current.out" x-cloak class="mt-2 text-xs text-rose-500">This option is sold out.</p>
        </div>

        <p class="mt-3 text-xs text-slate-400">SKU: {{ $product->sku }}</p>
    </div>
</div>

<script>
function variantBuy(cfg) {
    return {
        variants: cfg.variants || [],
        names: cfg.names || [],
        symbol: cfg.symbol || '',
        sel: { 1: '', 2: '', 3: '' },
        get hasOptions() { return this.names.length > 0; },
        optionValues(idx) {
            const key = 'o' + idx;
            return [...new Set(this.variants.map(v => v[key]).filter(x => x !== null && x !== ''))];
        },
        get current() {
            if (!this.hasOptions) return this.variants[0] || null;
            return this.variants.find(v =>
                (this.names.length < 1 || v.o1 === this.sel[1]) &&
                (this.names.length < 2 || v.o2 === this.sel[2]) &&
                (this.names.length < 3 || v.o3 === this.sel[3])
            ) || null;
        },
        get canAdd() { return !!this.current && !this.current.out; },
        get priceLabel() {
            const fmt = n => this.symbol + ' ' + Number(n).toFixed(2);
            if (this.current) return fmt(this.current.price);
            if (!this.variants.length) return fmt(0);
            const min = Math.min(...this.variants.map(v => v.price));
            const max = Math.max(...this.variants.map(v => v.price));
            return (max > min ? 'from ' : '') + fmt(min);
        },
    };
}
</script>

<script>
    // Meta ViewContent — fires when the browser Pixel is enabled (no-op otherwise).
    window.xisMetaTrack && xisMetaTrack('ViewContent', {
        content_ids: [@js((string) $product->id)],
        content_name: @js($product->name),
        content_type: 'product',
        content_category: @js($product->category?->name),
        currency: @js($store->currency),
        value: {{ (float) ($vs->min('sale_price') ?? 0) }},
    });
</script>
@endsection
