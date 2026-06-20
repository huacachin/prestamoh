@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';

    $dm2 = round($sumdm / 2, 2);
    $valor1 = $sumdiario + $dm2;
    $valor2 = $summensu + $dm2;
    $valor3 = $valor1 + $valor2;
@endphp

@section('content')
    <center><font color="red"><b>EGRESOS</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} width="50">N&deg;</th>
                <th {!! $hd !!} width="90">Fecha</th>
                <th {!! $hd !!} width="90">Usuario</th>
                <th {!! $hd !!}>A</th>
                <th {!! $hd !!}>Motivo</th>
                <th {!! $hd !!} width="90">S/.</th>
                <th {!! $hd !!} width="90">T.Comp.</th>
                <th {!! $hd !!} width="90">Respons.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $e)
                @php $st = ($e->modo === 'Otros') ? 'color:red;' : ''; @endphp
                <tr>
                    <th style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}"><b>{{ $i + 1 }}</b></th>
                    <th style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}"><b>{{ $e->date?->format('d/m/Y') }}</b></th>
                    <th style="border-style:dotted solid dotted solid;text-align:center;font-weight:100;{{ $st }}">{{ $e->user?->username ?? $e->user?->name ?? '' }}</th>
                    <th style="border-style:dotted solid dotted solid;text-align:center;font-weight:100;{{ $st }}">{{ $e->reason }}</th>
                    <th style="border-style:dotted solid dotted solid;text-align:center;font-weight:100;{{ $st }}">{{ $e->detail }}</th>
                    <th style="border-style:dotted solid dotted solid;text-align:center;font-weight:100;{{ $st }}">{{ number_format($e->total, 2) }}</th>
                    <th style="border-style:dotted solid dotted solid;text-align:center;font-weight:100;{{ $st }}">{{ $e->document_type }}</th>
                    <th style="border-style:dotted solid dotted solid;text-align:center;font-weight:100;{{ $st }}">{{ $e->in_charge }}</th>
                </tr>
            @empty
                <tr><td colspan="8" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse

            <tr bgcolor="#CEE7FF">
                <th colspan="4" rowspan="3" align="center"><b>Total</b></th>
                <td></td>
                <td align="right"><b>{{ number_format($total, 2) }}</b></td>
                <td></td>
                <td></td>
            </tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Fijos</b></td><td><b><font color="red">{{ number_format($tofijo, 2) }}</font></b></td><td></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><font color="red"><b>Otros</b></font></td><td><b>{{ number_format($totros, 2) }}</b></td><td></td><td></td></tr>
            <tr><td colspan="8"><b>&nbsp;</b></td></tr>
            <tr bgcolor="#CEE7FF"><td colspan="4"><b></b></td><td align="center"><b>Diario</b></td><td colspan="2" align="center"><b>{{ number_format($sumdiario, 2) }}</b></td><td><b>{{ number_format($valor1, 2) }}</b></td></tr>
            <tr bgcolor="#CEE7FF"><td colspan="4"><b></b></td><td align="center"><b>Mensual</b></td><td colspan="2" align="center"><b>{{ number_format($summensu, 2) }}</b></td><td><b>{{ number_format($valor2, 2) }}</b></td></tr>
            <tr bgcolor="#CEE7FF"><td colspan="4"><b></b></td><td align="center"><b>D.M</b></td><td><b>{{ number_format($sumdm, 2) }}</b></td><td><b>{{ number_format($dm2, 2) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td colspan="4"><b></b></td><td align="center"><b>Fijos</b></td><td colspan="2"><b></b></td><td><b><font color="red">{{ number_format($valor3, 2) }}</font></b></td></tr>
        </tbody>
    </table>
@endsection
