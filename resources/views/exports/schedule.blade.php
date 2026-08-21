@extends('exports.layout')

{{-- Excel del cronograma: espejo de /credits/{id}/schedule (homologado 21/08).
     Mismas columnas de la pantalla (sin Rec., que es interactiva), numeración
     de vencidas (1-16) y pendientes (1-4) en la columna Cuota, fila roja para
     vencidas y amarilla para pagadas tarde, Mora unificada (pagada negro /
     exonerada rojo con - D. días), Estado, y el pie completo: Totales,
     Retraso, Total pagado + mora, Saldo, Capital e Interés pendientes. --}}

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;vertical-align:middle;"';
    $celln = 'style="border-style:dotted solid dotted solid;text-align:right;"';
    $cellc = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $red = 'style="border-style:dotted solid dotted solid;text-align:right;color:red;"';
    $venctd = 'bgcolor="#f8d7da" style="border-style:dotted solid dotted solid;text-align:right;"';
    $venctdc = 'bgcolor="#f8d7da" style="border-style:dotted solid dotted solid;text-align:center;"';
    $tardetd = 'bgcolor="#fff3cd" style="border-style:dotted solid dotted solid;text-align:right;"';
    $tardetdc = 'bgcolor="#fff3cd" style="border-style:dotted solid dotted solid;text-align:center;"';
    $gris = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:right;font-weight:bold;"';
    $grisc = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:center;font-weight:bold;"';
    $grisr = 'bgcolor="#f0f0f0" style="border-style:dotted solid dotted solid;text-align:right;color:red;font-weight:bold;"';

    $tieneExc = ($totals['excedente'] ?? 0) > 0;
    $hoy = now()->format('Y-m-d');
@endphp

