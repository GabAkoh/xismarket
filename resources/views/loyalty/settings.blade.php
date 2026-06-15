@extends('layouts.app')
@section('title', 'Loyalty program')

@section('content')
@php $symbol = $currentTenant->currency ?? ''; @endphp

<x-page-header title="Loyalty program" subtitle="Reward customers with points they can redeem for store discounts." />

<form method="POST" action="{{ route('loyalty.update') }}" class="max-w-2xl" x-data="{
    earn: {{ (float) $loyalty->earn_rate }},
    redeem: {{ (float) $loyalty->redeem_value }},
}">
    @csrf @method('PUT')

    <x-card>
        <label class="flex items-center gap-3 mb-5">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($loyalty->is_active)
                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm font-medium text-slate-700">Loyalty program active</span>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Earn rate</label>
                <input type="number" name="earn_rate" step="0.0001" min="0" x-model.number="earn"
                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <p class="text-xs text-slate-400 mt-1">Points earned per 1 {{ $symbol }} of net spend.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Redeem value ({{ $symbol }})</label>
                <input type="number" name="redeem_value" step="0.0001" min="0" x-model.number="redeem"
                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <p class="text-xs text-slate-400 mt-1">Cash value of 1 point when redeemed.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Minimum points to redeem</label>
                <input type="number" name="min_redeem_points" step="1" min="0" value="{{ $loyalty->min_redeem_points }}"
                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
            </div>
        </div>

        <div class="mt-5 rounded-md bg-slate-50 border border-slate-100 p-3 text-sm text-slate-600">
            Example: spending <span class="font-semibold">{{ $symbol }} 100</span> earns
            <span class="font-semibold text-amber-600" x-text="Math.floor(100 * earn)"></span> points,
            worth <span class="font-semibold text-indigo-600" x-text="'{{ $symbol }} ' + (Math.floor(100 * earn) * redeem).toFixed(2)"></span> off a future purchase.
        </div>
    </x-card>

    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('customers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
