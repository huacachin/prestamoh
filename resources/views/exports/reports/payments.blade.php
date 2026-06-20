@extends('exports.layout')

@php
    $hd   = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
@endphp

@section('content')
    <center><font color="red"><b>REPORTE DE PAGO</b></font></center>
    <center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} width="13%"><b>N&deg;</b></th>
                <th {!! $hd !!} width="13%"><b>Fecha</b></th>
                <th {!! $hd !!} width="13%"><b>Hora</b></th>
                <th {!! $hd !!} width="8%"><b>Usuario</b></th>
                <th {!! $hd !!} width="8%"><b>Asesor</b></th>
                <th {!! $hd !!}><b>A</b></th>
                <th {!! $hd !!}><b>Motivo</b></th>
                <th {!! $hd !!} width="10%" align="center"><b>S/.</b></th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
                <tr>
                    <td {!! $cell !!}>{{ $r['n'] }}</td>
                    <td {!! $cell !!}>{{ \Carbon\Carbon::parse($r['fecha'])->format('d/m/Y') }}</td>
                    <td {!! $cell !!}>{{ $r['hora'] }}</td>
                    <td {!! $cell !!}>{{ $r['usuario'] }}</td>
                    <td {!! $cell !!}>{{ $r['asesor'] }}</td>
                    <td {!! $cell !!}>{{ $r['cliente'] }}</td>
                    <td {!! $cell !!}>{{ $r['detalle'] }}</td>
                    <td {!! $cell !!}>{{ number_format($r['monto'], 2) }}</td>
                </tr>
            @endforeach

            {{-- Bloque de totales (réplica de ingreso_excel2.php) --}}
            <tr>
                <th colspan="5" rowspan="6" align="center"><b>Total</b></th>
                <td><b></b></td>
                <td><b></b></td>
                <td align="right"><b>{{ number_format($totals['total'] ?? 0, 2) }}</b></td>
            </tr>
            <tr>
                <td align="center"><b>Fijos</b></td>
                <td align="center"><b>{{ number_format($totals['fijos'] ?? 0, 2) }}</b></td>
                <td></td>
            </tr>
            <tr>
                <td align="center"><font color="red"><b>Otros</b></font></td>
                <td align="center"><b>{{ number_format($totals['otros'] ?? 0, 2) }}</b></td>
                <td></td>
            </tr>
            <tr>
                <td align="center"><b>Capital</b></td>
                <td align="center"><b>{{ number_format($totals['capital'] ?? 0, 2) }}</b></td>
                <td></td>
            </tr>
            <tr>
                <td align="center"><b>Interes</b></td>
                <td align="center"><b>{{ number_format($totals['interes'] ?? 0, 2) }}</b></td>
                <td></td>
            </tr>
            <tr>
                <td align="center"><b>Mora</b></td>
                <td align="center"><b>{{ number_format($totals['mora'] ?? 0, 2) }}</b></td>
                <td></td>
            </tr>
        </tbody>
    </table>
    </center>
@endsection
