@extends('layouts.app')
@section('title', 'New role')

@section('content')
<x-page-header title="New role" />
<form method="POST" action="{{ route('roles.store') }}">
    @csrf
    @php $assigned = []; @endphp
    @include('roles._form')
</form>
@endsection
