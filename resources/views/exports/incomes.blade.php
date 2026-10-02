@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';

    // Montos como NÚMERO (02/10): valor crudo + formato de Excel, para que se
    // puedan sumar (ver exports/layout). Se ve igual que antes: "1,234.50".
    $num = "mso-number-format:'#,##0.00';";
    $n = fn ($v) => number_format((float) $v, 2, '.', '');
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
                    <td class="num" style="border-style:dotted solid dotted solid;text-align:center;{{ $st }}{!! $num !!}">{{ $n($r->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse

            <tr bgcolor="#CEE7FF">
                <th colspan="4" rowspan="6"><b>Total</b></th>
                <td></td>
                <td></td>
                <td class="num" align="right" style="{!! $num !!}"><b>{{ $n($total) }}</b></td>
            </tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Fijos</b></td><td class="num" align="center" style="{!! $num !!}"><b>{{ $n($tofijo) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><font color="red"><b>Otros</b></font></td><td class="num" align="center" style="{!! $num !!}"><b>{{ $n($totros) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Capital</b></td><td class="num" align="center" style="{!! $num !!}"><b>{{ $n($tocapi) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Interes</b></td><td class="num" align="center" style="{!! $num !!}"><b>{{ $n($totinte) }}</b></td><td></td></tr>
            <tr bgcolor="#CEE7FF"><td align="center"><b>Mora</b></td><td class="num" align="center" style="{!! $num !!}"><b>{{ $n($totmora) }}</b></td><td></td></tr>
        </tbody>
    </table>
@endsection
