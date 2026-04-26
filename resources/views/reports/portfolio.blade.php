@extends('layout.master')
@section('title', 'Cartera Activa')
@section('main-content')
    <livewire:reports.portfolio />
@endsection
@section('script')
    <script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
@endsection
