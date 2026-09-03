@extends('layout.master')

@section('title', 'Generar Contrato')

@section('main-content')
    <livewire:legal.contratos.form :garantia-id="$garantiaId" />
@endsection
