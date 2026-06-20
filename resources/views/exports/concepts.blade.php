@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
@endphp

@section('content')
    <center><font color="red"><b>CONCEPTOS FIJOS</b></font></center>
    <table border="1" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th {!! $hd !!} width="50">N&deg;</th>
                <th {!! $hd !!} width="50">Codigo</th>
                <th {!! $hd !!} width="200">Nombre</th>
                <th {!! $hd !!} width="200">Tipo</th>
                <th {!! $hd !!} width="200">ING. S/</th>
                <th {!! $hd !!} width="200">EGR. S/</th>
            </tr>
        </thead>
        <tbody>
            @php $ingo = 0; $egrino = 0; @endphp
            @forelse($concepts as $c)
                @php
                    $tipo = (strtoupper($c->type) === 'INGRESO' || $c->type === 'ingreso') ? '1' : '2';
                    if ($tipo === '1') { $ingo++; $num = $ingo; $color = 'black'; $tipoLabel = 'INGRESO'; }
                    else { $egrino++; $num = $egrino; $color = 'red'; $tipoLabel = 'EGRESO'; }
                @endphp
                <tr>
                    <td {!! $cell !!}><font color="{{ $color }}">{{ $num }}</font></td>
                    <td {!! $cell !!}>{{ $c->code }}</td>
                    <td {!! $cell !!}>{{ $c->name }}</td>
                    <td {!! $cell !!}>{{ $tipoLabel }}</td>
                    <td {!! $cell !!}>{{ $c->factor_ingreso }}</td>
                    <td {!! $cell !!}>{{ $c->factor_egreso }}</td>
                </tr>
            @empty
                <tr>
                    <td {!! $cell !!}>--</td>
                    <td {!! $cell !!}>--</td>
                    <td {!! $cell !!}>--</td>
                    <td {!! $cell !!}>--</td>
                    <td {!! $cell !!}>--</td>
                    <td {!! $cell !!}>--</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
