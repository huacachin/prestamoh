{{-- Encabezado del contrato: título (.titulo-contrato) + párrafo introductorio.
     Fuente: modelo a.1 (título y "CONSTE POR EL PRESENTE DOCUMENTO...");
     con $vm->custodia se usa la redacción de a.1.6 ("CON POSESIÓN");
     si todos los bienes son futuros, la de a.1.4 ("PRE-CONSTITUCIÓN");
     si hay bien futuro y presente, la de a.1.5.
     El TÍTULO sale de $vm->titulo() — misma fuente que la cita del Anexo 1
     en la cláusula de ejecución. --}}
@php
    $encFuturos = array_filter($vm->bienes, fn ($b) => ! empty($b['esFuturo']));
    $encTodosFuturos = count($encFuturos) > 0 && count($encFuturos) === count($vm->bienes);
    $encMixto = count($encFuturos) > 0 && ! $encTodosFuturos;

    $encNucleo = match (true) {
        $vm->custodia => 'EL CONTRATO DE CONSTITUCIÓN DE GARANTÍA MOBILIARIA CON POSESIÓN',
        $encTodosFuturos => 'EL CONTRATO DE PRE-CONSTITUCIÓN DE GARANTÍA MOBILIARIA',
        $encMixto => 'EL CONTRATO DE CONSTITUCIÓN DE GARANTÍA MOBILIARIA SOBRE BIEN FUTURO Y BIEN PRESENTE',
        default => 'EL CONTRATO DE CONSTITUCIÓN DE GARANTÍA MOBILIARIA',
    };
@endphp
<div class="titulo-contrato">{{ $vm->titulo() }}</div>
<p class="parrafo">CONSTE POR EL PRESENTE DOCUMENTO {{ $encNucleo }} MEDIANTE EL SISTEMA INFORMATIVO DE GARANTIAS MOBILIARIAS - SIGM, QUE CELEBRAN DE UNA PARTE:</p>
