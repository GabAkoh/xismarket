@extends('layouts.app')
@section('title', 'Balance Sheet')

@section('content')
<x-page-header title="Balance Sheet">
    <a href="{{ route('reports.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back to reports</a>
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('reports.balance-sheet') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium text-slate-700">As of</label>
            <input name="as_of" type="date" value="{{ $asOf->format('Y-m-d') }}" class="mt-1 rounded-md border border-slate-300 p-2">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
    </form>
</x-card>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <x-card title="Assets">
        <table class="w-full text-sm">
            <tbody class="divide-y">
                @forelse ($assets as $row)
                    <tr>
                        <td class="py-2 text-slate-700"><span class="font-mono text-slate-500">{{ $row['account']->code }}</span> · {{ $row['account']->name }}</td>
                        <td class="text-right tabular-nums text-slate-700">{{ number_format($row['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-2 text-slate-400">No assets.</td></tr>
                @endforelse
                <tr class="font-semibold text-slate-800 border-t">
                    <td class="py-2">Total assets</td>
                    <td class="text-right tabular-nums">{{ $currentTenant->currencySymbol() }} {{ number_format($totalAssets, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </x-card>

    <div class="space-y-4">
        <x-card title="Liabilities">
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    @forelse ($liabilities as $row)
                        <tr>
                            <td class="py-2 text-slate-700"><span class="font-mono text-slate-500">{{ $row['account']->code }}</span> · {{ $row['account']->name }}</td>
                            <td class="text-right tabular-nums text-slate-700">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-2 text-slate-400">No liabilities.</td></tr>
                    @endforelse
                    <tr class="font-semibold text-slate-800 border-t">
                        <td class="py-2">Total liabilities</td>
                        <td class="text-right tabular-nums">{{ $currentTenant->currencySymbol() }} {{ number_format($totalLiabilities, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </x-card>

        <x-card title="Equity">
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    @foreach ($equity as $row)
                        <tr>
                            <td class="py-2 text-slate-700"><span class="font-mono text-slate-500">{{ $row['account']->code }}</span> · {{ $row['account']->name }}</td>
                            <td class="text-right tabular-nums text-slate-700">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="py-2 text-slate-700">Net income (current period)</td>
                        <td class="text-right tabular-nums text-slate-700">{{ number_format($netIncome, 2) }}</td>
                    </tr>
                    <tr class="font-semibold text-slate-800 border-t">
                        <td class="py-2">Total equity</td>
                        <td class="text-right tabular-nums">{{ $currentTenant->currencySymbol() }} {{ number_format($totalEquity, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </x-card>
    </div>
</div>

<x-card class="mt-4">
    <div class="flex items-center justify-between text-sm">
        <span class="text-slate-500">Liabilities + Equity</span>
        <span class="tabular-nums font-semibold text-slate-800">{{ $currentTenant->currencySymbol() }} {{ number_format($totalLiabilities + $totalEquity, 2) }}</span>
    </div>
    @php $balanced = round($totalAssets, 2) === round($totalLiabilities + $totalEquity, 2); @endphp
    <p class="mt-2 text-xs {{ $balanced ? 'text-green-600' : 'text-red-600' }}">
        {{ $balanced ? 'Balanced: assets equal liabilities plus equity.' : 'Out of balance — review journal entries.' }}
    </p>
</x-card>
@endsection
