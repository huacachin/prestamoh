{{-- Cláusula: DECLARACIÓN JURADA DE PROPIEDAD Y LEGITIMIDAD. Fuentes: a.1
     (bien presente; a.1.2 pluraliza; a.3 flexiona número; a.4 jurídica en
     femenino), a.1.4 (bien futuro: título "...DE BIEN FUTURO" + acta de
     transferencia, kardex, notario e inscripción pendiente), a.1.3 (todos
     futuros: "...DE LOS BIENES FUTUROS"), a.1.5 (mixta: "...DE LOS BIENES",
     por vehículo). El constituyente flexiona con $vm->g (conjunto). --}}
@php
    $decFuturos = [];
    $decPresentes = [];
    foreach (array_values($vm->bienes) as $i => $b) {
        if (! empty($b['esFuturo'])) {
            $decFuturos[$i + 1] = $b;
        } else {
            $decPresentes[$i + 1] = $b;
        }
    }
    $decPlural = count($vm->bienes) > 1;
    $decTodosFuturos = $decFuturos !== [] && $decPresentes === [];
    $decMixto = $decFuturos !== [] && $decPresentes !== [];

    // Un párrafo de acta por cada acta distinta (a.1.3: dos futuros con una
    // misma acta comparten un solo párrafo en plural).
    $decActas = [];
    foreach ($decFuturos as $n => $b) {
        $clave = ($b['fechaActa'] ?? '').'|'.($b['kardex'] ?? '').'|'.($b['notario'] ?? '');
        $decActas[$clave]['nums'][] = $n;
        $decActas[$clave]['bien'] = $b;
    }

    // Toda esta cláusula la enuncia LA CONSTITUYENTE, no EL DEUDOR: en la
    // persona jurídica va en femenino (a.4: "SER PROPIETARIA", "ESTAR LEGITIMADA").
    $gc = $vm->g->constituyente();
    $constituyente = $gc->forma('EL CONSTITUYENTE', 'LA CONSTITUYENTE', 'LOS CONSTITUYENTES', 'LAS CONSTITUYENTES');
    // 'AL VEHICULO 1' / 'A LOS VEHICULOS 1 Y 2' (caso mixto, según a.1.5)
    $respectoA = fn (array $nums) => count($nums) > 1 ? 'A LOS VEHICULOS '.implode(' Y ', $nums) : 'AL VEHICULO '.$nums[0];

    $decTitulo = 'DECLARACIÓN JURADA DE PROPIEDAD Y LEGITIMIDAD';
    if ($decTodosFuturos) {
        $decTitulo .= count($decFuturos) > 1 ? ' DE LOS BIENES FUTUROS' : ' DE BIEN FUTURO';
    } elseif ($decMixto) {
        $decTitulo .= ' DE LOS BIENES';
    }
