<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · xismarket</title>
    <script src="https://cdn.tailwindcss.com"></script>
    {{-- Alpine is served locally so the app (esp. the POS register, which is fully Alpine-driven)
         keeps working when the CDN is unreachable; the CDN is kept as an automatic fallback. --}}
    <script defer src="{{ asset('js/alpine.min.js') }}"
            onerror="this.onerror=null;var s=document.createElement('script');s.defer=true;s.src='https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';document.head.appendChild(s);"></script>
    @stack('head')
</head>
<body class="h-full" x-data="{ sidebar: true }">
<div class="min-h-full flex">
    {{-- Sidebar --}}
    <aside class="w-64 shrink-0 bg-slate-900 text-slate-300 flex flex-col" x-show="sidebar" x-cloak>
        <div class="h-16 flex items-center px-6 border-b border-slate-800">
            <a href="{{ route('dashboard') }}" class="text-xl font-extrabold text-white">xismarket</a>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 text-sm">
            @php
                $nav = [
                    ['heading' => null, 'links' => [
                        ['route' => 'dashboard', 'label' => 'Dashboard', 'perm' => null],
                    ]],
                    ['heading' => 'Inventory', 'links' => [
                        ['route' => 'products.index', 'label' => 'Products', 'perm' => 'inventory.view'],
                        ['route' => 'products.report', 'label' => 'Product Report', 'perm' => 'inventory.view'],
                        ['route' => 'products.movements', 'label' => 'Product Movements', 'perm' => 'inventory.view'],
                        ['route' => 'products.valuation', 'label' => 'Stock Valuation', 'perm' => 'inventory.view'],
                        ['route' => 'categories.index', 'label' => 'Categories', 'perm' => 'inventory.view'],
                        ['route' => 'suppliers.index', 'label' => 'Suppliers', 'perm' => 'suppliers.manage'],
                        ['route' => 'warehouses.index', 'label' => 'Warehouses', 'perm' => 'warehouses.manage'],
                        ['route' => 'stock.index', 'label' => 'Stock Levels', 'perm' => 'inventory.view'],
                        ['route' => 'purchases.index', 'label' => 'Purchase Orders', 'perm' => 'purchases.view'],
                        ['route' => 'purchases.report', 'label' => 'Purchase Report', 'perm' => 'purchases.view'],
                    ]],
                    ['heading' => 'Point of Sale', 'links' => [
                        ['route' => 'pos.index', 'label' => 'Register', 'perm' => 'pos.use'],
                        ['route' => 'sales.index', 'label' => 'Sales', 'perm' => 'sales.view'],
                        ['route' => 'sales.report', 'label' => 'Sales Report', 'perm' => 'sales.view'],
                        ['route' => 'customers.index', 'label' => 'Customers', 'perm' => 'customers.view'],
                        ['route' => 'wallets.index', 'label' => 'Wallets', 'perm' => 'customers.view'],
                        ['route' => 'loyalty.settings', 'label' => 'Loyalty Program', 'perm' => 'customers.manage'],
                        ['route' => 'registers.index', 'label' => 'Registers & Shifts', 'perm' => 'registers.manage'],
                        ['route' => 'pos.settings', 'label' => 'Register Display', 'perm' => 'registers.manage'],
                        ['route' => 'payment-methods.settings', 'label' => 'Payment Methods', 'perm' => 'registers.manage'],
                    ]],
                    ['heading' => 'Online Orders', 'links' => [
                        ['route' => 'orders.index', 'label' => 'Orders', 'perm' => 'orders.view'],
                        ['route' => 'orders.report', 'label' => 'Orders Report', 'perm' => 'orders.view'],
                        ['route' => 'orders.create', 'label' => 'New Order', 'perm' => 'orders.manage'],
                        ['route' => 'storefront.settings', 'label' => 'Storefront', 'perm' => 'orders.manage'],
                    ]],
                    ['heading' => 'Delivery', 'links' => [
                        ['route' => 'deliveries.index', 'label' => 'Deliveries', 'perm' => 'deliveries.view'],
                        ['route' => 'drivers.index', 'label' => 'Drivers', 'perm' => 'drivers.manage'],
                    ]],
                    ['heading' => 'Accounting', 'links' => [
                        ['route' => 'accounts.index', 'label' => 'Chart of Accounts', 'perm' => 'accounting.view'],
                        ['route' => 'journals.index', 'label' => 'Journal Entries', 'perm' => 'accounting.view'],
                        ['route' => 'taxes.index', 'label' => 'Tax Rates', 'perm' => 'taxes.manage'],
                        ['route' => 'reports.index', 'label' => 'Financial Reports', 'perm' => 'reports.view'],
                    ]],
                    ['heading' => 'Administration', 'links' => [
                        ['route' => 'users.index', 'label' => 'Staff', 'perm' => 'users.view'],
                        ['route' => 'roles.index', 'label' => 'Roles', 'perm' => 'roles.view'],
                    ]],
                ];
            @endphp

            @foreach ($nav as $section)
                @php
                    $visible = collect($section['links'])->filter(fn ($l) =>
                        \Illuminate\Support\Facades\Route::has($l['route'])
                        && (is_null($l['perm']) || (auth()->user() && auth()->user()->hasPermission($l['perm'])))
                    );
                @endphp
                @if ($visible->isNotEmpty())
                    @if ($section['heading'])
                        <p class="px-6 mt-5 mb-1 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $section['heading'] }}</p>
                    @endif
                    @foreach ($visible as $link)
                        @php $active = request()->routeIs($link['route']) || request()->routeIs(\Illuminate\Support\Str::beforeLast($link['route'], '.').'.*'); @endphp
                        <a href="{{ route($link['route']) }}"
                           class="block px-6 py-2 {{ $active ? 'bg-slate-800 text-white border-l-2 border-indigo-400' : 'hover:bg-slate-800 hover:text-white' }}">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <button @click="sidebar = !sidebar" class="text-slate-500 hover:text-slate-800">☰</button>
                <h1 class="text-lg font-semibold text-slate-800">@yield('title', 'Dashboard')</h1>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-slate-500 hidden sm:block">{{ $currentTenant?->name }}</span>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center gap-2 text-sm text-slate-700">
                        <span class="h-8 w-8 rounded-full bg-indigo-600 text-white flex items-center justify-center text-xs font-bold">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </span>
                        <span class="hidden sm:block">{{ auth()->user()->name }}</span>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-slate-100 py-1 z-20">
                        <div class="px-4 py-2 text-xs text-slate-400 border-b">{{ auth()->user()->email }}</div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
<style>[x-cloak]{display:none!important;}</style>
@stack('scripts')
</body>
</html>
