@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
@endphp

@section('content')
    <center><font color="red"><b>INGRESOS</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} width="50">N&deg;</th>
                <th {!! $hd !!} width="90">Fecha</th>
                <th {!! $hd !!} width="90">Usuario</th>
                <th {!! $hd !!} width="90">Asesor</th>
                <th {!! $hd !!}>A</th>
                <th {!! $hd !!}>Motivo</th>
                <th {!! $hd !!} width="90">S/.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $r)
                @php $st = ($r->modo === 'Otros') ? 'color:red;' : ''; @endphp
                <tr>
                    <td style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}">{{ $i + 1 }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}">{{ $r->date?->format('d/m/Y') }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}">{{ $r->usuario }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}">{{ $r->asesor }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}">{{ $r->reason }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}">{{ $r->detail }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}">{{ number_format($r->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse

            <tr bgcolor="#CEE7FF">
                <th colspan="4" rowspan="6"><b>Total</b></th>
                <td></td>
                <td></td>
                <td align="right"><b>{{ number_format($total, 2) }}</b></td>
            </tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Fijos</b></td><td align="center"><b>{{ number_format($tofijo, 2) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><font color="red"><b>Otros</b></font></td><td align="center"><b>{{ number_format($totros, 2) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Capital</b></td><td align="center"><b>{{ number_format($tocapi, 2) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Interes</b></td><td align="center"><b>{{ number_format($totinte, 2) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Mora</b></td><td align="center"><b>{{ number_format($totmora, 2) }}</b></td><td></td></tr>
        </tbody>
    </table>
@endsection
