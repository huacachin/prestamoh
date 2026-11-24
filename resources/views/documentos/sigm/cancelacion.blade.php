{{-- Resumen de CANCELACIÓN SIGM (30/10/2026, Agregado 03.4 del Área Legal). Una plantilla para los 8
     modelos de "b. SIGM. Proceso. Cancelación de GM" (24/11/2026, Trello #144): deudor, deudora, deudores
     y empresa (que dice EL DEUDOR {razón social}), cada uno con uno o con dos vehículos. Con dos nombra las
     dos placas y sigue citando un solo formulario y un solo folio. Tal cual los modelos: "EL VEHICULO" sin
     tilde con uno y "LOS VEHÍCULOS" con tilde con dos. Es el texto para "Descripción de otros" del
     formulario de cancelación del SIGM; los datos salen de la constitución registrada (formulario, folio y
     fecha) y del contrato (placas y nombres). Texto plano. --}}
SE CANCELA LA CONSTITUCIÓN DE GARANTIA MOBILIARIA SOBRE {!! count($placas) === 2 ? 'LOS VEHÍCULOS DE PLACAS N° '.$placas[0].' Y N° '.$placas[1] : 'EL VEHICULO DE PLACA N° '.$placas[0] !!} INSCRITA MEDIANTE FORMULARIO N° {!! $formulario !!} Y FOLIO CAUSAL ELECTRÓNICO N° {!! $folio !!} DE FECHA {!! $fecha !!}, POR EL SUPUESTO DEL CUMPLIMIENTO DE LA OBLIGACIÓN, ES DECIR, {!! $sujeto !!} {!! $nombres !!} {!! $plural ? 'HAN' : 'HA' !!} CUMPLIDO CON EFECTUAR EL PAGO COMPLETO DEL MONTO DE LA GARANTÍA.
