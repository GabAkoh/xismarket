@extends('layouts.app')
@section('title', 'Add account')

@section('content')
<x-page-header title="Add account" />

<form method="POST" action="{{ route('accounts.store') }}" class="max-w-2xl">
    @csrf
    <x-card>
        @include('accounting.accounts._form')
    </x-card>
    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('accounts.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
