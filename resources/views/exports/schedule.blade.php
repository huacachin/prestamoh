@extends('exports.layout')

{{-- Excel del cronograma: espejo del legacy cliente_viewexcel2.php (25/09).
     Antes era espejo de la pantalla nueva (12 columnas); el área lo quiere
     igual al legacy: título "Reporte de Pago", bloque de cabecera (Cliente,
     Asesor, Dni, Tasa, Exp., Capital, N° Cred. con fecha, Moneda), 8 columnas
     (N° Cuota, Periodo, Capital, Interes, Total, Mora, Pagado, Fecha Pago),
     fila en rojo si el periodo cae domingo y en verde si cae sábado, bloque
     Total, filas de pagos fuera del cronograma y pie Totales / Saldo con las
     mismas fórmulas del legacy. Los datos salen del mismo componente que la
     pantalla (Schedule), así que las cifras coinciden con ella. --}}

@php
    use Carbon\Carbon;

    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;"';
    $sol = 'style="border-style:solid;text-align:center;vertical-align:middle;"';
    $celda = fn (string $color) => 'style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;'.$color.'"';
    // Domingo en rojo, sábado en verde, por la fecha del periodo (como el legacy).
    $colorDia = function (string $fecha): string {
        if ($fecha === '') {
            return '';
        }
        $d = Carbon::parse($fecha)->dayOfWeek;

        return $d === Carbon::SUNDAY ? 'color:red;' : ($d === Carbon::SATURDAY ? 'color:green;' : '');
    };
    $n2 = fn ($v) => number_format((float) $v, 2);

    $cuotas = collect($rows);
    $totCap = $cuotas->sum('capital');
    $totInt = $cuotas->sum('interes');
    $totExc = $cuotas->sum('excedente');
    $totMora = $cuotas->sum('mora');          // mora pagada anotada en cuotas
    $totPag = $cuotas->sum('pagado');         // capital + interés (+ excedente) pagado en cuotas
    $otros = collect($otrosRows);
    $otrosPag = $sumOtros;                    // pagos fuera del cronograma
    $otrosMora = $sumOtrosMora;               // mora sin cuota
    // Fórmulas del legacy: la tercera fila de "Totales" suma la mora de las cuotas
    // solo en los diarios (tipoplani 4); en semanales/mensuales no la incluye.
    $esDiario = (int) $credit->tipo_planilla === 4;
    $totalPagadoMas = $esDiario ? $totPag + $otrosPag + $totMora + $otrosMora : $totPag + $otrosPag + $otrosMora;
    $saldoLegacy = abs($totCap + $totInt + $totExc - $totPag - $otrosPag);
    $asesor = $credit->client?->asesor;
@endphp

