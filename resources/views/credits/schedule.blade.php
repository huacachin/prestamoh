@extends('layout.master')
@section('title', 'Cronograma')
@section('main-content')
    <livewire:credits.schedule :id="$id"/>
@endsection
