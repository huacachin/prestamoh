@extends('exports.layout')

{{-- Excel del Reporte Estadístico de Caja: espejo de /reports/cash-statistics
     (equivalente del legacy caja-estadisticae1.php), con las 5 secciones de la
     pantalla: diario del mes, detalles, distribución, resumen mensual y anual. --}}

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;vertical-align:middle;"';
    $celln = 'style="border-style:dotted solid dotted solid;text-align:right;"';
    $cellc = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $red = 'style="border-style:dotted solid dotted solid;text-align:right;color:red;"';
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
            <tr @if($r['is_sunday']) bgcolor="#ffe5e5" @endif>
                <td {!! $cellc !!}>{{ $r['day'] }}/{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</td>
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

    {{-- ══ 2) DETALLES ═════════════════════════════════════════════════ --}}
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} colspan="3">DETALLES</th>
                <th {!! $hd !!} colspan="6">Mensual / Semanal</th>
                <th {!! $hd !!} colspan="3">Diario</th>
                <th {!! $hd !!} colspan="2">Otros</th>
                <th {!! $hd !!}>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td {!! $cellc !!} colspan="3"><b>INGRESO</b></td>
                <td {!! $red !!} colspan="6"><b>{{ number_format($detalleSummary['ing_ms'], 2) }}</b></td>
                <td {!! $red !!} colspan="3"><b>{{ number_format($detalleSummary['ing_d'], 2) }}</b></td>
                <td {!! $celln !!} colspan="2"></td>
                <td {!! $celln !!}>{{ number_format($detalleSummary['ing_total'], 2) }}</td>
            </tr>
            <tr>
                <td {!! $cellc !!} colspan="3"><b>EGRESO</b></td>
                <td {!! $celln !!} colspan="6">{{ number_format($detalleSummary['egr_ms'], 2) }}</td>
                <td {!! $celln !!} colspan="3">{{ number_format($detalleSummary['egr_d'], 2) }}</td>
                <td {!! $celln !!} colspan="2"></td>
                <td {!! $celln !!}>{{ number_format($detalleSummary['egr_total'], 2) }}</td>
            </tr>
            <tr>
                <td {!! $cellc !!} colspan="3"><b>TOTAL</b></td>
                <td {!! $celln !!} colspan="6">{{ number_format($detalleSummary['tot_ms'], 2) }}</td>
                <td {!! $celln !!} colspan="3">{{ number_format($detalleSummary['tot_d'], 2) }}</td>
                <td {!! $celln !!} colspan="2">{{ number_format($detalleSummary['tot_otros'], 2) }}</td>
                <td {!! $red !!}><b>{{ number_format($detalleSummary['tot_total'], 2) }}</b></td>
            </tr>
            <tr>
                <td {!! $cellc !!} colspan="3"><b>%</b></td>
                <td {!! $red !!} colspan="6"><b>{{ number_format($detalleSummary['pct_ms'], 2) }}%</b></td>
                <td {!! $red !!} colspan="3"><b>{{ number_format($detalleSummary['pct_d'], 2) }}%</b></td>
                <td {!! $celln !!} colspan="2"></td>
                <td {!! $red !!}><b>{{ number_format($detalleSummary['pct_total'], 2) }}%</b></td>
            </tr>
        </tbody>
    </table>

    <br>

    {{-- ══ 3) DISTRIBUCIÓN DE UTILIDAD ═════════════════════════════════ --}}
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} colspan="2">DETALLES</th>
                <th {!! $hd !!}>%</th>
                <th {!! $hd !!} colspan="2">M.S</th>
                <th {!! $hd !!} colspan="2">D</th>
                <th {!! $hd !!}>M.S + D</th>
            </tr>
        </thead>
        <tbody>
        @foreach($distribution as $dist)
            @php $marca = in_array($dist['label'], ['Utilidad', 'Total'], true); @endphp
            <tr>
                <td {!! $cellc !!} colspan="2"><b>{{ $dist['label'] }}</b></td>
                <td {!! $marca ? $red : $cellc !!}><b>{{ $dist['pct'] }}</b></td>
                <td {!! $dist['label'] === 'Total' ? $red : $celln !!} colspan="2"><b>{{ number_format($dist['ms'], 2) }}</b></td>
                <td {!! $dist['label'] === 'Total' ? $red : $celln !!} colspan="2"><b>{{ number_format($dist['d'], 2) }}</b></td>
                <td {!! $marca ? $red : $celln !!}><b>{{ number_format($dist['total'], 2) }}</b></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <br>

    {{-- ══ 4) RESUMEN MENSUAL DEL AÑO ══════════════════════════════════ --}}
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
        </tbody>
    </table>

    <br>

    {{-- ══ 5) RESUMEN ANUAL ════════════════════════════════════════════ --}}
    <center><font color="red"><b>RESUMEN ANUAL</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!}>A&ntilde;o</th>
                <th {!! $hd !!}>Capital T.</th>
                <th {!! $hd !!}>Capital</th>
                <th {!! $hd !!}>N&deg;</th>
                <th {!! $hd !!}>Mensual</th>
                <th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th>
                <th {!! $hd !!}>Semanal</th>
                <th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>N&deg;</th>
                <th {!! $hd !!}>Diario</th>
                <th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>Total</th>
                <th {!! $hd !!}>Otros</th>
                <th {!! $hd !!}>Egreso</th>
                <th {!! $hd !!}>Utilidad Caja 3</th>
                <th {!! $hd !!}>Ing. Fijos</th>
                <th {!! $hd !!}>Ing. Otros</th>
                <th {!! $hd !!}>Ing. Total</th>
                <th {!! $hd !!}>Egr. Fijos</th>
                <th {!! $hd !!}>Egr. Otros</th>
                <th {!! $hd !!}>Egr. Total</th>
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
