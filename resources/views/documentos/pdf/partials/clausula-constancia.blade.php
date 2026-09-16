{{-- CLÁUSULA: CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL.
     Variantes por $vm->destino:
       - 'propio'  → redacción de la maestra a.1 (a favor de los deudores).
       - 'tercero' → redacción de a.1.1 (depósito a cuenta de un tercero
                     autorizado; datos y motivo en $vm->tercero).
       - 'gerente' → redacción de a.4.1 (cuenta del gerente general del
                     deudor persona jurídica).
     Monto SIEMPRE via $vm->monto('obligacion'); el banco sale del voucher.

     Son DOS viñetas, como en las maestras: la de certificación y la de
     "INSERTO:", que es la que remite expresamente al Anexo 2. La imagen del
     voucher no va aquí (eso sí vive en el Anexo 2), pero el párrafo que la
     cita es parte del contrato y sin él se pierde el vínculo entre ambos.

     $banco YA TRAE EL ARTÍCULO ('EL BANCO DE CRÉDITO...', 'LA CAJA MUNICIPAL
     ...'), así que se escribe "EN {{ $banco }}" y nunca "EN EL {{ $banco }}". --}}
@php
    $banco = mb_strtoupper($vm->voucher['bancoNombreLegal'] ?? 'LA ENTIDAD BANCARIA');
    $nombres = mb_strtoupper(implode(' Y ', array_column($vm->deudores, 'nombre')));
    $anexo2 = 'CUYO TENOR GRÁFICO Y LITERAL SE ENCUENTRA EN EL ANEXO 2 DEL PRESENTE CONTRATO.';
@endphp
@php
    $n = $vm->ord->numero('constancia');
    $i = 0;
@endphp
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('constancia') }}: CONSTANCIA DE ENTREGA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL</div>
    <p class="parrafo">LAS PARTES DEJAN CONSTANCIA DE QUE, EN CUMPLIMIENTO DE LO PACTADO EN EL PRESENTE CONTRATO DE GARANTÍA MOBILIARIA, SE HA REALIZADO EL DEPÓSITO/TRANSFERENCIA DEL MONTO DE LA OBLIGACIÓN PRINCIPAL:</p>

    @if ($vm->destino === 'tercero')
        <div class="numerales">
            <div class="numeral">{{ $n }}.{{ ++$i }}. AMBAS PARTES CERTIFICAN LA VALIDEZ Y EFICACIA LEGAL DEL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA A FAVOR DE {{ $vm->g->deudor() }} {{ $nombres }} POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} EN {{ $banco }}.</div>
        </div>
        <p class="parrafo">ASIMISMO, LAS PARTES DEJAN CONSTANCIA QUE, POR EXPRESA AUTORIZACIÓN DE {{ $vm->g->deudor() }}, DICHO DEPÓSITO FUE REALIZADO A LA CUENTA BANCARIA N° {{ $vm->tercero['cuenta'] }} A NOMBRE DE {{ mb_strtoupper($vm->tercero['nombre']) }}, IDENTIFICADO CON DNI N.° {{ $vm->tercero['dni'] }}, DEBIDO A QUE {{ mb_strtoupper($vm->tercero['motivo']) }}, CIRCUNSTANCIA QUE NO AFECTA LA VALIDEZ NI EL DESTINO DEL PAGO, EL CUAL SE CONSIDERA ÍNTEGRAMENTE RECIBIDO POR {{ $vm->g->deudor() }}.</p>
        <div class="numerales">
            <div class="numeral">{{ $n }}.{{ ++$i }}. INSERTO: EL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} EFECTUADO EN {{ $banco }}, A LA CUENTA DE {{ mb_strtoupper($vm->tercero['nombre']) }}, POR AUTORIZACIÓN EXPRESA DE {{ $vm->g->deudor() }} {{ $nombres }}, {{ $anexo2 }}</div>
        </div>
    @elseif ($vm->destino === 'gerente')
        <div class="numerales">
            <div class="numeral">{{ $n }}.{{ ++$i }}. AMBAS PARTES CERTIFICAN LA VALIDEZ DEL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA REALIZADO A LA CUENTA DE TITULARIDAD DEL GERENTE GENERAL {{ mb_strtoupper($vm->g->del()) }} {{ $vm->g->deudorSolo() }} {{ $nombres }}, POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} CONFORME SE ACREDITA EN LA TRANSACCION REALIZADA EN {{ $banco }}.</div>
            <div class="numeral">{{ $n }}.{{ ++$i }}. INSERTO: EL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA A FAVOR DEL GERENTE GENERAL {{ mb_strtoupper($vm->g->del()) }} {{ $vm->g->deudorSolo() }} {{ $nombres }} POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} EN {{ $banco }}, {{ $anexo2 }}</div>
        </div>
    @else
        <div class="numerales">
            <div class="numeral">{{ $n }}.{{ ++$i }}. AMBAS PARTES CERTIFICAN LA VALIDEZ DEL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA A FAVOR DE {{ $vm->g->deudor() }} {{ $nombres }} POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} EN {{ $banco }}.</div>
            <div class="numeral">{{ $n }}.{{ ++$i }}. INSERTO: EL COMPROBANTE DEL DEPÓSITO/TRANSFERENCIA BANCARIA A FAVOR DE {{ $vm->g->deudor() }} {{ $nombres }} POR EL IMPORTE TOTAL DE {{ $vm->monto('obligacion') }} EN {{ $banco }}, {{ $anexo2 }}</div>
        </div>
    @endif
</div>
