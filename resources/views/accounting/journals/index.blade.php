@extends('layouts.app')
@section('title', 'Journal Entries')

@section('content')
<x-page-header title="Journal Entries">
    @permission('journals.manage')
        <a href="{{ route('journals.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New entry</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Date</th><th>Reference</th><th>Memo</th><th class="text-right">Amount</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($entries as $entry)
                <tr>
                    <td class="py-3 text-slate-600">{{ $entry->entry_date->format('Y-m-d') }}</td>
                    <td class="text-slate-500">{{ $entry->reference ?? '—' }}</td>
                    <td class="font-medium text-slate-700">{{ $entry->memo ?: '—' }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ $currentTenant->currency }} {{ number_format($entry->total_debit ?? 0, 2) }}</td>
                    <td class="text-right">
                        <a href="{{ route('journals.show', $entry) }}" class="text-indigo-600 hover:underline">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-slate-400">No journal entries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-card>
@endsection
