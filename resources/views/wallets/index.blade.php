@extends('layouts.app')
@section('title', 'Wallets')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header title="Wallets" subtitle="Customer store credit across the business">
    @permission('customers.manage')
        <a href="{{ route('wallets.bulk') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Bulk top-up</a>
    @endpermission
</x-page-header>

{{-- Summary --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Outstanding store credit</p>
        <p class="mt-1 text-2xl font-bold text-indigo-600">{{ $symbol }} {{ number_format($totalCredit, 2) }}</p>
        <p class="text-xs text-slate-400">Total liability owed to customers</p>
    </div>
    <div class="bg-white rounded-lg shadow-sm p-5">
        <p class="text-sm text-slate-500">Customers with credit</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($holders) }}</p>
        <p class="text-xs text-slate-400">wallets holding a balance</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Customers with a balance --}}
    <div class="lg:col-span-2">
        <x-card title="Wallet balances">
            <form method="GET" action="{{ route('wallets.index') }}" class="mb-4">
                <input name="q" value="{{ request('q') }}" placeholder="Search customers…"
                       class="w-full sm:w-64 rounded-md border border-slate-300 p-2 text-sm">
            </form>

            <table class="w-full text-sm">
                <thead class="text-left text-slate-400 border-b">
                    <tr><th class="py-2">Customer</th><th>Phone</th><th class="text-right">Balance</th><th></th></tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="py-3">
                                <a href="{{ route('customers.show', $customer) }}" class="font-medium text-indigo-600 hover:underline">{{ $customer->name }}</a>
                                @if ($customer->email)<div class="text-xs text-slate-400">{{ $customer->email }}</div>@endif
                            </td>
                            <td class="text-slate-500">{{ $customer->phone ?: '—' }}</td>
                            <td class="text-right font-semibold text-indigo-600">{{ $symbol }} {{ number_format($customer->balance, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:underline">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-400">No customers hold store credit.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $customers->links() }}</div>
        </x-card>
    </div>

    {{-- Recent activity --}}
    <div>
        <x-card title="Recent activity">
            @if ($recent->isEmpty())
                <p class="text-sm text-slate-400">No wallet activity yet.</p>
            @else
                <ul class="text-sm divide-y">
                    @foreach ($recent as $t)
                        <li class="py-2 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ route('customers.show', $t->customer_id) }}" class="font-medium text-slate-700 hover:text-indigo-600 truncate block">{{ $t->customer?->name ?? 'Customer #'.$t->customer_id }}</a>
                                <div class="text-xs text-slate-400">{{ $t->reason ?: ucfirst($t->type) }} · {{ $t->created_at->format('M d, H:i') }}</div>
                            </div>
                            <span class="shrink-0 font-medium {{ $t->type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $t->type === 'credit' ? '+' : '−' }}{{ $symbol }} {{ number_format($t->amount, 2) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
@endsection
