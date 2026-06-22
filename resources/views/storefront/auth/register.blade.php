@extends('storefront.layout')
@section('title', 'Create account')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-slate-200 p-6 sm:p-8">
    <h1 class="text-2xl font-bold text-slate-900">Create your account</h1>
    <p class="text-sm text-slate-500 mt-1">Join the {{ $store->name }} community — track orders and check out faster.</p>

    <form method="POST" action="{{ route('shop.register.store') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Full name</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
            @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Phone <span class="text-slate-400">(optional)</span></label>
            <input type="text" name="phone" value="{{ old('phone') }}"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Password</label>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
            @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Confirm password</label>
            <input type="password" name="password_confirmation" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
        </div>
        <button class="w-full rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Create account</button>
    </form>

    <p class="mt-5 text-sm text-slate-500 text-center">
        Already have an account?
        <a href="{{ route('shop.login') }}" class="text-indigo-600 font-medium hover:underline">Sign in</a>
    </p>
</div>
@endsection
