<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shop') · {{ $store->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="{{ asset('js/alpine.min.js') }}"
            onerror="this.onerror=null;var s=document.createElement('script');s.defer=true;s.src='https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';document.head.appendChild(s);"></script>
</head>
@php
    $cartCount = app(\App\Services\Storefront\CartService::class)->count();
    $navCategories = \App\Models\Inventory\Category::orderBy('name')->take(6)->get(['id', 'name']);
    $promo = (bool) $store->setting('storefront.promo_enabled', true)
        ? $store->setting('storefront.promo', 'Free delivery on orders over '.$store->currencySymbol().' 150 · Shop the latest arrivals today')
        : null;
@endphp
<body class="h-full flex flex-col text-slate-800">
    {{-- Promotional announcement bar --}}
    @if ($promo)
        <div class="bg-indigo-600 text-white text-center text-xs sm:text-sm py-2 px-4">{{ $promo }}</div>
    @endif

    <header class="bg-white border-b border-slate-200 sticky top-0 z-20">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-4">
            <a href="{{ route('shop.home') }}" class="text-xl font-extrabold text-indigo-600 shrink-0">{{ $store->name }}</a>
            <form method="GET" action="{{ route('shop.home') }}" class="flex-1 max-w-md hidden sm:block">
                <input name="q" value="{{ request('q') }}" placeholder="Search products…"
                       class="w-full rounded-full border border-slate-300 px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </form>
            <a href="{{ route('shop.cart') }}" class="relative ml-auto inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm font-medium hover:bg-slate-200">
                🛒 <span class="hidden sm:inline">Cart</span>
                @if ($cartCount > 0)
                    <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center">{{ $cartCount }}</span>
                @endif
            </a>
        </div>

        {{-- Category navigation --}}
        @if ($navCategories->isNotEmpty())
            <nav class="border-t border-slate-100 bg-white">
                <div class="max-w-6xl mx-auto px-4 flex items-center gap-5 overflow-x-auto text-sm">
                    <a href="{{ route('shop.home') }}" class="py-2.5 font-medium text-slate-700 hover:text-indigo-600 whitespace-nowrap">Shop All</a>
                    @foreach ($navCategories as $navCat)
                        <a href="{{ route('shop.home', ['category' => $navCat->id]) }}"
                           class="py-2.5 text-slate-500 hover:text-indigo-600 whitespace-nowrap {{ request('category') == $navCat->id ? 'text-indigo-600 font-medium' : '' }}">{{ $navCat->name }}</a>
                    @endforeach
                </div>
            </nav>
        @endif
    </header>

    <main class="flex-1 max-w-6xl w-full mx-auto px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="max-w-6xl mx-auto px-4 py-6 text-sm text-slate-400 flex flex-wrap justify-between gap-2">
            <span>© {{ $store->name }}</span>
            <span>Powered by xismarket</span>
        </div>
    </footer>
</body>
</html>
