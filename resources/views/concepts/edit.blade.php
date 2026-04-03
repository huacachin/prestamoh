@extends('layout.master')
@section('title', 'Editar Concepto')
@section('main-content')
    <livewire:concepts.edit :id="$conceptId"/>
@endsection
