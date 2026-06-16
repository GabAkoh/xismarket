@extends('layouts.app')
@section('title', 'Customers')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp
<x-page-header title="Customers">
    @if (Route::has('loyalty.settings'))
        <a href="{{ route('loyalty.settings') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Loyalty program</a>
    @endif
    @permission('customers.manage')
        <a href="{{ route('customers.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add customer</a>
    @endpermission
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('customers.index') }}" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search name, email, phone or ID number…" class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
    </form>
</x-card>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th>Email</th><th>Phone</th><th class="text-right">Wallet</th><th class="text-right">Points</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($customers as $customer)
                <tr>
                    <td class="py-3 font-medium text-slate-700">
                        <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:underline">{{ $customer->name }}</a>
                        @if ($customer->identity_number)
                            <div class="text-xs font-normal text-slate-400">ID: {{ $customer->identity_number }}</div>
                        @endif
                    </td>
                    <td class="text-slate-500">{{ $customer->email ?: '—' }}</td>
                    <td class="text-slate-500">{{ $customer->phone ?: '—' }}</td>
                    <td class="text-right {{ $customer->balance > 0 ? 'text-indigo-600 font-medium' : 'text-slate-400' }}">{{ $symbol }} {{ number_format($customer->balance, 2) }}</td>
                    <td class="text-right {{ $customer->loyalty_points > 0 ? 'text-amber-600 font-medium' : 'text-slate-400' }}">{{ number_format($customer->loyalty_points) }}</td>
                    <td class="text-right">
                        <a href="{{ route('customers.show', $customer) }}" class="text-slate-500 hover:underline">View</a>
                        @permission('customers.manage')
                            <a href="{{ route('customers.edit', $customer) }}" class="ml-3 text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline" onsubmit="return confirm('Remove this customer?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-400">No customers yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-card>
@endsection
