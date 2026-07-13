@extends('layouts.app')
@section('title', 'Pay supplier')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header :title="'Pay supplier — '.$supplier->name"
               subtitle="Settle what you owe this vendor. Debits the vendor's payable and credits the account you pay from.">
    <a href="{{ route('suppliers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

@if (session('error'))
    <div class="max-w-lg mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-2 text-sm text-red-700">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <x-card title="Outstanding">
            <div class="flex items-baseline justify-between">
                <span class="text-sm text-slate-500">Balance owed to {{ $supplier->name }}</span>
                <span class="text-2xl font-bold {{ $outstanding > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ $symbol }} {{ number_format($outstanding, 2) }}</span>
            </div>
            <p class="mt-3 text-xs text-slate-400">This balance is the vendor's Accounts-Payable account (raised each time a purchase order is received). Payments are capped to it.</p>
        </x-card>
    </div>

    <div>
        <x-card title="Payment">
            @if ($outstanding <= 0)
                <p class="text-sm text-slate-400">Nothing is currently owed to this vendor.</p>
            @else
                <form method="POST" action="{{ route('suppliers.pay.store', $supplier) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Amount ({{ $symbol }})</label>
                        <input type="number" name="amount" step="0.01" min="0.01" max="{{ number_format($outstanding, 2, '.', '') }}"
                               value="{{ old('amount', number_format($outstanding, 2, '.', '')) }}"
                               required class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Pay from</label>
                        <select name="account" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->code }}" @selected(old('account', '1000') === $account->code)>{{ $account->code }} · {{ $account->name }}</option>
                            @endforeach
                        </select>
                        @error('account')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Reference (optional)</label>
                        <input type="text" name="reference" maxlength="255" value="{{ old('reference') }}"
                               placeholder="e.g. transfer ref" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                    </div>
                    <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Record payment</button>
                </form>
                <p class="mt-3 text-xs text-slate-400">Posts Dr Accounts Payable / Cr the account you pay from.</p>
            @endif
        </x-card>
    </div>
</div>
@endsection
