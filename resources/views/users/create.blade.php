@extends('layouts.app')
@section('title', 'Add staff')

@section('content')
<x-page-header title="Add staff" />

<form method="POST" action="{{ route('users.store') }}" class="max-w-2xl">
    @csrf
    <x-card>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Name</label>
                <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Password</label>
                <input name="password" type="password" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Confirm password</label>
                <input name="password_confirmation" type="password" required class="mt-1 w-full rounded-md border border-slate-300 p-2">
            </div>
        </div>

        <div class="mt-5">
            <label class="block text-sm font-medium text-slate-700 mb-2">Roles</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach ($roles as $role)
                    <label class="flex items-center gap-2 text-sm rounded-md border border-slate-200 px-3 py-2">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', [])))>
                        {{ $role->name }}
                    </label>
                @endforeach
            </div>
        </div>
    </x-card>
    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('users.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
