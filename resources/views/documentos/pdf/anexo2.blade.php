{{-- ANEXO 2 — Constancia de entrega del monto de la obligación principal.
     CALCADO del maestro del área legal (15/09, las 15 plantillas .docx): título,
     imagen del voucher ARRIBA, línea DETALLES con la transcripción LITERAL del
     voucher —no etiquetas nuestras— y al pie las formas de pago. Se conserva
     Sin párrafo de identificación: el área lo pidió fuera el 15/09 tras
     revisarlo, así que el documento queda exactamente como su maestro. El
     vínculo con el crédito vive en la base y en el nombre del archivo, no en
     la hoja. Fuera quedaron también el membrete y el subtítulo de modalidad.
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
        // La lectura automática ya entrega el texto con "DETALLES:" delante
        // (15/09, pedido del área) y el operador puede escribirlo también: se
        // quita aquí para no imprimirlo dos veces, porque la etiqueta la pone
        // la propia plantilla en negrita.
        $detalles = ltrim(preg_replace('/^\s*DETALLES\s*:\s*/u', '', $detalles));
    @endphp
    @if ($detalles !== '')
        <p class="detalles"><strong>DETALLES:</strong> {{ $detalles }}.</p>
    @endif

</body>
</html>
