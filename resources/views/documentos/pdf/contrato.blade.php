{{-- CONTRATO de garantía mobiliaria — documento SUELTO (el Anexo 1 y el
     Anexo 2 se emiten por separado). Recibe: $vm (App\Services\Documentos\
     ContratoVm) y $medio ('pdf' | 'previa' | 'word'). SIN pie de página
     (05/09): las maestras en papel no lo llevan; los anexos sí lo mantienen
     porque se archivan sueltos. Las cláusulas activas y su numeración
     las gobierna $vm->clausulas / $vm->ord. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $vm->numero }}</title>
    @include('documentos.pdf.estilos', ['compacto' => true])
</head>
<body>

@include('documentos.pdf.partials.encabezado')

@foreach ($vm->clausulas as $clave)
    @include('documentos.pdf.partials.clausula-'.$clave)
@endforeach

@if ($vm->clausulasAdicionales)
    <div class="clausula">
        <div class="clausula-titulo">CLÁUSULAS ADICIONALES</div>
        <p class="parrafo">{{ mb_strtoupper($vm->clausulasAdicionales) }}</p>
    </div>
@endif

@include('documentos.pdf.partials.firmas')

</body>
</html>
