{{-- CLÁUSULA: DECLARACIÓN SOBRE CORREO ELECTRÓNICO. Un párrafo por deudor,
     cada uno con su correo y su Genero individual ($d['g'], siempre singular:
     persona natural o jurídica). --}}
<div class="clausula">
    <div class="clausula-titulo">{{ $vm->ord->de('correo') }}: DECLARACIÓN SOBRE CORREO ELECTRÓNICO</div>
    @foreach ($vm->deudores as $d)
        <p class="parrafo">{{ $d['g']->deudor() }} {{ mb_strtoupper($d['nombre']) }} DECLARA QUE SU CORREO ELECTRÓNICO ES {{ mb_strtoupper($d['correo']) }}, EL CUAL CONSTITUYE UN MEDIO VÁLIDO DE COMUNICACIÓN ENTRE LAS PARTES PARA EFECTOS DE NOTIFICACIONES, COORDINACIÓN Y LA RECEPCIÓN DE INFORMACIÓN RELACIONADA CON LA PRESENTE GARANTÍA MOBILIARIA Y SU EVENTUAL INSCRIPCIÓN EN EL SISTEMA INFORMATIVO DE GARANTÍAS MOBILIARIAS (SIGM).</p>
    @endforeach
</div>
