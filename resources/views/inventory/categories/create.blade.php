@extends('layouts.app')
@section('title', 'Add category')

@section('content')
<x-page-header title="Add category" />

<form method="POST" action="{{ route('categories.store') }}" class="max-w-xl">
    @csrf
    <x-card>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Name</label>
                <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Parent category</label>
                <select name="parent_id" class="mt-1 w-full rounded-md border border-slate-300 p-2">
                    <option value="">— None —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('parent_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-card>
    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('categories.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
