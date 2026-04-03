@extends('layout.master')
@section('title', 'Editar Sucursal')
@section('main-content')
    <livewire:headquarters.edit :id="$headquarterId"/>
@endsection
