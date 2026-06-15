@extends('layouts.app')
@section('title', 'Add supplier')

@section('content')
<x-page-header title="Add supplier" />

<form method="POST" action="{{ route('suppliers.store') }}" class="max-w-2xl">
    @csrf
    <x-card>
        @include('inventory.suppliers._form')
    </x-card>
    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('suppliers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
