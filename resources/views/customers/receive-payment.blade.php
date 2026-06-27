@extends('layouts.app')
@section('title', 'Receive payment')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header :title="'Receive payment — '.$customer->name"
               subtitle="Apply one payment across this customer's open balances (oldest first).">
    <a href="{{ route('customers.show', $customer) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Open balances --}}
    <div class="lg:col-span-2">
        <x-card title="Open balances">
            @if ($openSales->isEmpty())
                <p class="text-sm text-slate-400">No open credit sales. Any amount received will be added to store credit (an advance on account).</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400 border-b">
                        <tr><th class="py-2">Sale</th><th>Date</th><th class="text-right">Total</th><th class="text-right">Balance due</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($openSales as $sale)
                            <tr>
                                <td class="py-2">
                                    @if (Route::has('sales.show'))
                                        <a href="{{ route('sales.show', $sale) }}" class="font-medium text-indigo-600 hover:underline">{{ $sale->number }}</a>
                                    @else
                                        <span class="font-medium text-slate-700">{{ $sale->number }}</span>
                                    @endif
                                </td>
                                <td class="text-slate-400">{{ $sale->completed_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="text-right">{{ $symbol }} {{ number_format($sale->total, 2) }}</td>
                                <td class="text-right font-medium text-rose-600">{{ $symbol }} {{ number_format($sale->balance_due, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t font-semibold">
                            <td class="py-2" colspan="3">Total outstanding</td>
                            <td class="text-right text-rose-600">{{ $symbol }} {{ number_format($outstanding, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </x-card>
    </div>

    {{-- Payment form --}}
    <div>
        <x-card title="Payment">
            <form method="POST" action="{{ route('customers.receive-payment.store', $customer) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Amount ({{ $symbol }})</label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                           value="{{ old('amount', $outstanding > 0 ? number_format($outstanding, 2, '.', '') : '') }}"
                           required class="w-full rounded-md border border-slate-300 p-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Method</label>
                    <select name="method" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                        @foreach ($methods as $m)
                            <option value="{{ $m['key'] }}" @selected(old('method') === $m['key'])>{{ $m['label'] }}</option>
                        @endforeach
                        @if ((float) $customer->balance > 0)
                            <option value="wallet" @selected(old('method') === 'wallet')>Wallet ({{ $symbol }} {{ number_format($customer->balance, 2) }})</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Reference (optional)</label>
                    <input type="text" name="reference" maxlength="255" value="{{ old('reference') }}"
                           placeholder="e.g. transfer ref" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                </div>
                <label class="flex items-start gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="remainder_to_credit" value="1" checked class="mt-0.5 rounded border-slate-300 text-indigo-600">
                    <span>Add any overpayment (beyond what's owed) to store credit as an advance.</span>
                </label>
                <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Receive payment</button>
            </form>
            <p class="mt-3 text-xs text-slate-400">Applied oldest-first to open sales; each settled sale posts to Accounts Receivable. Wallet payments draw down existing store credit.</p>
        </x-card>
    </div>
</div>
@endsection
