<?php

namespace Database\Seeders;

use App\Models\LegalSetting;
use Illuminate\Database\Seeder;

/**
 * Constantes de negocio del Área Legal, extraídas de las plantillas Word
 * vigentes (carpeta "Proyecto. Sistema Area Legal") y de las plantillas de
 * cobranza de NotificationsModal. Editables después desde legal/settings.
 * Re-ejecutable: NO pisa valores ya editados (firstOrCreate por clave).
 */
class LegalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'clave' => 'acreedor',
                'etiqueta' => 'Acreedor (titular de las garantías)',
                'valor' => [
                    'nombre' => 'GUILMER NICEFARO HUACACHIN PAUCAR',
                    'dni' => '40463004',
                    'nacionalidad' => 'PERUANO',
                    'estado_civil' => 'CASADO BAJO RÉGIMEN DE SEPARACIÓN DE PATRIMONIOS INSCRITO EN LA PARTIDA REGISTRAL N° 13103733 DEL REGISTRO DE PERSONAS NATURALES DE LA OFICINA REGISTRAL DE LIMA',
                    'domicilio' => 'DPTO. SEC. B UCV 72 LOTE 51 ZONA E AA.HH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
                ],
            ],
            [
                'clave' => 'apoderada',
                'etiqueta' => 'Apoderada del acreedor',
                'valor' => [
                    'nombre' => 'LICET TAFUR COLLANTES',
                    'dni' => '45861856',
                    'estado_civil' => 'CASADA',
                    'domicilio' => 'DPTO. SEC. B UCV 72 LOTE 51 ZONA E AA.HH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
                    'partida_poder' => '15958665',
                ],
            ],
            [
                'clave' => 'usuaria_sigm',
                'etiqueta' => 'Usuaria/administradora de la cuenta SIGM',
                'valor' => [
                    'nombre' => 'ROSA LINDA TAFUR CUENCA',
                    'dni' => '72957633',
                    'domicilio' => 'UCV 158C, ZONA K, LOTE 36, AAHH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
                ],
            ],
            [
                'clave' => 'abogada',
                'etiqueta' => 'Abogada firmante del Área Legal',
                'valor' => [
                    'nombre' => 'Rosa Linda Tafur Cuenca',
                    'titulo' => 'Abog.',
                ],
            ],
            [
                'clave' => 'representantes_ejecucion',
                'etiqueta' => 'Representantes para la ejecución extrajudicial',
                'valor' => [
                    ['nombre' => 'RUBÉN VENTOCILLA OROSCO', 'dni' => '20904185', 'domicilio' => 'UCV 47B LOTE 49, ZONA C, HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
                    ['nombre' => 'DARIO RUBÉN QUIQUIA RIVADENEYRA', 'dni' => '41810748', 'domicilio' => 'UCV 27, LOTE 1, ZONA B, HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
                    ['nombre' => 'EMETERIO FÉLIX QUIQUIA REBADENAYRA', 'dni' => '10501983', 'domicilio' => 'UCV 27, LOTE 1, ZONA B, AAHH HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
                    ['nombre' => 'ELMER IDENCIO QUIQUIA RIVADENEYRA', 'dni' => '43072213', 'domicilio' => 'LOTE 29, UCV 174, ZONA N, A.H. HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
                    ['nombre' => 'LUIS ÁNGEL NICOLÁS LUNA', 'dni' => '43222400', 'domicilio' => 'ASENTAMIENTO HUMANO HUAYCÁN, UCV 162-B, ZONA K, LOTE 26, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
                    ['nombre' => 'HUGO HUAMÁN QUISPE', 'dni' => '07615328', 'domicilio' => 'UCV 172-B, LOTE 39, ZONA M, ASENTAMIENTO HUMANO HUAYCÁN, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA'],
                ],
            ],
            [
                'clave' => 'cuentas_acreedor',
                'etiqueta' => 'Cuentas bancarias del acreedor (desembolsos)',
                'valor' => [
                    ['banco' => 'BCP', 'numero' => '191-15272135-0-98', 'titular' => 'HUACACHIN PAUCAR GUILMER NICEFARO'],
                    ['banco' => 'Interbank', 'numero' => '488-3132361376', 'titular' => 'HUACACHIN PAUCAR GUILMER NICEFARO'],
                ],
            ],
            [
                'clave' => 'whatsapp_gps',
                'etiqueta' => 'WhatsApp del acreedor para credenciales GPS',
                'valor' => '+51 982 333 689',
            ],
            [
                'clave' => 'ciudad_firma',
                'etiqueta' => 'Ciudad de firma de los contratos',
                'valor' => 'LIMA',
            ],
            [
                'clave' => 'marca_documentos',
                'etiqueta' => 'Membrete de los documentos',
                'valor' => 'HUACACHIN - CRÉDITO VEHICULAR',
            ],
        ];

        foreach ($items as $it) {
            LegalSetting::firstOrCreate(
                ['clave' => $it['clave']],
                ['valor' => $it['valor'], 'etiqueta' => $it['etiqueta']]
            );
        }
    }
}
