@extends('layouts.app')
@section('title', 'Storefront content')

@section('content')
<x-page-header title="Storefront content">
    <a href="{{ route('shop.home', ['store' => $store->slug]) }}" target="_blank" rel="noopener"
       class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">View storefront ↗</a>
</x-page-header>

<form method="POST" action="{{ route('storefront.settings.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
    @csrf @method('PUT')

    {{-- Business details (shown on the storefront, receipts and order emails) --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Business details</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Phone</label>
                <input name="phone" maxlength="50" value="{{ old('phone', $store->phone) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Address</label>
                <input name="address" maxlength="255" value="{{ old('address', $store->address) }}"
                       class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
        </div>
        <p class="mt-1 text-xs text-slate-400">Displayed in the shop footer, on POS receipts, and on order emails.</p>
    </x-card>

    {{-- Branding --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Logo</h2>
        @php $logo = $store->setting('storefront.logo'); @endphp
        @if ($logo)
            <div class="mb-2 flex items-center gap-3">
                <img src="{{ asset('storage/'.$logo) }}" alt="Logo" class="h-10 rounded border border-slate-200 bg-slate-50 object-contain px-2">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remove_logo" value="1">
                    Remove logo
                </label>
            </div>
        @endif
        <input type="file" name="logo" accept="image/*"
               class="w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
        <p class="mt-1 text-xs text-slate-400">Shown in the shop header in place of the store name. JPG, PNG, WEBP or GIF up to 2&nbsp;MB.</p>
        @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </x-card>

    {{-- Promo bar --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Promotion bar</h2>
        <label class="flex items-center gap-2 text-sm text-slate-700 mb-3">
            <input type="checkbox" name="promo_enabled" value="1" @checked(old('promo_enabled', $store->setting('storefront.promo_enabled', true)))>
            Show the promotion bar at the top of the shop
        </label>
        <label class="block text-sm font-medium text-slate-700">Message</label>
        <input name="promo" maxlength="255"
               value="{{ old('promo', $store->setting('storefront.promo')) }}"
               placeholder="Free delivery on orders over {{ $store->currencySymbol() }} 150 · Shop the latest arrivals today"
               class="mt-1 w-full rounded-md border border-slate-300 p-2">
    </x-card>

    {{-- Hero --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Hero section</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Headline</label>
                <input name="hero_title" maxlength="150"
                       value="{{ old('hero_title', $store->setting('storefront.hero_title')) }}"
                       placeholder="Quality you can trust. Value you can feel."
                       class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Sub-heading</label>
                <textarea name="hero_subtitle" rows="2" maxlength="500"
                          placeholder="Discover a hand-picked selection from {{ $store->name }} — everyday essentials and special finds, delivered to your door."
                          class="mt-1 w-full rounded-md border border-slate-300 p-2">{{ old('hero_subtitle', $store->setting('storefront.hero_subtitle')) }}</textarea>
            </div>
            @php $heroImage = $store->setting('storefront.hero_image'); @endphp
            <div>
                <label class="block text-sm font-medium text-slate-700">Hero image</label>
                @if ($heroImage)
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ asset('storage/'.$heroImage) }}" alt="Hero image" class="h-20 w-32 rounded-md border border-slate-200 object-cover">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_hero_image" value="1">
                            Remove image
                        </label>
                    </div>
                @endif
                <input type="file" name="hero_image" accept="image/*"
                       class="mt-2 w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-slate-400">JPG, PNG, WEBP or GIF up to 4&nbsp;MB. If empty, the newest product image is used. Landscape (4:3) works best.</p>
                @error('hero_image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </x-card>

    {{-- Brand story --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-3">Brand story</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Title</label>
                <input name="story_title" maxlength="150"
                       value="{{ old('story_title', $store->setting('storefront.story_title')) }}"
                       placeholder="Our Story"
                       class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Body</label>
                <textarea name="story_body" rows="5" maxlength="2000"
                          placeholder="{{ $store->name }} was built on a simple idea: bring you dependable quality at a fair price…"
                          class="mt-1 w-full rounded-md border border-slate-300 p-2">{{ old('story_body', $store->setting('storefront.story_body')) }}</textarea>
            </div>
            @php $storyImage = $store->setting('storefront.story_image'); @endphp
            <div>
                <label class="block text-sm font-medium text-slate-700">Story image</label>
                @if ($storyImage)
                    <div class="mt-2 flex items-center gap-3">
                        <img src="{{ asset('storage/'.$storyImage) }}" alt="Story image" class="h-20 w-20 rounded-md border border-slate-200 object-cover">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_story_image" value="1">
                            Remove image
                        </label>
                    </div>
                @endif
                <input type="file" name="story_image" accept="image/*"
                       class="mt-2 w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-slate-400">Shown beside your story on the storefront. JPG, PNG, WEBP or GIF up to 4&nbsp;MB. Square works best.</p>
                @error('story_image')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </x-card>

    {{-- Shipping methods --}}
    @php $shippingRows = old('shipping_methods', $store->shippingMethods()); @endphp
    <div class="bg-white rounded-lg shadow-sm p-5" x-data="{ rows: @js(array_values($shippingRows)) }">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-sm font-semibold text-slate-700">Shipping methods</h2>
            <button type="button" @click="rows.push({ label: '', fee: '0', pickup: false })" class="text-sm font-medium text-indigo-600 hover:underline">+ Add method</button>
        </div>
        <p class="text-xs text-slate-400 mb-3">The options shoppers choose at online checkout. Mark a method as “Pickup” if it needs no delivery address.</p>

        <div class="flex items-center gap-2 px-1 pb-1 text-xs font-medium uppercase tracking-wider text-slate-400">
            <span class="flex-1">Method name</span>
            <span class="w-28">Fee ({{ $store->currencySymbol() }})</span>
            <span class="w-20 text-center">Pickup</span>
            <span class="w-5"></span>
        </div>
        <template x-for="(row, i) in rows" :key="i">
            <div class="mb-2 flex items-center gap-2">
                <input type="text" :name="`shipping_methods[${i}][label]`" x-model="row.label" maxlength="100"
                       placeholder="e.g. Express Delivery" class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
                <input type="hidden" :name="`shipping_methods[${i}][pickup]`" :value="row.pickup ? 1 : 0">
                <input type="number" :name="`shipping_methods[${i}][fee]`" x-model="row.fee" min="0" step="0.01"
                       class="w-28 rounded-md border border-slate-300 p-2 text-sm text-right">
                <span class="w-20 flex justify-center">
                    <input type="checkbox" x-model="row.pickup" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                </span>
                <button type="button" @click="rows.splice(i, 1)" class="w-5 text-slate-300 hover:text-red-500 text-sm" title="Remove">✕</button>
            </div>
        </template>
        <p class="mt-2 text-xs text-slate-400">Leave all rows empty to fall back to the defaults (Standard Delivery + Store Pickup).</p>
    </div>

    {{-- Social media --}}
    @php
        $socialFields = [
            'facebook' => ['Facebook', 'https://facebook.com/yourpage'],
            'instagram' => ['Instagram', 'https://instagram.com/yourhandle'],
            'x' => ['X (Twitter)', 'https://x.com/yourhandle'],
            'tiktok' => ['TikTok', 'https://tiktok.com/@yourhandle'],
            'youtube' => ['YouTube', 'https://youtube.com/@yourchannel'],
            'whatsapp' => ['WhatsApp', 'https://wa.me/2348012345678'],
        ];
        $social = old('social', $store->setting('storefront.social', []));
    @endphp
    <x-card>
        <h2 class="text-sm font-semibold text-slate-700 mb-1">Social media</h2>
        <p class="text-xs text-slate-400 mb-3">Add full links to your profiles. Only the ones you fill in are shown in the shop footer.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach ($socialFields as $key => [$label, $placeholder])
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                    <input type="url" name="social[{{ $key }}]" maxlength="255"
                           value="{{ $social[$key] ?? '' }}" placeholder="{{ $placeholder }}"
                           class="mt-1 w-full rounded-md border border-slate-300 p-2 text-sm">
                </div>
            @endforeach
        </div>
    </x-card>

    {{-- Testimonials --}}
    @php
        $testimonialRows = array_values(old('testimonials', $store->setting('storefront.testimonials', [])) ?: []);
    @endphp
    <div class="bg-white rounded-lg shadow-sm p-5" x-data="{ rows: @js($testimonialRows ?: [['name' => '', 'text' => '']]) }">
        <div class="flex items-center justify-between mb-1">
            <h2 class="text-sm font-semibold text-slate-700">Testimonials</h2>
            <button type="button" @click="rows.push({ name: '', text: '' })" class="text-sm font-medium text-indigo-600 hover:underline">+ Add review</button>
        </div>
        <p class="text-xs text-slate-400 mb-3">Leave every row empty to show the default sample reviews on the storefront.</p>

        <template x-for="(row, i) in rows" :key="i">
            <div class="mb-3 rounded-md border border-slate-100 p-3">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-400" x-text="'Review #' + (i + 1)"></span>
                    <button type="button" @click="rows.splice(i, 1)" class="text-xs text-red-500 hover:underline">Remove</button>
                </div>
                <input type="text" :name="`testimonials[${i}][name]`" x-model="row.name" maxlength="100"
                       placeholder="Customer name" class="w-full rounded-md border border-slate-300 p-2 text-sm mb-2">
                <textarea :name="`testimonials[${i}][text]`" x-model="row.text" rows="2" maxlength="500"
                          placeholder="What they said about your store…" class="w-full rounded-md border border-slate-300 p-2 text-sm"></textarea>
            </div>
        </template>
    </div>

    <p class="text-xs text-slate-400">Leave a field blank to use the default shown in grey. The store phone and email in the story section come from your tenant profile.</p>

    <div class="flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
    </div>
</form>
@endsection
