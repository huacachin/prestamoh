{{-- CLÁUSULA: CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL.
     Variantes por $vm->destino:
       - 'propio'  → redacción de la maestra a.1 (a favor de los deudores).
       - 'tercero' → redacción de a.1.1 (depósito a cuenta de un tercero
                     autorizado; datos y motivo en $vm->tercero).
       - 'gerente' → redacción de a.4.1 (cuenta del gerente general del
                     deudor persona jurídica).
     Monto SIEMPRE via $vm->monto('obligacion'); el banco sale del voucher.
     El tenor gráfico del comprobante NO se inserta aquí: va en el Anexo 2
     (documento aparte). --}}
@php
    $banco = mb_strtoupper($vm->voucher['bancoNombreLegal'] ?? 'LA ENTIDAD BANCARIA');
    $nombres = mb_strtoupper(implode(' Y ', array_column($vm->deudores, 'nombre')));
@endphp
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('constancia') }}: CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL</div>
    <p class="parrafo">LAS PARTES DEJAN CONSTANCIA DE QUE, EN CUMPLIMIENTO DE LO PACTADO EN EL PRESENTE CONTRATO DE GARANTÍA MOBILIARIA, SE HA REALIZADO EL DEPÓSITO/TRANSFERENCIA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL:</p>

    @if ($vm->destino === 'tercero')
        <ul class="vinetas">
            <li>AMBAS PARTES CERTIFICAN LA VALIDEZ Y EFICACIA LEGAL DEL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA A FAVOR DE {{ $vm->g->deudor() }} {{ $nombres }} POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} EN EL {{ $banco }}.</li>
        </ul>
        <p class="parrafo">ASIMISMO, LAS PARTES DEJAN CONSTANCIA QUE, POR EXPRESA AUTORIZACIÓN DE {{ $vm->g->deudor() }}, DICHO DEPÓSITO FUE REALIZADO A LA CUENTA BANCARIA N° {{ $vm->tercero['cuenta'] }} A NOMBRE DE {{ mb_strtoupper($vm->tercero['nombre']) }}, IDENTIFICADO CON DNI N.° {{ $vm->tercero['dni'] }}, DEBIDO A QUE {{ mb_strtoupper($vm->tercero['motivo']) }}, CIRCUNSTANCIA QUE NO AFECTA LA VALIDEZ NI EL DESTINO DEL PAGO, EL CUAL SE CONSIDERA ÍNTEGRAMENTE RECIBIDO POR {{ $vm->g->deudor() }}.</p>
    @elseif ($vm->destino === 'gerente')
        <ul class="vinetas">
            <li>AMBAS PARTES CERTIFICAN LA VALIDEZ DEL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA REALIZADO A LA CUENTA DE TITULARIDAD DEL GERENTE GENERAL {{ mb_strtoupper($vm->g->del()) }} {{ $vm->g->deudorSolo() }} {{ $nombres }}, POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} CONFORME SE ACREDITA EN LA TRANSACCION REALIZADA EN EL {{ $banco }}.</li>
        </ul>
    @else
        <ul class="vinetas">
            <li>AMBAS PARTES CERTIFICAN LA VALIDEZ DEL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA A FAVOR DE {{ $vm->g->deudor() }} {{ $nombres }} POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} EN EL {{ $banco }}.</li>
        </ul>
    @endif
</div>
