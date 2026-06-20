@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;"';
@endphp

@section('content')
    <center style="color:red"><b>Reporte de Pago</b></center>
    <table id="tabla-cronograma" cellspacing="0" width="100%" border="1">
        <thead>
            <tr>
                <th {!! $hd !!}>N&deg; Cuota</th>
                <th {!! $hd !!}>Periodo</th>
                <th {!! $hd !!}>Capital</th>
                <th {!! $hd !!}>Interes</th>
                <th {!! $hd !!}>Total</th>
                <th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>Pagado</th>
                <th {!! $hd !!}>Fecha Pago</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                @php $bg = $r->color ? 'color:'.$r->color.';' : ''; @endphp
                <tr>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ $r->num }}</th>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ $r->periodo }}</th>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ number_format($r->capital, 2) }}</th>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ number_format($r->interes, 2) }}</th>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ number_format($r->total, 2) }}</th>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ number_format($r->mora, 2) }}</th>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ number_format($r->pagado, 2) }}</th>
                    <th {!! $cell !!} style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ $bg }}">{{ $r->fecha_pago }}</th>
                </tr>
            @empty
                <tr><td colspan="8" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse

            {{-- Total (2 filas, legacy) --}}
            <tr bgcolor="#CEE7FF">
                <th colspan="2" rowspan="2" style="text-align:center;"><b>Total</b></th>
                <th rowspan="2" style="text-align:center;"><b>{{ number_format($totCap, 2) }}</b></th>
                <th rowspan="2" style="text-align:center;"><b>{{ number_format($totInt, 2) }}</b></th>
                <th rowspan="2" style="text-align:center;"><b>{{ number_format($totCap + $totInt, 2) }}</b></th>
                <th style="text-align:center;"><b>{{ number_format($totMora, 2) }}</b></th>
                <th style="text-align:center;"><b>{{ number_format($totPag, 2) }}</b></th>
                <th></th>
            </tr>
            <tr bgcolor="#CEE7FF">
                <td colspan="2" style="text-align:center;"><b>{{ number_format($totMora + $totPag, 2) }}</b></td>
                <td></td>
            </tr>

            {{-- Créditos diarios (tipoplani=4): pagos antes/después del cronograma --}}
            @if(count($dailyExtras))
                @foreach($dailyExtras as $e)
                    <tr>
                        <td {!! $cell !!}><b>{{ $e['num'] }}</b></td>
                        <td {!! $cell !!}>&nbsp;</td>
                        <td {!! $cell !!}><b>0.00</b></td>
                        <td {!! $cell !!}><b>0.00</b></td>
                        <td {!! $cell !!}><b>0.00</b></td>
                        <td {!! $cell !!}><b>{{ number_format($e['mora'], 2) }}</b></td>
                        <td {!! $cell !!}><b>{{ number_format($e['pagado'], 2) }}</b></td>
                        <td {!! $cell !!}><b>{{ $e['fecha'] }}</b></td>
                    </tr>
                @endforeach
                <tr bgcolor="#CEE7FF">
                    <th colspan="5" style="text-align:center;"><b>Totales</b></th>
                    <th style="text-align:center;"><b>{{ number_format($sumMoraExtra, 2) }}</b></th>
                    <th style="text-align:center;"><b>{{ number_format($sumImpoExtra, 2) }}</b></th>
                    <th></th>
                </tr>
            @endif

            {{-- Saldo (igual que la pantalla: rojo) --}}
            @if(count($rows) > 0 || count($dailyExtras) > 0)
                <tr bgcolor="#F0F0F0">
                    <td colspan="5" style="text-align:center;color:red;"><b>Saldo</b></td>
                    <td colspan="2" style="text-align:center;color:red;"><b>{{ number_format(abs($saldo), 2) }}</b></td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
