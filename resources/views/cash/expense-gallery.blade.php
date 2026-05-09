@extends('layout.master')
@section('title', 'Adjuntos del egreso')
@section('main-content')
    <livewire:cash.expense-gallery :id="$id"/>
@endsection
