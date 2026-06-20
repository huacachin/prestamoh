@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;"';
    $cell = 'style="border-style:dotted solid dotted solid;"';
    $cellc = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $cellr = 'style="border-style:dotted solid dotted solid;text-align:right;"';
    $blue = 'color:#0d6efd;';
    $red = 'color:#dc3545;';
@endphp

@section('content')
    <center><font color="red"><b>REPORTE GENERAL CAJA 3</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} width="40">N&deg;</th>
                <th {!! $hd !!} width="90">FECHA</th>
                <th {!! $hd !!} width="200">DATOS DEL CLIENTE</th>
                <th {!! $hd !!} width="160">DETALLES</th>
                <th {!! $hd !!} width="100">INGRESO</th>
                <th {!! $hd !!} width="100">EGRESO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['days'] as $day)
                @foreach($day['items'] as $item)
                    <tr>
                        <td {!! $cellc !!}><b>{{ $item['n'] }}</b></td>
                        <td {!! $cellc !!}><b>{{ $item['fecha'] }}</b></td>
                        <td {!! $cell !!}>{{ $item['cliente'] }}</td>
                        <td {!! $cell !!}>{{ $item['detalle'] }}</td>
                        <td {!! $cellr !!}>@if($item['ingreso'] > 0)<font color="#0d6efd">{{ number_format($item['ingreso'], 2) }}</font>@endif</td>
                        <td {!! $cellr !!}>@if($item['egreso'] > 0)<font color="#dc3545">{{ number_format($item['egreso'], 2) }}</font>@endif</td>
                    </tr>
                @endforeach
                <tr bgcolor="#F0F0F0">
                    <td></td><td></td><td></td>
                    <td {!! $cell !!}><b>TOTAL</b></td>
                    <td {!! $cellr !!}><b><font color="#0d6efd">{{ number_format($day['total_ingreso'], 2) }}</font></b></td>
                    <td {!! $cellr !!}><b><font color="#dc3545">{{ number_format($day['total_egreso'], 2) }}</font></b></td>
                </tr>
                <tr bgcolor="#CEE7FF">
                    <td></td><td></td><td></td>
                    <td {!! $cell !!}><b>SALDO <font color="#dc3545">FINAL-INICIAL</font></b></td>
                    <td {!! $cellr !!}><b><font color="#0d6efd">{{ number_format($day['saldo'], 2) }}</font></b></td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="6" {!! $cellc !!}>Sin movimientos para el periodo seleccionado</td></tr>
            @endforelse

            @if(count($report['days']) > 0)
                <tr bgcolor="#FFFFFF">
                    <td colspan="4"><b>REPORTE GENERAL <font color="#dc3545">CAJA 3 - </font>TOTAL <font color="#dc3545">GENERAL</font></b></td>
                    <td {!! $cellr !!}><b><font color="#0d6efd">{{ number_format($report['total_ingresos'], 2) }}</font></b></td>
                    <td {!! $cellr !!}><b><font color="#dc3545">{{ number_format($report['total_egresos'], 2) }}</font></b></td>
                </tr>
            @endif
        </tbody>
    </table>

    @if(count($report['days']) > 0)
        <br>
        <center><b>RESUMEN DE INGRESOS</b></center>
        <table border="1" cellspacing="0">
            <thead>
                <tr>
                    <th {!! $hd !!} width="40">N&deg;</th>
                    <th {!! $hd !!} colspan="2" width="240">Conceptos</th>
                    <th {!! $hd !!} colspan="2" width="200">Monto</th>
                </tr>
            </thead>
            <tbody>
                @php $sCount = 0; $advisorCount = count($report['by_advisor']); $advisorIdx = 0; @endphp
                <tr>
                    <td {!! $cellc !!}><b>{{ ++$sCount }}</b></td>
                    <td {!! $cell !!}><b>INTERES</b></td>
                    <td {!! $cellr !!}><font color="#0d6efd">0.00</font></td>
                    <td {!! $cellr !!}><font color="#0d6efd">{{ number_format($report['total_interes'], 2) }}</font></td>
                    <td {!! $cellr !!} rowspan="2">{{ number_format($report['total_interes'] + $report['total_mora'], 2) }}</td>
                </tr>
                <tr>
                    <td {!! $cellc !!}><b>{{ ++$sCount }}</b></td>
                    <td {!! $cell !!}><b>MORA</b></td>
                    <td {!! $cellr !!}><font color="#0d6efd">0.00</font></td>
                    <td {!! $cellr !!}><font color="#0d6efd">{{ number_format($report['total_mora'], 2) }}</font></td>
                </tr>
                @foreach($report['by_advisor'] as $row)
                    @php $advisorIdx++; @endphp
                    <tr>
                        <td {!! $cellc !!}><b>{{ ++$sCount }}</b></td>
                        <td {!! $cell !!}><b>{{ trim(($row->aa ?? '') . ' ' . ($row->asesores ?? '')) }}</b></td>
                        <td {!! $cellr !!}><font color="#0d6efd">{{ number_format($row->tm ?? 0, 2) }}</font></td>
                        <td {!! $cellr !!}><font color="#0d6efd">{{ number_format($row->gm, 2) }}</font></td>
                        @if($advisorIdx === 1)
                            <td {!! $cellr !!} rowspan="{{ max(1, $advisorCount) }}">{{ number_format($report['total_advisor'], 2) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr bgcolor="#CEE7FF">
                    <td></td>
                    <td {!! $cell !!}><b>Total General</b></td>
                    <td {!! $cellr !!}><b><font color="#0d6efd">0.00</font></b></td>
                    <td {!! $cellc !!} colspan="2"><b><font color="#0d6efd">{{ number_format($report['total_resumen'], 2) }}</font></b></td>
                </tr>
            </tfoot>
        </table>
    @endif
@endsection
