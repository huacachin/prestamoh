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
@php
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
    // Snapshots nuevos traen 'vehiculos' (varios); los emitidos antes del
    // 28/08 traen 'vehiculo' (uno) y deben seguir imprimiéndose igual.
    $vehiculos = $d['vehiculos'] ?? (($d['vehiculo'] ?? null) ? [$d['vehiculo']] : []);
    $v1 = $vehiculos[0] ?? null;
    $cred = $d['credito'];

    // 18/09: uno o VARIOS deudores (titular + copropietarios de los
    // vehículos anexados), una columna por cada uno, como el maestro
    // Desktop/clientes1.jpeg. Los snapshots anteriores solo traen
    // 'cliente' y siguen imprimiéndose igual, con una sola columna.
    $todosClientes = $d['clientes'] ?? [$d['cliente']];
    // El maestro pone DOS columnas. Del tercer deudor en adelante —que
    // hoy no existe en la cartera, pero el modelo lo permite— se listan
    // en su propia tabla debajo, igual que los vehículos adicionales:
    // apilar más columnas ensanchaba la tabla fuera de la hoja (el ancho
    // de cada columna lo manda la palabra más larga, y un correo es una
    // sola palabra), y forzar la rejilla la estiraba a dos páginas.
    $clientes = array_slice($todosClientes, 0, 2);
    $clientesExtra = array_slice($todosClientes, 2);
    $nDeudores = max(1, count($clientes));
    $varios = $nDeudores > 1;
    // Anchos del bloque de clientes: la etiqueta y una columna por deudor.
    // Con dos deudores el bloque crece y el del vehículo se aprieta.
    $wEtiqueta = $varios ? 9 : 12;
    $wVehLabel = $varios ? 14 : 20;
    $wSim = $varios ? 4 : 5;
    $wDeudor = $varios ? 22 : 38;
    $wMonto = 100 - $wEtiqueta - ($wDeudor * $nDeudores) - $wVehLabel - $wSim;
    // Ancho útil de la hoja: A4 (21 cm) menos los márgenes del anexo
    // (2,8 + 2). Lo usan el cálculo de capacidad de abajo y las medidas
    // en centímetros que necesita Word.
    $anchoUtilCm = 16.2;
    // Documentos emitidos antes del rediseño: campos nuevos con fallback.
    $plazo = $cred['plazo'] ?? ($cred['cuotas'].' cuotas');
    $tim = $cred['tim'] ?? '5%';
    $fechaInicio = $cred['fecha_inicio'] ?? $d['fecha'];

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
    // Con codeudor la columna de dirección se parte en dos y cada una es
    // más angosta, así que el mismo texto ocupa casi el doble de
    // renglones: se mide con los caracteres que entran POR COLUMNA y se
    // toma el deudor con la dirección más larga, que es quien manda el
    // alto de la fila.
    // Tipo de documento para rotular la fila: solo si TODOS lo comparten.
    $tiposDoc = array_unique(array_map(fn (array $c) => ($c['documento_tipo'] ?? '') ?: 'DNI', $clientes));
    $tipoDocComun = count($tiposDoc) === 1 ? reset($tiposDoc) : null;

    // ── Cuántas filas de cronograma caben ────────────────────────────
    // Cuánto se lleva la cabecera depende de los DATOS: una dirección larga
    // o un segundo deudor la parten en más renglones y empujan el cronograma
    // hacia abajo. Se mide, no se adivina.
    //
    // La constante sale del render del 18/09: la tipografía del anexo gasta
    // ~0,26 cm por carácter a 8.5pt —una dirección de 139 caracteres se parte
    // en 7 renglones con un deudor (columna de 5,9 cm) y en 13 con dos
    // (3,3 cm)—. La fórmula anterior suponía la mitad de ancho por carácter
    // y por eso creía que la cabecera medía la mitad de lo que mide.
    $capacidad = function (float $escala) use ($clientes, $clientesExtra, $vehiculos, $wDeudor, $anchoUtilCm) {
        $cmPorChar = 0.26 * $escala;
        $porLinea = fn (float $pct, float $padding) => max(6, (int) floor(($pct / 100 * $anchoUtilCm - $padding) / $cmPorChar));
        $ancho = $porLinea($wDeudor, 0.26);
        // Cada fila de la cabecera crece con el dato MÁS largo de los
        // deudores —es quien manda el alto de la fila—.
        $lineas = 0;
        foreach (['nombre', 'documento', 'domicilio', 'celular', 'correo'] as $campo) {
            $lineas += max(array_map(
                fn (array $c) => max(1, (int) ceil(
                    mb_strlen((string) ($c[$campo] ?? ($campo === 'domicilio' ? ($c['direccion'] ?? '') : ''))) / $ancho
                )),
                $clientes
            ));
        }
        // Los deudores del tercero en adelante traen su propia tabla: dos
        // filas de cabecera más lo que mida cada uno, con los anchos de ESA
        // tabla y su letra más chica.
        $extra = count($clientesExtra) > 0 ? 2 + array_sum(array_map(
            fn (array $c) => max(1, ...array_map(
                fn (array $par) => (int) ceil(mb_strlen((string) ($c[$par[0]] ?? '')) / $porLinea($par[1], 0.22)),
                [['nombre', 22], ['domicilio', 40], ['correo', 18]]
            )),
            $clientesExtra
        )) : 0;
        // Base 24: las 5 filas de la cabecera en un solo renglón. El pie va
        // anclado abajo y no consume alto del flujo. Encoger la letra acorta
        // cada renglón, así que un renglón de más cuesta menos de una fila.
        return 24
            - 4 * max(0, count($vehiculos) - 1)
            - (int) ceil($extra * $escala)
            - (int) ceil(max(0, $lineas - 5) * $escala);
    };
    // Primero al tamaño del maestro. Si al cronograma no le queda sitio, se
    // reduce TODO el bloque de datos y se vuelve a medir (18/09, idea de
    // Antony: "de repente si reduces todo"). Reducir gana por partida doble:
    // acorta cada renglón y mete más caracteres en él, así que la dirección
    // se parte en menos líneas. El caso normal —uno o dos deudores— nunca
    // entra aquí y se imprime igual que el maestro.
    $porColumnaMax = $capacidad(1.0);
    $cabChica = $porColumnaMax <= 10;
    if ($cabChica) {
        $porColumnaMax = $capacidad(7 / 8.5);
    }
    $porColumnaMax = max(10, $porColumnaMax);
    // Máximo TRES columnas. Las celdas del cronograma van con nowrap
    // (una fecha no se parte), así que cada columna tiene un ancho mínimo
    // de ~4,8 cm: tres llenan 14,4 de los 16,2 cm útiles y la cuarta se
    // imprimía FUERA del papel —con 72 y 96 cuotas el texto llegaba a
    // 614,9 pt sobre un borde útil de 538,6—. El conteo de páginas no lo
    // veía porque salirse a la derecha no agrega hojas (18/09).
    $cols = min(3, max(1, (int) ceil($n / $porColumnaMax)));
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
    $anchoColCm = round($anchoUtilCm / max(1, count($grupos)), 2);
    // Menos el padding horizontal de la celda contenedora (0 4px ≈ 0.22cm).
    $anchoCronCm = count($grupos) === 1 ? $anchoUtilCm : round($anchoColCm - 0.22, 2);

