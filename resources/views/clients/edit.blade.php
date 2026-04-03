@extends('layout.master')
@section('title', 'Editar Cliente')
@section('main-content')
    <livewire:clients.edit :id="$id"/>
@endsection
