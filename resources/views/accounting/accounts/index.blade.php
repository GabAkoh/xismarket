@extends('layouts.app')
@section('title', 'Chart of Accounts')

@section('content')
<x-page-header title="Chart of Accounts">
    @permission('accounts.manage')
        <a href="{{ route('accounts.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add account</a>
    @endpermission
</x-page-header>

@forelse ($grouped as $type => $accounts)
    <x-card title="{{ ucfirst($type) }}" class="mb-5">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b">
                <tr><th class="py-2 w-24">Code</th><th>Name</th><th>Subtype</th><th class="text-right">Balance</th><th>Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($accounts as $account)
                    <tr>
                        <td class="py-3 font-mono text-slate-600">{{ $account->code }}</td>
                        <td class="font-medium text-slate-700">{{ $account->name }}</td>
                        <td class="text-slate-500">{{ $account->subtype ?? '—' }}</td>
                        <td class="text-right tabular-nums text-slate-700">{{ $currentTenant->currency }} {{ number_format($account->balance(), 2) }}</td>
                        <td>
                            @if ($account->is_active)
                                <span class="text-xs text-green-600">● Active</span>
                            @else
                                <span class="text-xs text-slate-400">● Inactive</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @permission('accounts.manage')
                                <a href="{{ route('accounts.edit', $account) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('Delete this account?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-3 text-red-600 hover:underline">Delete</button>
                                </form>
                            @endpermission
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
@empty
    <x-card>
        <p class="text-slate-500 text-sm">No accounts yet. <a href="{{ route('accounts.create') }}" class="text-indigo-600 hover:underline">Create your first account</a>.</p>
    </x-card>
@endforelse
@endsection
