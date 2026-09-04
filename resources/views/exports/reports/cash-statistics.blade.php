@extends('exports.layout')

{{-- Excel del Reporte Estadístico de Caja: espejo FIEL de
     /reports/cash-statistics (equivalente del legacy caja-estadisticae1.php).
     Detalles homologados con la pantalla: domingos con fondo rojo SOLO en la
     celda Fecha (como la pantalla), fila Promedio gris, rojos en totales,
     y las 7 secciones completas: diario, detalles y distribución del mes,
     resumen mensual (Total + Promedio), detalles y distribución del acumulado
     del año, y resumen anual. --}}

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;vertical-align:middle;"';
    $celln = 'style="border-style:dotted solid dotted solid;text-align:right;"';
    $cellc = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $red = 'style="border-style:dotted solid dotted solid;text-align:right;color:red;"';
    $redc = 'style="border-style:dotted solid dotted solid;text-align:center;color:red;"';
    // Domingo: mismo #ffe5e5 de la pantalla, solo en la celda Fecha.
    // Va como bgcolor del <td> porque Excel ignora el del <tr>.
    $domc = 'bgcolor="#ffe5e5" style="border-style:dotted solid dotted solid;text-align:center;"';
    // Promedio: fondo #f0f0f0 como la pantalla.
    $gris = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:right;"';
    $grisc = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:center;"';
    $grisr = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:right;color:red;"';
@endphp

