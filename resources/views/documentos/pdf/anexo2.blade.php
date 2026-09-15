{{-- ANEXO 2 — Constancia de entrega del monto de la obligación principal.
     CALCADO del maestro del área legal (15/09, las 15 plantillas .docx): título,
     imagen del voucher ARRIBA, línea DETALLES con la transcripción LITERAL del
     voucher —no etiquetas nuestras— y al pie las formas de pago. Se conserva
     el párrafo de identificación (cliente, crédito, banco, fecha), que el
     maestro no trae: sin él, el PDF suelto no dice a qué crédito pertenece
     (decisión de Antony, 15/09). Fuera quedaron el membrete y el subtítulo de
     modalidad, que tampoco están en el maestro.
     Recibe el snapshot congelado ($d) y $medio ('pdf' | 'previa' | 'word'). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 2 — Crédito #{{ $d['credito']['numero'] }}</title>
    @include('documentos.pdf.estilos')
</head>
<body>
    {{-- Pie del maestro: las cuentas a las que paga el cliente (config). --}}
    <div class="pie-pagina pie-formas">{{ config('documentos.formas_pago') }}</div>

    <div class="anexo-titulo">ANEXO 2</div>
    <div class="anexo-subtitulo">CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL</div>

    {{-- La imagen va ARRIBA, como en el maestro: el voucher es el documento y
         la transcripción lo acompaña. --}}
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

    {{-- Identificación: lo único que separa este documento del maestro. --}}
    <p class="parrafo">
        LAS PARTES DEJAN CONSTANCIA DE QUE SE HA REALIZADO EL DEPÓSITO/TRANSFERENCIA DEL MONTO DE LA
        OBLIGACIÓN PRINCIPAL A FAVOR DE {{ $d['cliente']['nombre'] }} ({{ $d['cliente']['documento_tipo'] }}
        N° {{ $d['cliente']['documento'] }}) POR EL CRÉDITO N° {{ $d['credito']['numero'] }},
        EN {{ $d['banco_legal'] }}, EL {{ $d['fecha'] }}.
    </p>

    @php
        // La transcripción es LITERAL (texto del voucher, tal como se ve).
        // Los snapshots viejos la traían como pares label/valor: se siguen
        // renderizando a su manera para que un documento ya emitido no cambie.
        $t = $d['transcripcion'] ?? '';
        $detalles = is_array($t)
            ? collect($t)->map(fn (array $c) => $c['label'].': '.$c['valor'])->implode('; ')
            : trim((string) $t);
        // En mayúsculas como el maestro (y como el resto del documento), sin
        // importar si lo escribió el operador o la lectura automática.
        $detalles = mb_strtoupper(rtrim($detalles, " .;"));
    @endphp
    @if ($detalles !== '')
        <p class="detalles"><strong>DETALLES:</strong> {{ $detalles }}.</p>
    @endif

</body>
</html>
