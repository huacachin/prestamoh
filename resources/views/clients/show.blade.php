@extends('layout.master')
@section('title', 'Ver Cliente')
@section('main-content')
    <livewire:clients.show :id="$id"/>
@endsection
