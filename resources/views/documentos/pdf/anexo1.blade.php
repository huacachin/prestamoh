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
        table.ax td.sim { border-right: none; width: 5%; font-weight: bold; }
        table.ax td.montod { border-left: none; text-align: right; font-weight: bold; }
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
        /* 17/09 (Antony, Desktop/anexo1raya.jpeg): el maestro lleva una RAYA
           encima del pie, DEL ANCHO DE LA TABLA (no de la hoja), con aire
           entre la raya y el texto. Regla común a los tres medios; la
           posición la pone cada rama. */
        .ax-pie { border-top: 0.8pt solid #000; padding-top: 6px;
                  text-align: center; font-weight: bold; font-size: 9pt; }
        @if(($medio ?? 'pdf') === 'pdf')
        /* PDF: anclado abajo. left/right 0 en dompdf es el área de contenido
           (dentro de los márgenes), así que la raya mide lo que las tablas. */
        .ax-pie { position: fixed; bottom: -0.4cm; left: 0; right: 0; }
        @elseif(($medio ?? 'pdf') === 'word')
        /* Word (16/09): NO ignora el position:absolute — lo convierte en un
           marco flotante que se posa encima del cronograma. Aquí va en el
           flujo, al final, con aire arriba; sin min-height al body (en Word
           estira el documento a dos hojas). La raya mide el ancho de texto,
           que es el de las tablas. */
        .ax-pie { margin-top: 14pt; }
        @else
        /* Previa: el pie va al fondo de la HOJA, absoluto dentro de .ax-hoja
           (el contenido, SIN el padding que hace de margen). Antes se
           posicionaba contra el body y left/right 0 abarcaban también el
           padding: la raya salía más ancha que las tablas — eso vio Antony. */
        .ax-hoja { position: relative; min-height: 24.5cm; }
        .ax-pie { position: absolute; bottom: 0; left: 0; right: 0; }
        @endif
        table.ax-split { width: 100%; border-collapse: collapse; }
        table.ax-split td.col { vertical-align: top; padding: 0 4px; border: 0; }
        @if(($medio ?? 'pdf') === 'word')
        /* Con el <colgroup> ya declarado, table-layout: fixed hace que
           Word respete el reparto en vez de autoajustar al contenido.
           NO se pone en el PDF: ahí la primera fila con colspan dejaría
           los anchos indefinidos también para dompdf. */
        table.ax { table-layout: fixed; }
        table.ax td.sim { padding-left: 2px; padding-right: 0; }
        /* La fila Total de la tabla plana mezcla celdas del total con celdas
           separadoras, así que el azul no puede ir en la fila (tr.total) como
           en el PDF: va por celda. */
        table.ax-cron td.total-celda { background: #1f70c1; color: #fff; font-weight: bold; border-color: #000; }
        @endif
    </style>
</head>
<body>
    {{-- SIN pie de página (15/09), como el contrato: el Anexo 1 cabe en una
         hoja y el maestro del área no lo lleva. El Anexo 2 sí lo conserva. --}}
    {{-- .ax-hoja: en la previa, el contenedor contra el que se ancla el pie
         (solo el contenido, sin el padding de los márgenes) para que la raya
         mida lo que las tablas. En PDF y Word no tiene estilos. --}}
    <div class="ax-hoja">
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
    @if ($medio === 'word')
        {{-- Word deduce la rejilla desde la primera fila, que aquí lleva
             colspan: sin <colgroup> reparte las columnas a su criterio. --}}
        <colgroup>
            <col style="width: 12%">
            <col style="width: 38%">
            <col style="width: 20%">
            <col style="width: 5%">
            <col style="width: 25%">
        </colgroup>
    @endif
        <tr>
            <th class="azul" colspan="2" style="width: 50%;">DATOS DEL CLIENTE</th>
            <th class="azul" colspan="3" style="width: 50%;">DATOS DEL VEHÍCULO</th>
        </tr>
        <tr>
            <td class="etiqueta" style="width: 12%;">Cliente</td>
            <td style="width: 38%;">{{ $d['cliente']['nombre'] }}</td>
            {{-- 17/09 (Antony): "Placa de Rodaje", como las demás etiquetas, no en mayúsculas. --}}
            <td class="etiqueta" style="width: 20%; white-space: nowrap;">Placa de Rodaje</td>
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
        @if ($medio === 'word')
            {{-- Word deduce la rejilla desde la primera fila, que aquí lleva
                 colspan: sin <colgroup> reparte las columnas a su criterio. --}}
            <colgroup>
                <col style="width: 16%">
                <col style="width: 22%">
                <col style="width: 22%">
                <col style="width: 24%">
                <col style="width: 16%">
            </colgroup>
        @endif
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
    @if ($medio === 'word')
        {{-- Word deduce la rejilla desde la primera fila, que aquí lleva
             colspan: sin <colgroup> reparte las columnas a su criterio. --}}
        <colgroup>
            <col style="width: 18%">
            <col style="width: 5%">
            <col style="width: 27%">
            <col style="width: 24%">
            <col style="width: 26%">
        </colgroup>
    @endif
        <tr><th class="azul" colspan="5">DATOS DEL CRÉDITO</th></tr>
        <tr>
            <td class="etiqueta" style="width: 18%; text-align: center;">Nro.</td>
            {{-- Numeración del ÁREA ("2026-230"), no el id interno: es la que
                 citan en sus registros. Los documentos emitidos antes del
                 15/09 no la traen y siguen mostrando su id. --}}
            <td class="valor-cent" colspan="2" style="width: 32%;">{{ $cred['correlativo'] ?? $cred['numero'] }}</td>
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
        // Una sola columna mientras quepa; luego se reparte (pedido 04/09). El
        // anexo debe caber en UNA hoja (regla del 28/08, blindada por
        // Anexo1UnaHojaTest): la letra y los paddings del cronograma se
        // reducen por tramos según cuántas filas cargue cada columna.
        //
        // Se reparte por CAPACIDAD, no por tramos fijos (16/09). Lo que cabe
        // depende de cuánto ocupe la cabecera, y eso VARÍA con los datos: una
        // dirección de 6 líneas o un segundo vehículo empujan el cronograma
        // hacia abajo. Antes el corte era fijo (36 en una columna, 72 en dos),
        // medido con una dirección corta y un solo vehículo — por eso el 16/09
        // un crédito de 28 cuotas se fue a una segunda hoja y su última fila
        // pisó el pie.
        //
        // Se parte de 24 filas y se descuenta lo que la cabecera se lleva:
        // cada vehículo extra trae su propia tabla, y una dirección larga se
        // reparte en varias líneas dentro de su celda.
        // OJO: el snapshot guarda la dirección como 'domicilio' (es lo que
        // pinta la tabla de arriba). Hasta el 17/09 esto leía 'direccion', que
        // no existe: el descuento por dirección larga nunca actuó y el barrido
        // pasaba solo porque el pie iba anclado y no ocupaba alto.
        $lineasDireccion = (int) ceil(mb_strlen((string) ($d['cliente']['domicilio'] ?? $d['cliente']['direccion'] ?? '')) / 45);
        // El pie va anclado abajo (no ocupa alto del flujo), así que la base
        // sigue en 24 filas; con la clave corregida, una dirección de 6
        // renglones la baja a 20 y 24 cuotas pasan a dos columnas.
        $porColumnaMax = 24
            - 4 * max(0, count($vehiculos) - 1)
            - 2 * max(0, $lineasDireccion - 2);
        $porColumnaMax = max(10, $porColumnaMax);
        // Máximo 4 columnas: más no caben a lo ancho de la hoja.
        $cols = min(4, max(1, (int) ceil($n / $porColumnaMax)));
        $porColumna = max(1, (int) ceil($n / $cols));
        $grupos = array_chunk($filas, $porColumna);
        // El apretado mira DOS cosas: cuántas filas carga la columna y cuánto
        // se llevó la cabecera. Con 3 vehículos y dirección larga queda poco
        // alto aunque la columna lleve pocas filas, y ahí hay que encoger
        // igual (16/09).
        $claseCron = ($porColumna >= 30 || $porColumnaMax <= 12)
            ? 'apretado2'
            : (($porColumna >= 20 || $porColumnaMax <= 18) ? 'apretado' : '');

        // ── Anchos en CENTÍMETROS, solo para Word (16/09) ─────────────────
        // El importador de Word mide el `width: 100%` de una tabla ANIDADA
        // contra el ancho de texto de la PÁGINA, no contra la celda que la
        // contiene. Con el cronograma en columnas eso ensanchaba cada bloque
        // al ancho de la hoja entera: el Anexo 1 se salía por la derecha o
        // Word encogía las columnas a su criterio y partía las fechas.
        // Dándole medidas absolutas no queda nada que interpretar.
        // 16.2 cm = A4 (21) menos los márgenes del anexo (2.8 + 2).
        $anchoUtilCm = 16.2;
        $anchoColCm = round($anchoUtilCm / max(1, count($grupos)), 2);
        // Menos el padding horizontal de la celda contenedora (0 4px ≈ 0.22cm).
        $anchoCronCm = count($grupos) === 1 ? $anchoUtilCm : round($anchoColCm - 0.22, 2);
    @endphp

    <div class="ax-titulo">CRONOGRAMA DE PAGO</div>
    @if (count($grupos) === 1)
        {{-- Columna única SIN envoltorio: dompdf no parte tablas anidadas y
             empujaba el cronograma entero a la página 2. --}}
        @php $grupo = $grupos[0]; $esUltimo = true; @endphp
        @include('documentos.pdf.anexo1-cron', ['grupo' => $grupo, 'esUltimo' => true, 'claseCron' => $claseCron, 'fmt' => $fmt, 'total' => $d['cronograma']['total'], 'anchoCronCm' => $anchoCronCm])
    @elseif ($medio === 'word')
        {{-- Word (16/09): UNA tabla plana en vez de tablas anidadas. Word no
             aplica las reglas de clase a una tabla dentro de otra tabla, y el
             cronograma salía sin un solo borde y sin la cabecera azul mientras
             las tablas de datos de arriba —de primer nivel— se veían bien. --}}
        @include('documentos.pdf.anexo1-cron-plano', ['grupos' => $grupos, 'claseCron' => $claseCron, 'fmt' => $fmt, 'anchoUtilCm' => $anchoUtilCm])
    @else
    {{-- width="100%" como ATRIBUTO además del CSS: Word necesita una
         referencia de ancho para el envoltorio; dompdf lo ignora porque la
         regla de autor (table.ax-split) tiene más peso. --}}
    <table class="ax-split" width="100%">
        <tr>
            @foreach ($grupos as $grupo)
                <td class="col" valign="top" style="width: {{ $medio === 'word' ? $anchoColCm.'cm' : round(100 / count($grupos), 4).'%' }};">
                    @include('documentos.pdf.anexo1-cron', ['grupo' => $grupo, 'esUltimo' => $loop->last, 'claseCron' => $claseCron, 'fmt' => $fmt, 'total' => $d['cronograma']['total'], 'anchoCronCm' => $anchoCronCm])
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
    </div>{{-- /.ax-hoja --}}
</body>
</html>
