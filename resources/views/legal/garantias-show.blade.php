@extends('layout.master')

@section('title', 'Detalle de Garantía')

@section('main-content')
    <livewire:legal.garantias.show :garantia-id="$garantiaId" />
@endsection
