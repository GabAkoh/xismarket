@extends('layouts.app')
@section('title', 'Warehouses')

@section('content')
<x-page-header title="Warehouses">
    @permission('warehouses.manage')
        <a href="{{ route('warehouses.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add warehouse</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th>Code</th><th>Address</th><th>Default</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($warehouses as $warehouse)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $warehouse->name }}</td>
                    <td class="text-slate-500">{{ $warehouse->code ?? '—' }}</td>
                    <td class="text-slate-500">{{ $warehouse->address ?? '—' }}</td>
                    <td>
                        @if ($warehouse->is_default)
                            <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">Default</span>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @permission('warehouses.manage')
                            <a href="{{ route('warehouses.edit', $warehouse) }}" class="text-indigo-600 hover:underline">Edit</a>
                            @unless ($warehouse->is_default)
                                <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" class="inline" onsubmit="return confirm('Remove this warehouse?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-3 text-red-600 hover:underline">Delete</button>
                                </form>
                            @endunless
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-400">No warehouses yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $warehouses->links() }}</div>
</x-card>
@endsection
