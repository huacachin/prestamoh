@extends('exports.layout')

@php
    $hd   = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $tc   = ($tc ?? 0) > 0 ? $tc : 1;
@endphp

@section('content')
    <center><font color="red"><b>PENDIENTES POR COBRAR</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} rowspan="2" width="60">N&ordm;</th>
                <th {!! $hd !!} rowspan="2" width="40">Exp</th>
                <th {!! $hd !!} rowspan="2" width="90">Codigo</th>
                <th {!! $hd !!} rowspan="2" width="100">DNI</th>
                <th {!! $hd !!} rowspan="2" width="400">Nombre y Apellidos</th>
                <th {!! $hd !!} rowspan="2" width="60">Dt.</th>
                <th {!! $hd !!} rowspan="2" width="120">Capital</th>
                <th {!! $hd !!} colspan="4" width="50">Interes</th>
                <th {!! $hd !!} rowspan="2" width="120">Cuota</th>
                <th {!! $hd !!} rowspan="2" width="100">Pago</th>
                <th {!! $hd !!} rowspan="2" width="80">Saldo</th>
                <th {!! $hd !!} rowspan="2" width="140">Fec/Cred</th>
                <th {!! $hd !!} rowspan="2" width="140">Fec/Venc</th>
                <th {!! $hd !!} rowspan="2" width="120">Cel/Titu</th>
                <th {!! $hd !!} rowspan="2" width="90">Estado</th>
                <th {!! $hd !!} rowspan="2" width="230">Tiempo</th>
                <th {!! $hd !!} rowspan="2" width="90">Ases.</th>
            </tr>
            <tr>
                <th {!! $hd !!} width="60">TC</th>
                <th {!! $hd !!} width="50">%</th>
                <th {!! $hd !!} width="70">S/</th>
                <th {!! $hd !!} width="35">C.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                @php
                    $bgrow = ($r['estado'] ?? '') === 'Vencida' ? "style='background-color:yellow;'" : '';
                    $tipo  = (int) ($r['tipo_planilla'] ?? 0);
                    $tcSt  = $tipo === 1 ? "style='color:blue;'" : ($tipo === 3 ? "style='color:red;'" : '');
                    $estSt = ($r['estado'] ?? '') === 'Vencida' ? "style='color:red;'" : '';
                    $pct   = (float) ($r['interes_pct'] ?? 0);
                    $pctTxt = (intval($pct) == $pct) ? (string) intval($pct) : $pct;
                @endphp
                <tr {!! $bgrow !!}>
                    <td {!! $cell !!}>{{ $r['n'] }}</td>
                    <td {!! $cell !!}><font color="black">{{ $r['exp'] }}</font></td>
                    <td {!! $cell !!}>{{ $r['codigo'] }}</td>
                    <td class="txt" {!! $cell !!}>{{ $r['dni'] }}</td>
                    <td {!! $cell !!}>{{ $r['cliente'] }}</td>
                    <td {!! $cell !!}><font color="red">{{ $r['cod_rem'] }}</font></td>
                    <td {!! $cell !!}>{{ number_format($r['cuota'], 2) }}</td>
                    <td {!! $tcSt !!} style="border-style:dotted solid dotted solid;text-align:center;"><b>{{ $r['tc_label'] }}{{ $r['tc_label'] !== '' ? '.' : '' }}</b></td>
                    <td {!! $cell !!}>{{ $pctTxt }}</td>
                    <td {!! $cell !!}>{{ number_format($r['interes_monto'], 2) }}</td>
                    <td {!! $cell !!}></td>
                    <td {!! $cell !!}>{{ number_format($r['total'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['pago'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['saldo'], 2) }}</td>
                    <td {!! $cell !!}>{{ $r['fecha_cred'] }}</td>
                    <td {!! $cell !!}>{{ $r['fecha_venc'] }}</td>
                    <td {!! $cell !!}>{{ $r['celular'] }}</td>
                    <td {!! $estSt !!} style="border-style:dotted solid dotted solid;text-align:center;">{{ $r['estado'] }}</td>
                    <td {!! $cell !!}>{{ $r['tiempo'] }}</td>
                    <td {!! $cell !!}>{{ $r['asesor'] }}</td>
                </tr>
            @empty
                <tr><td colspan="20" {!! $cell !!}>--</td></tr>
            @endforelse

            {{-- Total Soles --}}
            <tr>
                <td {!! $hd !!}></td>
                <td {!! $hd !!} colspan="4"><b>Total Soles</b></td>
                <td {!! $hd !!}></td>
                <td {!! $hd !!}>{{ number_format($totals['cuota'] ?? 0, 2) }}</td>
                <td {!! $hd !!} colspan="2"></td>
                <td {!! $hd !!}>{{ number_format($totals['interes'] ?? 0, 2) }}</td>
                <td {!! $hd !!} colspan="1"></td>
                <td {!! $hd !!}>{{ number_format($totals['total'] ?? 0, 2) }}</td>
                <td {!! $hd !!}>{{ number_format($totals['pago'] ?? 0, 2) }}</td>
                <td {!! $hd !!}>{{ number_format($totals['saldo'] ?? 0, 2) }}</td>
                <td {!! $hd !!} colspan="6"></td>
            </tr>
            {{-- Total Dolares --}}
            <tr>
                <td {!! $hd !!}></td>
                <td {!! $hd !!} colspan="4"><b>Total Dolares</b></td>
                <td {!! $hd !!}></td>
                <td {!! $hd !!}>{{ number_format(($totals['cuota'] ?? 0) / $tc, 2) }}</td>
                <td {!! $hd !!} colspan="2"></td>
                <td {!! $hd !!}>{{ number_format(($totals['interes'] ?? 0) / $tc, 2) }}</td>
                <td {!! $hd !!} colspan="1"></td>
                <td {!! $hd !!}>{{ number_format(($totals['total'] ?? 0) / $tc, 2) }}</td>
                <td {!! $hd !!}>{{ number_format(($totals['pago'] ?? 0) / $tc, 2) }}</td>
                <td {!! $hd !!}>{{ number_format(($totals['saldo'] ?? 0) / $tc, 2) }}</td>
                <td {!! $hd !!} colspan="6"></td>
            </tr>
        </tbody>
    </table>
@endsection
