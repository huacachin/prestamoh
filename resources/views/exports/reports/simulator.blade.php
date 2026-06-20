@extends('exports.layout')

@php
    // Cabecera celeste de "Monto" y "Mes N" (legacy #5bc0de).
    $hd  = "style='text-align:center;background-color:#5bc0de;'";
    $cel = "style='text-align:center;'";
    $cmo = "style='text-align:center;color:red;'";

    // Un bloque de 12 meses: cabecera + Pagar/Mora + valores.
    //  $startMonth = primer mes del bloque (1,13,25,37,49).
    //  $weekly     = true → INTERES SEMANAL, false → INTERES MENSUAL.
    $block = function (int $startMonth, bool $weekly) use ($capital, $interes, $hd, $cel, $cmo) {
        $out  = "<table border='1' width='98%' style='border-collapse:collapse;margin-top:5px;'>";
        // Fila 1: cabecera
        $out .= "<thead><tr><th colspan='2' {$hd}>Monto</th>";
        for ($i = 1; $i <= 12; $i++) {
            $m = $startMonth + $i - 1;
            $out .= "<th colspan='2' {$hd}>Mes {$m}</th>";
        }
        $out .= "</tr><tr><td colspan='2'></td>";
        for ($i = 1; $i <= 12; $i++) {
            $out .= "<td {$cel}>Pagar</td><td {$cmo}>Mora</td>";
        }
        $out .= "</tr></thead><tbody><tr>";
        // Fila valores: Monto + 12 meses (Pagar / Mora)
        $out .= "<td colspan='2' {$cel}>" . number_format($capital, 2) . "</td>";
        for ($i = 1; $i <= 12; $i++) {
            $m = $startMonth + $i - 1;
            if ($weekly) {
                $cuo = $m * 4;
                $pag = $cuo > 0 ? $capital / $cuo + ($capital * $interes) / 100 / 4 : 0;
            } else {
                $pag = $m > 0 ? $capital / $m + ($capital * $interes) / 100 : 0;
            }
            $mora = $pag * $interes / 100 / 30 * 2;
            $out .= "<td {$cel}>" . number_format($pag, 2) . "</td>";
            $out .= "<td {$cmo}>" . number_format($mora, 2) . "</td>";
        }
        $out .= "</tr></tbody></table>";

        return $out;
    };
@endphp

@section('content')
    <table width="100%" cellspacing="0">
        <thead>
            <tr><th colspan="26" style="color:red;font-weight:600;">SIMULACION DE CREDITO</th></tr>
        </thead>
        <tbody>
            <tr>
                <td style="width:70px;"><b>Nombre : </b></td>
                <td style="text-align:left;"><b>{{ $nombre }}</b></td>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td style="text-align:left;width:70px;"><b>Importe : </b></td>
                <td style="text-align:left;"><b>{{ number_format($capital, 2) }}</b></td>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td style="text-align:left;width:70px;"><b>Interes : </b></td>
                <td style="text-align:left;"><b>{{ rtrim(rtrim(number_format($interes, 2), '0'), '.') }}%</b></td>
            </tr>
        </tbody>
    </table>

    <h3><center style="color:red;font-weight:600;">INTERES MENSUAL</center></h3>
    @foreach([1, 13, 25, 37, 49] as $start)
        {!! $block($start, false) !!}
    @endforeach

    <h3><center style="color:red;font-weight:600;">INTERES SEMANAL</center></h3>
    @foreach([1, 13, 25, 37, 49] as $start)
        {!! $block($start, true) !!}
    @endforeach
@endsection