@section('content')
    <center style="color:red"><b>Reporte de Pago</b></center>
    <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="15%"><b>Cliente </b></td>
            <td width="50%" align="left" colspan="3">{{ $credit->client?->fullName() }}</td>
            <td><b>Asesor </b></td>
            <td colspan="3" align="left">{{ $asesor?->name ?? $asesor?->username ?? '' }}</td>
        </tr>
        <tr>
            <td><b>Dni </b></td>
            <td align="left" colspan="3">{{ $credit->client?->documento }}</td>
            <td><b>Tasa % </b></td>
            <td colspan="3" align="left">{{ $n2($credit->interes) }}</td>
        </tr>
        <tr>
            <td><b>N&deg; Exp.</b></td>
            <td align="left" colspan="3">{{ $credit->client?->expediente }}</td>
            <td><b>Capital </b></td>
            <td colspan="3" align="left">{{ $n2($credit->importe) }}</td>
        </tr>
        <tr>
            <td><b>N&deg; Cred.</b></td>
            <td align="left" colspan="3">{{ $credit->id }} - <b>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</b></td>
            <td><b>Moneda </b></td>
            <td colspan="3" align="left">Soles</td>
        </tr>
    </table>
    <table border="1" cellspacing="0">
        <thead>
            <tr height="25">
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
        @foreach($cuotas as $row)
            @php $c = $celda($colorDia($row['periodo'])); @endphp
            <tr>
                <th {!! $c !!}>{{ $row['n'] }}</th>
                <th {!! $c !!}>{{ $row['periodo'] }}</th>
                <th {!! $c !!}>{{ $n2($row['capital']) }}</th>
                <th {!! $c !!}>{{ $n2($row['interes']) }}</th>
                <th {!! $c !!}>{{ $n2($row['capital'] + $row['interes'] + $row['excedente']) }}</th>
                <th {!! $c !!}>{{ $n2($row['mora']) }}</th>
                <th {!! $c !!}>{{ $n2($row['pagado']) }}</th>
                <th {!! $c !!}>@if($row['pagado'] >= 0.01){{ $row['fecha_pago'] }}@endif</th>
            </tr>
        @endforeach
        {{-- Bloque Total del cronograma (Total | capital | interés | total | mora | pagado, y debajo mora+pagado) --}}
        <tr>
            <th {!! $sol !!} colspan="2" rowspan="2"><b>Total</b></th>
            <th {!! $sol !!} rowspan="2"><b>{{ $n2($totCap) }}</b></th>
            <th {!! $sol !!} rowspan="2"><b>{{ $n2($totInt) }}</b></th>
            <th {!! $sol !!} rowspan="2"><b>{{ $n2($totCap + $totInt + $totExc) }}</b></th>
            <th {!! $sol !!}><b>{{ $n2($totMora) }}</b></th>
            <th {!! $sol !!}><b>{{ $n2($totPag) }}</b></th>
            <th {!! $sol !!}></th>
        </tr>
        <tr>
            <td {!! $sol !!} colspan="2"><b>{{ $n2($totMora + $totPag) }}</b></td>
            <td {!! $sol !!}></td>
        </tr>
        {{-- Pagos fuera del cronograma (mismas filas que en la pantalla) --}}
        @foreach($otros as $row)
            @php $c = $celda(''); @endphp
            <tr>
                <td {!! $c !!}><b>{{ $row['n'] }}</b></td>
                <td {!! $c !!}><b>&nbsp;</b></td>
                <td {!! $c !!}><b>0.00</b></td>
                <td {!! $c !!}><b>0.00</b></td>
                <td {!! $c !!}><b>0.00</b></td>
                <td {!! $c !!}><b>{{ $row['mora'] > 0 ? $n2($row['mora']) : '' }}</b></td>
                <td {!! $c !!}><b>{{ $n2($row['pagado']) }}</b></td>
                <td {!! $c !!}><b>{{ $row['fecha_pago'] }}</b></td>
            </tr>
        @endforeach
        {{-- Pie: Totales de los pagos fuera del cronograma y Saldo, fórmulas del legacy --}}
        <tr>
            <th {!! $sol !!} colspan="5" rowspan="3"><b>Totales</b></th>
            <th {!! $sol !!}><b>{{ $n2($otrosMora) }}</b></th>
            <th {!! $sol !!}><b>{{ $n2($otrosPag) }}</b></th>
            <th {!! $sol !!} rowspan="3"></th>
        </tr>
        <tr>
            <td {!! $sol !!} colspan="2"><b>{{ $n2($otrosPag + $otrosMora) }}</b></td>
        </tr>
        <tr>
            <td {!! $sol !!} colspan="2"><b>{{ $n2($totalPagadoMas) }}</b></td>
        </tr>
        <tr>
            <td {!! $sol !!} colspan="5"><font color="red"><b>Saldo</b></font></td>
            <td {!! $sol !!} colspan="2"><font color="red"><b>{{ $n2($saldoLegacy) }}</b></font></td>
            <td {!! $sol !!}></td>
        </tr>
        </tbody>
    </table>
@endsection
