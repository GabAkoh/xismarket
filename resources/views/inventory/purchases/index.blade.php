@extends('layouts.app')
@section('title', 'Purchase Orders')

@section('content')
<x-page-header title="Purchase Orders">
    @permission('purchases.manage')
        <a href="{{ route('purchases.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New purchase order</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Reference</th><th>Supplier</th><th>Warehouse</th><th class="text-right">Items</th><th class="text-right">Total</th><th>Status</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($orders as $order)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $order->reference }}</td>
                    <td class="text-slate-500">{{ $order->supplier?->name ?? '—' }}</td>
                    <td class="text-slate-500">{{ $order->warehouse?->name ?? '—' }}</td>
                    <td class="text-right text-slate-500">{{ $order->items_count }}</td>
                    <td class="text-right text-slate-700">{{ $currentTenant->currency }} {{ number_format((float) $order->total, 2) }}</td>
                    <td>
                        @if ($order->isReceived())
                            <span class="text-xs text-green-600">● Received</span>
                        @else
                            <span class="text-xs text-amber-600">● Draft</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('purchases.show', $order) }}" class="text-indigo-600 hover:underline">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-6 text-center text-slate-400">No purchase orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-card>
@endsection
