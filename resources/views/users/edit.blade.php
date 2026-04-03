@extends('layout.master')
@section('title', 'Editar Usuario')
@section('main-content')
    <livewire:users.edit :id="$user"/>
@endsection
