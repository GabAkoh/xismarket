@extends('layouts.app')
@section('title', 'Categories')

@section('content')
<x-page-header title="Categories">
    @permission('categories.manage')
        <a href="{{ route('categories.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add category</a>
    @endpermission
</x-page-header>

<x-card>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400 border-b">
            <tr><th class="py-2">Name</th><th>Parent</th><th class="text-right">Products</th><th></th></tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($categories as $category)
                <tr>
                    <td class="py-3 font-medium text-slate-700">{{ $category->name }}</td>
                    <td class="text-slate-500">{{ $category->parent?->name ?? '—' }}</td>
                    <td class="text-right text-slate-500">{{ $category->products_count }}</td>
                    <td class="text-right">
                        @permission('categories.manage')
                            <a href="{{ route('categories.edit', $category) }}" class="text-indigo-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Remove this category?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        @endpermission
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-6 text-center text-slate-400">No categories yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="mt-4">{{ $categories->links() }}</div>
</x-card>
@endsection
