@extends('layout.master')
@section('title', 'Editar Egreso')
@section('main-content')
    <livewire:cash.edit-expense :id="$id"/>
@endsection
