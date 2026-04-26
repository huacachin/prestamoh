@extends('layout.master')
@section('title', 'Refinanciar Crédito')
@section('main-content')
    <livewire:payments.refinance :creditId="$creditId"/>
@endsection
