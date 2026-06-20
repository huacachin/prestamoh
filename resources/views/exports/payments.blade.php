@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
@endphp

@section('content')
    <center><font color="red"><b>PAGO/CREDITO</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} width="40">N&deg;</th>
                <th {!! $hd !!} width="60">Exp.</th>
                <th {!! $hd !!} width="80">Codigo</th>
                <th {!! $hd !!} width="220">Nombre</th>
                <th {!! $hd !!} width="80">Moneda</th>
                <th {!! $hd !!} width="100">Capital</th>
                <th {!! $hd !!} width="50">%</th>
                <th {!! $hd !!} width="50">C.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($credits as $i => $c)
                @php
                    $cli = $c->client;
                    $nombre = $cli ? trim(($cli->apellido_pat ?? '').' '.($cli->apellido_mat ?? '').' '.($cli->nombre ?? '')) : '';
                @endphp
                <tr>
                    <td {!! $cell !!}>{{ $i + 1 }}</td>
                    <td {!! $cell !!}>{{ $cli?->expediente }}</td>
                    <td {!! $cell !!}>{{ $c->id }}</td>
                    <td style="border-style:dotted solid dotted solid;">{{ $nombre }}</td>
                    <td {!! $cell !!}>{{ $c->moneda }}</td>
                    <td {!! $cell !!}>{{ number_format($c->importe, 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($c->interes, 0) }}</td>
                    <td {!! $cell !!}>{{ $c->cuotas }}</td>
                </tr>
            @empty
                <tr><td colspan="8" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse

            <tr bgcolor="#CEE7FF">
                <td colspan="5" style="text-align:center;"><b>Totales</b></td>
                <td style="text-align:center;"><b>{{ number_format($sumCapital, 2) }}</b></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
@endsection
