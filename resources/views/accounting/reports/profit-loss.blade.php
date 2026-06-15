@extends('layouts.app')
@section('title', 'Profit & Loss')

@section('content')
<x-page-header title="Profit & Loss">
    <a href="{{ route('reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to reports</a>
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('reports.profit-loss') }}" class="flex flex-wrap items-end gap-3">
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
</x-card>

<x-card title="Income">
    <table class="w-full text-sm">
        <tbody class="divide-y">
            @forelse ($income as $row)
                <tr>
                    <td class="py-2 text-slate-700"><span class="font-mono text-slate-500">{{ $row['account']->code }}</span> · {{ $row['account']->name }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-slate-400">No income in this period.</td></tr>
            @endforelse
            <tr class="font-semibold text-slate-800 border-t">
                <td class="py-2">Total income</td>
                <td class="text-right tabular-nums">{{ $currentTenant->currency }} {{ number_format($totalIncome, 2) }}</td>
            </tr>
        </tbody>
    </table>
</x-card>

<x-card title="Expenses" class="mt-4">
    <table class="w-full text-sm">
        <tbody class="divide-y">
            @forelse ($expense as $row)
                <tr>
                    <td class="py-2 text-slate-700"><span class="font-mono text-slate-500">{{ $row['account']->code }}</span> · {{ $row['account']->name }}</td>
                    <td class="text-right tabular-nums text-slate-700">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td class="py-2 text-slate-400">No expenses in this period.</td></tr>
            @endforelse
            <tr class="font-semibold text-slate-800 border-t">
                <td class="py-2">Total expenses</td>
                <td class="text-right tabular-nums">{{ $currentTenant->currency }} {{ number_format($totalExpense, 2) }}</td>
            </tr>
        </tbody>
    </table>
</x-card>

<x-card class="mt-4">
    <div class="flex items-center justify-between font-semibold text-lg">
        <span class="text-slate-800">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</span>
        <span class="tabular-nums {{ $netProfit >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $currentTenant->currency }} {{ number_format($netProfit, 2) }}</span>
    </div>
</x-card>
@endsection
