@props(['title' => 'xismarket'])
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="{{ asset('js/alpine.min.js') }}"
            onerror="this.onerror=null;var s=document.createElement('script');s.defer=true;s.src='https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';document.head.appendChild(s);"></script>
</head>
<body class="h-full">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <a href="{{ url('/') }}" class="text-3xl font-extrabold tracking-tight text-indigo-600">xismarket</a>
            <p class="mt-1 text-sm text-slate-500">Inventory · POS · Accounting · Users</p>
        </div>
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-6 shadow rounded-lg sm:px-10">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
