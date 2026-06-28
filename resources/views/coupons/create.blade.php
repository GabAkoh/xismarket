@extends('layouts.app')
@section('title', 'Add coupon')

@section('content')
<x-page-header title="Add coupon">
    <a href="{{ route('coupons.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Back</a>
</x-page-header>

<form method="POST" action="{{ route('coupons.store') }}" class="max-w-2xl space-y-4">
    @csrf
    @include('coupons._form')
    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create coupon</button>
</form>
@endsection
