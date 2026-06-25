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
                {{ $selectedCategory?->name ?? 'Products' }}
            @endif
        </h1>
        <a href="{{ route('shop.home') }}" class="text-sm text-indigo-600 hover:underline">← Back to home</a>
    </div>

    {{-- Breadcrumb trail (hierarchical browsing) --}}
    @if ($breadcrumb->isNotEmpty())
        <nav class="mb-3 flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
            <a href="{{ route('shop.home') }}" class="hover:text-indigo-600">Home</a>
            @foreach ($breadcrumb as $crumb)
                <span class="text-slate-300">/</span>
                @if ($loop->last)
                    <span class="font-semibold text-slate-700">{{ $crumb->name }}</span>
                @else
                    <a href="{{ route('shop.home', ['category' => $crumb->id]) }}" class="hover:text-indigo-600">{{ $crumb->name }}</a>
                @endif
            @endforeach
        </nav>
    @endif

    @if ($chipCategories->isNotEmpty())
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="{{ route('shop.home', ['q' => request('q')]) }}"
               class="px-3 py-1.5 rounded-full text-sm {{ ! request('category') ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-100' }}">All</a>
            @foreach ($chipCategories as $cat)
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

    {{-- What makes us special --}}
    @php
        $specials = [
            ['icon' => '🧸', 'title' => 'Premium Quality', 'text' => "Every item is carefully selected — baby and children's essentials you can trust."],
            ['icon' => '💛', 'title' => 'Fair Prices', 'text' => 'Premium quality without the premium markup, so every family can afford the best.'],
            ['icon' => '🚚', 'title' => 'Fast Delivery', 'text' => 'Quick, reliable delivery right to your door — order today, relax tomorrow.'],
            ['icon' => '👪', 'title' => 'Trusted by Parents', 'text' => 'Loved by families who come back again and again for products that deliver.'],
        ];
    @endphp
    <section class="mb-12">
        <h2 class="text-2xl font-bold text-slate-800 mb-1 text-center">What Makes {{ $store->name }} Special</h2>
        <p class="text-center text-slate-500 mb-6">A little of what you can count on, every time you shop.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($specials as $s)
                <div class="bg-white rounded-xl border border-slate-200 p-5 text-center">
                    <div class="text-4xl">{{ $s['icon'] }}</div>
                    <h3 class="mt-3 font-semibold text-slate-800">{{ $s['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 leading-relaxed">{{ $s['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Featured Collections (curated) --}}
    @if ($featuredCollections->isNotEmpty())
        <section class="mb-12">
            <div class="flex items-end justify-between mb-5">
                <h2 class="text-2xl font-bold text-slate-800">Featured Collections</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($featuredCollections as $col)
                    <a href="{{ route('shop.home', ['category' => $col->id]) }}"
                       class="group relative aspect-[4/3] rounded-2xl overflow-hidden bg-gradient-to-br from-indigo-100 to-slate-200 border border-slate-200 shadow-sm hover:shadow-md transition">
                        @if ($col->image)
                            <img src="{{ asset('storage/'.$col->image) }}" alt="{{ $col->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/10 to-transparent"></div>
                        <div class="absolute bottom-0 left-0 p-5 text-white">
                            <div class="text-xl font-bold leading-tight">{{ $col->name }}</div>
                            @if ($col->subtitle)
                                <div class="mt-0.5 text-sm text-white/85">{{ $col->subtitle }}</div>
                            @endif
                            <span class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-white/90 group-hover:gap-2 transition-all">Shop now →</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

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
            @php $storyImage = $store->setting('storefront.story_image'); @endphp
            <div class="aspect-square rounded-2xl overflow-hidden bg-gradient-to-br from-amber-100 to-indigo-100 flex items-center justify-center text-6xl">
                @if ($storyImage)
                    <img src="{{ asset('storage/'.$storyImage) }}" alt="{{ $store->setting('storefront.story_title', 'Our Story') }}" class="h-full w-full object-cover">
                @else
                    🛍️
                @endif
            </div>
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

    {{-- Join our community --}}
    <section class="mb-12">
        <div class="rounded-2xl bg-gradient-to-br from-indigo-600 to-indigo-500 px-6 py-10 sm:px-12 text-center text-white">
            <h2 class="text-2xl sm:text-3xl font-bold">Join Our Community</h2>
            <p class="mt-2 text-indigo-100 max-w-xl mx-auto">Be first to hear about new arrivals, exclusive offers and parenting tips from {{ $store->name }}.</p>
            <form method="POST" action="{{ route('shop.subscribe', ['store' => $store->slug]) }}"
                  class="mt-6 flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                @csrf
                <input type="email" name="email" required placeholder="Your email address"
                       class="flex-1 rounded-full px-5 py-3 text-sm text-slate-800 focus:ring-2 focus:ring-white">
                <button class="rounded-full bg-white px-6 py-3 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">Subscribe</button>
            </form>
            @error('email')<p class="mt-2 text-sm text-indigo-100">{{ $message }}</p>@enderror
        </div>
    </section>

    {{-- Full catalogue --}}
    <section id="shop" class="scroll-mt-24">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <h2 class="text-2xl font-bold text-slate-800">All products</h2>
        </div>

        @if ($chipCategories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-6">
                <a href="{{ route('shop.home') }}#shop"
                   class="px-3 py-1.5 rounded-full text-sm bg-indigo-600 text-white">All</a>
                @foreach ($chipCategories as $cat)
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
