@extends('layouts.app')
@section('title', 'Categories')

@section('content')
@php $parent = request('parent'); @endphp
<x-page-header title="Categories">
    <a href="{{ route('categories.export', request()->only('parent')) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export CSV</a>
    @permission('categories.manage')
        <a href="{{ route('categories.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add category</a>
    @endpermission
</x-page-header>

{{-- Parent filter --}}
<x-card class="mb-4">
    <form method="GET" action="{{ route('categories.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Parent</label>
            <select name="parent" onchange="this.form.submit()" class="rounded-md border border-slate-300 p-2 text-sm">
                <option value="">All categories</option>
                <option value="none" @selected($parent === 'none')>Top-level only</option>
                <optgroup label="Children of…">
                    @foreach ($parents as $p)
                        <option value="{{ $p->id }}" @selected((string) $parent === (string) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </optgroup>
            </select>
        </div>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Apply</button>
    </form>
</x-card>

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
