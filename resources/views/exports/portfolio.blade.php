@extends('exports.layout')

@php
    $hd   = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $tc   = ($tc ?? 0) > 0 ? $tc : 1;
    $m    = $morisidad ?? [];
    $tt   = $tipoTotals ?? [];
@endphp

@section('content')
    <center><font color="red"><b>RESUMEN DE CREDITO</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="2" width="15">N&ordm;</th>
                <th {!! $hd !!} rowspan="2" width="40">Exp</th>
                <th {!! $hd !!} rowspan="2" width="50">Codigo</th>
                <th {!! $hd !!} rowspan="2" width="50">DNI</th>
                <th {!! $hd !!} rowspan="2" width="250">Nombre y Apellidos</th>
                <th {!! $hd !!} rowspan="2" width="60">Dt.</th>
                <th {!! $hd !!} rowspan="2" width="70">Capital</th>
                <th {!! $hd !!} colspan="4" width="50">Interes</th>
                <th {!! $hd !!} rowspan="2" width="70">Total</th>
                <th {!! $hd !!} rowspan="2" width="70">Pago</th>
                <th {!! $hd !!} rowspan="2" width="80">Saldo</th>
                <th {!! $hd !!} rowspan="2" width="100">Fec/Cred</th>
                <th {!! $hd !!} rowspan="2" width="100">Fec/Venc</th>
                <th {!! $hd !!} rowspan="2" width="120">Fec/Ult/Pag</th>
                <th {!! $hd !!} rowspan="2" width="90">Cel/Titu</th>
                <th {!! $hd !!} rowspan="2" width="70">Estado</th>
                <th {!! $hd !!} rowspan="2" width="70">Tiempo</th>
                <th {!! $hd !!} rowspan="2" width="70">Ases</th>
            </tr>
            <tr>
                <th {!! $hd !!} width="60">TC</th>
                <th {!! $hd !!} width="50">%</th>
                <th {!! $hd !!} width="50">S/</th>
                <th {!! $hd !!} width="35">C.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                @php
                    $bgrow = ! empty($r['is_refi']) ? "style='background-color:yellow;'" : '';
                    $tipo  = (int) ($r['tipo_planilla'] ?? 0);
                    $tcSt  = $tipo === 1 ? "style='color:blue;'" : ($tipo === 3 ? "style='color:red;'" : '');
                    $estSt = ($r['estado'] ?? '') === 'Vencida' ? "style='color:red;'" : '';
                    $pct   = (float) ($r['interes_pct'] ?? 0);
                    $pctTxt = (intval($pct) == $pct) ? (string) intval($pct) : number_format($pct, 2);
                @endphp
                <tr {!! $bgrow !!}>
                    <td {!! $cell !!}>{{ $r['n'] }}</td>
                    <td {!! $cell !!}><font color="black">{{ $r['exp'] }}</font></td>
                    <td {!! $cell !!}>{{ $r['codigo'] }}</td>
                    <td class="txt" {!! $cell !!}>{{ $r['dni'] }}</td>
                    <td {!! $cell !!}>{{ $r['cliente'] }}</td>
                    <td {!! $cell !!}><font color="red">{{ $r['cod_rem'] }}</font></td>
                    <td {!! $cell !!}>{{ number_format($r['capital'], 2) }}</td>
                    <td {!! $tcSt !!} style="border-style:dotted solid dotted solid;text-align:center;"><b>{{ $r['tc_label'] }}{{ $r['tc_label'] !== '' ? '.' : '' }}</b></td>
                    <td {!! $cell !!}>{{ $pctTxt }}</td>
                    <td {!! $cell !!}>{{ number_format($r['interes_monto'], 2) }}</td>
                    <td {!! $cell !!}>{{ $r['cuotas'] }}</td>
                    <td {!! $cell !!}>{{ number_format($r['total'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['pago'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['saldo'], 2) }}</td>
                    <td {!! $cell !!}>{{ $r['fecha_cred'] }}</td>
                    <td {!! $cell !!}>{{ $r['fecha_venc'] }}</td>
                    <td {!! $cell !!}>{{ $r['fecha_ult_pago'] }}</td>
                    <td {!! $cell !!}>{{ $r['celular'] }}</td>
                    <td {!! $estSt !!} style="border-style:dotted solid dotted solid;text-align:center;">{{ $r['estado'] }}</td>
                    <td {!! $cell !!}>{{ $r['tiempo'] }}</td>
                    <td {!! $cell !!}>{{ $r['asesor'] }}</td>
                </tr>
            @empty
                <tr><td colspan="21" {!! $cell !!}>--</td></tr>
            @endforelse

            {{-- Total Soles --}}
            <tr>
                <td></td>
                <td colspan="4" style="color:#000;"><b>Total Soles</b></td>
                <td></td>
                <td style="color:#0d6efd;"><b>{{ number_format($totals['capital'] ?? 0, 2) }}</b></td>
                <td colspan="2"></td>
                <td style="color:#0d6efd;"><b>{{ number_format($totals['interes'] ?? 0, 2) }}</b></td>
                <td colspan="1"></td>
                <td style="color:#0d6efd;"><b>{{ number_format($totals['total'] ?? 0, 2) }}</b></td>
                <td style="color:#0d6efd;"><b>{{ number_format($totals['pago'] ?? 0, 2) }}</b></td>
                <td style="color:#0d6efd;"><b>{{ number_format($totals['saldo'] ?? 0, 2) }}</b></td>
                <td colspan="7"></td>
            </tr>
            {{-- Total Dolares --}}
            <tr>
                <td></td>
                <td colspan="4" style="color:#000;"><b>Total Dolares</b></td>
                <td></td>
                <td style="color:#0d6efd;"><b>{{ number_format(($totals['capital'] ?? 0) / $tc, 2) }}</b></td>
                <td colspan="2"></td>
                <td style="color:#0d6efd;"><b>{{ number_format(($totals['interes'] ?? 0) / $tc, 2) }}</b></td>
                <td colspan="1"></td>
                <td style="color:#0d6efd;"><b>{{ number_format(($totals['total'] ?? 0) / $tc, 2) }}</b></td>
                <td style="color:#0d6efd;"><b>{{ number_format(($totals['pago'] ?? 0) / $tc, 2) }}</b></td>
                <td style="color:#0d6efd;"><b>{{ number_format(($totals['saldo'] ?? 0) / $tc, 2) }}</b></td>
                <td colspan="7"></td>
            </tr>

            {{-- MORA --}}
            <tr>
                <td style="color:red;text-align:center;"><b>{{ number_format($m['mora_pct'] ?? 0, 2) }}%</b></td>
                <td style="background-color:red;color:white;text-align:center;">MORA</td>
                <td style="background-color:#005F8C;color:white;text-align:center;">{{ $m['mora_count'] ?? 0 }}</td>
                <td style="background-color:#005F8C;color:white;text-align:center;" colspan="3">TOTAL MORA</td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['mora_capital'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;" colspan="2"></td>
                <td style="background-color:yellow;"><b>{{ number_format($m['mora_interes'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['mora_total'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['mora_saldo'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;" colspan="7"></td>
            </tr>
            {{-- ACTIVOS --}}
            <tr>
                <td style="color:green;text-align:center;"><b>{{ number_format($m['activos_pct'] ?? 0, 2) }}%</b></td>
                <td style="background-color:green;color:white;text-align:center;">ACTIVOS</td>
                <td style="background-color:#005F8C;color:white;text-align:center;">{{ $m['activos_count'] ?? 0 }}</td>
                <td style="background-color:#005F8C;color:white;text-align:center;" colspan="3">TOTAL ACTIVOS</td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['activos_capital'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;" colspan="2"></td>
                <td style="background-color:yellow;"><b>{{ number_format($m['activos_interes'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['activos_total'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['activos_saldo'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;" colspan="7"></td>
            </tr>
            {{-- TOTAL --}}
            <tr>
                <td style="color:#005F8C;text-align:center;"><b>100.00%</b></td>
                <td style="background-color:#005F8C;color:white;text-align:center;">TOTAL</td>
                <td style="background-color:#005F8C;color:white;text-align:center;">{{ number_format($m['total_count'] ?? 0, 0) }}</td>
                <td style="background-color:#005F8C;color:white;text-align:center;" colspan="3">TOTAL</td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['total_capital'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;" colspan="2"></td>
                <td style="background-color:yellow;"><b>{{ number_format($m['total_interes'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['total_total'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($m['total_saldo'] ?? 0, 2) }}</b></td>
                <td style="background-color:yellow;" colspan="7"></td>
            </tr>
        </tbody>
    </table>
    <br>

    {{-- Resumen Vigente / Vencidas --}}
    <table border="1" cellspacing="0" style="text-align:center;">
        <thead>
            <tr>
                <th {!! $hd !!}>Tipo</th>
                <th {!! $hd !!}>Total</th>
            </tr>
        </thead>
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;color:green;"><b>Vigente</b></td>
            <td style="border-style:dotted solid dotted solid;text-align:center;color:green;"><b>{{ number_format($vignt ?? 0, 0) }}</b></td>
        </tr>
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;color:red;"><b>Vencidas</b></td>
            <td style="border-style:dotted solid dotted solid;text-align:center;color:red;"><b>{{ number_format($venc ?? 0, 0) }}</b></td>
        </tr>
        <tr>
            <td style="text-align:center;"><b>Total</b></td>
            <td style="text-align:center;"><b>{{ number_format(($vignt ?? 0) + ($venc ?? 0), 0) }}</b></td>
        </tr>
    </table>
    <br>

    {{-- Resumen por planilla --}}
    @php
        $totsem = $tt['totsem'] ?? 0; $totmen = $tt['totmen'] ?? 0; $totdia = $tt['totdia'] ?? 0;
        $tis = $tt['totintesem'] ?? 0; $tim = $tt['totintemen'] ?? 0; $tid = $tt['totintdiario'] ?? 0;
        $total1 = $totsem + $totmen + $totdia;
        $totinterez = $tis + $tim + $tid;
    @endphp
    <table border="1" cellspacing="0" style="text-align:center;">
        <thead>
            <tr>
                <th {!! $hd !!}>Tipo</th>
                <th {!! $hd !!}>Total</th>
                <th {!! $hd !!} colspan="2">Capital</th>
                <th {!! $hd !!}>Interes</th>
                <th {!! $hd !!}>50%</th>
                <th {!! $hd !!}>33%</th>
                <th {!! $hd !!}>25%</th>
            </tr>
        </thead>
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;color:blue;">Semanal</td>
            <td {!! $cell !!}>{{ number_format($tt['sempo'] ?? 0, 0) }}</td>
            <th {!! $cell !!} rowspan="2">{{ number_format($totsem + $totmen, 2) }}</th>
            <td {!! $cell !!}>{{ number_format($totsem, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tis, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tis / 2, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tis / 3, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tis / 4, 2) }}</td>
        </tr>
        <tr>
            <td style="border-style:dotted solid dotted solid;text-align:center;color:red;"><b style="color:red;">Mensual</b></td>
            <td {!! $cell !!}>{{ number_format($tt['mempo'] ?? 0, 0) }}</td>
            <td {!! $cell !!}>{{ number_format($totmen, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tim, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tim / 2, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tim / 3, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tim / 4, 2) }}</td>
        </tr>
        <tr>
            <td {!! $cell !!}><b>Diario</b></td>
            <td {!! $cell !!}>{{ number_format($tt['dempo'] ?? 0, 0) }}</td>
            <td {!! $cell !!}>{{ number_format($totdia, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($totdia, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tid, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tid / 2, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tid / 3, 2) }}</td>
            <td {!! $cell !!}>{{ number_format($tid / 4, 2) }}</td>
        </tr>
        <tr style="background-color:#CEE7FF;">
            <td style="text-align:center;"><b>Total</b></td>
            <td style="text-align:center;"><b>{{ number_format(($tt['sempo'] ?? 0) + ($tt['mempo'] ?? 0) + ($tt['dempo'] ?? 0), 0) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($total1, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($total1, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($totinterez, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($totinterez / 2, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($totinterez / 3, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($totinterez / 4, 2) }}</b></td>
        </tr>
    </table>
    <br>

    {{-- Resumen por % de interes (CREDITO) --}}
    @php
        $newcu = 0; $newprez = 0; $nintx = 0; $nxpago = 0; $ntot = 0;
    @endphp
    <table border="1" cellspacing="0" style="text-align:center;">
        <thead>
            <tr>
                <th {!! $hd !!} colspan="6">CREDITO</th>
            </tr>
            <tr>
                <th {!! $hd !!}>%</th>
                <th {!! $hd !!}>Cantidad</th>
                <th {!! $hd !!}>Capital</th>
                <th {!! $hd !!}>Interes</th>
                <th {!! $hd !!}>Pagado</th>
                <th {!! $hd !!}>Total</th>
            </tr>
        </thead>
        @foreach($byInteres ?? [] as $b)
            @php
                $newcu += $b['ncount']; $newprez += $b['capital']; $nintx += $b['interes'];
                $nxpago += $b['pago']; $ntot += $b['total'];
            @endphp
            <tr>
                <td {!! $cell !!}>{{ $b['porce'] }}</td>
                <td {!! $cell !!}>{{ $b['ncount'] }}</td>
                <td {!! $cell !!}>{{ number_format($b['capital'], 2) }}</td>
                <td {!! $cell !!}>{{ number_format($b['interes'], 2) }}</td>
                <td {!! $cell !!}>{{ number_format($b['pago'], 2) }}</td>
                <td {!! $cell !!}>{{ number_format($b['total'], 2) }}</td>
            </tr>
        @endforeach
        <tr style="background-color:#CEE7FF;">
            <td style="text-align:center;"><b>Total</b></td>
            <td style="text-align:center;"><b>{{ $newcu }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($newprez, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($nintx, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($nxpago, 2) }}</b></td>
            <td style="text-align:center;"><b>{{ number_format($ntot, 2) }}</b></td>
        </tr>
    </table>
@endsection
