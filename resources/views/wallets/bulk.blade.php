@extends('layouts.app')
@section('title', 'Bulk wallet top-up')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Bulk wallet top-up" subtitle="Add the same store credit to several customers at once">
    <a href="{{ route('wallets.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<form method="POST" action="{{ route('wallets.bulk.store') }}" class="max-w-3xl space-y-4" x-data="{ q: '' }"
      onsubmit="return confirm('Apply this top-up to all selected customers?')">
    @csrf

    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Amount per customer ({{ $symbol }})</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Method</label>
                <select name="method" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Reason (optional)</label>
                <input type="text" name="reason" maxlength="255" value="{{ old('reason') }}" class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h2 class="text-sm font-semibold text-slate-700">Customers</h2>
            <div class="flex items-center gap-2">
                <input x-model="q" placeholder="Filter…" class="rounded-md border border-slate-300 p-1.5 text-sm">
                <button type="button" class="text-xs text-indigo-600 hover:underline"
                        @click="$root.querySelectorAll('input[type=checkbox]').forEach(c => { if (c.closest('label').style.display !== 'none') c.checked = true })">Select all</button>
                <button type="button" class="text-xs text-slate-500 hover:underline"
                        @click="$root.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false)">Clear</button>
            </div>
        </div>

        @if ($customers->isEmpty())
            <p class="text-sm text-slate-400">No customers yet.</p>
        @else
            <div class="max-h-80 overflow-y-auto divide-y border border-slate-100 rounded-md">
                @foreach ($customers as $c)
                    <label class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-slate-50"
                           x-show="q === '' || @js(strtolower($c->name.' '.(string) $c->phone)).includes(q.toLowerCase())">
                        <span class="flex items-center gap-2 min-w-0">
                            <input type="checkbox" name="customer_ids[]" value="{{ $c->id }}">
                            <span class="text-slate-700 truncate">{{ $c->name }}</span>
                            @if ($c->phone)<span class="text-xs text-slate-400">{{ $c->phone }}</span>@endif
                        </span>
                        <span class="text-xs text-slate-400 shrink-0">Bal: {{ $symbol }} {{ number_format($c->balance, 2) }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-400">The amount above is added to each selected customer (a funded top-up, posted to the books per customer).</p>
        @endif
    </x-card>

    <div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply top-up</button>
    </div>
</form>
@endsection
