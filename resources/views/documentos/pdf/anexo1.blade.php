{{-- ANEXO 1 — Cronograma de pagos. Documento COMPLETO renderizado desde el
     snapshot congelado ($d) por cualquiera de los tres medios ($medio:
     'pdf' | 'previa' | 'word'). El cronograma sale ÍNTEGRO de
     $d['cronograma'] (credit_installments) — nada se recalcula.

     Maquetado a UNA HOJA (28/08): cliente y crédito van uno al costado del
     otro, los vehículos de a dos por fila, y el cronograma se reparte en
     columnas según cuántas cuotas tenga. dompdf no soporta flex/grid, así
     que las columnas se arman con tablas contenedoras sin bordes. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 1 — Crédito #{{ $d['credito']['numero'] }}</title>
    @include('documentos.pdf.estilos')
</head>
<body>
    <div class="pie-pagina">Anexo 1 — Crédito #{{ $d['credito']['numero'] }} — Página <span class="num"></span></div>

    <div class="membrete">{{ $d['marca'] }}</div>
    <div class="anexo-titulo">ANEXO 1</div>
    <div class="anexo-subtitulo">CRONOGRAMA DE PAGOS</div>

    {{-- ── Cliente | Crédito ───────────────────────────────────────────── --}}
    <table class="grid2">
        <tr>
            <td class="celda izq">
                <table class="datos compacta">
                    <tr><th colspan="2">DATOS DEL CLIENTE</th></tr>
                    <tr>
                        <th style="width: 38%;">CLIENTE</th>
                        <td>{{ $d['cliente']['nombre'] }}</td>
                    </tr>
                    @if (filled($d['cliente']['documento']))
                        <tr>
                            <th>{{ $d['cliente']['documento_tipo'] }}</th>
                            <td>{{ $d['cliente']['documento'] }}</td>
                        </tr>
                    @endif
                    @if (filled($d['cliente']['domicilio']))
                        <tr>
                            <th>DOMICILIO</th>
                            <td>{{ $d['cliente']['domicilio'] }}</td>
                        </tr>
                    @endif
                    @if (filled($d['cliente']['celular']))
                        <tr>
                            <th>CELULAR</th>
                            <td>{{ $d['cliente']['celular'] }}</td>
                        </tr>
                    @endif
                    @if (filled($d['cliente']['correo']))
                        <tr>
                            <th>CORREO</th>
                            <td>{{ $d['cliente']['correo'] }}</td>
                        </tr>
                    @endif
                </table>
            </td>
            <td class="celda der">
                <table class="datos compacta">
                    <tr><th colspan="2">DATOS DEL CRÉDITO</th></tr>
                    <tr>
                        <th style="width: 45%;">N° DE CRÉDITO</th>
                        <td>{{ $d['credito']['numero'] }}</td>
                    </tr>
                    <tr>
                        <th>MONTO ({{ $d['credito']['moneda'] }})</th>
                        <td>S/ {{ number_format((float) $d['credito']['monto'], 2) }}</td>
                    </tr>
                    <tr>
                        <th>FRECUENCIA</th>
                        <td>{{ $d['credito']['frecuencia'] }}</td>
                    </tr>
                    <tr>
                        <th>N° DE CUOTAS</th>
                        <td>{{ $d['credito']['cuotas'] }}</td>
                    </tr>
                    <tr>
                        <th>CUOTA</th>
                        <td>S/ {{ number_format((float) $d['credito']['cuota'], 2) }}</td>
                    </tr>
                    <tr>
                        <th>FECHA</th>
                        <td>{{ $d['fecha'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Vehículos: de a dos por fila ─────────────────────────────────── --}}
    @php
        // Snapshots nuevos traen 'vehiculos' (varios); los emitidos antes del
        // 28/08 traen 'vehiculo' (uno) y deben seguir imprimiéndose igual.
        $vehiculos = $d['vehiculos'] ?? (($d['vehiculo'] ?? null) ? [$d['vehiculo']] : []);
        $varios = count($vehiculos) > 1;
    @endphp
    {{-- 1-2 vehículos: bloques al costado (formato clásico del anexo).
         3 o más: una fila por vehículo, que ocupa mucho menos alto. --}}
    @if (count($vehiculos) >= 3)
        <table class="datos compacta">
            <tr><th colspan="4">DATOS DE LOS VEHÍCULOS</th></tr>
            <tr>
                <th style="width: 18%;">PLACA</th>
                <th style="width: 32%;">MARCA / MODELO</th>
                <th style="width: 30%;">N° SERIE</th>
                <th style="width: 20%;">VALOR</th>
            </tr>
            @foreach ($vehiculos as $veh)
                <tr>
                    <td>{{ $veh['placa'] ?: '—' }}</td>
                    <td>{{ trim(($veh['marca'] ?: '').' '.($veh['modelo'] ?: '')) ?: '—' }}</td>
                    <td>{{ $veh['nro_serie'] ?: '—' }}</td>
                    <td>{{ $veh['valor'] !== null ? 'S/ '.number_format((float) $veh['valor'], 2) : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @elseif (count($vehiculos) > 0)
        <table class="grid2">
            <tr>
                @foreach ($vehiculos as $i => $veh)
                    <td class="celda {{ $loop->first ? 'izq' : 'der' }}">
                        <table class="datos compacta">
                            <tr>
                                <th colspan="2">DATOS DEL VEHÍCULO{{ $varios ? ' '.($i + 1) : '' }}</th>
                            </tr>
                            <tr>
                                <th style="width: 38%;">PLACA</th>
                                <td>{{ $veh['placa'] ?: '—' }}</td>
                            </tr>
                            <tr>
                                <th>MARCA / MODELO</th>
                                <td>{{ trim(($veh['marca'] ?: '').' '.($veh['modelo'] ?: '')) ?: '—' }}</td>
                            </tr>
                            <tr>
                                <th>N° SERIE</th>
                                <td>{{ $veh['nro_serie'] ?: '—' }}</td>
                            </tr>
                            @if ($veh['valor'] !== null)
                                <tr>
                                    <th>VALOR</th>
                                    <td>S/ {{ number_format((float) $veh['valor'], 2) }}</td>
                                </tr>
                            @endif
                        </table>
                    </td>
                @endforeach
                @if (count($vehiculos) === 1)
                    <td class="celda der"></td>
                @endif
            </tr>
        </table>
    @endif

    {{-- ── Cronograma en columnas ───────────────────────────────────────── --}}
    @php
        $filas = $d['cronograma']['filas'];
        $n = count($filas);
        // Columnas según el largo del cronograma, para que entre en la hoja.
        $cols = $n <= 12 ? 1 : ($n <= 26 ? 2 : ($n <= 60 ? 3 : 4));
        $porColumna = (int) ceil($n / $cols);
        $grupos = array_chunk($filas, max(1, $porColumna));
        // Con una sola columna la tabla no debe estirarse a todo el ancho
        $anchoCol = count($grupos) === 1 ? 45 : round(100 / count($grupos), 4);
    @endphp

    <div class="cron-titulo">Cronograma de pagos</div>
    <table class="cron-cols">
        <tr>
            @foreach ($grupos as $grupo)
                <td class="col" style="width: {{ $anchoCol }}%;">
                    <table class="cron-mini">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>FECHA</th>
                                <th>CUOTA S/</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grupo as $fila)
                                <tr>
                                    <td class="num">{{ $fila['n'] }}</td>
                                    <td class="fecha">{{ $fila['fecha'] }}</td>
                                    <td class="monto">{{ number_format((float) $fila['monto'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            @endforeach
            {{-- Con una sola columna hace falta una celda de relleno: si no,
                 la única celda se estira a todo el ancho de la tabla. --}}
            @if (count($grupos) === 1)
                <td></td>
            @endif
        </tr>
    </table>

    <div class="cron-total">
        TOTAL: S/ {{ number_format((float) $d['cronograma']['total'], 2) }}
    </div>
</body>
</html>
