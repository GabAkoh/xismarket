@extends('layouts.app')
@section('title', 'Deliveries')

@section('content')
@php
    $symbol = $currentTenant->currencySymbol() ?? '';
    $badges = [
        'pending' => 'bg-slate-100 text-slate-600', 'assigned' => 'bg-blue-100 text-blue-700',
        'out_for_delivery' => 'bg-amber-100 text-amber-700', 'delivered' => 'bg-green-100 text-green-700',
        'failed' => 'bg-red-100 text-red-700',
    ];
@endphp

<x-page-header title="Deliveries">
    @permission('deliveries.manage')
        <a href="{{ route('deliveries.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New delivery</a>
    @endpermission
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('deliveries.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
            <input name="q" value="{{ request('q') }}" placeholder="Tracking, recipient, address…" class="rounded-md border border-slate-300 p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select name="status" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button>
        <a href="{{ route('deliveries.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Reset</a>
    </form>
</x-card>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Tracking</th><th>Order</th><th>Recipient</th><th>Address</th><th>Driver</th><th>Status</th><th class="text-right">Fee</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($deliveries as $d)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $d->tracking_number ?: '—' }}</td>
                    <td class="text-slate-500">{{ $d->order?->number ?? '—' }}</td>
                    <td class="text-slate-600">{{ $d->recipient_name ?: '—' }}</td>
                    <td class="text-slate-500">{{ \Illuminate\Support\Str::limit($d->address, 28) ?: '—' }}</td>
                    <td class="text-slate-500">{{ $d->driver?->name ?? '—' }}</td>
                    <td><span class="text-xs px-2 py-0.5 rounded-full {{ $badges[$d->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst(str_replace('_',' ',$d->status)) }}</span></td>
                    <td class="text-right text-slate-600">{{ $symbol }}{{ number_format($d->fee, 2) }}</td>
                    <td class="text-right"><a href="{{ route('deliveries.show', $d) }}" class="text-indigo-600 hover:underline">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-slate-400">No deliveries found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $deliveries->links() }}</div>
</x-card>
@endsection
