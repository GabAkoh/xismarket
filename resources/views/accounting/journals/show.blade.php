@extends('layouts.app')
@section('title', 'Journal entry')

@section('content')
<x-page-header title="Journal entry #{{ $entry->id }}">
    <a href="{{ route('journals.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<x-card class="mb-4">
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div>
            <dt class="text-slate-400">Date</dt>
            <dd class="text-slate-700 font-medium">{{ $entry->entry_date->format('Y-m-d') }}</dd>
        </div>
        <div>
            <dt class="text-slate-400">Reference</dt>
            <dd class="text-slate-700 font-medium">{{ $entry->reference ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-slate-400">Posted by</dt>
            <dd class="text-slate-700 font-medium">{{ $entry->user?->name ?? 'System' }}</dd>
        </div>
        <div>
            <dt class="text-slate-400">Status</dt>
            <dd>{!! $entry->posted ? '<span class="text-xs text-green-600">● Posted</span>' : '<span class="text-xs text-slate-400">Draft</span>' !!}</dd>
        </div>
        @if ($entry->memo)
            <div class="col-span-2 sm:col-span-4">
                <dt class="text-slate-400">Memo</dt>
                <dd class="text-slate-700">{{ $entry->memo }}</dd>
            </div>
        @endif
    </dl>
</x-card>

<x-card title="Lines">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Account</th><th>Memo</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr>
        </thead>
        <tbody class="divide-y">
            @foreach ($entry->lines as $line)
                <tr>
                    <td class="py-3 text-slate-700">
                        <span class="font-mono text-slate-500">{{ $line->account->code }}</span> · {{ $line->account->name }}
                    </td>
                    <td class="text-slate-500">{{ $line->memo ?? '—' }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-semibold text-slate-800 border-t">
                <td class="py-3" colspan="2">Totals</td>
                <td class="text-right tabular-nums">{{ $currentTenant->currency }} {{ number_format($entry->totalDebit(), 2) }}</td>
                <td class="text-right tabular-nums">{{ $currentTenant->currency }} {{ number_format($entry->totalCredit(), 2) }}</td>
            </tr>
        </tfoot>
    </table>
</x-card>
@endsection
