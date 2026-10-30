{{-- Correo previo a la constitución SIGM (30/10/2026, Agregado 03.1 del Área Legal): los 9 modelos de
     "Correos. Previo de constitucion SIGM" en una sola plantilla, porque solo cambian el trato
     (señor/señora/señores), singular o plural, constitución o pre-constitución (bien futuro) y la cláusula
     (Décimo Tercera con GPS, Décimo Segunda sin GPS). Corregido de los modelos: "Estimada señores" y el
     "usted… me faculta" de a.3.1 con dos deudores.
     Formato de los .docx (30/10, Antony: "adaptar al formato que está en el documento"): las mismas negritas,
     párrafos justificados y la firma centrada con el nombre en negrita. HTML como el que arma Gmail (un div por
     línea y un div vacío entre párrafos) para pegarlo en el cuerpo; GeneradorSigm saca de aquí el texto plano.
     Un div por línea de código: el texto plano se arma línea a línea. --}}
<div style="text-align:justify">{{ $saludo }} <b>{{ $nombres }}</b>:</div>
<div><br></div>
<div style="text-align:justify">Por medio del presente, en mi calidad de <b>usuaria autorizada del Sistema Informativo de Garantías Mobiliarias (SIGM)</b> del acreedor <b>Guilmer Nicefaro Huacachin Paucar</b>, cumplo con {{ $plural ? 'informarles' : 'informarle' }} que se procederá a realizar la <b>{{ $futuro ? 'pre-constitución' : 'constitución' }} de la garantía mobiliaria</b> correspondiente al <b>Contrato de Crédito Vehicular con {{ $futuro ? 'Pre-constitución' : 'Constitución' }} de Garantía Mobiliaria en el Sistema Informativo de Garantías Mobiliarias – SIGM de fecha {{ $fecha }}</b>, suscrito entre ambas partes.</div>
<div><br></div>
<div style="text-align:justify">Dicha inscripción se efectuará conforme a lo dispuesto en el <b>Decreto Legislativo N.º 1400</b> y a la <b>autorización expresa</b> otorgada por {{ $plural ? 'ustedes' : 'usted' }} en la <b>Cláusula {{ $gps ? 'Décimo Tercera' : 'Décimo Segunda' }}</b> del referido contrato, mediante la cual me {{ $plural ? 'facultan' : 'faculta' }} a efectuar la constitución, modificación y, de ser el caso, la cancelación de la garantía mobiliaria en el SIGM.</div>
<div><br></div>
<div style="text-align:justify">El presente mensaje tiene carácter <b>informativo</b>, con la finalidad de dejar constancia de la ejecución del acto de constitución conforme a lo pactado en el contrato.</div>
<div><br></div>
<div style="text-align:justify">Sin otro particular, me despido.</div>
<div><br></div>
<div style="text-align:justify">Atentamente,</div>
<div style="text-align:center"><b>{{ $firma['nombre'] }}</b></div>
<div style="text-align:center">{{ $firma['cargo'] }}</div>
<div style="text-align:center">DNI N.º {{ $firma['dni'] }}</div>
<div style="text-align:center">Correo: {{ $firma['correo'] }}</div>
<div style="text-align:center">Cel.: {{ $firma['celular'] }}</div>
