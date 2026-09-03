@extends('layout.master')

@section('title', 'Nueva Garantía')

@section('main-content')
    <livewire:legal.garantias.create :credit-id="$creditId" />
@endsection
