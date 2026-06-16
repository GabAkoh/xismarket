@extends('storefront.layout')
@section('title', $filtering ? 'Shop' : $store->name)

@section('content')
@php $symbol = $store->currencySymbol() ?? ''; @endphp

@if ($filtering)
    {{-- ───────────────── Search / filter results ───────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h1 class="text-2xl font-bold text-slate-800">
            @if (request('q'))
                Results for “{{ request('q') }}”
            @else
                {{ optional($categories->firstWhere('id', (int) request('category')))->name ?? 'Products' }}
            @endif
        </h1>
        <a href="{{ route('shop.home') }}" class="text-sm text-indigo-600 hover:underline">← Back to home</a>
    </div>

    @if ($categories->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="{{ route('shop.home', ['q' => request('q')]) }}"
               class="px-3 py-1.5 rounded-full text-sm {{ ! request('category') ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' }}">All</a>
            @foreach ($categories as $cat)
                <a href="{{ route('shop.home', ['category' => $cat->id, 'q' => request('q')]) }}"
                   class="px-3 py-1.5 rounded-full text-sm {{ request('category') == $cat->id ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' }}">{{ $cat->name }}</a>
            @endforeach
        </div>
    @endif

    @if ($products->isEmpty())
        <div class="bg-white rounded-lg border border-slate-200 p-12 text-center text-slate-400">No products found.</div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($products as $product)
                @include('storefront._product_card')
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif

@else
    {{-- ───────────────── Landing page ───────────────── --}}

    {{-- Hero --}}
    @php
        $heroImage = $store->setting('storefront.hero_image')
            ?: optional($featured->firstWhere('image_path'))->image_path;
    @endphp
    <section class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center bg-white rounded-2xl border border-slate-200 p-6 sm:p-10 mb-12">
        <div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900 leading-tight">
                {{ $store->setting('storefront.hero_title', 'Quality you can trust. Value you can feel.') }}
            </h1>
            <p class="mt-4 text-slate-500 leading-relaxed">
                {{ $store->setting('storefront.hero_subtitle', 'Discover a hand-picked selection from '.$store->name.' — everyday essentials and special finds, delivered to your door.') }}
            </p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="#shop" class="rounded-full bg-indigo-600 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Browse Collections</a>
                <a href="#story" class="rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">Learn Our Story</a>
            </div>
        </div>
        <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-gradient-to-br from-indigo-100 to-slate-200 flex items-center justify-center">
            @if ($heroImage)
                <img src="{{ asset('storage/'.$heroImage) }}" alt="{{ $store->name }}" class="w-full h-full object-cover">
            @else
                <span class="text-7xl font-extrabold text-white/70">{{ strtoupper(substr($store->name, 0, 1)) }}</span>
            @endif
        </div>
    </section>

    {{-- Shop by category --}}
    @if ($categoryTiles->isNotEmpty())
        <section class="mb-12">
            <div class="flex items-end justify-between mb-5">
                <h2 class="text-2xl font-bold text-slate-800">Shop by category</h2>
                <a href="#shop" class="text-sm text-indigo-600 hover:underline">View all</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($categoryTiles as $tile)
                    <a href="{{ route('shop.home', ['category' => $tile->id]) }}"
                       class="group relative aspect-[4/3] rounded-xl overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200 border border-slate-200">
                        @if ($tile->image)
                            <img src="{{ asset('storage/'.$tile->image) }}" alt="{{ $tile->name }}" class="w-full h-full object-cover group-hover:scale-105 transition">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/55 to-transparent"></div>
                        <div class="absolute bottom-0 left-0 p-3 text-white">
                            <div class="font-semibold leading-tight">{{ $tile->name }}</div>
                            <div class="text-xs text-white/80">{{ $tile->count }} {{ \Illuminate\Support\Str::plural('item', $tile->count) }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured products --}}
    @if ($featured->isNotEmpty())
        <section class="mb-12">
            <div class="flex items-end justify-between mb-5">
                <h2 class="text-2xl font-bold text-slate-800">Bestsellers</h2>
                <a href="#shop" class="text-sm text-indigo-600 hover:underline">Shop all</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($featured as $product)
                    @include('storefront._product_card')
                @endforeach
            </div>
        </section>
    @endif

    {{-- Brand story --}}
    <section id="story" class="grid grid-cols-1 md:grid-cols-3 gap-8 items-center bg-white rounded-2xl border border-slate-200 p-6 sm:p-10 mb-12">
        <div class="md:col-span-1">
            <div class="aspect-square rounded-2xl bg-gradient-to-br from-amber-100 to-indigo-100 flex items-center justify-center text-6xl">🛍️</div>
        </div>
        <div class="md:col-span-2">
            <h2 class="text-2xl font-bold text-slate-800">{{ $store->setting('storefront.story_title', 'Our Story') }}</h2>
            <p class="mt-3 text-slate-500 leading-relaxed">
                {{ $store->setting('storefront.story_body', $store->name.' was built on a simple idea: bring you dependable quality at a fair price, with friendly service you can count on. Every product on our shelves is chosen with care — so you can shop with confidence and we can keep earning your trust, one order at a time.') }}
            </p>
            <div class="mt-4 flex flex-wrap gap-4 text-sm text-slate-500">
                @if ($store->phone)<span>📞 {{ $store->phone }}</span>@endif
                @if ($store->email)<span>✉️ {{ $store->email }}</span>@endif
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    @php
        $testimonials = $store->setting('storefront.testimonials', []) ?: [
            ['name' => 'Amara O.', 'text' => 'Fast delivery and everything arrived exactly as described. This is my go-to store now.'],
            ['name' => 'Daniel K.', 'text' => 'Great prices and the quality is excellent. The checkout was quick and simple.'],
            ['name' => 'Grace U.', 'text' => 'Lovely selection and wonderful customer service. Highly recommend to everyone.'],
        ];
    @endphp
    <section class="mb-12">
        <h2 class="text-2xl font-bold text-slate-800 mb-5 text-center">What our customers say</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($testimonials as $t)
                <figure class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="text-amber-400 text-sm">★★★★★</div>
                    <blockquote class="mt-2 text-sm text-slate-600 leading-relaxed">“{{ $t['text'] }}”</blockquote>
                    <figcaption class="mt-3 text-sm font-semibold text-slate-700">{{ $t['name'] }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    {{-- Full catalogue --}}
    <section id="shop" class="scroll-mt-24">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <h2 class="text-2xl font-bold text-slate-800">All products</h2>
        </div>

        @if ($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-6">
                <a href="{{ route('shop.home') }}#shop"
                   class="px-3 py-1.5 rounded-full text-sm bg-indigo-600 text-white">All</a>
                @foreach ($categories as $cat)
                    <a href="{{ route('shop.home', ['category' => $cat->id]) }}"
                       class="px-3 py-1.5 rounded-full text-sm bg-white border border-slate-200 text-slate-600 hover:bg-slate-100">{{ $cat->name }}</a>
                @endforeach
            </div>
        @endif

        @if ($products->isEmpty())
            <div class="bg-white rounded-lg border border-slate-200 p-12 text-center text-slate-400">No products yet.</div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach ($products as $product)
                    @include('storefront._product_card')
                @endforeach
            </div>
            <div class="mt-6">{{ $products->links() }}</div>
        @endif
    </section>
@endif
@endsection
