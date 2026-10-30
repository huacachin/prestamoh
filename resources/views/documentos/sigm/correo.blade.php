{{-- Correo previo a la constitución SIGM (30/10/2026, Agregado 03.1 del Área Legal): los 9 modelos de
     "Correos. Previo de constitucion SIGM" en una sola plantilla, porque solo cambian el trato
     (señor/señora/señores), singular o plural, constitución o pre-constitución (bien futuro) y la cláusula
     (Décimo Tercera con GPS, Décimo Segunda sin GPS). Corregido de los modelos: "Estimada señores" y el
     "usted… me faculta" de a.3.1 con dos deudores. Texto plano (GeneradorSigm::correo lo arma). --}}
{!! $saludo !!} {!! $nombres !!}:

Por medio del presente, en mi calidad de usuaria autorizada del Sistema Informativo de Garantías Mobiliarias (SIGM) del acreedor Guilmer Nicefaro Huacachin Paucar, cumplo con {!! $plural ? 'informarles' : 'informarle' !!} que se procederá a realizar la {!! $futuro ? 'pre-constitución' : 'constitución' !!} de la garantía mobiliaria correspondiente al Contrato de Crédito Vehicular con {!! $futuro ? 'Pre-constitución' : 'Constitución' !!} de Garantía Mobiliaria en el Sistema Informativo de Garantías Mobiliarias – SIGM de fecha {!! $fecha !!}, suscrito entre ambas partes.

Dicha inscripción se efectuará conforme a lo dispuesto en el Decreto Legislativo N.º 1400 y a la autorización expresa otorgada por {!! $plural ? 'ustedes' : 'usted' !!} en la Cláusula {!! $gps ? 'Décimo Tercera' : 'Décimo Segunda' !!} del referido contrato, mediante la cual me {!! $plural ? 'facultan' : 'faculta' !!} a efectuar la constitución, modificación y, de ser el caso, la cancelación de la garantía mobiliaria en el SIGM.

El presente mensaje tiene carácter informativo, con la finalidad de dejar constancia de la ejecución del acto de constitución conforme a lo pactado en el contrato.

Sin otro particular, me despido.

Atentamente,
{!! $firma['nombre'] !!}
{!! $firma['cargo'] !!}
DNI N.º {!! $firma['dni'] !!}
Correo: {!! $firma['correo'] !!}
Cel.: {!! $firma['celular'] !!}
