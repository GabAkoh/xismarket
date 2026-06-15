@extends('layouts.app')
@section('title', 'Edit role')

@section('content')
<x-page-header title="Edit role: {{ $role->name }}" />
<form method="POST" action="{{ route('roles.update', $role) }}">
    @csrf @method('PUT')
    @include('roles._form')
</form>
@endsection
