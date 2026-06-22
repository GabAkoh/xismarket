@extends('storefront.layout')
@section('title', 'Forgot password')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-slate-200 p-6 sm:p-8">
    <h1 class="text-2xl font-bold text-slate-900">Forgot your password?</h1>
    <p class="text-sm text-slate-500 mt-1">Enter your email and we'll send you a link to reset it.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('shop.password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <button class="w-full rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Email reset link</button>
    </form>

    <p class="mt-5 text-sm text-slate-500 text-center">
        <a href="{{ route('shop.login') }}" class="text-indigo-600 font-medium hover:underline">Back to sign in</a>
    </p>
</div>
@endsection
