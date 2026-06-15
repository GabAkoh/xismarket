@extends('layouts.app')
@section('title', 'Purchase order')

@section('content')
<x-page-header title="{{ $purchase->reference }}">
    @permission('purchases.manage')
        @unless ($purchase->isReceived())
            <form method="POST" action="{{ route('purchases.receive', $purchase) }}" onsubmit="return confirm('Receive this order and add stock?')">
                @csrf
                <button class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Receive order</button>
            </form>
        @endunless
    @endpermission
    <a href="{{ route('purchases.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <x-card title="Supplier"><p class="text-slate-700">{{ $purchase->supplier?->name ?? '—' }}</p></x-card>
    <x-card title="Warehouse"><p class="text-slate-700">{{ $purchase->warehouse?->name ?? '—' }}</p></x-card>
    <x-card title="Status">
        @if ($purchase->isReceived())
            <p class="text-green-600">Received {{ $purchase->received_at?->format('Y-m-d') }}</p>
        @else
            <p class="text-amber-600">Draft</p>
        @endif
    </x-card>
</div>

<x-card title="Line items">
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Product</th><th class="text-right">Quantity</th><th class="text-right">Unit cost</th><th class="text-right">Line total</th></tr>
        </thead>
        <tbody class="divide-y">
            @foreach ($purchase->items as $item)
                <tr>
                    <td class="py-3 text-slate-700">{{ $item->product?->name ?? '—' }}</td>
                    <td class="text-right text-slate-700">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                    <td class="text-right text-slate-500">{{ $currentTenant->currency }} {{ number_format((float) $item->unit_cost, 2) }}</td>
                    <td class="text-right text-slate-700">{{ $currentTenant->currency }} {{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t font-semibold">
                <td class="py-3" colspan="3">Total</td>
                <td class="text-right text-slate-800">{{ $currentTenant->currency }} {{ number_format((float) $purchase->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    @if ($purchase->note)
        <p class="mt-4 text-sm text-slate-500">{{ $purchase->note }}</p>
    @endif
</x-card>
@endsection
