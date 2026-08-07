<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed · {{ $store?->name ?? 'Shop' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-50 flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
        <div class="text-4xl mb-3">📭</div>
        <h1 class="text-xl font-bold text-slate-800">You've been unsubscribed</h1>
        <p class="mt-2 text-sm text-slate-500">
            @if ($email)
                <span class="font-medium text-slate-700">{{ $email }}</span> will no longer receive
            @else
                You'll no longer receive
            @endif
            community emails from {{ $store?->name ?? 'this store' }}. Changed your mind? You can re-subscribe any time from the storefront.
        </p>
        @if ($store)
            <a href="{{ route('shop.home', ['store' => $store->slug]) }}"
               class="mt-6 inline-block rounded-full bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Back to {{ $store->name }}</a>
        @endif
    </div>
</body>
</html>