@endphp
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('declaracion') }}: {{ $decTitulo }}</div>

    @if ($decTodosFuturos)
        <p class="parrafo">{{ $constituyente }} {{ $vm->g->verbo('DECLARA', 'DECLARAN') }} BAJO JURAMENTO QUE {{ $vm->g->verbo('HA', 'HAN') }} REALIZADO LA TRANSFERENCIA {{ $decPlural ? 'DE LOS VEHICULOS' : 'DEL VEHICULO' }} CONFORME A LEY, Y QUE {{ $vm->g->verbo('SERÁ', 'SERÁN') }} {{ mb_strtoupper($vm->g->el()) }} {{ $gc->propietario() }} {{ $decPlural ? 'DE LOS BIENES OBJETO' : 'DEL BIEN OBJETO' }} DE GARANTÍA Y QUE SOBRE EL MISMO NO EXISTE CARGA, GRAVAMEN NI MEDIDA ALGUNA QUE LIMITE SU LIBRE DISPOSICIÓN, SALVO LA PRESENTE GARANTÍA MOBILIARIA.</p>
        @foreach ($decActas as $acta)
            @php($actaPlural = count($acta['nums']) > 1)
            <p class="parrafo">ASIMISMO, SE DEJA CONSTANCIA QUE LA VENTA {{ $actaPlural ? 'DE LOS VEHICULOS' : 'DEL VEHÍCULO' }} DESCRITA EN EL PÁRRAFO ANTERIOR SE HA REALIZADO EL DÍA {{ $acta['bien']['fechaActa'] }}, MEDIANTE ACTA DE TRANSFERENCIA VEHICULAR Y CON KARDEX N° {{ $acta['bien']['kardex'] }}, EXTENDIDA ANTE EL NOTARIO PÚBLICO {{ $acta['bien']['notario'] }}. EN CONSECUENCIA, SE HACE CONSTAR QUE {{ $actaPlural ? 'LOS VEHICULOS SE ENCUENTRAN' : 'EL VEHÍCULO SE ENCUENTRA' }} ACTUALMENTE EN PODER Y POSESIÓN FÍSICA DE {{ $vm->g->deudor() }}, EN VIRTUD DE LA COMPRAVENTA ANTES MENCIONADA, ENCONTRÁNDOSE PENDIENTE ÚNICAMENTE LA INSCRIPCIÓN REGISTRAL {{ $actaPlural ? 'DE LOS BIENES' : 'DEL BIEN' }} A NOMBRE DE {{ mb_strtoupper($vm->g->mismo()) }}.</p>
        @endforeach
    @elseif ($decMixto)
        <p class="parrafo">{{ $constituyente }} {{ $vm->g->verbo('DECLARA', 'DECLARAN') }} BAJO JURAMENTO RESPECTO {{ $respectoA(array_keys($decFuturos)) }}, QUE {{ $vm->g->verbo('HA', 'HAN') }} REALIZADO LA TRANSFERENCIA {{ count($decFuturos) > 1 ? 'DE LOS VEHICULOS' : 'DEL VEHICULO' }} CONFORME A LEY, Y QUE {{ $vm->g->verbo('SERÁ', 'SERÁN') }} {{ mb_strtoupper($vm->g->el()) }} {{ $gc->propietario() }} {{ count($decFuturos) > 1 ? 'DE LOS BIENES OBJETO' : 'DEL BIEN OBJETO' }} DE GARANTÍA Y QUE SOBRE EL MISMO NO EXISTE CARGA, GRAVAMEN NI MEDIDA ALGUNA QUE LIMITE SU LIBRE DISPOSICIÓN, SALVO LA PRESENTE GARANTÍA MOBILIARIA.</p>
        @foreach ($decActas as $acta)
            @php($actaPlural = count($acta['nums']) > 1)
            <p class="parrafo">ASIMISMO, SE DEJA CONSTANCIA QUE LA VENTA {{ $actaPlural ? 'DE LOS VEHICULOS' : 'DEL VEHÍCULO' }} DESCRITA EN EL PÁRRAFO ANTERIOR SE HA REALIZADO EL DÍA {{ $acta['bien']['fechaActa'] }}, MEDIANTE ACTA DE TRANSFERENCIA VEHICULAR Y CON KARDEX N° {{ $acta['bien']['kardex'] }}, EXTENDIDA ANTE EL NOTARIO PÚBLICO {{ $acta['bien']['notario'] }}. EN CONSECUENCIA, SE HACE CONSTAR QUE {{ $actaPlural ? 'LOS VEHICULOS SE ENCUENTRAN' : 'EL VEHÍCULO SE ENCUENTRA' }} ACTUALMENTE EN PODER Y POSESIÓN FÍSICA DE {{ $vm->g->deudor() }}, EN VIRTUD DE LA COMPRAVENTA ANTES MENCIONADA, ENCONTRÁNDOSE PENDIENTE ÚNICAMENTE LA INSCRIPCIÓN REGISTRAL {{ $actaPlural ? 'DE LOS BIENES' : 'DEL BIEN' }} A NOMBRE DE {{ mb_strtoupper($vm->g->mismo()) }}.</p>
        @endforeach
        <p class="parrafo">POR OTRO LADO, {{ $constituyente }} {{ $vm->g->verbo('DECLARA', 'DECLARAN') }} BAJO JURAMENTO RESPECTO {{ $respectoA(array_keys($decPresentes)) }} SER {{ $gc->propietario() }} {{ count($decPresentes) > 1 ? 'DE LOS BIENES OBJETO' : 'DEL BIEN OBJETO' }} DE GARANTÍA Y QUE SOBRE EL MISMO NO EXISTE CARGA, GRAVAMEN NI MEDIDA ALGUNA QUE LIMITE SU LIBRE DISPOSICIÓN QUE SEA DE SU RESPONSABILIDAD, SALVO LA PRESENTE GARANTÍA MOBILIARIA; ASÍ COMO {{ $vm->g->verbo('DECLARA', 'DECLARAN') }} EXPRESAMENTE ESTAR {{ $gc->flex('LEGITIMADO') }} PARA CONSTITUIR LA PRESENTE GARANTÍA EN FAVOR DE EL ACREEDOR.</p>
    @else
        <p class="parrafo">{{ $constituyente }} {{ $vm->g->verbo('DECLARA', 'DECLARAN') }} BAJO JURAMENTO SER {{ $gc->propietario() }} {{ $decPlural ? 'DE LOS BIENES OBJETOS' : 'DEL BIEN OBJETO' }} DE GARANTÍA Y QUE SOBRE EL MISMO NO EXISTE CARGA, GRAVAMEN NI MEDIDA ALGUNA QUE LIMITE SU LIBRE DISPOSICIÓN, SALVO LA PRESENTE GARANTÍA MOBILIARIA; ASÍ COMO {{ $vm->g->verbo('DECLARA', 'DECLARAN') }} EXPRESAMENTE ESTAR {{ $gc->flex('LEGITIMADO') }} PARA CONSTITUIR LA PRESENTE GARANTÍA EN FAVOR DE EL ACREEDOR.</p>
    @endif
</div>
