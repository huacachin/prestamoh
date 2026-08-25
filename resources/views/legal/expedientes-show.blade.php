@extends('layout.master')

@section('title', 'Detalle de Expediente')

@section('main-content')
    <livewire:legal.expedientes.show :expediente-id="$expedienteId" />
@endsection
