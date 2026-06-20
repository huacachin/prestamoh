@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;"';
    $tcLabels = [1 => 'S.', 3 => 'M.', 4 => 'D.'];
@endphp

@section('content')
    <center><font color="red"><b>PRESTAMOS</b></font></center>
    <table border="1" cellspacing="0">
        <thead>
            <tr>
                <th {!! $hd !!} width="40">N&deg;</th>
                <th {!! $hd !!} width="80">Fecha</th>
                <th {!! $hd !!} width="90">Usuario</th>
                <th {!! $hd !!} width="60">Codigo</th>
                <th {!! $hd !!} width="220">Nombre</th>
                <th {!! $hd !!} width="90">Capital</th>
                <th {!! $hd !!} width="40">%</th>
                <th {!! $hd !!} width="90">S/</th>
                <th {!! $hd !!} width="40">C.</th>
                <th {!! $hd !!} width="90">Total</th>
                <th {!! $hd !!} width="90">Pago</th>
                <th {!! $hd !!} width="90">Saldo</th>
                <th {!! $hd !!} width="120">Asesor</th>
                <th {!! $hd !!} width="40">T.C.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($credits as $i => $c)
                @php
                    $iapli  = $pagosMap[$c->id]['iapli'] ?? 0;
                    $aplido = $pagosMap[$c->id]['aplido'] ?? 0;
                    $inter  = round(($c->importe * $c->interes) / 100, 2);
                    $pago   = $iapli + $aplido;
                    $total  = $c->importe + $inter;
                    $saldo  = $c->importe - $iapli - $aplido + $inter;
                    $cli    = $c->client;
                    $nombre = $cli ? trim(($cli->apellido_pat ?? '').' '.($cli->apellido_mat ?? '').' '.($cli->nombre ?? '')) : '';
                @endphp
                <tr>
                    <td {!! $cell !!}>{{ $i + 1 }}</td>
                    <td {!! $cell !!}>{{ $c->fecha_prestamo?->format('d/m/Y') }}</td>
                    <td {!! $cell !!}>{{ $c->user?->username ?? $c->user?->name ?? $c->usuario }}</td>
                    <td {!! $cell !!}>{{ $c->id }}</td>
                    <td style="border-style:dotted solid dotted solid;">{{ $nombre }}</td>
                    <td {!! $cell !!}>{{ number_format($c->importe, 2) }}</td>
                    <td {!! $cell !!}>{{ (int) $c->interes == (float) $c->interes ? (int) $c->interes : number_format($c->interes, 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($inter, 2) }}</td>
                    <td {!! $cell !!}>{{ $c->cuotas }}</td>
                    <td {!! $cell !!}>{{ number_format($total, 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($pago, 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($saldo, 2) }}</td>
                    <td {!! $cell !!}>{{ $cli?->asesor?->username ?? $cli?->asesor?->name ?? '' }}</td>
                    <td {!! $cell !!}>{{ $tcLabels[(int) $c->tipo_planilla] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="14" {!! $cell !!}>Sin resultados</td></tr>
            @endforelse

            <tr bgcolor="#CEE7FF">
                <td colspan="5" style="text-align:center;"><b>TOTALES:</b></td>
                <td style="text-align:center;"><b>{{ number_format($sumtotal, 2) }}</b></td>
                <td></td>
                <td style="text-align:center;"><b>{{ number_format($suminter, 2) }}</b></td>
                <td></td>
                <td style="text-align:center;"><b>{{ number_format($sumtotax, 2) }}</b></td>
                <td style="text-align:center;"><b>{{ number_format($sumpagos, 2) }}</b></td>
                <td style="text-align:center;"><b>{{ number_format($sumsaldo, 2) }}</b></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
@endsection
