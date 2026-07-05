@extends('layouts.app')
@section('title', 'Account Statement')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $money = fn ($v) => $symbol.' '.number_format((float) $v, 2);
@endphp

<x-page-header title="Account Statement" subtitle="Every posting to an account, with a running balance">
    <a href="{{ route('reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to reports</a>
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('reports.account-statement') }}" class="flex flex-wrap items-end gap-3">
        <div class="w-72">
            <label class="block text-xs font-medium text-slate-500 mb-1">Account</label>
            <x-searchable-select name="account_id" :options="$accountOptions" :selected="(string) optional($account)->id"
                placeholder="Choose an account" :allowNone="false" class="p-2 text-sm" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
            <input name="from" type="date" value="{{ $from->format('Y-m-d') }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
            <input name="to" type="date" value="{{ $to->format('Y-m-d') }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
    </form>
</x-card>

@if (! $account)
    <x-card>
        <p class="py-10 text-center text-slate-400">Choose an account above to view its statement.</p>
    </x-card>
@else
    <x-card>
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 class="text-lg font-semibold text-slate-800">
                <span class="font-mono text-slate-500">{{ $account->code }}</span> · {{ $account->name }}
            </h2>
            <span class="text-sm text-slate-500">
                {{ ucfirst($account->type) }} · {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400 border-b">
                    <tr>
                        <th class="py-2">Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr class="text-slate-500">
                        <td class="py-2" colspan="5">Opening balance</td>
                        <td class="text-right tabular-nums font-medium text-slate-700">{{ $money($opening) }}</td>
                    </tr>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="py-2 whitespace-nowrap text-slate-500">{{ $row->date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('journals.show', $row->entry_id) }}" class="font-mono text-indigo-600 hover:underline">{{ $row->reference ?: '#'.$row->entry_id }}</a>
                            </td>
                            <td class="text-slate-600">{{ $row->memo ?: '—' }}</td>
                            <td class="text-right tabular-nums text-slate-700">{{ $row->debit ? number_format($row->debit, 2) : '' }}</td>
                            <td class="text-right tabular-nums text-slate-700">{{ $row->credit ? number_format($row->credit, 2) : '' }}</td>
                            <td class="text-right tabular-nums font-medium {{ $row->balance < 0 ? 'text-red-600' : 'text-slate-700' }}">{{ number_format($row->balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400">No postings in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-200 font-semibold text-slate-800">
                        <td class="py-2" colspan="3">Period movement</td>
                        <td class="text-right tabular-nums">{{ number_format($periodDebit, 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($periodCredit, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="font-semibold text-slate-800">
                        <td class="py-2" colspan="5">Closing balance</td>
                        <td class="text-right tabular-nums {{ $closing < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $money($closing) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endif
@endsection
