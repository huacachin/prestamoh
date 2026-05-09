@extends('layout.master')
@section('title', 'Aval del cliente')
@section('main-content')
    <livewire:clients.aval :id="$id"/>
@endsection
