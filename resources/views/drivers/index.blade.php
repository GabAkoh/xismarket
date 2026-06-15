@extends('layouts.app')
@section('title', 'Drivers')

@section('content')
<x-page-header title="Drivers">
    @permission('drivers.manage')
        <a href="{{ route('drivers.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add driver</a>
    @endpermission
</x-page-header>

<x-card class="mb-4">
    <form method="GET" action="{{ route('drivers.index') }}" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search name, phone or vehicle…" class="flex-1 rounded-md border border-slate-300 p-2 text-sm">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
    </form>
</x-card>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th>Phone</th><th>Vehicle</th><th>Status</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($drivers as $driver)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $driver->name }}</td>
                    <td class="text-slate-500">{{ $driver->phone ?: '—' }}</td>
                    <td class="text-slate-500">{{ $driver->vehicle ?: '—' }}</td>
                    <td>
                        @if ($driver->is_active)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Active</span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Inactive</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @permission('drivers.manage')
                            <a href="{{ route('drivers.edit', $driver) }}" class="text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('drivers.destroy', $driver) }}" class="inline" onsubmit="return confirm('Remove this driver?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-8 text-center text-slate-400">No drivers yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $drivers->links() }}</div>
</x-card>
@endsection