@section('content')
    <center><font color="red"><b>REPORTE ESTADISTICO DE CAJA — {{ $months[(int) $month] ?? $month }} {{ $year }}</b></font></center>

    {{-- ══ 1) DIARIO DEL MES ═══════════════════════════════════════════ --}}
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="5">Fecha</th>
                <th {!! $hd !!} rowspan="5">Capital T.</th>
                <th {!! $hd !!} colspan="14">CREDITO</th>
                <th {!! $hd !!} colspan="6" rowspan="2">OTROS MOVIMIENTOS</th>
            </tr>
            <tr>
                <th {!! $hd !!} colspan="12">Ingreso - Caja</th>
                <th {!! $hd !!} rowspan="4">Egreso</th>
                <th {!! $hd !!} rowspan="4">Utilidad Caja 3</th>
            </tr>
            <tr>
                <th {!! $hd !!} rowspan="3">Capital2</th>
                <th {!! $hd !!} colspan="10">Interes</th>
                <th {!! $hd !!} rowspan="3">Otros</th>
                <th {!! $hd !!} colspan="3">Ingreso</th>
                <th {!! $hd !!} colspan="3">Egreso</th>
            </tr>
            <tr>
                <th {!! $hd !!} colspan="3">Mensual</th>
                <th {!! $hd !!} colspan="3">Semanal</th>
                <th {!! $hd !!} colspan="3">Diario</th>
                <th {!! $hd !!} rowspan="2">Total</th>
                <th {!! $hd !!} rowspan="2">Fijos</th>
                <th {!! $hd !!} rowspan="2">Otros</th>
                <th {!! $hd !!} rowspan="2">Total</th>
                <th {!! $hd !!} rowspan="2">Fijos</th>
                <th {!! $hd !!} rowspan="2">Otros</th>
                <th {!! $hd !!} rowspan="2">Total</th>
            </tr>
            <tr>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td {!! $r['is_sunday'] ? $domc : $cellc !!}>{{ $r['day'] }}/{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</td>
                <td {!! $celln !!}>{{ number_format($r['capital_t'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['capital_cobrado'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['mensual_n'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['mensual_s'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['mensual_mora'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['semanal_n'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['semanal_s'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['semanal_mora'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['diario_n'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['diario_s'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['diario_mora'], 2) }}</td>
                <td {!! $red !!}>{{ number_format($r['total_credito'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otros_ing'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otros_egr'], 2) }}</td>
                <td {!! $red !!}>{{ number_format($r['utilidad_caja3'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['ing_fijos'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['ing_otros'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['ing_total'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['egr_fijos'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['egr_otros'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['egr_total'], 2) }}</td>
            </tr>
        @endforeach
            <tr>
                <td {!! $cellc !!}><b>Total</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['capital_t'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['capital_cobrado'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $totals['mensual_n'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['mensual_s'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['mensual_mora'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $totals['semanal_n'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['semanal_s'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['semanal_mora'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $totals['diario_n'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['diario_s'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['diario_mora'], 2) }}</b></td>
                <td {!! $red !!}><b>{{ number_format($totals['total_credito'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['otros_ing'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['otros_egr'], 2) }}</b></td>
                <td {!! $red !!}><b>{{ number_format($totals['utilidad_caja3'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['ing_fijos'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['ing_otros'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['ing_total'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['egr_fijos'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['egr_otros'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($totals['egr_total'], 2) }}</b></td>
            </tr>
        </tbody>
    </table>

    <br>

    {{-- ══ 2) RESUMEN MENSUAL DEL AÑO (Total + Promedio) ═══════════════ --}}
    <center><font color="red"><b>RESUMEN MENSUAL {{ $year }}</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="5">Mes</th>
                <th {!! $hd !!} rowspan="5">Capital T.</th>
                <th {!! $hd !!} colspan="14">CREDITO</th>
                <th {!! $hd !!} colspan="6" rowspan="2">OTROS MOVIMIENTOS</th>
            </tr>
            <tr>
                <th {!! $hd !!} colspan="12">Ingreso - Caja</th>
                <th {!! $hd !!} rowspan="4">Egreso</th>
                <th {!! $hd !!} rowspan="4">Utilidad Caja 3</th>
            </tr>
            <tr>
                <th {!! $hd !!} rowspan="3">Capital</th>
                <th {!! $hd !!} colspan="10">Interes</th>
                <th {!! $hd !!} rowspan="3">Otros</th>
                <th {!! $hd !!} colspan="3">Ingreso</th>
                <th {!! $hd !!} colspan="3">Egreso</th>
            </tr>
            <tr>
                <th {!! $hd !!} colspan="3">Mensual</th>
                <th {!! $hd !!} colspan="3">Semanal</th>
                <th {!! $hd !!} colspan="3">Diario</th>
                <th {!! $hd !!} rowspan="2">Total</th>
                <th {!! $hd !!} rowspan="2">Fijos</th>
                <th {!! $hd !!} rowspan="2">Otros</th>
                <th {!! $hd !!} rowspan="2">Total</th>
                <th {!! $hd !!} rowspan="2">Fijos</th>
                <th {!! $hd !!} rowspan="2">Otros</th>
                <th {!! $hd !!} rowspan="2">Total</th>
            </tr>
            <tr>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
            </tr>
        </thead>
        <tbody>
        @foreach($monthRowsData as $r)
            <tr>
                <td {!! $cellc !!}><b>{{ $r['mes_nombre'] }}</b></td>
                <td {!! $celln !!}>{{ number_format($r['capineto'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['capital'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['n1'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['mensual'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['mora3'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['n2'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['semanal'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['mora1'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['n3'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['diario'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['mora4'], 2) }}</td>
                <td {!! $red !!}>{{ number_format($r['total'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otros2'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['egresov'], 2) }}</td>
                <td {!! $red !!}>{{ number_format($r['utilidad2'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['fijoi'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otrosi'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['ingT'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['fijoe'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otrose'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['egrT'], 2) }}</td>
            </tr>
        @endforeach
            <tr>
                <td {!! $cellc !!}><b>Total</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['capineto_sum'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['capital'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $monthTotals['n1'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['mensual'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['mora3'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $monthTotals['n2'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['semanal'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['mora1'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $monthTotals['n3'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['diario'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['mora4'], 2) }}</b></td>
                <td {!! $red !!}><b>{{ number_format($monthTotals['total'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['otros2'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['egresov'], 2) }}</b></td>
                <td {!! $red !!}><b>{{ number_format($monthTotals['utilidad2'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['fijoi'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['otrosi'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['fijoi'] + $monthTotals['otrosi'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['fijoe'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['otrose'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($monthTotals['fijoe'] + $monthTotals['otrose'], 2) }}</b></td>
            </tr>
            <tr>
                <td {!! $grisc !!}><b>Promedio</b></td>
                <td {!! $gris !!}>{{ number_format($monthTotals['capineto_sum'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['capital'] / $monthsCount, 2) }}</td>
                <td {!! $grisc !!}>{{ number_format($monthTotals['n1'] / $monthsCount, 0) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['mensual'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['mora3'] / $monthsCount, 2) }}</td>
                <td {!! $grisc !!}>{{ number_format($monthTotals['n2'] / $monthsCount, 0) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['semanal'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['mora1'] / $monthsCount, 2) }}</td>
                <td {!! $grisc !!}>{{ number_format($monthTotals['n3'] / $monthsCount, 0) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['diario'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['mora4'] / $monthsCount, 2) }}</td>
                <td {!! $grisr !!}>{{ number_format($monthTotals['total'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['otros2'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['egresov'] / $monthsCount, 2) }}</td>
                <td {!! $grisr !!}>{{ number_format($monthTotals['utilidad2'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['fijoi'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['otrosi'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format(($monthTotals['fijoi'] + $monthTotals['otrosi']) / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['fijoe'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format($monthTotals['otrose'] / $monthsCount, 2) }}</td>
                <td {!! $gris !!}>{{ number_format(($monthTotals['fijoe'] + $monthTotals['otrose']) / $monthsCount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <br>

    {{-- ══ 3) RESUMEN ANUAL ════════════════════════════════════════════ --}}
    <center><font color="red"><b>RESUMEN ANUAL</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="5">A&ntilde;o</th>
                <th {!! $hd !!} rowspan="5">Capital T.</th>
                <th {!! $hd !!} colspan="14">CREDITO</th>
                <th {!! $hd !!} colspan="6" rowspan="2">OTROS MOVIMIENTOS</th>
            </tr>
            <tr>
                <th {!! $hd !!} colspan="12">Ingreso - Caja</th>
                <th {!! $hd !!} rowspan="4">Egreso</th>
                <th {!! $hd !!} rowspan="4">Utilidad Caja 3</th>
            </tr>
            <tr>
                <th {!! $hd !!} rowspan="3">Capital</th>
                <th {!! $hd !!} colspan="10">Interes</th>
                <th {!! $hd !!} rowspan="3">Otros</th>
                <th {!! $hd !!} colspan="3">Ingreso</th>
                <th {!! $hd !!} colspan="3">Egreso</th>
            </tr>
            <tr>
                <th {!! $hd !!} colspan="3">Mensual</th>
                <th {!! $hd !!} colspan="3">Semanal</th>
                <th {!! $hd !!} colspan="3">Diario</th>
                <th {!! $hd !!} rowspan="2">Total</th>
                <th {!! $hd !!} rowspan="2">Fijos</th>
                <th {!! $hd !!} rowspan="2">Otros</th>
                <th {!! $hd !!} rowspan="2">Total</th>
                <th {!! $hd !!} rowspan="2">Fijos</th>
                <th {!! $hd !!} rowspan="2">Otros</th>
                <th {!! $hd !!} rowspan="2">Total</th>
            </tr>
            <tr>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th><th {!! $hd !!}>S/</th><th {!! $hd !!}>Mora</th>
            </tr>
        </thead>
        <tbody>
        @foreach($yearRowsData as $r)
            <tr>
                <td {!! $cellc !!}><b>{{ $r['idano'] }}</b></td>
                <td {!! $celln !!}>{{ number_format($r['capineto'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['capital'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['n1'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['mensual'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['mora3'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['n2'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['semanal'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['mora1'], 2) }}</td>
                <td {!! $cellc !!}>{{ $r['n3'] ?: '' }}</td>
                <td {!! $celln !!}>{{ number_format($r['diario'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['mora4'], 2) }}</td>
                <td {!! $red !!}>{{ number_format($r['total'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otros2'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['egresov'], 2) }}</td>
                <td {!! $red !!}>{{ number_format($r['utilidad2'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['fijoi'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otrosi'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['ingT'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['fijoe'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['otrose'], 2) }}</td>
                <td {!! $celln !!}>{{ number_format($r['egrT'], 2) }}</td>
            </tr>
        @endforeach
            <tr>
                <td {!! $cellc !!}><b>Total</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['capineto'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['capital'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $yearTotals['n1'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['mensual'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['mora3'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $yearTotals['n2'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['semanal'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['mora1'], 2) }}</b></td>
                <td {!! $cellc !!}><b>{{ $yearTotals['n3'] }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['diario'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['mora4'], 2) }}</b></td>
                <td {!! $red !!}><b>{{ number_format($yearTotals['total'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['otros2'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['egresov'], 2) }}</b></td>
                <td {!! $red !!}><b>{{ number_format($yearTotals['utilidad2'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['fijoi'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['otrosi'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['fijoi'] + $yearTotals['otrosi'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['fijoe'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['otrose'], 2) }}</b></td>
                <td {!! $celln !!}><b>{{ number_format($yearTotals['fijoe'] + $yearTotals['otrose'], 2) }}</b></td>
            </tr>
        </tbody>
    </table>
@endsection
