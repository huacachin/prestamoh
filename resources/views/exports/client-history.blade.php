@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $tot = 'style="text-align:center;"';

    $c = $client;
    $nombre = $c ? trim(($c->apellido_pat ?? '').' '.($c->apellido_mat ?? '').' '.($c->nombre ?? '')) : ('#'.$clientId);
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
    $movil = trim(($c?->celular1 ?? '').' '.($c?->celular2 ?? ''));
@endphp

@section('content')
    <table border="0" cellspacing="0" cellpadding="0">
        <tr><td colspan="8" style="text-align:center;"><b>HISTORIAL DE CREDITICIO</b></td></tr>
        <tr>
            <td><b>Nombres :</b></td>
            <td colspan="4">{{ $nombre }}</td>
            <td><b>Fecha Nacimiento</b></td>
            <td colspan="2">{{ $fmt($c?->fecha_nacimiento) }}</td>
        </tr>
        <tr>
            <td><b>N&deg; Expediente</b></td>
            <td colspan="4">{{ $c?->expediente }}</td>
            <td><b>Nacionalidad</b></td>
            <td colspan="2">Peruano</td>
        </tr>
        <tr>
            <td><b>Direccion</b></td>
            <td colspan="4">{{ $c?->direccion }}</td>
            <td><b>Movil</b></td>
            <td colspan="2">{{ $movil }}</td>
        </tr>
        <tr>
            <td><b>Registrado el</b></td>
            <td colspan="4">{{ $fmt($c?->fecha_registro) }}</td>
            <td><b>DNI</b></td>
            <td colspan="2" class="txt">{{ $c?->documento }}</td>
        </tr>
    </table>

    <center><b>HISTORIAL DE PAGOS</b></center>

    <table border="1" cellspacing="0" width="700">
        <thead>
            <tr>
                <th {!! $hd !!}>Nº</th>
                <th {!! $hd !!}>Usuario</th>
                <th {!! $hd !!}>Codigo</th>
                <th {!! $hd !!}>Cod. Ant.</th>
                <th {!! $hd !!}>F. Credito</th>
                <th {!! $hd !!}>F. Vcto</th>
                <th {!! $hd !!}>F. Pago</th>
                <th {!! $hd !!}>F. Cancelado</th>
                <th {!! $hd !!}>Capital</th>
                <th {!! $hd !!}>%</th>
                <th {!! $hd !!}>Interes</th>
                <th {!! $hd !!}>Cuotas</th>
                <th {!! $hd !!}>Total</th>
                <th {!! $hd !!}>Capital R.</th>
                <th {!! $hd !!}>Interes G.</th>
                <th {!! $hd !!}>Mora</th>
                <th {!! $hd !!}>Total</th>
                <th {!! $hd !!}>Saldo Capital</th>
                <th {!! $hd !!}><font color="red">S/</font></th>
                <th {!! $hd !!}><font color="red">MxD</font></th>
                <th {!! $hd !!}><font color="red">Dias</font></th>
                <th {!! $hd !!}>Gat.</th>
                <th {!! $hd !!}>Asesor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td {!! $cell !!}>{{ $r['n'] }}</td>
                    <td {!! $cell !!}>{{ $r['usuario'] }}</td>
                    <td {!! $cell !!}>{{ $r['codigo'] }}</td>
                    <td {!! $cell !!}>{{ $r['cod_ant'] }}</td>
                    <td {!! $cell !!}>{{ $r['f_credito'] }}</td>
                    <td {!! $cell !!}>{{ $r['f_vcto'] }}</td>
                    <td {!! $cell !!}>{{ $r['f_pago'] }}</td>
                    <td {!! $cell !!}>{{ $r['f_cancelado'] }}</td>
                    <td {!! $cell !!}>{{ number_format($r['capital'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['pct'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['interes'], 2) }}</td>
                    <td {!! $cell !!}>{{ $r['cuotas'] }}</td>
                    <td {!! $cell !!}>{{ number_format($r['total'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['capital_r'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['interes_g'], 2) }}</td>
                    <td {!! $cell !!}>{{ $r['mora'] }}</td>
                    <td {!! $cell !!}>{{ number_format($r['total_gan'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['saldo'], 2) }}</td>
                    <td {!! $cell !!}>{{ $r['s'] }}</td>
                    <td {!! $cell !!}>{{ $r['mxd'] }}</td>
                    <td {!! $cell !!}>{{ $r['dias'] }}</td>
                    <td {!! $cell !!}>{{ $r['gat'] }}</td>
                    <td {!! $cell !!}>{{ $r['asesor'] }}</td>
                </tr>
            @empty
                <tr><td colspan="23" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse
            <tr bgcolor="#CEE7FF">
                <td {!! $tot !!}><b>Total</b></td>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td {!! $tot !!}>{{ number_format($totom, 2) }}</td>
                <td></td>
                <td {!! $tot !!}>{{ number_format($tdm, 2) }}</td>
                <td></td>
                <td {!! $tot !!}>{{ number_format($total1, 2) }}</td>
                <td {!! $tot !!}>{{ number_format($mmay, 2) }}</td>
                <td {!! $tot !!}>{{ number_format($imintc, 2) }}</td>
                <td {!! $tot !!}>{{ number_format($moramora, 2) }}</td>
                <td {!! $tot !!}>{{ number_format($total2, 2) }}</td>
                <td {!! $tot !!}>{{ number_format($total3, 2) }}</td>
                <td {!! $tot !!}>{{ number_format($montomorxdia, 2) }}</td>
                <td></td><td></td><td></td>
            </tr>
        </tbody>
    </table>
@endsection
