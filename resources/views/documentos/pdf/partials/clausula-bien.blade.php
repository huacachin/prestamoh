{{-- Cláusula: IDENTIFICACIÓN DEL BIEN OBJETO DE GARANTÍA. Fuentes: a.1 (un
     bien), a.1.2 (dos bienes: "VEHÍCULO 1"/"VEHÍCULO 2" y título en plural),
     a.1.5 (mixto: marcadores "(BIEN FUTURO)"/"(BIEN PRESENTE)" solo cuando
     conviven futuro y presente, como hace la maestra). --}}
@php
    $bienPlural = count($vm->bienes) > 1;
    $bienHayFuturo = array_filter($vm->bienes, fn ($b) => ! empty($b['esFuturo'])) !== [];
    $bienHayPresente = array_filter($vm->bienes, fn ($b) => empty($b['esFuturo'])) !== [];
    $bienMixto = $bienHayFuturo && $bienHayPresente;

    // "PROPIEDAD: BIENES PROPIO" (sic) en los 6 modelos de DOS bienes
    // presentes (a.1.2/a.2.2/a.3.2/b.1.2/b.2.2/b.3.2) — verificado contra
    // los Word: los de dos bienes FUTUROS (a.x.3) mantienen "BIEN PROPIO"
    // en singular. Se calca tal cual, con su concordancia y todo.
    $bienPropiedad = ($bienPlural && ! $bienHayFuturo) ? 'BIENES PROPIO' : 'BIEN PROPIO';
@endphp
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('bien') }}: IDENTIFICACIÓN {{ $bienPlural ? 'DE LOS BIENES OBJETO' : 'DEL BIEN OBJETO' }} DE GARANTÍA</div>

    @foreach (array_values($vm->bienes) as $i => $b)
        @php
            // Solo los atributos con valor: un contrato no debe imprimir "CATEGORÍA: ;"
            // cuando la ficha del vehículo (p. ej. importada del Excel) viene incompleta.
            $bienAttrs = array_filter([
                'PLACA' => $b['placa'],
                'MARCA' => $b['marca'],
                'MODELO' => $b['modelo'],
                'Nº DE MOTOR' => $b['motor'],
                'Nº DE SERIE' => $b['serie'],
                'CATEGORÍA' => $b['categoria'],
                'AÑO DE FABRICACIÓN/MODELO' => $b['anio'],
                'CARROCERÍA' => $b['carroceria'],
                'COLOR' => $b['color'],
                'COMBUSTIBLE' => $b['combustible'],
            ], fn ($v) => trim((string) $v) !== '');
            $bienLinea = implode('; ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($bienAttrs), $bienAttrs));
        @endphp
        <p class="parrafo">VEHÍCULO{{ $bienPlural ? ' '.($i + 1) : '' }}@if ($bienMixto) {{ ! empty($b['esFuturo']) ? '(BIEN FUTURO)' : '(BIEN PRESENTE)' }}@endif: {{ $bienLinea }}; PROPIEDAD: {{ $bienPropiedad }} (EN ADELANTE {{ $bienPlural ? 'LOS VEHICULOS' : 'EL VEHICULO' }}).</p>
    @endforeach
</div>
