@extends('layout.master')
@section('title', 'Documentos del cliente')
@section('main-content')
    <livewire:clients.documentos :id="$id"/>
@endsection