@section('content')
    <center><font color="red"><b>CRONOGRAMA DE PAGOS — CRÉDITO #{{ $credit->id }}</b></font></center>
    <table border="1" cellspacing="0">
        <tr>
            <td {!! $grisc !!}>Cliente</td>
            <td {!! $cellc !!} colspan="4">{{ $credit->client?->fullName() }}</td>
            <td {!! $grisc !!}>DNI</td>
            <td {!! $cellc !!}>{{ $credit->client?->documento }}</td>
            <td {!! $grisc !!}>Capital</td>
            <td {!! $celln !!}>{{ number_format($credit->importe, 2) }}</td>
            <td {!! $grisc !!}>INT. %</td>
            <td {!! $cellc !!}>{{ round($credit->interes, 2) }}</td>
            <td {!! $grisc !!}>F. Préstamo</td>
            <td {!! $cellc !!}>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
        </tr>
    </table>
    <br>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!}>Cuota</th>
                <th {!! $hd !!}>Fecha Venc.</th>
                <th {!! $hd !!}>Capital</th>
                <th {!! $hd !!}>Interés</th>
                @if($tieneExc)
                    <th {!! $hd !!}>Excedente</th>
                @endif
                <th {!! $hd !!}>Pagado Cap.</th>
                <th {!! $hd !!}>Pagado Int.</th>
                <th {!! $hd !!}>Pagado</th>
                <th {!! $hd !!}>Saldo</th>
                <th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>Estado</th>
                <th {!! $hd !!}>Fecha Pago</th>
            </tr>
        </thead>
        <tbody>
        @php $nVencida = 0; $nPendiente = 0; @endphp
        @foreach($rows as $row)
            @php
                $vencida = ! $row['flag_pagado'] && $row['periodo'] !== '' && $row['periodo'] < $hoy;
                $pendiente = ! $row['flag_pagado'] && ! $vencida;
                if ($vencida) $nVencida++;
                if ($pendiente) $nPendiente++;
                // Roja: vencida impaga (gana). Amarilla: pagada tarde.
                $tdn = $vencida ? $venctd : ($row['tarde'] ? $tardetd : $celln);
                $tdc = $vencida ? $venctdc : ($row['tarde'] ? $tardetdc : $cellc);
            @endphp
            <tr>
                <td {!! $tdc !!}>@if($vencida){{ $nVencida }}-@elseif($pendiente){{ $nPendiente }}-@endif{{ $row['n'] }}</td>
                <td {!! $tdc !!}>{{ $row['periodo'] }}</td>
                <td {!! $tdn !!}>{{ number_format($row['capital'], 2) }}</td>
                <td {!! $tdn !!}>{{ number_format($row['interes'], 2) }}</td>
                @if($tieneExc)
                    <td {!! $tdn !!}>{{ number_format($row['excedente'], 2) }}</td>
                @endif
                <td {!! $tdn !!}>{{ number_format($row['pagado_cap'], 2) }}</td>
                <td {!! $tdn !!}>{{ number_format($row['pagado_int'], 2) }}</td>
                <td {!! $tdn !!}>{{ number_format($row['pagado_cap'] + $row['pagado_int'] + $row['pagado_exc'], 2) }}</td>
                <td {!! $tdn !!}>{{ number_format($row['saldo'], 2) }}</td>
                <td {!! $tdn !!}>
                    @if($row['mora'] > 0){{ number_format($row['mora'], 2) }}@endif
                    @if($row['mora_exon'] > 0)
                        @if($row['mora'] > 0)<br>@endif
                        <font color="red">{{ number_format($row['mora_exon'], 2) }} - D. {{ $row['mora_exon_dias'] }}</font>
                    @endif
                    @if($row['mora'] <= 0 && $row['mora_exon'] <= 0)
                        0.00
                    @endif
                </td>
                <td {!! $tdc !!}>
                    @if($row['flag_pagado'])<font color="green">Pagado</font>
                    @elseif($vencida)<font color="red">Vencida</font>
                    @else<font color="#b58900">Pendiente</font>
                    @endif
                </td>
                <td {!! $tdc !!}>{{ $row['fecha_pago'] }}@if($row['hora']) {{ $row['hora'] }}@endif</td>
            </tr>
        @endforeach

        {{-- Pagos OTROS (fuera del cronograma) --}}
        @foreach($otrosRows as $row)
            <tr>
                <td {!! $cellc !!}><b>{{ $row['n'] }}</b></td>
                <td {!! $cellc !!}></td>
                <td {!! $cellc !!}><b>0.00</b></td>
                <td {!! $cellc !!}><b>0.00</b></td>
                @if($tieneExc)
                    <td {!! $cellc !!}><b>0.00</b></td>
                @endif
                <td {!! $cellc !!}><b>0.00</b></td>
                <td {!! $cellc !!}><b>0.00</b></td>
                <td {!! $celln !!}><b>{{ number_format($row['pagado'], 2) }}</b></td>
                <td {!! $cellc !!}></td>
                <td {!! $celln !!}><b>{{ number_format($row['mora'], 2) }}</b></td>
                <td {!! $cellc !!}>Otros</td>
                <td {!! $cellc !!}><b>{{ $row['fecha_pago'] }}@if($row['hora']) {{ $row['hora'] }}@endif</b></td>
            </tr>
        @endforeach

        @php
            $tPagCap = collect($rows)->sum('pagado_cap');
            $tPagInt = collect($rows)->sum('pagado_int');
            $tPagExc = collect($rows)->sum('pagado_exc');
            $tSaldoCol = collect($rows)->sum('saldo');
            $moraGlobal = $totals['mora'] + $sumOtrosMora;
            $exonGlobal = $totals['mora_exon'] + $sumOtrosExon;
            $exonDiasGlobal = $totals['mora_exon_dias'] + $sumOtrosExonDias;
            $vencidasRows = collect($rows)->filter(fn ($r) => ! $r['flag_pagado'] && $r['periodo'] !== '' && $r['periodo'] < $hoy);
            $tRetraso = $vencidasRows->sum('saldo');
            $intPendienteTotal = round($totals['interes'] - $tPagInt, 2);
            $lblSpan = $tieneExc ? 8 : 7;
        @endphp
        {{-- Totales --}}
        <tr>
            <td {!! $grisc !!} colspan="2">Totales</td>
            <td {!! $gris !!}>{{ number_format($totals['capital'], 2) }}</td>
            <td {!! $gris !!}>{{ number_format($totals['interes'], 2) }}</td>
            @if($tieneExc)
                <td {!! $gris !!}>{{ number_format($totals['excedente'], 2) }}</td>
            @endif
            <td {!! $gris !!}>{{ number_format($tPagCap, 2) }}</td>
            <td {!! $gris !!}>{{ number_format($tPagInt, 2) }}</td>
            <td {!! $gris !!}>{{ number_format($tPagCap + $tPagInt + $tPagExc + $sumOtros, 2) }}</td>
            <td {!! $gris !!}>{{ number_format($tSaldoCol, 2) }}</td>
            <td {!! $gris !!}>
                @if($moraGlobal > 0){{ number_format($moraGlobal, 2) }}@endif
                @if($exonGlobal > 0)
                    @if($moraGlobal > 0)<br>@endif
                    <font color="red">{{ number_format($exonGlobal, 2) }}@if($exonDiasGlobal > 0) - D. {{ $exonDiasGlobal }}@endif</font>
                @endif
                @if($moraGlobal <= 0 && $exonGlobal <= 0)
                    0.00
                @endif
            </td>
            <td {!! $grisc !!}></td>
            <td {!! $grisc !!}></td>
        </tr>
        {{-- Retraso: lo impago SOLO de las cuotas vencidas --}}
        <tr>
            <td {!! $grisr !!} colspan="{{ $lblSpan }}">Retraso</td>
            <td {!! $grisr !!}>{{ number_format($tRetraso, 2) }}</td>
            <td {!! $grisc !!}></td>
            <td {!! $grisc !!}></td>
            <td {!! $grisc !!}></td>
        </tr>
        @if($moraGlobal > 0)
            <tr>
                <td {!! $grisc !!} colspan="{{ $lblSpan }}">Total pagado + mora</td>
                <td {!! $gris !!} colspan="2">{{ number_format($totalGeneral, 2) }}</td>
                <td {!! $grisc !!}></td>
                <td {!! $grisc !!}></td>
            </tr>
        @endif
        <tr>
            <td {!! $grisr !!} colspan="{{ $lblSpan }}">Saldo</td>
            <td {!! $grisr !!} colspan="2">{{ number_format(abs($saldo), 2) }}</td>
            <td {!! $grisc !!}></td>
            <td {!! $grisc !!}></td>
        </tr>
        <tr>
            <td {!! $grisr !!} colspan="{{ $lblSpan }}">Capital pendiente total</td>
            <td {!! $grisr !!} colspan="2">{{ number_format($capPendienteTotal, 2) }}</td>
            <td {!! $grisc !!}></td>
            <td {!! $grisc !!}></td>
        </tr>
        <tr>
            <td {!! $grisr !!} colspan="{{ $lblSpan }}">Interés pendiente total</td>
            <td {!! $grisr !!} colspan="2">{{ number_format($intPendienteTotal, 2) }}</td>
            <td {!! $grisc !!}></td>
            <td {!! $grisc !!}></td>
        </tr>
        </tbody>
    </table>
@endsection
