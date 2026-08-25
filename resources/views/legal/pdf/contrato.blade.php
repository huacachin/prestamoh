{{-- Documento completo: contrato + Anexo 1 (cronograma) + Anexo 2 (constancia).
     Recibe: $vm (App\Services\Legal\ContratoViewModel) y $paraPdf (bool: true
     al renderizar con dompdf, false en la vista previa HTML del navegador —
     decide cómo se referencia la imagen del voucher). Las cláusulas activas y
     su numeración las gobierna $vm->clausulas / $vm->ord. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $vm->numero }}</title>
    @include('legal.pdf.estilos')
</head>
<body>

@if ($paraPdf ?? true)
    <div class="pie-pagina">{{ $vm->numero }} — Página <span class="num"></span></div>
@endif

@include('legal.pdf.partials.encabezado')

@foreach ($vm->clausulas as $clave)
    @include('legal.pdf.partials.clausula-'.$clave)
@endforeach

@if ($vm->clausulasAdicionales)
    <div class="clausula">
        <div class="clausula-titulo">CLÁUSULAS ADICIONALES</div>
        <p class="parrafo">{{ mb_strtoupper($vm->clausulasAdicionales) }}</p>
    </div>
@endif

@include('legal.pdf.partials.firmas')

<div class="salto"></div>
@include('legal.pdf.anexo1-cronograma')

@if ($vm->voucher)
    <div class="salto"></div>
    @include('legal.pdf.anexo2-constancia')
@endif

</body>
</html>
