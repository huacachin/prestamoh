@extends('layout.master')
@section('title', 'Permisos')
@section('main-content')
    <livewire:users.perms :id="$userId"/>
@endsection
