@extends('layouts.app')
@section('title', $customer->name)

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp

<x-page-header :title="$customer->name" subtitle="Customer profile, wallet &amp; loyalty">
    @permission('pos.use')
        @if ($outstanding > 0)
            <a href="{{ route('customers.receive-payment', $customer) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Receive payment</a>
        @endif
    @endpermission
    <a href="{{ route('customers.statement', $customer) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Statement</a>
    @permission('customers.manage')
        <a href="{{ route('customers.edit', $customer) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
    @endpermission
    <a href="{{ route('customers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left column: profile + balances --}}
    <div class="space-y-6">
        <x-card title="Details">
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-slate-400">ID / Tax no.</dt><dd class="text-slate-700">{{ $customer->identity_number ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Email</dt><dd class="text-slate-700">{{ $customer->email ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Phone</dt><dd class="text-slate-700">{{ $customer->phone ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Address</dt><dd class="text-slate-700 text-right">{{ $customer->address ?: '—' }}</dd></div>
                @if ($customer->notes)
                    <div class="pt-2 border-t border-slate-100 text-slate-500">{{ $customer->notes }}</div>
                @endif
            </dl>
        </x-card>

        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-5">
                <p class="text-sm text-slate-500">Wallet balance</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600">{{ $symbol }} {{ number_format($customer->balance, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-5">
                <p class="text-sm text-slate-500">Loyalty points</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ number_format($customer->loyalty_points) }}</p>
                @if ($loyalty->is_active)
                    <p class="text-xs text-slate-400">≈ {{ $symbol }} {{ number_format($loyalty->valueOf($customer->loyalty_points), 2) }}</p>
                @endif
            </div>
        </div>

        @if ($outstanding > 0)
            <div class="bg-white rounded-lg shadow-sm p-5 border border-rose-100">
                <p class="text-sm text-slate-500">Outstanding (owes you)</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ $symbol }} {{ number_format($outstanding, 2) }}</p>
                @permission('pos.use')
                    <a href="{{ route('customers.receive-payment', $customer) }}" class="mt-3 inline-block rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Receive payment</a>
                @endpermission
            </div>
        @endif

        @permission('customers.manage')
        <x-card title="Top up wallet">
            <form method="POST" action="{{ route('customers.wallet.topup', $customer) }}" class="space-y-3">
                @csrf
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Amount ({{ $symbol }})</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required class="w-full rounded-md border border-slate-300 p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Method</label>
                        <select name="method" class="rounded-md border border-slate-300 p-2 text-sm">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <input type="text" name="reason" placeholder="Note (optional)" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add credit</button>
            </form>
        </x-card>

        @if ((float) $customer->balance > 0)
        <x-card title="Withdraw from wallet">
            <form method="POST" action="{{ route('customers.wallet.withdraw', $customer) }}" class="space-y-3"
                  onsubmit="return confirm('Pay this store credit back out and reduce the wallet balance?')">
                @csrf
                <div class="flex gap-2">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Amount ({{ $symbol }})</label>
                        <input type="number" name="amount" step="0.01" min="0.01" max="{{ number_format((float) $customer->balance, 2, '.', '') }}" required class="w-full rounded-md border border-slate-300 p-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Paid out as</label>
                        <select name="method" class="rounded-md border border-slate-300 p-2 text-sm">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <input type="text" name="reason" placeholder="Reason (optional)" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <button class="w-full rounded-md border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">Withdraw credit</button>
            </form>
        </x-card>
        @endif

        <x-card title="Adjust loyalty points">
            <form method="POST" action="{{ route('customers.loyalty.adjust', $customer) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Points (use a negative number to deduct)</label>
                    <input type="number" name="points" step="1" required class="w-full rounded-md border border-slate-300 p-2 text-sm">
                </div>
                <input type="text" name="reason" placeholder="Reason (optional)" class="w-full rounded-md border border-slate-300 p-2 text-sm">
                <button class="w-full rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-100">Adjust points</button>
            </form>
        </x-card>
        @endpermission
    </div>

    {{-- Right column: ledgers + sales --}}
    <div class="lg:col-span-2 space-y-6">
        <x-card title="Wallet history">
            @if ($customer->walletTransactions->isEmpty())
                <p class="text-sm text-slate-400">No wallet activity yet.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400 border-b">
                        <tr><th class="py-2">When</th><th>Reason</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($customer->walletTransactions as $t)
                            <tr>
                                <td class="py-2 text-slate-400">{{ $t->created_at->format('M d, H:i') }}</td>
                                <td class="text-slate-600">{{ $t->reason ?: ucfirst($t->type) }}</td>
                                <td class="text-right font-medium {{ $t->type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $t->type === 'credit' ? '+' : '−' }}{{ $symbol }} {{ number_format($t->amount, 2) }}
                                </td>
                                <td class="text-right text-slate-500">{{ $symbol }} {{ number_format($t->balance_after, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Loyalty history">
            @if ($customer->loyaltyTransactions->isEmpty())
                <p class="text-sm text-slate-400">No loyalty activity yet.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400 border-b">
                        <tr><th class="py-2">When</th><th>Reason</th><th class="text-right">Points</th><th class="text-right">Balance</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($customer->loyaltyTransactions as $t)
                            <tr>
                                <td class="py-2 text-slate-400">{{ $t->created_at->format('M d, H:i') }}</td>
                                <td class="text-slate-600">{{ $t->reason ?: ucfirst($t->type) }}</td>
                                <td class="text-right font-medium {{ $t->points >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $t->points >= 0 ? '+' : '' }}{{ number_format($t->points) }}
                                </td>
                                <td class="text-right text-slate-500">{{ number_format($t->points_balance) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Recent purchases">
            @if ($recentSales->isEmpty())
                <p class="text-sm text-slate-400">No purchases yet.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400 border-b">
                        <tr><th class="py-2">Sale</th><th>Status</th><th class="text-right">Total</th><th class="text-right">When</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($recentSales as $sale)
                            <tr>
                                <td class="py-2">
                                    @if (Route::has('sales.show'))
                                        <a href="{{ route('sales.show', $sale) }}" class="font-medium text-indigo-600 hover:underline">{{ $sale->number }}</a>
                                    @else
                                        <span class="font-medium text-slate-700">{{ $sale->number }}</span>
                                    @endif
                                </td>
                                <td><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100">{{ $sale->status }}</span></td>
                                <td class="text-right">{{ $symbol }} {{ number_format($sale->total, 2) }}</td>
                                <td class="text-right text-slate-400">{{ $sale->completed_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>
</div>
@endsection
