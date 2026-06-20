@extends('exports.layout')

@php
    $hd   = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;vertical-align: middle;"';
    $cellR = 'style="border-style:dotted solid dotted solid;text-align: right;vertical-align: middle;"';
    $days = $report['days'] ?? [];
@endphp

@section('content')
    <center><font color="red"><b>REPORTE GENERAL CAJA 2</b></font></center>
    <div id="printme" style="width:100%">
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!}>N&deg;</th>
                <th {!! $hd !!}>Fecha</th>
                <th {!! $hd !!}>DATOS DEL CLIENTE</th>
                <th {!! $hd !!}>DETALLES</th>
                <th {!! $hd !!}>INGRESO</th>
                <th {!! $hd !!}>EGRESO</th>
            </tr>
        </thead>
        <tbody>
            @foreach($days as $day)
                @foreach($day['items'] as $item)
                    <tr>
                        <td {!! $cell !!}><b>{{ $item['n'] }}</b></td>
                        <td {!! $cell !!}><b>{{ $item['fecha'] }}</b></td>
                        <td {!! $cell !!}>{{ $item['cliente'] }}</td>
                        <td {!! $cell !!}>{{ $item['detalle'] }}</td>
                        <td {!! $cellR !!}><font color="blue">{{ ($item['ingreso'] ?? 0) > 0 ? number_format($item['ingreso'], 2) : '' }}</font></td>
                        <td {!! $cellR !!}><font color="red">{{ ($item['egreso'] ?? 0) > 0 ? number_format($item['egreso'], 2) : '' }}</font></td>
                    </tr>
                @endforeach

                {{-- TOTAL del día --}}
                <tr style="background:#CEE7FF">
                    <td style="border-style:dotted solid dotted solid;text-align:center;"></td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;"></td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;"></td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;"><b>TOTAL</b></td>
                    <td style="border-style:dotted solid dotted solid;text-align: right;"><font color="blue"><b>{{ number_format($day['total_ingreso'], 2) }}</b></font></td>
                    <td style="border-style:dotted solid dotted solid;text-align: right;"><font color="red"><b>{{ number_format($day['total_egreso'], 2) }}</b></font></td>
                </tr>
                {{-- SALDO FINAL-INICIAL --}}
                <tr style="background:#CEE7FF">
                    <td style="border-style:dotted solid dotted solid;text-align:center;"></td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;"></td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;"></td>
                    <td style="border-style:dotted solid dotted solid;text-align: center;"><b>SALDO <font color="red">FINAL-INICIAL</font></b></td>
                    <td style="border-style:dotted solid dotted solid;text-align: right;"><b><font color="blue">{{ number_format($day['saldo'], 2) }}</font></b></td>
                    <td style="border-style:dotted solid dotted solid;text-align: right;"></td>
                </tr>
            @endforeach

            {{-- TOTAL GENERAL --}}
            <tr>
                <td colspan="4" style="border-style:dotted solid dotted solid;text-align:center;"><b><font size="2">REPORTE GENERAL </font><font color="red" size="2">CAJA 2 - </font><font size="2">TOTAL</font> <font color="red" size="2">GENERAL</font></b></td>
                <td colspan="2" style="border-style:dotted solid dotted solid;text-align: center;"><b><font size="2" color="blue">{{ number_format($report['balance_general'] ?? 0, 2) }}</font></b></td>
            </tr>
        </tbody>
    </table>
    </div>
@endsection
