{{-- ANEXO 2 — Constancia de entrega del monto de la obligación principal.
     Documento COMPLETO renderizado desde el snapshot congelado ($d) por
     cualquiera de los tres medios ($medio: 'pdf' | 'previa' | 'word').
     Una sola vista para los 17 formatos Word del área: el título del bloque y
     los pares LABEL: VALOR ya vienen resueltos por BancosVoucher en
     $d['titulo'] y $d['transcripcion']; la línea "DETALLES: ...; ...; ..."
     corrida calca las constancias reales. La imagen del comprobante se
     referencia según el medio (ruta del filesystem para dompdf, URL absoluta
     para Word, /storage relativa para la previa); si aún no se subió
     (imagen_path null) solo la previa muestra el recuadro placeholder. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 2 — Crédito #{{ $d['credito']['numero'] }}</title>
    @include('documentos.pdf.estilos')
</head>
<body>
    <div class="pie-pagina">Anexo 2 — Crédito #{{ $d['credito']['numero'] }} — Página <span class="num"></span></div>

    <div class="membrete">{{ $d['marca'] }}</div>
    <div class="anexo-titulo">ANEXO 2</div>
    <div class="anexo-subtitulo">CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL</div>

    <p class="parrafo">
        LAS PARTES DEJAN CONSTANCIA DE QUE SE HA REALIZADO EL DEPÓSITO/TRANSFERENCIA DEL MONTO DE LA
        OBLIGACIÓN PRINCIPAL A FAVOR DE {{ $d['cliente']['nombre'] }} ({{ $d['cliente']['documento_tipo'] }}
        N° {{ $d['cliente']['documento'] }}) POR EL CRÉDITO N° {{ $d['credito']['numero'] }},
        EN {{ $d['banco_legal'] }}, EL {{ $d['fecha'] }}.
    </p>

    <p class="subtitulo">{{ $d['titulo'] }}</p>

    @if (! empty($d['transcripcion']))
        @php
            $detalles = collect($d['transcripcion'])
                ->map(fn (array $c) => $c['label'].': '.$c['valor'])
                ->implode('; ');
        @endphp
        <p class="detalles"><strong>DETALLES:</strong> {{ $detalles }}.</p>
    @endif

    @if (filled($d['imagen_path'] ?? null))
        @php
            $src = match ($medio) {
                'pdf' => \Illuminate\Support\Facades\Storage::disk('public')->path($d['imagen_path']),
                'word' => url('/storage/'.$d['imagen_path']),
                default => '/storage/'.$d['imagen_path'], // previa (iframe srcdoc)
            };
        @endphp
        <div class="voucher-img">
            <img src="{{ $src }}" alt="Comprobante de la operación">
        </div>
    @elseif ($medio === 'previa')
        <div class="voucher-img" style="border: 1pt dashed #999; padding: 30px 12px; color: #666;">
            La imagen del comprobante se insertará al generar
        </div>
    @endif

    <p class="nota-pie">El presente comprobante forma parte del acto constitutivo — Crédito N° {{ $d['credito']['numero'] }}</p>
</body>
</html>
