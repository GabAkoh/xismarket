@extends('layouts.app')
@section('title', 'Staff')

@section('content')
<x-page-header title="Staff">
    @permission('users.manage')
        <a href="{{ route('users.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add staff</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th>Email</th><th>Roles</th><th>Status</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @foreach ($users as $user)
                <tr>
                    <td class="py-3 font-medium text-slate-700">
                        {{ $user->name }}
                        @if ($user->is_owner)<span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">Owner</span>@endif
                    </td>
                    <td class="text-slate-500">{{ $user->email }}</td>
                    <td>
                        @forelse ($user->roles as $role)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $role->name }}</span>
                        @empty
                            <span class="text-slate-300">—</span>
                        @endforelse
                    </td>
                    <td>
                        @if ($user->is_active)
                            <span class="text-xs text-green-600">● Active</span>
                        @else
                            <span class="text-xs text-slate-400">● Inactive</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @permission('users.manage')
                            <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:underline">Edit</a>
                            @unless ($user->is_owner || $user->id === auth()->id())
                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('Remove this staff member?')">
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
    <div class="mt-4">{{ $users->links() }}</div>
</x-card>
@endsection
