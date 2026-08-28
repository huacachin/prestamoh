{{-- Estilos compartidos de los documentos del cliente. REGLA dompdf (heredada
     de payments/ticket-pdf): nada de flexbox — solo bloques y tablas.
     Recibe $medio del render ('pdf' | 'previa' | 'word'):
       pdf    → márgenes en @page (impresión: izquierdo más ancho para el
                legajo) y pie con numeración de páginas.
       previa → el margen va como padding del body (iframe en el navegador).
       word   → sin margen aquí: lo fija el wrapper de DocResponse (@page A4). --}}
<style>
    @php $medio = $medio ?? 'pdf'; @endphp
    @if($medio === 'pdf')
    @page { margin: 2.2cm 2cm 2.4cm 2.8cm; }
    .pie-pagina {
        position: fixed;
        bottom: -1.2cm;
        left: 0;
        right: 0;
        text-align: right;
        font-size: 7.5pt;
        color: #444;
    }
    .pie-pagina .num:after { content: counter(page); }
    @else
    .pie-pagina { display: none; }
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
        font-family: "DejaVu Serif", serif;
        font-size: 9.5pt;
        line-height: 1.45;
        color: #000;
        margin: 0;
        @if($medio === 'previa')
        padding: 2.2cm 2cm 2.4cm 2.8cm;
        background: #fff;
        @endif
    }
    .titulo-contrato {
        font-size: 11pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        margin: 0 0 14px 0;
    }
    .parrafo { text-align: justify; margin: 0 0 8px 0; }
    .clausula { margin: 0 0 10px 0; }
    .clausula-titulo {
        font-weight: bold;
        text-transform: uppercase;
        margin: 10px 0 4px 0;
    }
    .subtitulo { font-weight: bold; margin: 6px 0 2px 0; }
    ul.vinetas { margin: 0 0 8px 18px; padding: 0; }
    ul.vinetas li { text-align: justify; margin: 0 0 6px 0; }

    table.datos {
        width: 100%;
        border-collapse: collapse;
        margin: 4px 0 8px 0;
        font-size: 9pt;
    }
    table.datos th, table.datos td {
        border: 0.6pt solid #000;
        padding: 3px 5px;
        text-align: left;
        vertical-align: top;
    }
    table.datos th { background: #eee; text-transform: uppercase; }

    .firmas { page-break-inside: avoid; margin-top: 26px; }
    table.tabla-firmas { width: 100%; border-collapse: collapse; }
    table.tabla-firmas td {
        width: 50%;
        padding: 30px 14px 8px 14px;
        text-align: center;
        vertical-align: bottom;
        font-size: 9pt;
    }
    .linea-firma { border-top: 0.8pt solid #000; padding-top: 3px; }

    .salto { page-break-before: always; }

    .anexo-titulo {
        font-size: 11pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        margin: 0 0 4px 0;
    }
    .anexo-subtitulo {
        font-size: 9.5pt;
        text-align: center;
        text-transform: uppercase;
        margin: 0 0 12px 0;
    }
    .membrete {
        font-size: 8.5pt;
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
    .detalles { text-align: justify; margin: 6px 0; }
    .nota-pie { font-size: 8pt; text-align: center; margin-top: 14px; }
</style>
