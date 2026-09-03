{{-- ANEXO 2 — Constancia de entrega del monto de la obligación principal.
     Una sola vista para los 17 formatos Word del área: el título del bloque y
     los pares LABEL: VALOR ya vienen resueltos por BancosVoucher en
     $vm->voucher (titulo, camposOrdenados). La línea "DETALLES: ...; ...; ..."
     corrida calca las constancias reales. El orquestador (contrato.blade.php)
     solo incluye este anexo cuando $vm->voucher no es null; $paraPdf decide si
     la imagen se referencia por ruta absoluta (dompdf) o por URL (previa). --}}
<div class="membrete">{{ $vm->constante('marca_documentos') }}</div>
<div class="anexo-titulo">ANEXO 2</div>
<div class="anexo-subtitulo">CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL</div>

<p class="subtitulo">{{ $vm->voucher['titulo'] }}</p>

@if (! empty($vm->voucher['camposOrdenados']))
    @php
        $detalles = collect($vm->voucher['camposOrdenados'])
            ->map(fn (array $c) => $c['label'].': '.$c['valor'])
            ->implode('; ');
    @endphp
    <p class="detalles"><strong>DETALLES:</strong> {{ $detalles }}.</p>
@endif

@php $imagenVoucher = $paraPdf ? $vm->voucher['imagenAbs'] : $vm->voucher['imagenUrl']; @endphp
@if ($imagenVoucher)
    <div class="voucher-img">
        <img src="{{ $imagenVoucher }}" alt="Comprobante de la operación">
    </div>
@endif

<p class="nota-pie">El presente comprobante forma parte del acto constitutivo de la garantía mobiliaria — Contrato {{ $vm->numero }}</p>
