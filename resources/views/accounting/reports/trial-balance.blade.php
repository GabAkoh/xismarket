@extends('layouts.app')
@section('title', 'Trial Balance')

@section('content')
<x-page-header title="Trial Balance">
    <a href="{{ route('reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to reports</a>
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2 w-24">Code</th><th>Account</th><th>Type</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($rows as $row)
                <tr>
                    <td class="py-3 font-mono text-slate-500">{{ $row['account']->code }}</td>
                    <td class="text-slate-700">{{ $row['account']->name }}</td>
                    <td class="text-slate-500 capitalize">{{ $row['type'] }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-slate-400">No posted activity yet.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="font-semibold text-slate-800 border-t">
                <td class="py-3" colspan="3">Totals</td>
                <td class="text-right tabular-nums">{{ $currentTenant->currency }} {{ number_format($totalDebit, 2) }}</td>
                <td class="text-right tabular-nums">{{ $currentTenant->currency }} {{ number_format($totalCredit, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</x-card>
@endsection
