@extends('layouts.app')
@section('title', 'Suppliers')

@section('content')
@php $symbol = $currentTenant->currencySymbol() ?? ''; @endphp
<x-page-header title="Suppliers">
    @permission('suppliers.manage')
        <a href="{{ route('suppliers.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add supplier</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th>Email</th><th>Phone</th><th>Address</th><th class="text-right">Owed</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($suppliers as $supplier)
                @php $owed = $supplier->balanceOwed(); @endphp
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $supplier->name }}</td>
                    <td class="text-slate-500">{{ $supplier->email ?? '—' }}</td>
                    <td class="text-slate-500">{{ $supplier->phone ?? '—' }}</td>
                    <td class="text-slate-500">{{ $supplier->address ?? '—' }}</td>
                    <td class="text-right {{ $owed > 0 ? 'font-medium text-rose-600' : 'text-slate-400' }}">{{ $symbol }}{{ number_format($owed, 2) }}</td>
                    <td class="text-right whitespace-nowrap">
                        @permission('suppliers.manage', 'purchases.pay')
                            @if ($owed > 0)
                                <a href="{{ route('suppliers.pay', $supplier) }}" class="text-emerald-600 hover:underline">Pay</a>
                            @endif
                        @endpermission
                        @permission('suppliers.manage')
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="ml-3 text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="inline" onsubmit="return confirm('Remove this supplier?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-400">No suppliers yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $suppliers->links() }}</div>
</x-card>
@endsection
