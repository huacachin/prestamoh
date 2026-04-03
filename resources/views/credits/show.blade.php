@extends('layout.master')
@section('title', 'Detalle Crédito')
@section('main-content')
    <livewire:credits.show :id="$id"/>
@endsection
