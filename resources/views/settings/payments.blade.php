@extends('layouts.app')
@section('title', 'Online payments')

@section('content')
<x-page-header title="Online payments" subtitle="Accept card payments on the storefront with Paystack." />

@if (session('status'))
    <div class="max-w-2xl mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-2 text-sm text-green-700">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('payments.settings.update') }}" class="max-w-2xl">
    @csrf @method('PUT')

    <x-card>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="enabled" value="1" @checked($enabled) class="mt-0.5 rounded border-slate-300 text-indigo-600">
            <span>
                <span class="font-medium">Enable online card payments (Paystack)</span>
                <span class="block text-xs text-slate-500">When on (and keys are set), checkout offers “Pay now (card)”. When off, only pay-on-delivery is shown.</span>
            </span>
        </label>

        <div class="mt-5">
            <label class="block text-sm font-medium text-slate-700">Paystack public key</label>
            <input name="paystack_public" value="{{ old('paystack_public', $publicKey) }}" placeholder="pk_live_… or pk_test_…"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-sm">
            @error('paystack_public')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">Paystack secret key</label>
            <input name="paystack_secret" type="password" autocomplete="off"
                   placeholder="{{ $hasSecret ? '•••••••• (saved — leave blank to keep)' : 'sk_live_… or sk_test_…' }}"
                   class="mt-1 w-full rounded-md border border-slate-300 p-2 font-mono text-sm">
            <p class="mt-1 text-xs text-slate-400">Stored encrypted and never shown again. Enter a new value only to change it.</p>
            @error('paystack_secret')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="mt-5 rounded-md bg-slate-50 border border-slate-100 p-3 text-xs text-slate-500 space-y-1">
            <p class="font-semibold text-slate-600">Setup</p>
            <p>Get keys from your Paystack dashboard → <span class="font-medium">Settings → API Keys &amp; Webhooks</span>. Use <span class="font-mono">test</span> keys to trial, then <span class="font-mono">live</span> keys.</p>
            <p>Register this <span class="font-medium">Webhook URL</span> in the same Paystack page so payments confirm reliably:</p>
            <p class="font-mono break-all text-slate-600">{{ url('/shop/'.$store->slug.'/paystack/webhook') }}</p>
        </div>
    </x-card>

    <div class="mt-4">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
    </div>
</form>
@endsection