@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 1 — Crédito #{{ $d['credito']['numero'] }}</title>
    {{-- pieWord: el Anexo 1 es el único documento con pie, y en Word necesita
         el pie DE LA SECCIÓN para quedar siempre al fondo de la hoja. --}}
    @include('documentos.pdf.estilos', ['pieWord' => true])
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
        /* La de deudores adicionales es la excepción: sus datos son nombres,
           direcciones y correos largos. Con nowrap la tabla se estiraba hasta
           salirse del papel por la derecha, así que aquí la rejilla es fija y
           el texto se parte. */
        table.ax.cli-extra { table-layout: fixed; }
        table.ax.cli-extra td, table.ax.cli-extra th { font-size: 6.8pt; padding: 0.5px 3px;
                                                       white-space: normal; word-wrap: break-word; }
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
        /* Word (18/09, Antony: "la dirección siempre abajo como pie de
           página"): va en el PIE DE LA SECCIÓN, que Word repite al fondo de
           cada hoja. En el flujo quedaba pegado al final del cronograma, a
           media hoja. El position:fixed/absolute no sirve aquí: Word lo
           convierte en un marco flotante que se posa sobre el contenido. */
        .ax-pie { margin: 0; }
        @else
        /* Previa: el pie va al fondo de la HOJA, absoluto dentro de .ax-hoja
           (el contenido, SIN el padding que hace de margen). Antes se
           posicionaba contra el body y left/right 0 abarcaban también el
           padding: la raya salía más ancha que las tablas — eso vio Antony. */
        .ax-hoja { position: relative; min-height: 24.5cm; }
        .ax-pie { position: absolute; bottom: 0; left: 0; right: 0; }
        @endif
        @if(($d['clientes'] ?? null) && count($d['clientes']) > 1)
        /* Con codeudor el bloque de clientes se lleva una columna más y las
           del vehículo se angostan. Un N° de serie es UN SOLO token sin
           espacios: sin esto desborda la celda y se ve cortado. */
        table.ax.cab { table-layout: fixed; }
        table.ax tr.rejilla td { border: none; padding: 0; font-size: 0; line-height: 0; height: 0; }
        table.ax td.valor-cent, table.ax td.montod,
        table.ax td.cli, table.ax td.cli span { word-wrap: break-word; }
        @endif
        @if($cabChica)
        /* Cabecera encogida (18/09, idea de Antony: "de repente si reduces
           todo"). Solo cuando al cronograma ya no le queda sitio. Baja el
           alto de las filas Y mete más caracteres por renglón, así que la
           dirección se parte en menos líneas: el bloque de datos pierde
           cerca de un tercio de su alto. */
        table.ax td, table.ax th { font-size: 7pt; padding: 0.5px 3px; }
        table.ax.cli-extra td, table.ax.cli-extra th { font-size: 6.2pt; }
        .ax-banner { font-size: 10.5pt; }
        .ax-titulo { font-size: 9.5pt; }
        /* "Reduces todo" es todo: el cronograma también, porque en estos
           casos carga el máximo de filas por columna. */
        table.ax-cron td, table.ax-cron th { font-size: 6.5pt; padding: 0.5px 2px; line-height: 1; }
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
    <table class="ax cab">
    @if ($varios && $medio !== 'word')
        {{-- Fila-rejilla. dompdf NO lee el <colgroup> (lo descarta antes de
             armar el árbol de marcos) y, con table-layout: fixed, resuelve
             los anchos mirando SOLO la primera fila; la primera fila de aquí
             son dos celdas con colspan, que él ignora, así que repartía las
             seis columnas por igual —de ahí el hueco entre el "S/" y el
             importe del vehículo—. Esta fila sin alto le da la rejilla
             declarada y no se ve en la hoja. --}}
        <tr class="rejilla">
            <td style="width: {{ $wEtiqueta }}%;"></td>
            @foreach ($clientes as $ignorado)
                <td style="width: {{ $wDeudor }}%;"></td>
            @endforeach
            <td style="width: {{ $wVehLabel }}%;"></td>
            <td style="width: {{ $wSim }}%;"></td>
            <td style="width: {{ $wMonto }}%;"></td>
        </tr>
    @endif
    @if ($medio === 'word' || $varios)
        {{-- Rejilla explícita. En Word siempre (la deduce de la primera fila,
             que aquí lleva colspan). En el PDF cuando hay codeudor: dompdf
             mide cada columna por la palabra MÁS LARGA que contiene —y un
             correo es una sola palabra—, así que ensanchaba la tabla fuera
             del papel y el bloque del vehículo se salía de la hoja. --}}
        <colgroup>
            <col style="width: {{ $wEtiqueta }}%">
            @foreach ($clientes as $ignorado)
                <col style="width: {{ $wDeudor }}%">
            @endforeach
            <col style="width: {{ $wVehLabel }}%">
            <col style="width: {{ $wSim }}%">
            <col style="width: {{ $wMonto }}%">
        </colgroup>
    @endif
        <tr>
            {{-- Plural cuando hay codeudor, como el maestro. --}}
            <th class="azul" colspan="{{ 1 + $nDeudores }}">DATOS {{ $varios ? 'DE LOS CLIENTES' : 'DEL CLIENTE' }}</th>
            <th class="azul" colspan="3">DATOS DEL VEHÍCULO</th>
        </tr>
        <tr>
            <td class="etiqueta" style="width: {{ $wEtiqueta }}%;">{{ $varios ? 'Clientes' : 'Cliente' }}</td>
            @foreach ($clientes as $c)
                <td class="cli" style="width: {{ $wDeudor }}%;">{{ $c['nombre'] }}</td>
            @endforeach
            {{-- 17/09 (Antony): "Placa de Rodaje", como las demás etiquetas, no en mayúsculas. --}}
            <td class="etiqueta" style="width: {{ $wVehLabel }}%; white-space: nowrap;">Placa de Rodaje</td>
            <td class="valor-cent" colspan="2" style="width: {{ $wSim + $wMonto }}%;">{{ $v1['placa'] ?? '—' }}</td>
        </tr>
        <tr>
            {{-- El maestro rotula una vez, con el tipo del titular. Eso vale
                 mientras los deudores tengan el MISMO tipo de documento; si
                 uno lleva carné de extranjería y el otro DNI, su número
                 saldría bajo la etiqueta equivocada —en un documento que se
                 firma ante notaría—, así que ahí se rotula genérico y cada
                 número lleva el suyo delante. --}}
            <td class="etiqueta">{{ $tipoDocComun ?: 'Documento' }}</td>
            @foreach ($clientes as $c)
                <td class="cli">{{ $tipoDocComun ? $c['documento'] : trim(($c['documento_tipo'] ?: 'DNI').' '.$c['documento']) }}</td>
            @endforeach
            <td class="etiqueta">Marca</td>
            <td class="valor-cent" colspan="2">{{ ($v1['marca'] ?? '') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Dirección</td>
            @foreach ($clientes as $c)
                <td class="cli">{{ $c['domicilio'] }}</td>
            @endforeach
            <td class="etiqueta">Modelo</td>
            <td class="valor-cent" colspan="2">{{ ($v1['modelo'] ?? '') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Celular</td>
            @foreach ($clientes as $c)
                <td class="cli">{{ $c['celular'] }}</td>
            @endforeach
            <td class="etiqueta">N° Serie</td>
            <td class="valor-cent" colspan="2">{{ ($v1['nro_serie'] ?? '') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Correo</td>
            @foreach ($clientes as $c)
                <td class="cli"><span class="ax-correo">{{ $c['correo'] }}</span></td>
            @endforeach
            <td class="etiqueta">Valor Vehículo</td>
            @if (($v1['valor'] ?? null) !== null)
                <td class="sim">S/</td>
                <td class="montod">{{ $fmt($v1['valor']) }}</td>
            @else
                <td class="valor-cent" colspan="2">—</td>
            @endif
        </tr>
    </table>

    {{-- ── Deudores adicionales (3° en adelante), mismo estilo ──────────
         El maestro pone dos columnas; si un vehículo tuviera más
         copropietarios, los demás se listan aquí en filas. Así no hay
         columna que angostar ni tabla que se salga del papel. --}}
    @if (count($clientesExtra) > 0)
        <table class="ax adicionales cli-extra">
            {{-- La dirección es el dato largo: se lleva el ancho que el
                 celular y el DNI no necesitan. Con el reparto anterior se
                 partía en 8 renglones y estiraba la tabla de más. --}}
            <colgroup>
                <col style="width: 22%">
                <col style="width: 10%">
                <col style="width: 40%">
                <col style="width: 10%">
                <col style="width: 18%">
            </colgroup>
            {{-- Fila-rejilla, por lo mismo que la tabla de arriba: dompdf no
                 lee el colgroup y su primera fila lleva colspan. En Word no
                 hace falta (sí lee el colgroup) y pintaría una fila fina. --}}
            @if ($medio !== 'word')
            <tr class="rejilla">
                <td style="width: 22%;"></td><td style="width: 10%;"></td>
                <td style="width: 40%;"></td><td style="width: 10%;"></td>
                <td style="width: 18%;"></td>
            </tr>
            @endif
            <tr><th class="azul" colspan="5">DATOS DE LOS CLIENTES ADICIONALES</th></tr>
            <tr>
                <th class="azul" style="width: 22%;">CLIENTE</th>
                <th class="azul" style="width: 10%;">DNI</th>
                <th class="azul" style="width: 40%;">DIRECCIÓN</th>
                <th class="azul" style="width: 10%;">CELULAR</th>
                <th class="azul" style="width: 18%;">CORREO</th>
            </tr>
            @foreach ($clientesExtra as $c)
                <tr>
                    <td class="cli">{{ $c['nombre'] }}</td>
                    <td class="valor-cent">{{ $c['documento'] ?: '—' }}</td>
                    <td class="cli">{{ $c['domicilio'] ?: '—' }}</td>
                    <td class="valor-cent">{{ $c['celular'] ?: '—' }}</td>
                    <td class="cli"><span class="ax-correo">{{ $c['correo'] }}</span></td>
                </tr>
            @endforeach
        </table>
    @endif

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
    @php
        $pieTexto = 'DPTO. SEC. B UCV 72 LOTE 51 ZONA E AAHH HUAYCAN, DISTRITO DE ATE<br>CELULAR: 982333689/981352577';
    @endphp
    @if (($medio ?? 'pdf') === 'word')
        {{-- Pie de la SECCIÓN de Word: es lo único que lo deja siempre al fondo
             de la hoja. Va al cierre del body, con mso-element:footer, y la
             regla @page WordSection1 lo referencia por su id. --}}
        <div class="msoFooterHost" style="mso-element: footer;" id="f1">
            <div class="ax-pie">{!! $pieTexto !!}</div>
        </div>
    @else
        <div class="ax-pie">{!! $pieTexto !!}</div>
    @endif
    </div>{{-- /.ax-hoja --}}
</body>
</html>
