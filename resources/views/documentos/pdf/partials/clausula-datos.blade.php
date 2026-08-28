{{-- Cláusula: DATOS DE LOS CONTRATANTES. Fuentes: a.1 (deudor), a.2 (deudora),
     a.3 (dos deudores: un bloque por deudor), a.4 (persona jurídica: razón
     social + RUC + gerente general). Los datos del ACREEDOR y su APODERADA
     salen SIEMPRE de $vm->constante('acreedor') / $vm->constante('apoderada'),
     nunca hardcodeados; "EL ACREEDOR" y "LA APODERADA" quedan literales.

     La NACIONALIDAD no flexiona (ver App\Support\Documentos\Nacionalidades):
     va siempre PERUANO / VENEZOLANO, sea deudor o deudora. Lo que sí flexiona
     a su lado es IDENTIFICADO/A y el estado civil. --}}
@php
    $acreedor = $vm->constante('acreedor');
    $apoderada = $vm->constante('apoderada');

    // Datos personales de un deudor, SIN el domicilio: es la parte que se
    // repite por persona cuando el contrato lleva dos (a.3).
    $nucleo = fn (array $d) => trim($d['nombre'])
        .', DE NACIONALIDAD '.$d['nacionalidad']
        .', '.$d['g']->flex('IDENTIFICADO').' CON '.($d['documentoTipo'] ?? 'DNI').' N° '.$d['dni']
        .', OCUPACIÓN '.$d['ocupacion']
        .', ESTADO CIVIL '.$d['g']->flex($d['estadoCivil']);

    // Dos deudores van en UN SOLO párrafo, encadenados con "Y" y cerrando en
    // "AMBOS CON DOMICILIO EN" (a.3), no en un bloque por persona.
    $dosDeudores = count($vm->deudores) === 2 && empty($vm->deudores[0]['esJuridica']);

    // El área pide además una variante con domicilios separados (para deudores
    // que no son cónyuges) que NO existe en ninguna de las 32 maestras. En vez
    // de un flag aparte se deduce del dato: si ambos declaran el mismo
    // domicilio es el caso compartido, y si no, cada uno lleva el suyo.
    $domicilioCompartido = $dosDeudores
        && mb_strtoupper(trim($vm->deudores[0]['domicilio'])) === mb_strtoupper(trim($vm->deudores[1]['domicilio']));
@endphp
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('datos') }}: DATOS DE LOS CONTRATANTES</div>

    @if ($dosDeudores)
        @php($gc = $vm->g->constituyente())
        <p class="parrafo">DATOS {{ mb_strtoupper($gc->del()) }} {{ $gc->forma('CONSTITUYENTE', 'CONSTITUYENTE', 'CONSTITUYENTES', 'CONSTITUYENTES') }} Y {{ $vm->g->deudor() }}: @if ($domicilioCompartido){{ $nucleo($vm->deudores[0]) }}, Y {{ $nucleo($vm->deudores[1]) }}, AMBOS CON DOMICILIO EN {{ $vm->deudores[0]['domicilio'] }}.@else{{ $nucleo($vm->deudores[0]) }}, CON DOMICILIO EN {{ $vm->deudores[0]['domicilio'] }} Y {{ $nucleo($vm->deudores[1]) }}, CON DOMICILIO EN {{ $vm->deudores[1]['domicilio'] }}.@endif</p>
    @else
    @foreach ($vm->deudores as $d)
        @if ($d['esJuridica'])
            <p class="parrafo">DATOS DE LA CONSTITUYENTE Y {{ $d['g']->deudor() }}: {{ $d['nombre'] }} IDENTIFICADA CON RUC N° {{ $d['ruc'] }}, Y CON DOMICILIO EN {{ $d['domicilio'] }}; DEBIDAMENTE REPRESENTADA POR SU GERENTE GENERAL {{ $d['gerente']['nombre'] }},@if (! empty($d['gerente']['nacionalidad'])) DE NACIONALIDAD {{ $d['gerente']['nacionalidad'] }},@endif {{ $d['gerente']['g']->flex('IDENTIFICADO') }} CON {{ $d['gerente']['documentoTipo'] ?? 'DNI' }} N° {{ $d['gerente']['dni'] }}, OCUPACIÓN {{ $d['gerente']['ocupacion'] }}, ESTADO CIVIL {{ $d['gerente']['g']->flex($d['gerente']['estadoCivil']) }}, CON DOMICILIO EN {{ $d['gerente']['domicilio'] }}, CONFORME SE ENCUENTRA {{ $d['gerente']['g']->flex('INSCRITO') }} EN LA PARTIDA REGISTRAL N° {{ $d['partida'] }} DEL REGISTRO DE PERSONAS JURIDICAS DE LA OFICINA REGISTRAL DE {{ $d['oficinaRegistral'] }}.</p>
        @else
            <p class="parrafo">DATOS {{ mb_strtoupper($d['g']->del()) }} CONSTITUYENTE Y {{ $d['g']->deudor() }}: {{ $nucleo($d) }}, CON DOMICILIO EN {{ $d['domicilio'] }}.</p>
        @endif
    @endforeach
    @endif

    <p class="parrafo">DATOS DEL ACREEDOR A CUYO FAVOR SE CONSTITUYE LA GARANTÍA MOBILIARIA: {{ $acreedor['nombre'] }}, DE NACIONALIDAD {{ $acreedor['nacionalidad'] }}, IDENTIFICADO CON DNI N° {{ $acreedor['dni'] }}, ESTADO CIVIL {{ $acreedor['estado_civil'] }}, CON DOMICILIO EN {{ $acreedor['domicilio'] }}; QUIEN SE ENCUENTRA DEBIDAMENTE REPRESENTADO PARA LA PRESENTE CONSTITUCIÓN DE GARANTÍA MOBILIARIA EN SU CALIDAD DE APODERADA A LA SEÑORA {{ $apoderada['nombre'] }}, IDENTIFICADA CON DNI N.º {{ $apoderada['dni'] }}, ESTADO CIVIL {{ $apoderada['estado_civil'] }}, CON DOMICILIO EN {{ $apoderada['domicilio'] }}, EN VIRTUD DEL PODER AMPLIO Y ESPECIAL INSCRITO EN LA PARTIDA REGISTRAL N.º {{ $apoderada['partida_poder'] }} DEL REGISTRO DE PERSONAS NATURALES DE LA OFICINA REGISTRAL DE LIMA.</p>
</div>
