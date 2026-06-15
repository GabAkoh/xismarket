@extends('layouts.app')
@section('title', 'Edit product')

@section('content')
<x-page-header title="Edit product" />

<form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" class="max-w-3xl">
    @csrf @method('PUT')
    <x-card>
        @include('inventory.products._form')
    </x-card>
    <div class="mt-4 flex gap-2">
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        <a href="{{ route('products.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</a>
    </div>
</form>
@endsection
