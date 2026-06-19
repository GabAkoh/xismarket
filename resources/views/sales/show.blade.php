@extends('layouts.app')
@section('title', 'Sale '.$sale->number)

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Sale {{ $sale->number }}">
    <a href="{{ route('pos.receipt', $sale) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Receipt</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2">
        <x-card title="Items">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400 border-b">
                    <tr><th class="py-2">Product</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Disc</th><th class="text-right">Tax</th><th class="text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="py-2">
                                <div class="text-slate-700">{{ $item->name }}</div>
                                <div class="text-xs text-slate-400">{{ $item->sku }}</div>
                            </td>
                            <td class="text-right text-slate-600">
                                {{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}
                                @if ((float) $item->returned_quantity > 0)
                                    <div class="text-xs text-amber-600">− {{ rtrim(rtrim(number_format($item->returned_quantity, 3), '0'), '.') }} returned</div>
                                @endif
                            </td>
                            <td class="text-right text-slate-600">{{ $symbol }}{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-right text-slate-600">{{ $symbol }}{{ number_format($item->discount, 2) }}</td>
                            <td class="text-right text-slate-600">{{ number_format($item->tax_rate * 100, 2) }}%</td>
                            <td class="text-right font-medium text-slate-700">{{ $symbol }}{{ number_format($item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </div>

    <div class="space-y-4">
        <x-card title="Summary">
            <dl class="text-sm space-y-1.5">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd>{{ $symbol }}{{ number_format($sale->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd>−{{ $symbol }}{{ number_format($sale->discount_total, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Tax</dt><dd>{{ $symbol }}{{ number_format($sale->tax_total, 2) }}</dd></div>
                <div class="flex justify-between font-bold text-slate-800 pt-1 border-t"><dt>Total</dt><dd>{{ $symbol }}{{ number_format($sale->total, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd>{{ $symbol }}{{ number_format($sale->paid_total, 2) }}</dd></div>
                @if ((float) $sale->refunded_total > 0)
                    <div class="flex justify-between text-red-600"><dt>Refunded</dt><dd>−{{ $symbol }}{{ number_format($sale->refunded_total, 2) }}</dd></div>
                @endif
                @if ($sale->balance_due > 0)
                    <div class="flex justify-between font-semibold text-red-600"><dt>Balance due</dt><dd>{{ $symbol }}{{ number_format($sale->balance_due, 2) }}</dd></div>
                @else
                    <div class="flex justify-between"><dt class="text-slate-500">Change</dt><dd>{{ $symbol }}{{ number_format($sale->change_due, 2) }}</dd></div>
                @endif
            </dl>
        </x-card>

        @if ($sale->isPartiallyPaid())
            @permission('pos.use')
            @php
                // A method already recorded on this sale can't be used again (any amount).
                $usedMethods = $sale->payments->pluck('method')->all();
                $methodOptions = $currentTenant->paymentMethodLabels();
                // A credit settlement is real money — don't offer credit methods here.
                foreach ($currentTenant->creditPaymentMethodKeys() as $creditKey) {
                    unset($methodOptions[$creditKey]);
                }
                if ($sale->customer && $sale->customer->balance > 0) {
                    $methodOptions['wallet'] = 'Wallet ('.$symbol.number_format($sale->customer->balance, 2).')';
                }
                $availableMethods = array_diff(array_keys($methodOptions), $usedMethods);
            @endphp
            <x-card title="Record payment">
                <p class="text-sm text-slate-500 mb-3">Outstanding balance:
                    <span class="font-semibold text-red-600">{{ $symbol }}{{ number_format($sale->balance_due, 2) }}</span></p>
                @if (empty($availableMethods))
                    <p class="text-sm text-amber-600">Every payment method has already been used on this sale.</p>
                @else
                    <form method="POST" action="{{ route('sales.payment', $sale) }}" class="space-y-3">
                        @csrf
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-slate-500 mb-1">Amount ({{ $symbol }})</label>
                                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $sale->balance_due }}"
                                       value="{{ number_format((float) $sale->balance_due, 2, '.', '') }}" required
                                       class="w-full rounded-md border border-slate-300 p-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Method</label>
                                <select name="method" class="rounded-md border border-slate-300 p-2 text-sm">
                                    @foreach ($availableMethods as $val)
                                        <option value="{{ $val }}">{{ $methodOptions[$val] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Record payment</button>
                    </form>
                @endif
            </x-card>
            @endpermission
        @endif

        {{-- Returns — partial (line-level) or full --}}
        @permission('sales.refund')
            @if (! in_array($sale->status, ['refunded', 'partially_paid']))
                @php $returnable = $sale->items->filter(fn ($i) => $i->returnableQuantity() > 0); @endphp
                @if ($returnable->isNotEmpty())
                    <x-card title="Return items">
                        <form method="POST" action="{{ route('sales.refund', $sale) }}" class="space-y-3"
                              onsubmit="return confirm('Process this return? Stock and accounting will be reversed for the returned items.')">
                            @csrf
                            <div class="flex justify-end">
                                <button type="button" class="text-xs text-indigo-600 hover:underline"
                                        onclick="this.closest('form').querySelectorAll('input[type=number]').forEach(i => i.value = i.max)">Return all</button>
                            </div>
                            <div class="space-y-2">
                                @foreach ($returnable as $item)
                                    @php $ret = $item->returnableQuantity(); @endphp
                                    <div class="flex items-center justify-between gap-2 text-sm">
                                        <div class="min-w-0">
                                            <div class="text-slate-700 truncate">{{ $item->name }}</div>
                                            <div class="text-xs text-slate-400">{{ rtrim(rtrim(number_format($ret, 3), '0'), '.') }} returnable</div>
                                        </div>
                                        <input type="number" name="returns[{{ $item->id }}]" min="0" max="{{ $ret }}" step="0.001" value="0"
                                               class="w-20 rounded-md border border-slate-300 p-1.5 text-sm text-right">
                                    </div>
                                @endforeach
                            </div>
                            <button class="w-full rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Process return</button>
                        </form>
                    </x-card>
                @endif
            @endif
        @endpermission

        <x-card title="Details">
            <dl class="text-sm space-y-1.5">
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt>
                    <dd>
                        @php $sc = ['completed'=>'bg-green-100 text-green-700','partially_paid'=>'bg-amber-100 text-amber-700','partially_refunded'=>'bg-orange-100 text-orange-700','refunded'=>'bg-red-100 text-red-700'][$sale->status] ?? 'bg-slate-100 text-slate-600'; @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full capitalize {{ $sc }}">{{ str_replace('_', ' ', $sale->status) }}</span>
                    </dd>
                </div>
                <div class="flex justify-between"><dt class="text-slate-500">Date</dt><dd>{{ optional($sale->completed_at)->format('d M Y H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Cashier</dt><dd>{{ $sale->user?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Customer</dt><dd>{{ $sale->customer?->name ?? 'Walk-in' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Register</dt><dd>{{ $sale->register?->name ?? '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card title="Payments">
            <ul class="text-sm space-y-1.5">
                @forelse ($sale->payments as $payment)
                    @php $isRefund = (float) $payment->amount < 0; @endphp
                    <li class="flex justify-between {{ $isRefund ? 'text-red-600' : '' }}">
                        <span class="capitalize {{ $isRefund ? '' : 'text-slate-500' }}">{{ $payment->method }}{{ $isRefund ? ' refund' : '' }} @if($payment->reference)<span class="text-xs text-slate-400">({{ $payment->reference }})</span>@endif</span>
                        <span>{{ $isRefund ? '−' : '' }}{{ $symbol }}{{ number_format(abs((float) $payment->amount), 2) }}</span>
                    </li>
                @empty
                    <li class="text-slate-400">No payments recorded.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
@endsection
