@extends('layouts.app')
@section('title', 'Financial Reports')

@section('content')
<x-page-header title="Financial Reports" />

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <a href="{{ route('reports.trial-balance') }}" class="block">
        <x-card>
            <h3 class="font-semibold text-slate-800">Trial Balance</h3>
            <p class="mt-1 text-sm text-slate-500">Debit and credit totals for every account.</p>
        </x-card>
    </a>
    @if (Route::has('reports.receivables'))
        <a href="{{ route('reports.receivables') }}" class="block">
            <x-card>
                <h3 class="font-semibold text-slate-800">Accounts Receivable</h3>
                <p class="mt-1 text-sm text-slate-500">Outstanding customer balances, aged.</p>
            </x-card>
        </a>
    @endif
    <a href="{{ route('reports.profit-loss') }}" class="block">
        <x-card>
            <h3 class="font-semibold text-slate-800">Profit &amp; Loss</h3>
            <p class="mt-1 text-sm text-slate-500">Income versus expense over a date range.</p>
        </x-card>
    </a>
    <a href="{{ route('reports.balance-sheet') }}" class="block">
        <x-card>
            <h3 class="font-semibold text-slate-800">Balance Sheet</h3>
            <p class="mt-1 text-sm text-slate-500">Assets, liabilities and equity as of a date.</p>
        </x-card>
    </a>
</div>
@endsection
