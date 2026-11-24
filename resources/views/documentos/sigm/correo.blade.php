{{-- Correo previo a la constitución SIGM. Una sola plantilla para los 27 modelos del área (24/11/2026,
     Trello #142: "1.1 GPS. Correos Previo de cons. SIGM", 21, y "1.2 S-N GPS. Correos Previo de const.
     SIGM", 6), porque solo cambian:
       · el trato: señor / señora / señores / "Señores {razón social}" (empresa), con su singular o plural
         y quién otorga la autorización (usted, ustedes, su representada);
       · los bienes: constitución (presentes), pre-constitución (futuros) o las dos (uno de cada uno), en
         el acto que se inscribe y en la constancia final;
       · el título del contrato que se cita: el que lleva el contrato (ContratoVm::varianteDelTitulo()).
         Va con los bienes, salvo en la empresa con bien futuro y bien presente, que lleva el título base;
       · la cláusula: Décimo Tercera con GPS, Décimo Segunda sin GPS.
     Corregido de los modelos, con el visto bueno del área (24/11/2026):
       · a.3.3 y a.3.4 saludan "Estimados {nombres}": va "Estimados señores", como el resto;
       · a.3.4 cita "…Pre-constitución de Garantía Mobiliaria sobre Bien Futuro…": el contrato no lleva
         "sobre Bien Futuro".
     Formato de los .docx (30/10, Antony: "adaptar al formato que está en el documento"): las mismas negritas,
     párrafos justificados y la firma centrada con el nombre en negrita. HTML como el que arma Gmail (un div por
     línea y un div vacío entre párrafos) para pegarlo en el cuerpo; GeneradorSigm saca de aquí el texto plano.
     Un div por línea de código: el texto plano se arma línea a línea. --}}
@php
    [$acto, $constancia] = match ($bienes) {
        'futuro' => ['<b>pre-constitución de la garantía mobiliaria</b>', 'pre-constitución'],
        'mixto' => ['pre-constitución de la garantía mobiliaria del bien futuro y la constitución de la garantía mobiliaria del bien presente', 'pre-constitución del bien futuro y de constitución del bien presente'],
        default => ['<b>constitución de la garantía mobiliaria</b>', 'constitución'],
    };
    $tituloContrato = [
        'futuro' => 'Pre-constitución de Garantía Mobiliaria',
        'mixto' => 'Constitución de Garantía Mobiliaria sobre Bien Futuro y Bien Presente',
    ][$titulo] ?? 'Constitución de Garantía Mobiliaria';
@endphp
<div style="text-align:justify">{{ $saludo }} <b>{{ $nombres }}</b>:</div>
<div><br></div>
<div style="text-align:justify">Por medio del presente, en mi calidad de <b>usuaria autorizada del Sistema Informativo de Garantías Mobiliarias (SIGM)</b> del acreedor <b>Guilmer Nicefaro Huacachin Paucar</b>, cumplo con {{ $plural ? 'informarles' : 'informarle' }} que se procederá a realizar la {!! $acto !!} correspondiente al <b>Contrato de Crédito Vehicular con {{ $tituloContrato }} en el Sistema Informativo de Garantías Mobiliarias – SIGM de fecha {{ $fecha }}</b>, suscrito entre ambas partes.</div>
<div><br></div>
<div style="text-align:justify">Dicha inscripción se efectuará conforme a lo dispuesto en el <b>Decreto Legislativo N.º 1400</b> y a la <b>autorización expresa</b> otorgada por {{ $otorgante }} en la <b>Cláusula {{ $gps ? 'Décimo Tercera' : 'Décimo Segunda' }}</b> del referido contrato, mediante la cual me {{ $plural ? 'facultan' : 'faculta' }} a efectuar la constitución, modificación y, de ser el caso, la cancelación de la garantía mobiliaria en el SIGM.</div>
<div><br></div>
<div style="text-align:justify">El presente mensaje tiene carácter <b>informativo</b>, con la finalidad de dejar constancia de la ejecución del acto de {{ $constancia }} conforme a lo pactado en el contrato.</div>
<div><br></div>
<div style="text-align:justify">Sin otro particular, me despido.</div>
<div><br></div>
<div style="text-align:justify">Atentamente,</div>
<div style="text-align:center"><b>{{ $firma['nombre'] }}</b></div>
<div style="text-align:center">{{ $firma['cargo'] }}</div>
<div style="text-align:center">DNI N.º {{ $firma['dni'] }}</div>
<div style="text-align:center">Correo: {{ $firma['correo'] }}</div>
<div style="text-align:center">Cel.: {{ $firma['celular'] }}</div>
