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
    // Top-level categories ranked by products in their whole sub-tree, so the nav
    // shows the real browse sections (e.g. Boys/Girls/Baby) rather than empty roots.
    $navCategories = app(\App\Services\Storefront\CategoryNavService::class)->topLevel(6);
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
            @php $storeLogo = $store->setting('storefront.logo'); @endphp
            <a href="{{ route('shop.home') }}" class="shrink-0">
                @if ($storeLogo)
                    <img src="{{ asset('storage/'.$storeLogo) }}" alt="{{ $store->name }}" class="h-10 w-auto object-contain">
                @else
                    <span class="text-xl font-extrabold text-indigo-600">{{ $store->name }}</span>
                @endif
            </a>
            <form method="GET" action="{{ route('shop.home') }}" class="flex-1 max-w-md hidden sm:block">
                <input name="q" value="{{ request('q') }}" placeholder="Search products…"
                       class="w-full rounded-full border border-slate-300 px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
            </form>
            @php $shopper = auth('customer')->user(); @endphp
            <div class="ml-auto flex items-center gap-2">
                @if ($shopper)
                    <a href="{{ route('shop.account') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-4 py-2 text-sm font-medium hover:bg-slate-200">
                        👤 {{ \Illuminate\Support\Str::of($shopper->name)->explode(' ')->first() }}
                    </a>
                @else
                    <a href="{{ route('shop.login') }}" class="hidden sm:inline-flex items-center rounded-full px-3 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600">Sign in</a>
                    <a href="{{ route('shop.register') }}" class="hidden sm:inline-flex items-center rounded-full bg-slate-100 px-4 py-2 text-sm font-medium hover:bg-slate-200">Sign up</a>
                @endif
                <a href="{{ route('shop.cart') }}" class="relative inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm font-medium hover:bg-slate-200">
                    🛒 <span class="hidden sm:inline">Cart</span>
                    @if ($cartCount > 0)
                        <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-indigo-600 text-white text-xs flex items-center justify-center">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
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

    @php
        $socialPlatforms = [
            'facebook' => ['Facebook', 'bg-blue-600 hover:bg-blue-700'],
            'instagram' => ['Instagram', 'bg-pink-600 hover:bg-pink-700'],
            'x' => ['X', 'bg-slate-900 hover:bg-black'],
            'tiktok' => ['TikTok', 'bg-slate-900 hover:bg-black'],
            'youtube' => ['YouTube', 'bg-red-600 hover:bg-red-700'],
            'whatsapp' => ['WhatsApp', 'bg-green-600 hover:bg-green-700'],
        ];
        $social = array_filter($store->setting('storefront.social', []) ?: []);
    @endphp
    <footer class="border-t border-slate-200 bg-white">
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                {{-- Store identity + contact (darker, larger for visibility) --}}
                <div>
                    <div class="text-lg font-bold text-slate-900">{{ $store->name }}</div>
                    <div class="mt-2 space-y-1 text-sm text-slate-600">
                        @if ($store->address)
                            <div>📍 {{ $store->address }}</div>
                        @endif
                        @if ($store->phone)
                            <div>📞 <a href="tel:{{ preg_replace('/[^0-9+]/', '', $store->phone) }}" class="font-medium text-slate-700 hover:text-indigo-600">{{ $store->phone }}</a></div>
                        @endif
                    </div>
                </div>

                {{-- Social handles --}}
                @if (! empty($social))
                    <div>
                        <div class="text-sm font-semibold text-slate-900 mb-2">Follow us</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($socialPlatforms as $key => [$label, $color])
                                @if (! empty($social[$key]))
                                    <a href="{{ $social[$key] }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center rounded-full px-3.5 py-1.5 text-xs font-semibold text-white transition {{ $color }}">{{ $label }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 flex flex-wrap justify-between gap-2 text-sm text-slate-500">
                <span>© {{ date('Y') }} {{ $store->name }}. All rights reserved.</span>
                <span>Powered by xismarket</span>
            </div>
        </div>
    </footer>
</body>
</html>
