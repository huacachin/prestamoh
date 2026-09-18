{{-- Estilos compartidos de los documentos del cliente. REGLA dompdf (heredada
     de payments/ticket-pdf): nada de flexbox — solo bloques y tablas.
     Recibe $medio del render ('pdf' | 'previa' | 'word'):
       pdf    → márgenes en @page (impresión: izquierdo más ancho para el
                legajo). Ningún documento lleva pie desde el 18/09.
       previa → el margen va como padding del body (iframe en el navegador).
       word   → sin margen aquí: lo fija el wrapper de DocResponse (@page A4).
     Y $compacto (solo lo pasa el CONTRATO): interlineado sencillo, márgenes
     y separaciones apretadas para no pasar de 5 hojas. Los anexos NO lo
     pasan — su maquetación ya está validada y no debe moverse. --}}
<style>
    @php
        $medio = $medio ?? 'pdf';
        $compacto = $compacto ?? false;
        // 18/09: lo pasa el Anexo 1, el único documento con pie. En Word, un
        // pie DE VERDAD (el de la sección) es lo único que lo deja siempre al
        // fondo de la hoja; en el flujo quedaba pegado al cronograma.
        $pieWord = $pieWord ?? false;
        // 05/09: el contrato debe caber en 5 hojas (regla del área legal) y
        // las cláusulas van "pegadas" como en las maestras en papel.
        $margenes = $compacto ? '1.8cm 1.8cm 1.5cm 2.4cm' : '2.2cm 2cm 2.4cm 2.8cm';
        // Un solo juego de TTF en public/fonts/bookman: dompdf los lee por
        // RUTA (public_path está dentro de su chroot) y la previa del
        // navegador por URL. Así la pantalla se ve igual que el papel.
        $bookmanSrc = $medio === 'pdf' ? public_path('fonts/bookman') : asset('fonts/bookman');
    @endphp
    /* Bookman Old Style (05/09): la tipografía de las maestras del área
       legal. Embebida en el PDF —fsType=0, embebido libre— y servida a la
       previa por URL.
       En WORD no se declaran (16/09): Word no descarga fuentes web al abrir
       un .doc, así que las cuatro reglas eran inútiles y encima podían
       disparar el aviso de "contenido externo". Word toma la Bookman Old
       Style INSTALADA —viene con Office— por el font-family del body, y si
       no está cae al serif de respaldo. */
    @if($medio !== 'word')
    @font-face { font-family: "Bookman Old Style"; font-style: normal; font-weight: normal;
                 src: url("{{ $bookmanSrc }}/BookmanOldStyle.ttf") format("truetype"); }
    @font-face { font-family: "Bookman Old Style"; font-style: normal; font-weight: bold;
                 src: url("{{ $bookmanSrc }}/BookmanOldStyleBold.ttf") format("truetype"); }
    @font-face { font-family: "Bookman Old Style"; font-style: italic; font-weight: normal;
                 src: url("{{ $bookmanSrc }}/BookmanOldStyleItalic.ttf") format("truetype"); }
    @font-face { font-family: "Bookman Old Style"; font-style: italic; font-weight: bold;
                 src: url("{{ $bookmanSrc }}/BookmanOldStyleBoldItalic.ttf") format("truetype"); }
    @endif
    @if($medio === 'pdf')
    @page { margin: {{ $margenes }}; }
    @elseif($medio === 'word')
    /* Word (16/09): la caja de página se declara AQUÍ y no en DocResponse,
       porque aquí es donde se sabe si el documento es el CONTRATO (márgenes
       apretados de $compacto) o un anexo. Fijarla allá le imponía al contrato
       los márgenes del anexo y le corría todos los saltos de línea.
       El tamaño va en PUNTOS y no como la palabra "A4": en una instalación
       configurada en Carta la palabra se ignora y cambia la caja, y el
       Anexo 1 —calibrado para entrar justo en UNA hoja— se parte en dos.
       595.28 x 841.89 pt es el mismo A4 que lleva el MediaBox del PDF. */
    @page WordSection1 { size: 595.28pt 841.89pt; margin: {{ $margenes }};@if($pieWord) mso-footer: f1; mso-footer-margin: 1.2cm;@endif }
    div.WordSection1 { page: WordSection1; }
    @if($pieWord)
    /* El pie de la sección: Word lo repite al fondo de CADA hoja. El div que
       lo alimenta va al final del body con mso-element:footer e id="f1". */
    div.msoFooterHost { mso-element: footer; }
    @endif
    @endif
    /* OJO dompdf (verificado empíricamente con 3.1.6): el marco de página
       hereda el estilo del elemento raíz, así que NI el selector universal *
       NI `html` pueden llevar margin — ambos anulan los márgenes de @page.
       Reset con lista explícita SIN html: */
    body, div, p, h1, h2, h3, h4, table, thead, tbody, tfoot, tr, th, td,
    ul, ol, li, span, b, i, strong, em, img {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: "Bookman Old Style", "Bookman", "DejaVu Serif", serif;
        font-size: 6.5pt;
        line-height: {{ $compacto ? '1.2' : '1.5' }};
        color: #000;
        margin: 0;
        @if($medio === 'previa')
        padding: {{ $margenes }};
        background: #fff;
        @endif
    }
    /* Como en las maestras: negrita Y subrayado. */
    .titulo-contrato {
        font-size: 8pt;
        font-weight: bold;
        text-decoration: underline;
        text-align: center;
        text-transform: uppercase;
        margin: 0 0 8px 0;
    }
    .parrafo { text-align: justify; margin: 0 0 {{ $compacto ? '4px' : '8px' }} 0; }
    .clausula { margin: 0 0 4px 0; }
    .clausula-titulo {
        font-weight: bold;
        text-transform: uppercase;
        margin: 5px 0 2px 0;
    }
    .subtitulo { font-weight: bold; margin: {{ $compacto ? '4px 0 1px' : '6px 0 2px' }} 0; }
    ul.vinetas { margin: 0 0 4px 18px; padding: 0; }
    ul.vinetas li { text-align: justify; margin: 0 0 3px 0; }

    /* Listas NUMERADAS: las maestras usan numeración decimal (1., 2., 3...)
       en OCTAVO, NOVENO y DÉCIMO QUINTO — no bullets. El propio texto lo
       exige: "EN CASO DE LA HIPÓTESIS PREVISTA EN EL NUMERAL PRECEDENTE"
       no apunta a nada si no hay numerales. El start del segundo bloque de
       GPS continúa la cuenta tras el párrafo intercalado. */
    ol.numerada { margin: 0 0 4px 18px; padding: 0; list-style-type: decimal; }
    ol.numerada li { text-align: justify; margin: 0 0 3px 0; }

    /* Numerales COMPUESTOS (05/09): OCTAVO, NOVENO y DÉCIMO QUINTO numeran
       8.1, 8.2… con el ordinal de su cláusula delante — el mismo formato que
       ya usaba DÉCIMO SÉPTIMO. Van en divs y no en <ol> porque el número lo
       arma Blade; la sangría francesa (text-indent negativo) alinea el texto
       corrido bajo la primera línea, como en las maestras. */
    .numerales { margin: 0 0 4px 18px; }
    .numerales .numeral {
        text-align: justify;
        margin: 0 0 3px 0;
        padding-left: 26px;
        text-indent: -26px;
    }
    /* Párrafo de continuación DENTRO de un numeral (p. ej. el segundo
       párrafo del 9.4 en GPS): sin número delante, así que no lleva la
       sangría francesa — todas sus líneas van a la altura del cuerpo del
       numeral, no pegadas al margen. */
    .numerales .numeral-cont {
        text-align: justify;
        margin: 0 0 3px 0;
        padding-left: 26px;
    }

    table.datos {
        width: 100%;
        border-collapse: collapse;
        margin: {{ $compacto ? '2px 0 4px' : '4px 0 8px' }} 0;
        font-size: 6.5pt;
    }
    table.datos th, table.datos td {
        border: 0.6pt solid #000;
        padding: {{ $compacto ? '1.5px 4px' : '3px 5px' }};
        text-align: left;
        vertical-align: top;
    }
    table.datos th { background: #eee; text-transform: uppercase; }

    /* 15/09: SIN aire extra antes de "EN SEÑAL DE CONFORMIDAD" —la frase va
       pegada al final de las cláusulas— y el hueco se pasa ARRIBA DE LA LÍNEA,
       que es donde hace falta: ahí se firma a mano. */
    .firmas { page-break-inside: avoid; margin-top: 0; }
    table.tabla-firmas { width: 100%; border-collapse: collapse; }
    /* vertical-align: top — con 'bottom' las líneas de firma quedaban a
       distinta altura cuando una caja tenía más renglones que la otra
       (la del acreedor lleva 5 y la del deudor 3). */
    table.tabla-firmas td {
        width: 50%;
        /* El padding superior ES el espacio para firmar sobre la línea. */
        padding: 62px 14px 8px 14px;
        text-align: center;
        vertical-align: top;
        font-size: 6.5pt;
    }
    .linea-firma { border-top: 0.8pt solid #000; padding-top: 3px; }

    @if($medio === 'word')
    /* Word y las firmas (16/09). Dos cosas no sobreviven al importador:
       1) El hueco para firmar es un padding-top de 62px en la celda. Word lo
          traduce a márgenes de celda y descarta los superiores asimétricos
          grandes: el espacio en blanco desaparecía y las cajas quedaban
          pegadas al párrafo de "EN SEÑAL DE CONFORMIDAD". Se pasa a
          espacio ANTES de la línea, que es lo que Word sí mapea.
       2) page-break-inside: avoid está en el <div> .firmas, y Word solo
          entiende ese corte en párrafos y en FILAS de tabla. Se traslada a
          la fila. No se hace en el PDF: dompdf sí lo soporta en filas y
          empujaría el bloque a una hoja nueva, rompiendo el tope de 5. */
    table.tabla-firmas { table-layout: fixed; }
    table.tabla-firmas td { padding: 8pt 10pt; vertical-align: top; }
    table.tabla-firmas tr { page-break-inside: avoid; }
    .firmas .linea-firma { margin-top: 46pt; }
    @endif

    .salto { page-break-before: always; }

    /* Anexo 2 (15/09, área legal): el título va en negrita Y subrayado, y el
       subtítulo en negrita, como sus maestros. Solo los usa anexo2.blade;
       el Anexo 1 tiene sus propias clases (.ax-*). */
    .anexo-titulo {
        font-size: 8pt;
        font-weight: bold;
        text-decoration: underline;
        text-align: center;
        text-transform: uppercase;
        margin: 0 0 4px 0;
    }
    .anexo-subtitulo {
        font-size: 7pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        margin: 0 0 12px 0;
    }
    .membrete {
        font-size: 7pt;
        text-align: center;
        text-transform: uppercase;
        font-weight: bold;
        margin: 0 0 10px 0;
    }
    /* ── Anexo 1 a UNA hoja (28/08): bloques en paralelo ──────────────────
       dompdf no soporta flex/grid, así que la maquetación en columnas se
       arma con tablas contenedoras sin bordes. --------------------------- */
    table.grid2 { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0 0 8px 0; }
    table.grid2 > tr > td, table.grid2 td.celda { width: 50%; vertical-align: top; padding: 0; }
    table.grid2 td.izq { padding-right: 5px; }
    table.grid2 td.der { padding-left: 5px; }

    /* Bloques de datos compactos (mismo aspecto, menos alto por fila) */
    table.datos.compacta { margin: 0 0 6px 0; font-size: 8.5pt; }
    table.datos.compacta th, table.datos.compacta td { padding: 1.5px 5px; line-height: 1.25; }

    /* Cronograma repartido en varias columnas para que entre en la hoja */
    .cron-titulo { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; margin: 2px 0 3px 0; }
    table.cron-cols { width: 100%; border-collapse: separate; border-spacing: 0; }
    table.cron-cols > tr > td, table.cron-cols td.col { vertical-align: top; padding: 0 4px 0 0; }
    table.cron-mini { width: 100%; border-collapse: collapse; font-size: 8pt; }
    table.cron-mini th, table.cron-mini td { border: 0.6pt solid #000; padding: 1px 4px; }
    table.cron-mini th { background: #eee; text-align: center; font-size: 7.5pt; }
    table.cron-mini td.num { text-align: right; width: 14%; }
    table.cron-mini td.fecha { text-align: center; }
    table.cron-mini td.monto { text-align: right; }
    .cron-total { margin-top: 5px; font-size: 9pt; font-weight: bold; text-align: right; }

    table.cronograma { width: 60%; margin: 6px auto 10px auto; border-collapse: collapse; font-size: 9pt; }
    table.cronograma th, table.cronograma td { border: 0.6pt solid #000; padding: 2px 6px; }
    table.cronograma th { background: #eee; text-align: center; }
    table.cronograma td.num, table.cronograma td.monto { text-align: right; }
    table.cronograma td.fecha { text-align: center; }
    table.cronograma tr.total td { font-weight: bold; }

    .voucher-img { text-align: center; margin: 10px 0; }
    .voucher-img img { max-width: 320px; max-height: 420px; }
    /* Anexo 2 (18/09, Antony): la transcripción debajo del voucher va
       subrayada ENTERA, con su etiqueta en negrita incluida, como el
       maestro. (Sin escribir aquí la etiqueta literal: AnexoDosFidelidadTest
       cuenta sus apariciones en el HTML y este bloque viaja dentro.) */
    .detalles { text-align: justify; margin: 6px 0; text-decoration: underline; }
    .nota-pie { font-size: 6pt; text-align: center; margin-top: 14px; }
</style>
