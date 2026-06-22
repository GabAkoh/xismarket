@extends('storefront.layout')
@section('title', 'Sign in')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-slate-200 p-6 sm:p-8">
    <h1 class="text-2xl font-bold text-slate-900">Welcome back</h1>
    <p class="text-sm text-slate-500 mt-1">Sign in to your {{ $store->name }} account.</p>

    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('shop.login.store') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-slate-700">Password</label>
                <a href="{{ route('shop.password.request') }}" class="text-xs text-indigo-600 hover:underline">Forgot password?</a>
            </div>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
        </div>
        <button class="w-full rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Sign in</button>
    </form>

    <p class="mt-5 text-sm text-slate-500 text-center">
        New to {{ $store->name }}?
        <a href="{{ route('shop.register') }}" class="text-indigo-600 font-medium hover:underline">Create an account</a>
    </p>
</div>
@endsection
