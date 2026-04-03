@extends('layout.master')
@section('title', 'Registrar Pago')
@section('main-content')
    <livewire:payments.create :creditId="$creditId"/>
@endsection
