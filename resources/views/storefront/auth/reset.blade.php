@extends('storefront.layout')
@section('title', 'Reset password')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-2xl border border-slate-200 p-6 sm:p-8">
    <h1 class="text-2xl font-bold text-slate-900">Choose a new password</h1>

    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('shop.password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label class="block text-sm font-medium text-slate-700">Email</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">New password</label>
            <input type="password" name="password" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
            @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Confirm new password</label>
            <input type="password" name="password_confirmation" required
                   class="mt-1 w-full rounded-md border border-slate-300 p-2.5">
        </div>
        <button class="w-full rounded-full bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Reset password</button>
    </form>
</div>
@endsection
