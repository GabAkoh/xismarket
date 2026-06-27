@extends('layouts.app')
@section('title', 'Statement · '.$customer->name)

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Customer Statement">
    <button onclick="window.print()" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Print</button>
    <a href="{{ route('customers.show', $customer) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

{{-- Date range filter --}}
<x-card class="mb-4 print:hidden">
    <form method="GET" action="{{ route('customers.statement', $customer) }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
        <a href="{{ route('customers.statement', $customer) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">All time</a>
    </form>
</x-card>

<div class="bg-white rounded-lg shadow-sm p-6" id="statement">
    {{-- Letterhead --}}
    <div class="flex flex-wrap justify-between gap-4 border-b border-slate-200 pb-4 mb-4">
        <div>
            <h2 class="text-lg font-bold text-slate-800">{{ $currentTenant->name }}</h2>
            <p class="text-sm text-slate-500">Statement of account</p>
        </div>
        <div class="text-sm text-right">
            <p class="font-semibold text-slate-700">{{ $customer->name }}</p>
            @if ($customer->loyalty_no)<p class="text-slate-500">Loyalty: {{ $customer->loyalty_no }}</p>@endif
            @if ($customer->identity_number)<p class="text-slate-500">ID: {{ $customer->identity_number }}</p>@endif
            @if ($customer->email)<p class="text-slate-500">{{ $customer->email }}</p>@endif
            @if ($customer->phone)<p class="text-slate-500">{{ $customer->phone }}</p>@endif
            <p class="text-slate-400 mt-1">
                {{ $from ? $from->format('d M Y') : 'Beginning' }} – {{ $to ? $to->format('d M Y') : \Illuminate\Support\Carbon::today()->format('d M Y') }}
            </p>
        </div>
    </div>

    {{-- Ledger --}}
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr>
                <th class="py-2">Date</th><th>Reference</th><th>Description</th>
                <th class="text-right">Charges</th><th class="text-right">Payments</th><th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <tr class="text-slate-500">
                <td class="py-2" colspan="5">Opening balance</td>
                <td class="text-right tabular-nums font-medium">{{ $symbol }} {{ number_format($opening, 2) }}</td>
            </tr>
            @forelse ($rows as $row)
                <tr>
                    <td class="py-2 text-slate-500">{{ $row['date']->format('d M Y') }}</td>
                    <td class="text-slate-600">
                        @permission('sales.view')
                            <a href="{{ route('sales.show', $row['sale_id']) }}" class="text-indigo-600 hover:underline">{{ $row['ref'] }}</a>
                        @else
                            {{ $row['ref'] }}
                        @endpermission
                    </td>
                    <td class="text-slate-600">{{ $row['description'] }}</td>
                    <td class="text-right tabular-nums text-slate-600">{{ $row['charge'] > 0 ? number_format($row['charge'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums text-green-600">{{ $row['payment'] > 0 ? number_format($row['payment'], 2) : '—' }}</td>
                    <td class="text-right tabular-nums font-medium text-slate-700">{{ $symbol }} {{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-400">No activity in this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="border-t font-semibold text-slate-800">
                <td class="py-3" colspan="3">Period totals</td>
                <td class="text-right tabular-nums">{{ number_format($totalCharges, 2) }}</td>
                <td class="text-right tabular-nums">{{ number_format($totalPayments, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    {{-- Closing balance --}}
    <div class="mt-6 flex justify-end">
        <div class="w-full sm:w-72 rounded-lg border {{ $closing > 0 ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' }} p-4">
            <div class="flex justify-between text-sm">
                <span class="text-slate-600">Opening balance</span>
                <span class="tabular-nums">{{ $symbol }} {{ number_format($opening, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-600">Invoiced</span>
                <span class="tabular-nums">{{ $symbol }} {{ number_format($totalCharges, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-600">Paid</span>
                <span class="tabular-nums">− {{ $symbol }} {{ number_format($totalPayments, 2) }}</span>
            </div>
            <div class="flex justify-between font-bold text-base pt-2 mt-2 border-t {{ $closing > 0 ? 'text-red-700' : 'text-green-700' }}">
                <span>{{ $closing > 0 ? 'Balance due' : 'Balance' }}</span>
                <span class="tabular-nums">{{ $symbol }} {{ number_format($closing, 2) }}</span>
            </div>
        </div>
    </div>

    @if ($closing > 0 && Route::has('sales.payment'))
        <p class="mt-4 text-xs text-slate-400 print:hidden">Tip: record a payment against an outstanding invoice from its sale page.</p>
    @endif
</div>

@push('head')
<style>@media print { aside, header, .print\:hidden, button { display:none !important; } main { padding:0 !important; } #statement { box-shadow:none !important; } }</style>
@endpush
@endsection
