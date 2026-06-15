@extends('layouts.app')
@section('title', 'Roles')

@section('content')
<x-page-header title="Roles">
    @permission('roles.manage')
        <a href="{{ route('roles.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New role</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Role</th><th>Description</th><th class="text-center">Permissions</th><th class="text-center">Users</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @foreach ($roles as $role)
                <tr>
                    <td class="py-3 font-medium text-slate-700">
                        {{ $role->name }}
                        @if ($role->is_system)<span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-slate-100 text-slate-500">system</span>@endif
                    </td>
                    <td class="text-slate-500">{{ $role->description }}</td>
                    <td class="text-center">{{ $role->permissions_count }}</td>
                    <td class="text-center">{{ $role->users_count }}</td>
                    <td class="text-right">
                        @permission('roles.manage')
                            <a href="{{ route('roles.edit', $role) }}" class="text-indigo-600 hover:underline">Edit</a>
                            @unless ($role->is_system)
                                <form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline" onsubmit="return confirm('Delete this role?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-3 text-red-600 hover:underline">Delete</button>
                                </form>
                            @endunless
                        @endpermission
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-card>
@endsection
