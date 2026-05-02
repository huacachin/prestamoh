@extends('layout.master')
@section('title', 'Adjuntos del cliente')
@section('main-content')
    <livewire:clients.gallery :id="$id"/>
@endsection
