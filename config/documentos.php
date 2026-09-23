<?php

/**
 * Constantes de los documentos del cliente (Anexo 1, Contrato, Anexo 2).
 * Datos verbatim de las plantillas Word vigentes del área. Si cambian
 * (nueva apoderada, cuenta nueva), se edita aquí y se despliega.
 */
return [

    'marca' => 'HUACACHIN - CRÉDITO VEHICULAR',

    'ciudad_firma' => 'LIMA',

    'acreedor' => [
        'nombre' => 'GUILMER NICEFARO HUACACHIN PAUCAR',
        'dni' => '40463004',
        'nacionalidad' => 'PERUANO',
        'estado_civil' => 'CASADO BAJO RÉGIMEN DE SEPARACIÓN DE PATRIMONIOS INSCRITO EN LA PARTIDA REGISTRAL N° 13103733 DEL REGISTRO DE PERSONAS NATURALES DE LA OFICINA REGISTRAL DE LIMA',
        'domicilio' => 'DPTO. SEC. B UCV 72 LOTE 51 ZONA E AA.HH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
    ],

    'apoderada' => [
        'nombre' => 'LICET TAFUR COLLANTES',
        'dni' => '45861856',
        'estado_civil' => 'CASADA',
        'domicilio' => 'DPTO. SEC. B UCV 72 LOTE 51 ZONA E AA.HH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
        'partida_poder' => '15958665',
    ],

    'usuaria_sigm' => [
        'nombre' => 'ROSA LINDA TAFUR CUENCA',
        'dni' => '72957633',
        'domicilio' => 'UCV 158C, ZONA K, LOTE 36, AAHH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
    ],

    'representantes_ejecucion' => [
        ['nombre' => 'RUBÉN VENTOCILLA OROSCO', 'dni' => '20904185', 'domicilio' => 'UCV 47B LOTE 49, ZONA C, HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
        ['nombre' => 'DARIO RUBÉN QUIQUIA RIVADENEYRA', 'dni' => '41810748', 'domicilio' => 'UCV 27, LOTE 1, ZONA B, HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
        ['nombre' => 'EMETERIO FÉLIX QUIQUIA REBADENAYRA', 'dni' => '10501983', 'domicilio' => 'UCV 27, LOTE 1, ZONA B, AAHH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
        ['nombre' => 'ELMER IDENCIO QUIQUIA RIVADENEYRA', 'dni' => '43072213', 'domicilio' => 'LOTE 29, UCV 174, ZONA N, A.H. HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
        ['nombre' => 'LUIS ÁNGEL NICOLÁS LUNA', 'dni' => '43222400', 'domicilio' => 'ASENTAMIENTO HUMANO HUAYCÁN, UCV 162-B, ZONA K, LOTE 26, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
        ['nombre' => 'HUGO HUAMÁN QUISPE', 'dni' => '07615328', 'domicilio' => 'UCV 172-B, LOTE 39, ZONA M, ASENTAMIENTO HUMANO HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
    ],

    'whatsapp_gps' => '+51 982 333 689',

    /*
     * Copia de IMPRESIÓN (22/09): el botón "Imprimir" sirve el PDF emitido
     * rasterizado con Ghostscript (una imagen por hoja) porque la
     * fotocopiadora tarda 15-20 s por hoja con las fuentes de dompdf.
     * Ver App\Services\Documentos\CopiaImpresion.
     */
    'impresion' => [
        'ghostscript' => env('GHOSTSCRIPT_BIN', 'gs'),
        'dpi' => 300,
        'timeout' => 90, // segundos por documento
        'sufijo' => '-impresion',
        // La fotocopiadora cobra el color: el contrato es texto negro; los
        // anexos llevan cabeceras de color y la foto del voucher.
        'color' => [
            'contrato' => false,
            'anexo1' => true,
            'anexo2' => true,
        ],
    ],

    /*
     * Pie del ANEXO 2 (15/09): los maestros del área legal lo llevan al pie
     * en las 15 plantillas, con las cuentas a las que el cliente paga. Si las
     * cuentas cambian, se cambia aquí y sale en todos los anexos nuevos.
     */
    'formas_pago' => 'Formas de pago: BCP Cuenta N° 191-15272135-0-98 | CCI 002-191-15272135098-59; '
        .'Yape N.º 981352577 Titular: Guilmer Nicefaro Huacachin Paucar',

];
