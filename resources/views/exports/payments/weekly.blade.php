@extends('exports.layout')

@section('content')
    @include('exports.payments._periods', ['titulo' => 'REPORTE DE CREDITO SEMANAL'])
@endsection
