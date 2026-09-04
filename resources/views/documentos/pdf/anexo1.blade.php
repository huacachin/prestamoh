{{-- ANEXO 1 — Cronograma de pagos. Documento COMPLETO renderizado desde el
     snapshot congelado ($d) por cualquiera de los tres medios ($medio:
     'pdf' | 'previa' | 'word'). El cronograma sale ÍNTEGRO de
     $d['cronograma'] (credit_installments) — nada se recalcula.

     Diseño homologado al maestro Excel del área legal (04/09, Desktop/
     anexo1.jpeg): banner gris, tablas azules con bordes negros, cronograma
     N°/FECHAS/CUOTAS con fila Total azul, números con coma decimal
     (15.000,00) y el pie fijo de Huaycán. Cronogramas largos (>30 cuotas)
     se reparten en columnas con el mismo estilo para no desbordar la hoja.
     dompdf no soporta flex/grid: todo va con tablas. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 1 — Crédito #{{ $d['credito']['numero'] }}</title>
    @include('documentos.pdf.estilos')
    <style>
        /* Estilos SOLO del Anexo 1 (el maestro Excel); no tocan contratos. */
        .ax-banner { background: #7f7f7f; color: #fff; text-align: center; font-weight: bold;
                     font-size: 12pt; padding: 3px; border: 1px solid #000; }
        .ax-titulo { text-align: center; font-weight: bold; font-size: 10.5pt; margin: 2px 0; }
        table.ax { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.ax th, table.ax td { border: 1px solid #000; padding: 1.5px 5px; font-size: 8.5pt; line-height: 1.2; }
        /* "S/" pegado a la izquierda y monto a la derecha SIN floats (dompdf
           los rompe dentro de celdas): dos celdas con el borde interior fundido. */
        table.ax td.sim { border-right: 0; width: 5%; font-weight: bold; }
        table.ax td.montod { border-left: 0; text-align: right; font-weight: bold; }
        table.ax th.azul { background: #1f70c1; color: #fff; text-align: center; font-weight: bold; }
        table.ax td.etiqueta { font-weight: bold; }
        table.ax td.valor-cent { text-align: center; font-weight: bold; }
        table.ax td.valor-der { text-align: right; font-weight: bold; }
        .ax-correo { color: #0563c1; text-decoration: underline; }
        .ax-moneda { width: 12%; }
        table.ax-cron td.num { text-align: center; }
        table.ax-cron td, table.ax-cron th { white-space: nowrap; line-height: 1.2; }
        /* Tramos de compactado segun filas por columna (una hoja siempre) */
        table.ax-cron.apretado td, table.ax-cron.apretado th { font-size: 7.8pt; padding: 1px 4px; line-height: 1.1; }
        table.ax-cron.apretado2 td, table.ax-cron.apretado2 th { font-size: 7.2pt; padding: 0.5px 3px; line-height: 1; }
        table.ax.adicionales td, table.ax.adicionales th { font-size: 7.5pt; padding: 1px 4px; white-space: nowrap; }
        table.ax-cron tr.total td { background: #1f70c1; color: #fff; font-weight: bold; border-color: #000; }
        /* Pie del maestro. SOLO en el PDF va anclado abajo (no consume alto
           del flujo y se repite por hoja); en la previa del navegador y en
           Word el position:fixed ancla al viewport y CORTA la linea del
           celular — ahi va en el flujo normal. */
        @if(($medio ?? 'pdf') === 'pdf')
        .ax-pie { position: fixed; bottom: -0.4cm; left: 0; right: 0;
                  text-align: center; font-weight: bold; font-size: 9pt; }
        @else
        /* Previa: el pie va al fondo de la HOJA (body con alto minimo A4 y
           pie absoluto) — sin fixed, que ancla al viewport y corta lineas.
           Word ignora el absolute y lo deja al final del flujo, que esta bien. */
        body { position: relative; min-height: 24.5cm; }
        .ax-pie { position: absolute; bottom: 0; left: 0; right: 0;
                  text-align: center; font-weight: bold; font-size: 9pt; }
        @endif
        table.ax-split { width: 100%; border-collapse: collapse; }
        table.ax-split > tbody > tr > td.col { vertical-align: top; padding: 0 4px; border: 0; }
    </style>
</head>
<body>
    <div class="pie-pagina">Anexo 1 — Crédito #{{ $d['credito']['numero'] }} — Página <span class="num"></span></div>

    <div class="ax-banner">{{ $d['marca'] }}</div>
    <div class="ax-titulo">ANEXO 1</div>

    {{-- ── Cliente | Vehículo (como el maestro) ─────────────────────────── --}}
    @php
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
        // Snapshots nuevos traen 'vehiculos' (varios); los emitidos antes del
        // 28/08 traen 'vehiculo' (uno) y deben seguir imprimiéndose igual.
        $vehiculos = $d['vehiculos'] ?? (($d['vehiculo'] ?? null) ? [$d['vehiculo']] : []);
        $v1 = $vehiculos[0] ?? null;
        $cred = $d['credito'];
        // Documentos emitidos antes del rediseño: campos nuevos con fallback.
        $plazo = $cred['plazo'] ?? ($cred['cuotas'].' cuotas');
        $tim = $cred['tim'] ?? '5%';
        $fechaInicio = $cred['fecha_inicio'] ?? $d['fecha'];
    @endphp
    <table class="ax">
        <tr>
            <th class="azul" colspan="2" style="width: 50%;">DATOS DEL CLIENTE</th>
            <th class="azul" colspan="3" style="width: 50%;">DATOS DEL VEHÍCULO</th>
        </tr>
        <tr>
            <td class="etiqueta" style="width: 12%;">Cliente</td>
            <td style="width: 38%;">{{ $d['cliente']['nombre'] }}</td>
            <td class="etiqueta" style="width: 20%; white-space: nowrap;">PLACA DE RODAJE</td>
            <td class="valor-cent" colspan="2" style="width: 30%;">{{ $v1['placa'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">{{ $d['cliente']['documento_tipo'] ?: 'DNI' }}</td>
            <td>{{ $d['cliente']['documento'] }}</td>
            <td class="etiqueta">Marca</td>
            <td class="valor-cent" colspan="2">{{ ($v1['marca'] ?? '') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Dirección</td>
            <td>{{ $d['cliente']['domicilio'] }}</td>
            <td class="etiqueta">Modelo</td>
            <td class="valor-cent" colspan="2">{{ ($v1['modelo'] ?? '') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Celular</td>
            <td>{{ $d['cliente']['celular'] }}</td>
            <td class="etiqueta">N° Serie</td>
            <td class="valor-cent" colspan="2">{{ ($v1['nro_serie'] ?? '') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Correo</td>
            <td><span class="ax-correo">{{ $d['cliente']['correo'] }}</span></td>
            <td class="etiqueta">Valor Vehículo</td>
            @if (($v1['valor'] ?? null) !== null)
                <td class="sim">S/</td>
                <td class="montod">{{ $fmt($v1['valor']) }}</td>
            @else
                <td class="valor-cent" colspan="2">—</td>
            @endif
        </tr>
    </table>

    {{-- ── Vehículos adicionales (2° en adelante), mismo estilo ─────────── --}}
    @if (count($vehiculos) > 1)
        <table class="ax adicionales">
            <tr><th class="azul" colspan="5">DATOS DE LOS VEHÍCULOS ADICIONALES</th></tr>
            <tr>
                <th class="azul" style="width: 16%;">PLACA</th>
                <th class="azul" style="width: 22%;">MARCA</th>
                <th class="azul" style="width: 22%;">MODELO</th>
                <th class="azul" style="width: 24%;">N° SERIE</th>
                <th class="azul" style="width: 16%;">VALOR</th>
            </tr>
            @foreach (array_slice($vehiculos, 1) as $veh)
                <tr>
                    <td class="valor-cent">{{ $veh['placa'] ?: '—' }}</td>
                    <td class="valor-cent">{{ $veh['marca'] ?: '—' }}</td>
                    <td class="valor-cent">{{ $veh['modelo'] ?: '—' }}</td>
                    <td class="valor-cent">{{ $veh['nro_serie'] ?: '—' }}</td>
                    <td class="valor-der">{{ $veh['valor'] !== null ? 'S/ '.$fmt($veh['valor']) : '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    {{-- ── Datos del crédito ────────────────────────────────────────────── --}}
    <table class="ax">
        <tr><th class="azul" colspan="5">DATOS DEL CRÉDITO</th></tr>
        <tr>
            <td class="etiqueta" style="width: 18%; text-align: center;">Nro.</td>
            <td class="valor-cent" colspan="2" style="width: 32%;">{{ $cred['numero'] }}</td>
            <td class="etiqueta" style="width: 24%;">Moneda</td>
            <td class="valor-der" style="width: 26%;">{{ ucfirst(mb_strtolower($cred['moneda'])) }}</td>
        </tr>
        <tr>
            <td class="etiqueta" style="text-align: center;">Monto Crédito</td>
            <td class="sim">S/</td>
            <td class="montod">{{ $fmt($cred['monto']) }}</td>
            <td class="etiqueta">Plazo</td>
            <td class="valor-der">{{ $plazo }}</td>
        </tr>
        <tr>
            <td class="etiqueta" style="text-align: center;">Fecha Inicio</td>
            <td class="valor-cent" colspan="2">{{ $fechaInicio }}</td>
            <td class="etiqueta">TIM (Tasa interés moratorio)</td>
            <td class="valor-der">{{ $tim }}</td>
        </tr>
    </table>

    {{-- ── Cronograma ───────────────────────────────────────────────────── --}}
    @php
        $filas = $d['cronograma']['filas'];
        $n = count($filas);
        // Hasta 36 cuotas: una sola columna a lo ancho, como el maestro;
        // hasta 72 en dos (pedido 04/09). El anexo debe caber en UNA hoja
        // (regla del 28/08, blindada por Anexo1UnaHojaTest): la letra y los
        // paddings del cronograma se reducen por tramos según cuántas filas
        // cargue cada columna.
        $cols = $n <= 36 ? 1 : ($n <= 72 ? 2 : ($n <= 108 ? 3 : 4));
        $porColumna = max(1, (int) ceil($n / $cols));
        $grupos = array_chunk($filas, $porColumna);
        $claseCron = $porColumna >= 30 ? 'apretado2' : ($porColumna >= 20 ? 'apretado' : '');
    @endphp

    <div class="ax-titulo">CRONOGRAMA DE PAGO</div>
    @if (count($grupos) === 1)
        {{-- Columna única SIN envoltorio: dompdf no parte tablas anidadas y
             empujaba el cronograma entero a la página 2. --}}
        @php $grupo = $grupos[0]; $esUltimo = true; @endphp
        @include('documentos.pdf.anexo1-cron', ['grupo' => $grupo, 'esUltimo' => true, 'claseCron' => $claseCron, 'fmt' => $fmt, 'total' => $d['cronograma']['total']])
    @else
    <table class="ax-split">
        <tr>
            @foreach ($grupos as $grupo)
                <td class="col" style="width: {{ round(100 / count($grupos), 4) }}%;">
                    @include('documentos.pdf.anexo1-cron', ['grupo' => $grupo, 'esUltimo' => $loop->last, 'claseCron' => $claseCron, 'fmt' => $fmt, 'total' => $d['cronograma']['total']])
                </td>
            @endforeach
        </tr>
    </table>
    @endif

    {{-- Pie fijo del maestro del área legal --}}
    <div class="ax-pie">
        DPTO. SEC. B UCV 72 LOTE 51 ZONA E AAHH HUAYCAN, DISTRITO DE ATE<br>
        CELULAR: 982333689/981352577
    </div>
</body>
</html>
