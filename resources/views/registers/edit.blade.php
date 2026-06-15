@extends('layouts.app')
@section('title', 'Edit register')

@section('content')
<x-page-header title="Edit register" />

<form method="POST" action="{{ route('registers.update', $register) }}" class="max-w-2xl">
    @csrf @method('PUT')
    @include('registers._form', ['register' => $register])
    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('registers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
