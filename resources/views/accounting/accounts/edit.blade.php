@extends('layouts.app')
@section('title', 'Edit account')

@section('content')
<x-page-header title="Edit account" />

<form method="POST" action="{{ route('accounts.update', $account) }}" class="max-w-2xl">
    @csrf @method('PUT')
    <x-card>
        @include('accounting.accounts._form')
    </x-card>
    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Update</button>
        <a href="{{ route('accounts.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
