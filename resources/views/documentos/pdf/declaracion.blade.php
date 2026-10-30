{{-- Declaraciones juradas de PROPIEDAD y de PUBLICIDAD (30/10/2026, Agregado 01 del Área Legal).
     Un solo envoltorio para los 8 modelos de "4. Declaración jurada Propiedad" y "5. Declaración Jurada
     Publicidad": el cuerpo de cada uno vive en declaraciones/{propiedad|publicidad}-{variante}.blade.php
     (texto del .docx); aquí van la página, la fecha (solo la de propiedad la lleva aparte) y las firmas.
     Recibe el snapshot congelado ($d, ver GeneradorDeclaracion), $medio y $dato. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @php
        $propiedad = $d['tipo'] === 'djprop';
        $p1 = $d['declarantes'][0] ?? [];
        $p2 = $d['declarantes'][1] ?? [];
        $e = $d['empresa'] ?? [];
        $g = $e['gerente'] ?? [];
        $b = $d['bien'];
        $acreedor = $d['constantes']['acreedor'];
        $publicidad = $d['constantes']['publicidad'];
        $ciudad = $d['ciudad'];
    @endphp
    <title>{{ $propiedad ? 'Declaración jurada de propiedad' : 'Declaración jurada de publicidad' }} — Crédito #{{ $d['credito']['numero'] }}</title>
    {{-- Márgenes de los .docx del área: 1.6 cm arriba, 2 cm a los lados y 1.5 cm abajo. --}}
    @include('documentos.pdf.estilos', ['margenes' => '1.6cm 2cm 1.5cm 2cm'])
    <style>
        /* Los tamaños de los modelos: 7 pt (propiedad) y 8 pt (publicidad), en la Bookman de la casa
           (el contrato va en 6.5 pt). Una sola hoja: con 7.5 pt la de bien futuro con dos declarantes
           dejaba las firmas en una segunda hoja. */
        body { font-size: {{ $propiedad ? '7pt' : '8pt' }}; line-height: 1.3; }
        .dj-titulo { text-align: center; font-weight: bold; text-decoration: underline;
                     font-size: {{ $propiedad ? '8pt' : '9pt' }}; margin: 0 0 8px 0; }
        .dj-fecha { text-align: right; margin-top: 10px; }
        table.dj-firmas { width: 100%; border-collapse: collapse; margin-top: 6px; page-break-inside: avoid; }
        /* Espacio para firmar sobre la línea (30/10, Antony: "bajarlo un poco más"): en la de publicidad
           sobra hoja; en la de propiedad, lo más que deja una sola hoja con el texto más largo
           (bien futuro, dos declarantes), apretando un poco los párrafos. */
        .parrafo { margin: 0 0 {{ $propiedad ? '5px' : '8px' }} 0; }
        table.dj-firmas td { vertical-align: top; text-align: center; padding: {{ $propiedad ? '80px' : '95px' }} 8px 2px 8px; }
        table.dj-firmas .linea { border-top: 0.8pt solid #000; padding-top: 3px; }
        table.dj-firmas.negrita td { font-weight: bold; }
    </style>
</head>
<body>
    @include('documentos.pdf.declaraciones.'.($propiedad ? 'propiedad' : 'publicidad').'-'.$d['variante'])

    @if ($propiedad)
        <p class="parrafo dj-fecha">{{ $ciudad }}, {{ $dato($d['fecha_de']) }}.</p>
    @endif

    @include('documentos.pdf.declaraciones.firmas')
</body>
</html>
