@extends('exports.layout')

{{-- Excel del Reporte Estadístico de Crédito: espejo de
     /reports/credit-statistics. Dos secciones: diaria del mes seleccionado y
     mensual del año, con columnas Cap./Int. por tasa de interés, domingos con
     la celda Fecha en rojo (por CELDA — Excel ignora el bgcolor del <tr>),
     intereses y TOTAL en rojo/negrita, fila Total en gris. --}}

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;vertical-align:middle;"';
    $celln = 'style="border-style:dotted solid dotted solid;text-align:right;"';
    $cellc = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $red = 'style="border-style:dotted solid dotted solid;text-align:right;color:red;font-weight:bold;"';
    $dom = 'bgcolor="#ff0000" style="border-style:dotted solid dotted solid;text-align:center;color:white;"';
    $gris = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:right;font-weight:bold;"';
    $grisc = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:center;font-weight:bold;"';
    $grisr = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:right;color:red;font-weight:bold;"';
    // Mismo formato de la pantalla: vacío si es 0, sin ceros colgantes
    $fmt = fn ($v) => $v != 0 ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '';
@endphp

@section('content')
    <center><font color="red"><b>REPORTE ESTADISTICO DE CREDITO — {{ $months[$selemes] ?? $selemes }} {{ $selecano }}</b></font></center>

    @foreach([
        ['titulo' => 'DIARIO DEL MES', 'rows' => $dailyRows, 'totals' => $dailyTotals, 'rates' => $dailyRates, 'label' => 'fecha'],
        ['titulo' => 'MENSUAL '.$selecano, 'rows' => $monthlyRows, 'totals' => $monthlyTotals, 'rates' => $monthlyRates, 'label' => 'mes_label'],
    ] as $sec)
        @if(!$loop->first)
            <br>
            <center><font color="red"><b>RESUMEN {{ $sec['titulo'] }}</b></font></center>
        @endif
        <table border="1" cellspacing="0">
            <thead>
                <tr>
                    <th {!! $hd !!} rowspan="2">Fecha</th>
                    <th {!! $hd !!} rowspan="2">Ingresos Creditos</th>
                    <th {!! $hd !!} rowspan="2">Egresos Capital</th>
                    @foreach($sec['rates'] as $rate)
                        <th {!! $hd !!} colspan="2">{{ $rate }}%</th>
                    @endforeach
                    <th {!! $hd !!} rowspan="2">TOTAL</th>
                </tr>
                <tr>
                    @foreach($sec['rates'] as $rate)
                        <th {!! $hd !!}>Cap.</th>
                        <th {!! $hd !!}>Int.</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @foreach($sec['rows'] as $row)
                <tr>
                    <td {!! ($row['is_sunday'] ?? false) ? $dom : $cellc !!}>{{ $row[$sec['label']] }}</td>
                    <td {!! $celln !!}>{{ $fmt($row['ingresos']) }}</td>
                    <td {!! $celln !!}>{{ $fmt($row['egresos']) }}</td>
                    @foreach($sec['rates'] as $rate)
                        @php $cell = $row['rates'][(string) $rate]; @endphp
                        <td {!! $celln !!}>{{ $fmt($cell['cap']) }}</td>
                        <td {!! $red !!}>{{ $cell['int'] != 0 ? number_format($cell['int'], 2) : '' }}</td>
                    @endforeach
                    <td {!! $red !!}>{{ number_format($row['total_int'], 2) }}</td>
                </tr>
            @endforeach
                <tr>
                    <td {!! $grisc !!}>Total</td>
                    <td {!! $gris !!}>{{ number_format($sec['totals']['ingresos'], 2) }}</td>
                    <td {!! $gris !!}>{{ number_format($sec['totals']['egresos'], 2) }}</td>
                    @foreach($sec['rates'] as $rate)
                        <td {!! $gris !!}>{{ number_format($sec['totals']['rates_cap'][(string) $rate], 2) }}</td>
                        <td {!! $grisr !!}>{{ number_format($sec['totals']['rates_int'][(string) $rate], 2) }}</td>
                    @endforeach
                    <td {!! $grisr !!}>{{ number_format($sec['totals']['total_inter'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach
@endsection
