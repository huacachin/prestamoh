{{-- Cláusula: DATOS DE LOS CONTRATANTES. Fuentes: a.1 (deudor), a.2 (deudora),
     a.3 (dos deudores: un bloque por deudor), a.4 (persona jurídica: razón
     social + RUC + gerente general). Los datos del ACREEDOR y su APODERADA
     salen SIEMPRE de $vm->constante('acreedor') / $vm->constante('apoderada'),
     nunca hardcodeados; "EL ACREEDOR" y "LA APODERADA" quedan literales. --}}
@php
    $acreedor = $vm->constante('acreedor');
    $apoderada = $vm->constante('apoderada');
@endphp
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('datos') }}: DATOS DE LOS CONTRATANTES</div>

    @foreach ($vm->deudores as $d)
        @if ($d['esJuridica'])
            <p class="parrafo">DATOS DE LA CONSTITUYENTE Y {{ $d['g']->deudor() }}: {{ $d['nombre'] }} IDENTIFICADA CON RUC N° {{ $d['ruc'] }}, Y CON DOMICILIO EN {{ $d['domicilio'] }}; DEBIDAMENTE REPRESENTADA POR SU GERENTE GENERAL {{ $d['gerente']['nombre'] }},@if (! empty($d['gerente']['nacionalidad'])) DE NACIONALIDAD {{ $d['gerente']['g']->flex($d['gerente']['nacionalidad']) }},@endif {{ $d['gerente']['g']->flex('IDENTIFICADO') }} CON DNI N° {{ $d['gerente']['dni'] }}, OCUPACIÓN {{ $d['gerente']['ocupacion'] }}, ESTADO CIVIL {{ $d['gerente']['g']->flex($d['gerente']['estadoCivil']) }}, CON DOMICILIO EN {{ $d['gerente']['domicilio'] }}, CONFORME SE ENCUENTRA {{ $d['gerente']['g']->flex('INSCRITO') }} EN LA PARTIDA REGISTRAL N° {{ $d['partida'] }} DEL REGISTRO DE PERSONAS JURIDICAS DE LA OFICINA REGISTRAL DE {{ $d['oficinaRegistral'] }}.</p>
        @else
            <p class="parrafo">DATOS {{ mb_strtoupper($d['g']->del()) }} CONSTITUYENTE Y {{ $d['g']->deudor() }}: {{ $d['nombre'] }}, DE NACIONALIDAD {{ $d['g']->flex($d['nacionalidad']) }}, {{ $d['g']->flex('IDENTIFICADO') }} CON DNI N° {{ $d['dni'] }}, OCUPACIÓN {{ $d['ocupacion'] }}, ESTADO CIVIL {{ $d['g']->flex($d['estadoCivil']) }}, CON DOMICILIO EN {{ $d['domicilio'] }}.</p>
        @endif
    @endforeach

    <p class="parrafo">DATOS DEL ACREEDOR A CUYO FAVOR SE CONSTITUYE LA GARANTÍA MOBILIARIA: {{ $acreedor['nombre'] }}, DE NACIONALIDAD {{ $acreedor['nacionalidad'] }}, IDENTIFICADO CON DNI N° {{ $acreedor['dni'] }}, ESTADO CIVIL {{ $acreedor['estado_civil'] }}, CON DOMICILIO EN {{ $acreedor['domicilio'] }}; QUIEN SE ENCUENTRA DEBIDAMENTE REPRESENTADO PARA LA PRESENTE CONSTITUCIÓN DE GARANTÍA MOBILIARIA EN SU CALIDAD DE APODERADA A LA SEÑORA {{ $apoderada['nombre'] }}, IDENTIFICADA CON DNI N.º {{ $apoderada['dni'] }}, ESTADO CIVIL {{ $apoderada['estado_civil'] }}, CON DOMICILIO EN {{ $apoderada['domicilio'] }}, EN VIRTUD DEL PODER AMPLIO Y ESPECIAL INSCRITO EN LA PARTIDA REGISTRAL N.º {{ $apoderada['partida_poder'] }} DEL REGISTRO DE PERSONAS NATURALES DE LA OFICINA REGISTRAL DE LIMA.</p>
</div>
