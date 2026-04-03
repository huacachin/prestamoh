@extends('layout.master')
@section('title', 'Nuevo Crédito')
@section('main-content')
    <livewire:credits.create :clientId="$clientId"/>
@endsection
