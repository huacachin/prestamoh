{{-- Cláusula: VALOR DEL BIEN, MONTO DE LA OBLIGACIÓN Y MONTO MÁXIMO DE LA
     GARANTÍA. Fuente: a.1, verbatim; título en plural con varios bienes
     (a.1.2/a.1.5). Montos SIEMPRE via $vm->monto(...) — cifra y letras
     juntas, nunca por separado. --}}
@php($valorPlural = count($vm->bienes) > 1)
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('valor') }}: VALOR {{ $valorPlural ? 'DE LOS BIENES' : 'DEL BIEN' }}, MONTO DE LA OBLIGACIÓN Y MONTO MÁXIMO DE LA GARANTÍA</div>

    <p class="parrafo">VALOR {{ $valorPlural ? 'DE LOS BIENES AFECTADOS' : 'DEL BIEN AFECTADO' }}: {{ $vm->monto('valor_bien') }}, AMBAS PARTES DEJAN CONSTANCIA DE SU CONFORMIDAD CON EL VALOR ASIGNADO {{ $valorPlural ? 'A LOS BIENES OBJETO' : 'AL BIEN OBJETO' }} DE LA PRESENTE GARANTÍA, CONSIDERANDO LAS CONDICIONES DEL BIEN Y SU DEPRECIACIÓN NATURAL, QUEDANDO FIJADO DICHO MONTO COMO REFERENCIA PARA TODOS LOS EFECTOS LEGALES CORRESPONDIENTES, INCLUSO EN CASO DE EJECUCIÓN JUDICIAL O EXTRAJUDICIAL.</p>

    <p class="parrafo">MONTO DE LA OBLIGACIÓN PRINCIPAL: {{ $vm->monto('obligacion') }}, MONTO QUE CORRESPONDE AL CRÉDITO OTORGADO POR EL ACREEDOR EN FAVOR DE {{ $vm->g->deudor() }} Y QUE CONSTITUYE LA OBLIGACIÓN GARANTIZADA CON LA PRESENTE GARANTÍA MOBILIARIA.</p>

    <p class="parrafo">MONTO MÁXIMO DE LA GARANTÍA: {{ $vm->monto('monto_maximo') }}, SIENDO EL MONTO QUE REPRESENTA EL LÍMITE MÁXIMO DE RESPONSABILIDAD DE {{ $vm->g->deudor() }} QUE INCLUYE LOS INTERESES COMPENSATORIOS Y CORRESPONDE AL VALOR TOTAL DE LA OBLIGACIÓN GARANTIZADA.</p>
</div>
