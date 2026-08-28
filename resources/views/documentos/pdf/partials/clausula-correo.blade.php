{{-- CLÁUSULA: DECLARACIÓN SOBRE CORREO ELECTRÓNICO.

     UN SOLO PÁRRAFO, con el Genero COLECTIVO y SIN el nombre del deudor:
       a.1 → "EL DEUDOR DECLARA QUE SU CORREO ELECTRÓNICO ES x@y.com, EL CUAL
              CONSTITUYE UN MEDIO VÁLIDO…"
       a.3 → "LOS DEUDORES DECLARAN QUE SUS CORREOS ELECTRÓNICOS SON: a@b.com
              Y c@d.com, LOS CUALES CONSTITUYEN UN MEDIO VÁLIDO…"

     Antes se emitía un párrafo por deudor, con el nombre intercalado y con el
     género INDIVIDUAL: en a.3 eso producía "LA DEUDORA MARÍA LÓPEZ DECLARA…"
     donde la maestra dice "LOS DEUDORES DECLARAN", y duplicaba la cláusula. --}}
@php
    $correos = array_values(array_filter(array_map(
        fn (array $d) => mb_strtoupper(trim((string) $d['correo'])),
        $vm->deudores
    )));
    $varios = count($correos) > 1;
@endphp
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('correo') }}: DECLARACIÓN SOBRE CORREO ELECTRÓNICO</div>
    <p class="parrafo">{{ $vm->g->deudor() }} {{ $vm->g->verbo('DECLARA', 'DECLARAN') }} QUE {{ $varios ? 'SUS CORREOS ELECTRÓNICOS SON:' : 'SU CORREO ELECTRÓNICO ES' }} {{ implode(' Y ', $correos) }}, {{ $varios ? 'LOS CUALES CONSTITUYEN' : 'EL CUAL CONSTITUYE' }} UN MEDIO VÁLIDO DE COMUNICACIÓN ENTRE LAS PARTES PARA EFECTOS DE NOTIFICACIONES, COORDINACIÓN Y LA RECEPCIÓN DE INFORMACIÓN RELACIONADA CON LA PRESENTE GARANTÍA MOBILIARIA{{ $vm->custodia ? ' CON POSESIÓN' : '' }} Y SU EVENTUAL INSCRIPCIÓN EN EL SISTEMA INFORMATIVO DE GARANTÍAS MOBILIARIAS (SIGM).</p>
</div>
