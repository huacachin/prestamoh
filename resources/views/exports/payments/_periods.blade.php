{{-- Vista compartida por los reportes Semanal y Mensual (12 periodos). --}}
{{-- Reproduce pagos_dia2.php / pagos_dia3.php (mismo layout, 12 columnas). --}}
@php
    $hd   = "style='text-align:center;vertical-align:middle;color:white;' bgcolor='#2874A6'";
    $cell = "style='border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;'";

    // Acumuladores de subtotales (MORA / ACTIVOS), igual que el legacy.
    $morisidad = ($sub['mora']['saldo'] ?? 0);
    $morisidadin = ($sub['activo']['saldo'] ?? 0);
    $mntSaldo = $tot['saldo'] ?? 0;
@endphp

    <center><font color="red"><b>{{ $titulo }}</b></font></center>
    <table border="1" cellspacing="0" style="table-layout:fixed;">
        <thead>
            <tr>
                <th {!! $hd !!}>N.</th>
                <th {!! $hd !!}>F.Cr.</th>
                <th {!! $hd !!}>F.Ve.</th>
                <th {!! $hd !!}>EXP.</th>
                <th {!! $hd !!}>COD.</th>
                <th {!! $hd !!}>N.C</th>
                <th {!! $hd !!}>DNI</th>
                <th {!! $hd !!}>CLIENTE</th>
                <th {!! $hd !!}>CAPITAL</th>
                <th {!! $hd !!}>%</th>
                <th {!! $hd !!}>INT.</th>
                <th {!! $hd !!}>T.P</th>
                <th {!! $hd !!}>C.</th>
                @for($i = 1; $i <= 12; $i++)
                    <th {!! $hd !!}>{{ $i }}</th>
                @endfor
                <th {!! $hd !!}>TOTAL</th>
                <th {!! $hd !!}>MORA</th>
                <th {!! $hd !!}>OTROS</th>
                <th {!! $hd !!}>SALDOS</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $r)
                <tr>
                    <td {!! $cell !!}>{{ $r['n'] }}</td>
                    <td {!! $cell !!}>{{ $r['fecha_pres'] }}</td>
                    <td {!! $cell !!}>{{ $r['fecha_venc'] }}</td>
                    <td style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;{{ empty($r['has_imagen']) ? 'background-color:yellow;' : '' }}">{{ $r['expediente'] }}</td>
                    <td {!! $cell !!}>{{ $r['codigo'] }}</td>
                    <td {!! $cell !!}>{{ $r['cuotas'] }}</td>
                    <td {!! $cell !!} class="txt">{{ $r['dni'] }}</td>
                    <td style="border-style:dotted solid dotted solid;vertical-align:middle;">{{ $r['cliente'] }}</td>
                    <td {!! $cell !!}>{{ number_format($r['capital'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['interes_pct'], 0) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['interes'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['apagar'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['cuota'], 2) }}</td>
                    @foreach($r['cuotas_cols'] as $col)
                        @php
                            $st = 'border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;';
                            if (! empty($col['bg']))    $st .= 'background-color:' . $col['bg'] . ';';
                            if (! empty($col['color'])) $st .= 'color:' . $col['color'] . ';';
                        @endphp
                        <td style="{{ $st }}">
                            @if($col['fecha'] !== '')
                                {{ \Carbon\Carbon::parse($col['fecha'])->format('d/m/Y') }}<br>{{ number_format($col['monto'] ?? 0, 2) }}
                            @endif
                        </td>
                    @endforeach
                    <td {!! $cell !!}>{{ number_format($r['pagado'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['mora'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['otros'], 2) }}</td>
                    <td {!! $cell !!}>{{ number_format($r['saldo'], 2) }}</td>
                </tr>
            @endforeach

            {{-- Fila de totales --}}
            <tr>
                <td colspan="8" align="center">Total</td>
                <td>{{ number_format($tot['capital'], 2) }}</td>
                <td></td>
                <td>{{ number_format($tot['interes'], 2) }}</td>
                <td>{{ number_format($tot['apagar'], 2) }}</td>
                <td>{{ number_format($tot['cuota'], 2) }}</td>
                <td colspan="12"></td>
                <td>{{ number_format($tot['pagado'], 2) }}</td>
                <td>{{ number_format($tot['mora'], 2) }}</td>
                <td>{{ number_format($tot['otros'], 2) }}</td>
                <td>{{ number_format($tot['saldo'], 2) }}</td>
            </tr>

            {{-- Subtotal MORA --}}
            <tr>
                <td style="color:red;text-align:center;" colspan="3"><b>{{ number_format($morosidadPct, 2) }}%</b></td>
                <td style="background-color:red;color:white;text-align:center;">MORA</td>
                <td style="background-color:#005F8C;color:white;text-align:center;">{{ $sub['mora']['n'] }}</td>
                <td style="background-color:#005F8C;color:white;text-align:center;" colspan="3">TOTAL MORA</td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['mora']['capital'], 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['interes'], 2) }}</b></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['mora']['interes'] + $sub['mora']['capital'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['cuota'], 2) }}</b></td>
                <td style="background-color:yellow;" colspan="12"></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['pagado'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['mora'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['otros'], 2) }}</b></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['mora']['saldo'], 2) }}</b></td>
            </tr>

            {{-- Subtotal ACTIVOS --}}
            <tr>
                <td style="color:green;text-align:center;" colspan="3"><b>{{ number_format($activosPct, 2) }}%</b></td>
                <td style="background-color:green;color:white;text-align:center;">ACTIVOS</td>
                <td style="background-color:#005F8C;color:white;text-align:center;">{{ $sub['activo']['n'] }}</td>
                <td style="background-color:#005F8C;color:white;text-align:center;" colspan="3">TOTAL ACTIVOS</td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['activo']['capital'], 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['activo']['interes'], 2) }}</b></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['activo']['interes'] + $sub['activo']['capital'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['activo']['cuota'], 2) }}</b></td>
                <td style="background-color:yellow;" colspan="12"></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['activo']['pagado'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['activo']['mora'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['activo']['otros'], 2) }}</b></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['activo']['saldo'], 2) }}</b></td>
            </tr>

            {{-- Subtotal TOTAL --}}
            <tr>
                <td style="color:blue;text-align:center;" colspan="3"><b>100.00%</b></td>
                <td style="background-color:#005F8C;color:white;text-align:center;">TOTAL</td>
                <td style="background-color:#005F8C;color:white;text-align:center;">{{ number_format($sub['activo']['n'] + $sub['mora']['n'], 2) }}</td>
                <td style="background-color:#005F8C;color:white;text-align:center;" colspan="3">TOTAL</td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['mora']['capital'] + $sub['activo']['capital'], 2) }}</b></td>
                <td style="background-color:yellow;"></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['interes'] + $sub['activo']['interes'], 2) }}</b></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['mora']['interes'] + $sub['mora']['capital'] + $sub['activo']['interes'] + $sub['activo']['capital'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['cuota'] + $sub['activo']['cuota'], 2) }}</b></td>
                <td style="background-color:yellow;" colspan="12"></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['pagado'] + $sub['activo']['pagado'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['mora'] + $sub['activo']['mora'], 2) }}</b></td>
                <td style="background-color:yellow;"><b>{{ number_format($sub['mora']['otros'] + $sub['activo']['otros'], 2) }}</b></td>
                <td style="background-color:yellow;color:red;"><b>{{ number_format($sub['mora']['saldo'] + $sub['activo']['saldo'], 2) }}</b></td>
            </tr>
        </tbody>
    </table>
