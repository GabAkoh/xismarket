@extends('layouts.app')
@section('title', 'Trial Balance')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol();
    // Signed balance (positive = debit) rendered with a Dr/Cr tag.
    $bal = fn ($v) => $v == 0
        ? '—'
        : number_format(abs($v), 2).' <span class="text-xs text-slate-400">'.($v > 0 ? 'Dr' : 'Cr').'</span>';
@endphp

<x-page-header title="Trial Balance">
    <a href="{{ route('reports.trial-balance.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Export (CSV)</a>
    <a href="{{ route('reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to reports</a>
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('reports.trial-balance') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium text-slate-700">From</label>
            <input name="from" type="date" value="{{ $from->format('Y-m-d') }}" class="mt-1 rounded-md border border-slate-300 p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">To</label>
            <input name="to" type="date" value="{{ $to->format('Y-m-d') }}" class="mt-1 rounded-md border border-slate-300 p-2">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
    </form>
    <p class="mt-2 text-xs text-slate-400">Opening carries all activity before {{ $from->toDateString() }}; movement is {{ $from->toDateString() }} to {{ $to->toDateString() }}. Dr = debit balance, Cr = credit balance.</p>
</x-card>

<x-card>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2 w-20">Code</th>
                <th>Account</th>
                <th>Type</th>
                <th class="text-right">Opening</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Closing</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $row)
                <tr>
                    <td class="py-3 font-mono text-slate-500">{{ $row['account']->code }}</td>
                    <td class="text-slate-700">{{ $row['account']->name }}</td>
                    <td class="text-slate-500 capitalize">{{ $row['type'] }}</td>
                    <td class="text-right tabular-nums text-slate-600">{!! $bal($row['opening']) !!}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums font-medium text-slate-800">{!! $bal($row['closing']) !!}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-4 text-slate-400">No posted activity in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="font-semibold text-slate-800 border-t">
                <td class="py-3" colspan="3">Totals</td>
                <td class="text-right tabular-nums">{{ $symbol }} {{ number_format($totalOpeningDebit, 2) }}</td>
                <td class="text-right tabular-nums">{{ $symbol }} {{ number_format($totalDebit, 2) }}</td>
                <td class="text-right tabular-nums">{{ $symbol }} {{ number_format($totalCredit, 2) }}</td>
                <td class="text-right tabular-nums">{{ $symbol }} {{ number_format($totalClosingDebit, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    </div>
    @if (abs($totalDebit - $totalCredit) > 0.005 || abs($totalOpening) > 0.005 || abs($totalClosing) > 0.005)
        <p class="mt-3 text-xs font-medium text-rose-600">Warning: the ledger does not balance for this range (debit ≠ credit). This usually means an unbalanced journal entry.</p>
    @else
        <p class="mt-3 text-xs text-slate-400">In balance: total debits equal total credits.</p>
    @endif
</x-card>
@endsection
