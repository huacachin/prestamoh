<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\SigmAviso;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Services\Documentos\GeneradorSigm;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Módulo SIGM de la pestaña Documentos (30/10/2026, Agregado 03 del Área Legal): correo previo que se
 * abre en Gmail, resumen de constitución, registro de la inscripción (formulario, folio y fecha) y
 * resumen de cancelación, todo desde el contrato emitido del crédito.
 */
class SigmDocumentosTest extends TestCase
{
    use RefreshDatabase;

    /** Los 27 modelos de correo del área, sacados de sus .docx con las negritas (24/11/2026, Trello #142). */
    private const DIR_CORREOS = __DIR__.'/../Fixtures/sigm/correos';

    /**
     * Lo que el correo corrige de su modelo, con el visto bueno del área (24/11/2026): el trato "Estimados
     * señores" que a.3.3 y a.3.4 dejan en "Estimados", y el título del contrato en a.3.4, que no lleva
     * "sobre Bien Futuro".
     */
    private const CORRECCIONES_DE_CORREO = [
        'a33' => ['Estimados <b>' => 'Estimados señores <b>'],
        'a34' => [
            'Estimados <b>' => 'Estimados señores <b>',
            'Pre-constitución de Garantía Mobiliaria sobre Bien Futuro en el' => 'Pre-constitución de Garantía Mobiliaria en el',
        ],
    ];

    /** Los 26 modelos de resumen del área, con la numeración real de sus listas (24/11/2026, Trello #143). */
    private const DIR_RESUMENES = __DIR__.'/../Fixtures/sigm/resumenes';

    /**
     * Lo que el resumen corrige de su modelo, con el visto bueno del área (24/11/2026): "OTORGANN" en los de
     * dos deudores, la línea de la obligación principal que a.3.2 no trae, el título del contrato en a.3.4
     * (no lleva "SOBRE BIEN FUTURO") y el plural que le faltaba al de dos deudores sin GPS con un vehículo.
     * Además, en ninguno va la placa en la línea del valor: se quita de todos en el propio test.
     */
    private const CORRECCIONES_DE_RESUMEN = [
        'a3' => ['OTORGANN' => 'OTORGAN'],
        'a32' => [
            'OTORGANN' => 'OTORGAN',
            "\nMONTO MÁXIMO DE LA GARANTÍA:" => "\nMONTO DE LA OBLIGACIÓN PRINCIPAL: [OBLIGACIÓN].\nMONTO MÁXIMO DE LA GARANTÍA:",
        ],
        'a33' => ['OTORGANN' => 'OTORGAN'],
        'a34' => [
            'OTORGANN' => 'OTORGAN',
            'PRE-CONSTITUCIÓN DE GARANTÍA MOBILIARIA SOBRE BIEN FUTURO EN EL' => 'PRE-CONSTITUCIÓN DE GARANTÍA MOBILIARIA EN EL',
        ],
        'a35' => ['OTORGANN' => 'OTORGAN'],
        'b3' => ['LOS DEUDORES OTORGA PODER' => 'LOS DEUDORES OTORGAN PODER', 'LOS DEUDORES ADEUDARA TOTAL' => 'LOS DEUDORES ADEUDARAN TOTAL'],
    ];

    /** Los 8 modelos de cancelación del área: deudor, deudora, deudores y empresa, con uno y con dos vehículos (24/11/2026, Trello #144). */
    private const DIR_CANCELACIONES = __DIR__.'/../Fixtures/sigm/cancelaciones';

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-30 10:00:00');

        $sede = Headquarter::create(['name' => 'Sede SIGM', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'legal-sigm', 'headquarter_id' => $sede->id]));

        $this->client = Client::create([
            'expediente' => '9920', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781900', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO',
            'email' => 'rosa.p@example.com',
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($n - 1),
                'importe_cuota' => 1250, 'importe_interes' => 50, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }
        $this->vehiculo = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'SIG111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-SG', 'nro_motor' => 'MOT-SG', 'valor' => 15000,
        ]);
    }

    /** Contrato REAL (modelo a.2: GPS, deudora) emitido con el wizard. */
    private function contratoReal(): DocumentoCliente
    {
        Storage::fake('public');

        return GeneradorContrato::generar($this->client, $this->credit, [$this->vehiculo->id], 'a2', [
            'fecha' => '28/08/2026', 'banco' => 'bcp', 'valor_bien' => 20000,
            'deudores' => [[
                'sexo' => 'F', 'nombre' => 'QUISPE MAMANI ROSA LINDA', 'dni' => '46781900',
                'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO',
                'domicilio' => 'CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'correo' => 'ROSA.P@EXAMPLE.COM',
            ]],
        ]);
    }

    /**
     * Contrato con el snapshot armado a mano: solo las claves que lee el módulo.
     *
     * @param  list<array<string, mixed>>  $deudores
     * @param  list<array<string, mixed>>  $bienes
     */
    private function contratoCon(array $deudores, array $bienes, bool $gps = true, bool $custodia = false, bool $juridica = false, int $redaccion = GeneradorContrato::REDACCION): DocumentoCliente
    {
        DocumentoCliente::query()->delete();

        return DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $this->credit->id, 'tipo' => 'contrato',
            'modelo' => 'a3', 'version' => 1, 'estado' => 'emitido',
            'snapshot' => [
                'fecha' => '15/10/2026', 'redaccion' => $redaccion, 'gps' => $gps, 'custodia' => $custodia, 'conjunto' => ['juridica' => $juridica],
                'montos' => ['valor_bien' => 30000, 'obligacion' => 5000, 'monto_maximo' => 6000],
                'deudores' => $deudores, 'bienes' => $bienes,
            ],
        ]);
    }

    private function persona(string $nombre, string $sexo, string $correo): array
    {
        return ['esJuridica' => false, 'nombre' => $nombre, 'sexo' => $sexo, 'correo' => $correo];
    }

    private function bien(string $placa, bool $futuro = false, float $valor = 15000): array
    {
        return ['placa' => $placa, 'esFuturo' => $futuro, 'valor' => $valor];
    }

    private function contexto(): array
    {
        return GeneradorSigm::contexto($this->client, $this->credit);
    }

    /**
     * Deja emitido el contrato de un modelo del área, que se reconoce por el nombre de su archivo: a = con
     * GPS, b = sin GPS; después 1 deudor, 2 deudora, 3 dos deudores, 4 empresa; y al final 2 = dos bienes
     * presentes, 3 = dos futuros, 4 = un futuro, 5 = futuro y presente (sin cifra, un bien presente; a41 es
     * la empresa con depósito al gerente, también un presente).
     *
     * @return list<array<string, mixed>> los deudores del contrato
     */
    private function contratoDelModelo(string $modelo): array
    {
        $this->assertSame(1, preg_match('/^([ab])([1-4])([1-5]?)$/', $modelo, $m), "Nombre de modelo inesperado: {$modelo}");
        [, $garantia, $persona, $variante] = $m;

        $rosa = $this->persona('QUISPE MAMANI ROSA LINDA', 'F', 'rosa.p@example.com');
        $juan = $this->persona('TAFUR CUENCA JUAN', 'M', 'juan.t@example.com');
        $empresa = ['esJuridica' => true, 'nombre' => 'INVERSIONES HUACACHIN SAC', 'sexo' => 'F', 'correo' => 'ihuacachin@gmail.com'];
        $deudores = ['1' => [$juan], '2' => [$rosa], '3' => [$rosa, $juan], '4' => [$empresa]][$persona];
        $uno = fn (bool $futuro) => $this->bien('UNO111', $futuro, 12000);
        $dos = fn (bool $futuro) => $this->bien('DOS222', $futuro, 18000);

        $this->contratoCon($deudores, match ($persona === '4' && $variante === '1' ? '' : $variante) {
            '2' => [$uno(false), $dos(false)],
            '3' => [$uno(true), $dos(true)],
            '4' => [$uno(true)],
            '5' => [$uno(true), $dos(false)],
            default => [$uno(false)],
        }, gps: $garantia === 'a', juridica: $persona === '4');

        return $deudores;
    }

    public function test_el_correo_sale_del_contrato_y_abre_gmail_con_todo_puesto(): void
    {
        $this->contratoReal();

        $correo = GeneradorSigm::correo($this->contexto());

        $this->assertSame(['rosa.p@example.com'], $correo['para']);
        $this->assertSame('Comunicación sobre la constitución de garantía mobiliaria en el SIGM – PLACA N° SIG111.', $correo['asunto']);
        $this->assertStringStartsWith('Estimada señora QUISPE MAMANI ROSA LINDA:', $correo['cuerpo']);
        $this->assertStringContainsString('cumplo con informarle que se procederá a realizar la constitución de la garantía mobiliaria', $correo['cuerpo']);
        $this->assertStringContainsString('SIGM de fecha 28 DE AGOSTO DEL 2026, suscrito entre ambas partes.', $correo['cuerpo']);
        $this->assertStringContainsString('otorgada por usted en la Cláusula Décimo Tercera del referido contrato, mediante la cual me faculta', $correo['cuerpo']);
        $this->assertStringEndsWith("Correo: legalhuacachin@gmail.com\nCel.: 969353280", $correo['cuerpo']);

        // El formato del modelo (.docx): las mismas negritas, párrafos justificados y la firma centrada.
        foreach ([
            '<div style="text-align:justify">Estimada señora <b>QUISPE MAMANI ROSA LINDA</b>:</div>',
            '<b>usuaria autorizada del Sistema Informativo de Garantías Mobiliarias (SIGM)</b> del acreedor <b>Guilmer Nicefaro Huacachin Paucar</b>',
            '<b>constitución de la garantía mobiliaria</b>',
            '<b>Contrato de Crédito Vehicular con Constitución de Garantía Mobiliaria en el Sistema Informativo de Garantías Mobiliarias – SIGM de fecha 28 DE AGOSTO DEL 2026</b>, suscrito',
            '<b>Decreto Legislativo N.º 1400</b> y a la <b>autorización expresa</b>',
            '<b>Cláusula Décimo Tercera</b>',
            'carácter <b>informativo</b>,',
            '<div style="text-align:center"><b>Rosa Linda Tafur Cuenca</b></div>',
            '<div style="text-align:center">Cel.: 969353280</div>',
        ] as $trozo) {
            $this->assertStringContainsString($trozo, $correo['html']);
        }

        // Gmail: sin cuerpo (se pega con formato) y, de respaldo, con el texto plano.
        parse_str(parse_url($correo['gmailSinCuerpo'], PHP_URL_QUERY), $q);
        $this->assertSame(['view' => 'cm', 'fs' => '1', 'authuser' => 'legalhuacachin@gmail.com', 'to' => 'rosa.p@example.com', 'su' => $correo['asunto']], $q);
        $this->assertStringStartsWith('https://mail.google.com/mail/?', $correo['gmail']);
        parse_str(parse_url($correo['gmail'], PHP_URL_QUERY), $q);
        $this->assertSame([$correo['asunto'], $correo['cuerpo']], [$q['su'], $q['body']]);
    }

    public function test_el_resumen_de_constitucion_lleva_la_fecha_los_montos_y_la_numeracion_del_modelo(): void
    {
        $this->contratoReal();

        $resumen = GeneradorSigm::resumen($this->contexto());

        $this->assertStringStartsWith('MEDIANTE LA PRESENTE, AMBAS PARTES ACUERDAN', $resumen);
        $this->assertStringContainsString('SIGM, DE FECHA 28 DE AGOSTO DEL 2026, ADJUNTANDO CON EL ANEXO 1', $resumen);
        $this->assertStringContainsString('QUINTO: VALOR DEL BIEN AFECTADO: S/ 20,000.00 (VEINTE MIL CON 00/100 SOLES).', $resumen);
        $this->assertStringContainsString('MONTO DE LA OBLIGACIÓN PRINCIPAL: S/ 5,000.00 (CINCO MIL CON 00/100 SOLES).', $resumen);
        foreach (['8.1 PARA PROCEDER CON LA VENTA EXTRAJUDICIAL, LA DEUDORA', '8.5 AMBAS PARTES', '8.7 EL ACREEDOR Y LA DEUDORA', '9.1 LA DEUDORA DECLARA', '9.4 EN CASO LA DEUDORA'] as $numeral) {
            $this->assertStringContainsString($numeral, $resumen);
        }
        $this->assertStringNotContainsString('SIG111', $resumen, 'en ningún resumen va la placa');
        $this->assertStringNotContainsString('{', $resumen);
    }

    public function test_el_modelo_cambia_segun_deudores_gps_y_vehiculos(): void
    {
        $rosa = $this->persona('QUISPE MAMANI ROSA LINDA', 'F', 'ROSA.P@EXAMPLE.COM');
        $juan = $this->persona('TAFUR CUENCA JUAN', 'M', 'Juan.T@Example.com');

        // Dos deudores: plural y los dos correos.
        $this->contratoCon([$rosa, $juan], [$this->bien('DOS222')]);
        $ctx = $this->contexto();
        $correo = GeneradorSigm::correo($ctx);
        $this->assertSame(['deudores', 'gps'], [$ctx['persona'], $ctx['resumen']]);
        $this->assertSame(['rosa.p@example.com', 'juan.t@example.com'], $correo['para']);
        $this->assertStringStartsWith('Estimados señores QUISPE MAMANI ROSA LINDA y TAFUR CUENCA JUAN:', $correo['cuerpo']);
        $this->assertStringContainsString('Estimados señores <b>QUISPE MAMANI ROSA LINDA y TAFUR CUENCA JUAN</b>:', $correo['html']);
        $this->assertStringContainsString('cumplo con informarles', $correo['cuerpo']);
        $this->assertStringContainsString('otorgada por ustedes en la Cláusula Décimo Tercera del referido contrato, mediante la cual me facultan', $correo['cuerpo']);
        $this->assertStringContainsString('LOS DEUDORES', GeneradorSigm::resumen($ctx));

        // Sin GPS: cláusula Décimo Segunda y resumen "sn".
        $this->contratoCon([$juan], [$this->bien('SNG333')], gps: false);
        $ctx = $this->contexto();
        $this->assertSame(['deudor', 'sn'], [$ctx['persona'], $ctx['resumen']]);
        $this->assertStringContainsString('Estimado señor TAFUR CUENCA JUAN:', GeneradorSigm::correo($ctx)['cuerpo']);
        $this->assertStringContainsString('Cláusula Décimo Segunda', GeneradorSigm::correo($ctx)['cuerpo']);

        // Bien futuro: pre-constitución.
        $this->contratoCon([$rosa], [$this->bien('FUT444', futuro: true)]);
        $ctx = $this->contexto();
        $correo = GeneradorSigm::correo($ctx);
        $this->assertSame('Comunicación sobre la Pre-constitución de garantía mobiliaria en el SIGM – PLACA N° FUT444.', $correo['asunto']);
        $this->assertStringContainsString('realizar la pre-constitución de la garantía mobiliaria correspondiente al Contrato de Crédito Vehicular con Pre-constitución', $correo['cuerpo']);

        // Dos vehículos con GPS: resumen con el valor de cada uno y correo con las dos placas.
        $this->contratoCon([$rosa], [$this->bien('UNO555', valor: 12000), $this->bien('DOS666', valor: 18000)]);
        $ctx = $this->contexto();
        $this->assertSame(['S/ 12,000.00 (DOCE MIL CON 00/100 SOLES)', 'S/ 18,000.00 (DIECIOCHO MIL CON 00/100 SOLES)'], $ctx['valores']);
        $resumen = GeneradorSigm::resumen($ctx);
        $this->assertStringContainsString('S/ 12,000.00 (DOCE MIL CON 00/100 SOLES)', $resumen);
        $this->assertStringContainsString('S/ 18,000.00 (DIECIOCHO MIL CON 00/100 SOLES)', $resumen);
        $this->assertSame('Comunicación sobre la constitución de garantía mobiliaria en el SIGM – PLACAS N° UNO555 y N° DOS666.', GeneradorSigm::correo($ctx)['asunto']);

        // Dos vehículos sin GPS: su propio resumen, con el valor de cada uno y sin la placa.
        $this->contratoCon([$rosa], [$this->bien('SN1999', valor: 12000), $this->bien('SN2000', valor: 18000)], gps: false);
        $ctx = $this->contexto();
        $this->assertSame('sn-2vehiculos', $ctx['resumen']);
        $this->assertStringContainsString('QUINTO: VALOR DEL VEHÍCULO 1: S/ 12,000.00 (DOCE MIL CON 00/100 SOLES).', GeneradorSigm::resumen($ctx));
    }

    /** 24/11/2026 (Trello #142): cada modelo de correo del área, palabra por palabra y con sus negritas. */
    public function test_el_correo_de_cada_contrato_sale_como_su_modelo_del_area(): void
    {
        $modelos = glob(self::DIR_CORREOS.'/*.txt');
        $this->assertCount(27, $modelos);

        foreach ($modelos as $ruta) {
            $modelo = basename($ruta, '.txt');
            $deudores = $this->contratoDelModelo($modelo);

            $correo = GeneradorSigm::correo($this->contexto());

            $esperado = strtr(trim((string) file_get_contents($ruta)), [
                '[PLACA]' => 'UNO111', '[PLACA 1]' => 'UNO111', '[PLACA 2]' => 'DOS222',
                '[PLACA DEL BIEN FUTURO]' => 'UNO111', '[PLACA DEL BIEN PRESENTE]' => 'DOS222',
                '[NOMBRES Y APELLIDOS]' => $deudores[0]['nombre'],
                '[NOMBRES Y APELLIDOS 1]' => 'QUISPE MAMANI ROSA LINDA', '[NOMBRES Y APELLIDOS 2]' => 'TAFUR CUENCA JUAN',
                '[RAZÓN SOCIAL]' => 'INVERSIONES HUACACHIN SAC',
                '[FECHA DEL CONTRATO]' => '15 DE OCTUBRE DEL 2026',
            ] + (self::CORRECCIONES_DE_CORREO[$modelo] ?? []));
            preg_match_all('#<div[^>]*>(.*?)</div>#', $correo['html'], $lineas);
            $generado = implode("\n", ['Asunto: '.$correo['asunto'], ...array_diff($lineas[1], ['<br>'])]);

            $this->assertSame($esperado, $generado, "El correo del modelo {$modelo} no sale como su modelo del área.");
        }
    }

    /**
     * 24/11/2026 (Trello #143): cada modelo de resumen del área, palabra por palabra y con su numeración. En
     * los modelos los datos del contrato van entre corchetes: fecha, valor de cada vehículo, obligación y monto
     * máximo (los tres de un vehículo sin GPS traían datos de ejemplo en su lugar). Los de GPS traían además
     * "PLACA [PLACA n] – " delante del valor: el área pidió que ningún resumen lleve la placa.
     */
    public function test_el_resumen_de_cada_contrato_sale_como_su_modelo_del_area(): void
    {
        $modelos = glob(self::DIR_RESUMENES.'/*.txt');
        $this->assertCount(26, $modelos);

        foreach ($modelos as $ruta) {
            $modelo = basename($ruta, '.txt');
            $this->contratoDelModelo($modelo);
            $ctx = $this->contexto();

            $sinPlaca = preg_replace('/PLACA \[PLACA [12]\] – /u', '', trim((string) file_get_contents($ruta)));
            $esperado = strtr(strtr($sinPlaca, self::CORRECCIONES_DE_RESUMEN[$modelo] ?? []), [
                '[FECHA DEL CONTRATO]' => '15 DE OCTUBRE DEL 2026',
                '[VALOR 1]' => count($ctx['placas']) === 1 ? 'S/ 30,000.00 (TREINTA MIL CON 00/100 SOLES)' : 'S/ 12,000.00 (DOCE MIL CON 00/100 SOLES)',
                '[VALOR 2]' => 'S/ 18,000.00 (DIECIOCHO MIL CON 00/100 SOLES)',
                '[OBLIGACIÓN]' => 'S/ 5,000.00 (CINCO MIL CON 00/100 SOLES)',
                '[MONTO MÁXIMO]' => 'S/ 6,000.00 (SEIS MIL CON 00/100 SOLES)',
            ]);

            $this->assertSame($esperado, GeneradorSigm::resumen($ctx), "El resumen del modelo {$modelo} no sale como su modelo del área.");
        }
    }

    /**
     * 24/11/2026 (Trello #142, #143 y #144): lo que sigue sin modelo avisa en vez de generar. La cancelación
     * solo falta con más de dos vehículos; custodia la tiene, pero no correo ni resumen (el área los debe).
     */
    public function test_el_correo_el_resumen_y_la_cancelacion_avisan_cuando_el_contrato_no_tiene_modelo(): void
    {
        $rosa = $this->persona('QUISPE MAMANI ROSA LINDA', 'F', 'rosa.p@example.com');
        $empresa = ['esJuridica' => true, 'nombre' => 'INVERSIONES HUACACHIN SAC', 'sexo' => 'F', 'correo' => 'ihuacachin@gmail.com'];
        $combinacion = 'No hay modelo para esta combinación de vehículos del contrato.';

        $avisos = fn () => array_values(array_intersect_key($this->contexto(), ['sinCorreo' => 1, 'sinResumen' => 1, 'sinCancelacion' => 1]));

        $this->contratoCon([$rosa], [$this->bien('CUS888')], custodia: true);
        $this->assertSame(['No hay modelo para contratos con custodia.', 'No hay modelo para contratos con custodia.', null], $avisos());

        $this->contratoCon([$rosa], [$this->bien('SNF111', futuro: true)], gps: false);
        $this->assertSame([$combinacion, $combinacion, null], $avisos());

        $this->contratoCon([$rosa], [$this->bien('TRE111'), $this->bien('TRE222'), $this->bien('TRE333')]);
        $this->assertSame([$combinacion, $combinacion, $combinacion], $avisos());

        $this->contratoCon([$empresa], [$this->bien('EMP333')], gps: false, juridica: true);
        $ctx = $this->contexto();
        $this->assertSame([$combinacion, $combinacion, null], $avisos());
        $this->expectExceptionObject(new InvalidArgumentException($combinacion));
        GeneradorSigm::resumen($ctx);
    }

    /**
     * 24/11/2026 (Trello #144): cada modelo de cancelación del área, palabra por palabra. Valen para todo
     * contrato de uno o dos vehículos: con o sin GPS, con bien futuro y con custodia (confirmado por el área).
     */
    public function test_la_cancelacion_de_cada_contrato_sale_como_su_modelo_del_area(): void
    {
        $modelos = glob(self::DIR_CANCELACIONES.'/*.txt');
        $this->assertCount(8, $modelos);

        $constitucion = new SigmAviso(['nro_formulario' => '2026-905021', 'folio' => '20261027002239830', 'fecha_presentacion' => '2026-10-27']);
        $rosa = $this->persona('QUISPE MAMANI ROSA LINDA', 'F', 'rosa.p@example.com');
        $juan = $this->persona('TAFUR CUENCA JUAN', 'M', 'juan.t@example.com');
        $empresa = ['esJuridica' => true, 'nombre' => 'INVERSIONES HUACACHIN SAC', 'sexo' => 'F', 'correo' => 'ihuacachin@gmail.com'];
        $deudoresDe = ['deudor' => [$juan], 'deudora' => [$rosa], 'deudores' => [$rosa, $juan], 'empresa' => [$empresa]];

        foreach ($modelos as $ruta) {
            [$persona, $vehiculos] = explode('-', basename($ruta, '.txt'));
            $esperado = strtr(trim((string) file_get_contents($ruta)), [
                '[PLACA]' => 'UNO111', '[PLACA 1]' => 'UNO111', '[PLACA 2]' => 'DOS222',
                '[NÚMERO DE FORMULARIO]' => '2026-905021', '[FOLIO CAUSAL ELECTRÓNICO]' => '20261027002239830',
                '[FECHA DD/MM/AAAA]' => '27/10/2026',
                '[NOMBRE DEL DEUDOR]' => 'TAFUR CUENCA JUAN', '[NOMBRE DE LA DEUDORA]' => 'QUISPE MAMANI ROSA LINDA',
                '[NOMBRE DEL PRIMER DEUDOR]' => 'QUISPE MAMANI ROSA LINDA', '[NOMBRE DEL SEGUNDO DEUDOR]' => 'TAFUR CUENCA JUAN',
                '[RAZÓN SOCIAL]' => 'INVERSIONES HUACACHIN SAC',
            ]);

            foreach (['con GPS' => [true, false, false], 'sin GPS' => [false, false, false], 'bien futuro' => [true, true, false], 'custodia' => [true, false, true]] as $caso => [$gps, $futuro, $custodia]) {
                $bienes = array_slice([$this->bien('UNO111', $futuro), $this->bien('DOS222')], 0, (int) $vehiculos);
                $this->contratoCon($deudoresDe[$persona], $bienes, gps: $gps, custodia: $custodia, juridica: $persona === 'empresa');

                $this->assertSame($esperado, GeneradorSigm::cancelacion($this->contexto(), $constitucion), "La cancelación de {$persona} con {$vehiculos} vehículo(s), {$caso}, no sale como su modelo del área.");
            }
        }
    }

    /**
     * 24/11/2026 (área legal: "sobre la empresa mantenlo con el título base"): el correo y el resumen citan
     * el título con que se emitió el contrato. El de la empresa con bien futuro y bien presente lleva el base
     * (sus modelos a.4.5, arriba); uno emitido antes de ese día se firmó con el del mixto y así se sigue citando.
     */
    public function test_la_empresa_con_bien_futuro_y_presente_emitida_antes_se_cita_con_el_titulo_con_que_se_firmo(): void
    {
        $empresa = ['esJuridica' => true, 'nombre' => 'INVERSIONES HUACACHIN SAC', 'sexo' => 'F', 'correo' => 'ihuacachin@gmail.com'];
        $this->contratoCon([$empresa], [$this->bien('UNO111', futuro: true), $this->bien('DOS222')], juridica: true, redaccion: 2);
        $ctx = $this->contexto();

        $this->assertSame(['mixto', 'mixto'], [$ctx['bienes'], $ctx['titulo']]);
        $this->assertStringContainsString('Contrato de Crédito Vehicular con Constitución de Garantía Mobiliaria sobre Bien Futuro y Bien Presente en el', GeneradorSigm::correo($ctx)['cuerpo']);
        $this->assertSame(2, substr_count(GeneradorSigm::resumen($ctx), 'CON CONSTITUCIÓN DE GARANTÍA MOBILIARIA SOBRE BIEN FUTURO Y BIEN PRESENTE EN EL SISTEMA'));
    }

    /** 13/11/2026 (Trello #139): modelos a.4 (correo), 1.7 (resumen) y 1.4 (cancelación) de la empresa deudora. */
    public function test_la_empresa_deudora_tiene_correo_resumen_y_cancelacion(): void
    {
        $empresa = ['esJuridica' => true, 'nombre' => 'INVERSIONES HUACACHIN SAC', 'sexo' => 'F', 'correo' => 'IHUACACHIN@GMAIL.COM'];
        $this->contratoCon([$empresa], [$this->bien('F7A197')], juridica: true);
        $ctx = $this->contexto();
        $this->assertSame(['empresa', 'gps', null, null, null], [$ctx['persona'], $ctx['resumen'], $ctx['sinCorreo'], $ctx['sinResumen'], $ctx['sinCancelacion']]);

        $correo = GeneradorSigm::correo($ctx);
        $this->assertSame(['ihuacachin@gmail.com'], $correo['para']);
        $this->assertSame('Comunicación sobre la constitución de garantía mobiliaria en el SIGM – PLACA N° F7A197.', $correo['asunto']);
        $this->assertStringStartsWith('Señores INVERSIONES HUACACHIN SAC:', $correo['cuerpo']);
        $this->assertStringContainsString('Señores <b>INVERSIONES HUACACHIN SAC</b>:', $correo['html']);
        $this->assertStringContainsString('cumplo con informarle que se procederá a realizar la constitución de la garantía mobiliaria', $correo['cuerpo']);
        $this->assertStringContainsString('otorgada por su representada en la Cláusula Décimo Tercera del referido contrato, mediante la cual me faculta', $correo['cuerpo']);

        // El resumen 1.7 es el del deudor con GPS palabra por palabra: EL DEUDOR, no LA DEUDORA.
        $resumen = GeneradorSigm::resumen($ctx);
        $this->assertSame(GeneradorSigm::resumen(['resumen' => 'gps', 'persona' => 'deudor'] + $ctx), $resumen);
        $this->assertStringContainsString('8.1 PARA PROCEDER CON LA VENTA EXTRAJUDICIAL, EL DEUDOR OTORGA', $resumen);
        $this->assertStringContainsString('SIGM, DE FECHA 15 DE OCTUBRE DEL 2026, ADJUNTANDO', $resumen);

        $constitucion = GeneradorSigm::registrar($this->client, $this->credit, '2026-619681', '20260602000995110', '2026-06-02');
        $this->assertSame('juridica', Garantia::where('credit_id', $this->credit->id)->value('tipo_persona'));
        $this->assertSame(
            'SE CANCELA LA CONSTITUCIÓN DE GARANTIA MOBILIARIA SOBRE EL VEHICULO DE PLACA N° F7A197 INSCRITA MEDIANTE FORMULARIO N° 2026-619681 Y FOLIO CAUSAL ELECTRÓNICO N° 20260602000995110 DE FECHA 02/06/2026, POR EL SUPUESTO DEL CUMPLIMIENTO DE LA OBLIGACIÓN, ES DECIR, EL DEUDOR INVERSIONES HUACACHIN SAC HA CUMPLIDO CON EFECTUAR EL PAGO COMPLETO DEL MONTO DE LA GARANTÍA.',
            GeneradorSigm::cancelacion($ctx, $constitucion)
        );

        // Con dos vehículos la empresa tiene los tres; sin GPS, solo la cancelación.
        $combinacion = 'No hay modelo para esta combinación de vehículos del contrato.';
        $this->contratoCon([$empresa], [$this->bien('EMP111'), $this->bien('EMP222')], juridica: true);
        $this->assertSame([null, null, null], [$this->contexto()['sinCorreo'], $this->contexto()['sinResumen'], $this->contexto()['sinCancelacion']]);
        $this->contratoCon([$empresa], [$this->bien('EMP333')], gps: false, juridica: true);
        $this->assertSame([$combinacion, $combinacion, null], [$this->contexto()['sinCorreo'], $this->contexto()['sinResumen'], $this->contexto()['sinCancelacion']]);
    }

    /** 13/11/2026 (Trello #137): "Fecha de constitución", "Registrar constitución" y, registrada, solo se toca el mismo día salvo el director. */
    public function test_la_constitucion_registrada_solo_se_modifica_el_mismo_dia_salvo_el_director(): void
    {
        config(['auditoria.eliminar_mismo_dia.activo' => true]);
        $this->contratoReal();

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalSigm')
            ->assertSee('Fecha de constitución *')
            ->assertSee('Registrar constitución')
            ->set('sigmFormulario', '2026-905021')->set('sigmFolio', '20261027002239830')->set('sigmFecha', '2026-10-30')
            ->call('guardarRegistroSigm')
            ->assertDispatched('successAlert', ['message' => 'Constitución SIGM registrada.'])
            ->assertSee('Guardar corrección')
            ->assertDontSee('Solo se puede modificar el mismo día');

        $this->travelTo('2026-10-31 09:00:00');
        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalSigm')
            ->assertSee('Solo se puede modificar el mismo día en que se registró. Pasado ese día, pídeselo al director.')
            ->assertDontSee('Guardar corrección')
            ->assertDontSee('Quitar registro')
            ->assertSeeHtml('wire:model="sigmFolio" placeholder="20261027002239830" disabled');
        $c->set('sigmFecha', '2026-10-29')->call('guardarRegistroSigm')->assertDispatched('errorAlert');
        $c->call('quitarRegistroSigm')->assertDispatched('errorAlert');
        $this->assertSame('2026-10-30', SigmAviso::sole()->fecha_presentacion->toDateString(), 'al día siguiente no cambia');

        $director = User::factory()->create(['username' => 'guilmer-sigm']);
        $director->syncRoles([Role::findOrCreate('director', 'web')]);
        Livewire::actingAs($director)->test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalSigm')
            ->assertSee('Guardar corrección')
            ->set('sigmFecha', '2026-10-29')->call('guardarRegistroSigm')
            ->assertDispatched('successAlert', ['message' => 'Constitución SIGM corregida.']);
        $this->assertSame('2026-10-29', SigmAviso::sole()->fecha_presentacion->toDateString(), 'el director sí corrige');
    }

    public function test_registra_la_inscripcion_y_arma_la_cancelacion_desde_la_ficha(): void
    {
        $this->contratoReal();

        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->assertSee('SIGM')
            ->call('abrirModalSigm')
            ->assertDispatched('sigm-modal-open')
            ->assertSet('sigmCreditoId', $this->credit->id)
            ->assertSee('Abrir en Gmail')
            ->assertSee('Copiar con formato')
            ->assertSee('Primero registra la inscripción de la constitución')
            ->assertSee('3. Registro de Constitución de GM')
            ->assertSee('NO CONSTITUIDO')
            ->set('sigmFormulario', '905021')
            ->set('sigmFolio', '2026-10-27')
            ->set('sigmFecha', '2026-11-02')
            ->call('guardarRegistroSigm')
            ->assertHasErrors(['sigmFormulario' => 'regex', 'sigmFolio' => 'regex', 'sigmFecha' => 'before_or_equal']);
        $this->assertSame(0, SigmAviso::count());

        $c->set('sigmFormulario', ' 2026-905021 ')
            ->set('sigmFolio', '2026 1027 0022 3983 0')
            ->set('sigmFecha', '2026-10-27')
            ->call('guardarRegistroSigm')
            ->assertHasNoErrors()
            ->assertDispatched('successAlert');

        $garantia = Garantia::sole();
        $aviso = SigmAviso::sole();
        $this->assertSame([$this->credit->id, $this->client->id, true, 'vigente', '2026-10-27'],
            [$garantia->credit_id, $garantia->client_id, (bool) $garantia->gps, $garantia->estado, $garantia->fecha_constitucion?->toDateString()]);
        $this->assertSame([$this->vehiculo->id], $garantia->vehiculos()->pluck('vehiculos.id')->all());
        $this->assertSame(['constitucion', '2026-905021', '20261027002239830', '2026-10-27', 'registrado'],
            [$aviso->tipo, $aviso->nro_formulario, $aviso->folio, $aviso->fecha_presentacion->toDateString(), $aviso->estado]);
        $this->assertNull($aviso->expense_id, 'sin tasa ni caja legal');
        $this->assertDatabaseHas('activity_log', ['description' => "Registró la inscripción SIGM del crédito #{$this->credit->id}: formulario 2026-905021, folio 20261027002239830, del 27/10/2026 (placa SIG111)"]);

        // 3.4: la cancelación cita la constitución registrada.
        $this->assertSame(
            'SE CANCELA LA CONSTITUCIÓN DE GARANTIA MOBILIARIA SOBRE EL VEHICULO DE PLACA N° SIG111 INSCRITA MEDIANTE FORMULARIO N° 2026-905021 Y FOLIO CAUSAL ELECTRÓNICO N° 20261027002239830 DE FECHA 27/10/2026, POR EL SUPUESTO DEL CUMPLIMIENTO DE LA OBLIGACIÓN, ES DECIR, LA DEUDORA QUISPE MAMANI ROSA LINDA HA CUMPLIDO CON EFECTUAR EL PAGO COMPLETO DEL MONTO DE LA GARANTÍA.',
            GeneradorSigm::cancelacion($this->contexto(), $aviso)
        );
        $c->assertSee('INSCRITA MEDIANTE FORMULARIO N° 2026-905021')->assertSee('CONSTITUIDO')
            ->assertDontSee('NO CONSTITUIDO')->assertSee('Inscrita el 27/10/2026');

        // Corregir: el mismo aviso, no uno nuevo.
        $c->set('sigmFecha', '2026-10-28')->call('guardarRegistroSigm')->assertHasNoErrors();
        $this->assertSame('2026-10-28', SigmAviso::sole()->fecha_presentacion->toDateString());
        $this->assertDatabaseHas('activity_log', ['description' => "Corrigió la inscripción SIGM del crédito #{$this->credit->id}: formulario 2026-905021, folio 20261027002239830, del 28/10/2026 (placa SIG111)"]);

        // Quitar el registro borra el aviso y la garantía que quedó vacía.
        $c->call('quitarRegistroSigm')->assertDispatched('successAlert')->assertSet('sigmFormulario', '');
        $this->assertSame([0, 0], [SigmAviso::count(), Garantia::count()]);
    }

    public function test_el_boton_sigm_va_en_la_tabla_uno_por_credito_con_contrato(): void
    {
        // 30/10 (Trello "2. Agregar botón SIGM en cada contrato"): la columna SIGM de la tabla, un botón por crédito
        // con contrato emitido, y Anular solo con el ícono.
        $this->contratoReal();
        $nuevo = fn (string $fecha) => Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => $fecha, 'importe' => 3000, 'cuotas' => 2, 'tipo_planilla' => 3,
            'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->credit->headquarter_id,
        ]);
        $conContrato = $nuevo('2026-10-01');
        DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $conContrato->id, 'tipo' => 'contrato', 'modelo' => 'a1', 'version' => 1, 'estado' => 'emitido',
            'snapshot' => ['fecha' => '01/10/2026', 'gps' => true, 'deudores' => [$this->persona('QUISPE MAMANI ROSA LINDA', 'F', 'ROSA.P@EXAMPLE.COM')],
                'bienes' => [$this->bien('SIG222')], 'montos' => ['valor_bien' => 9000, 'obligacion' => 3000, 'monto_maximo' => 3600]],
        ]);
        $sinContrato = $nuevo('2026-10-20');
        DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $sinContrato->id, 'tipo' => 'anexo1', 'version' => 1, 'estado' => 'emitido', 'snapshot' => [],
        ]);

        $c = Livewire::test(Documentos::class, ['id' => $this->client->id]);
        $html = $c->html();
        $this->assertSame(2, substr_count($html, 'wire:click="abrirModalSigm('), 'uno por crédito con contrato, ninguno arriba');
        $this->assertStringContainsString('wire:click="abrirModalSigm('.$this->credit->id.')"', $html);
        $this->assertStringContainsString('wire:click="abrirModalSigm('.$conContrato->id.')"', $html);
        $this->assertStringContainsString('title="Primero genera el contrato del crédito">—</span>', $html);
        $this->assertStringContainsString('title="Anular documento" aria-label="Anular documento">', $html);
        $this->assertStringNotContainsString('</i> Anular', $html);
        // 06/11 (Trello, tarjeta 5): la etiqueta de cada tipo lleva el color de su botón.
        $this->assertStringContainsString('class="badge doc-tipo-contrato"', $html);
        $this->assertStringContainsString('class="badge doc-tipo-anexo1"', $html);

        // Abre en el crédito de la fila; sin contrato (o sin crédito), en el más reciente con contrato.
        $c->call('abrirModalSigm', $this->credit->id)->assertSet('sigmCreditoId', $this->credit->id)->assertDispatched('sigm-modal-open')
            ->call('abrirModalSigm', $sinContrato->id)->assertSet('sigmCreditoId', $conContrato->id)
            ->call('abrirModalSigm')->assertSet('sigmCreditoId', $conContrato->id);
    }

    public function test_no_repite_folios_ni_pisa_ni_borra_una_garantia_del_area_legal(): void
    {
        $this->contratoReal();
        $otroCredito = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-07-01', 'importe' => 3000, 'cuotas' => 2,
            'tipo_planilla' => 3, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->credit->headquarter_id,
        ]);
        $otra = Garantia::create(['credit_id' => $otroCredito->id, 'client_id' => $this->client->id]);
        SigmAviso::create(['garantia_id' => $otra->id, 'tipo' => 'constitucion', 'nro_formulario' => '2026-111111', 'folio' => '20260701000000001', 'fecha_presentacion' => '2026-07-01']);

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalSigm')
            ->set('sigmFormulario', '2026-111111')
            ->set('sigmFolio', '20260701000000001')
            ->set('sigmFecha', '2026-10-27')
            ->call('guardarRegistroSigm')
            ->assertHasErrors(['sigmFormulario' => 'unique', 'sigmFolio' => 'unique']);

        // Una garantía que el Área Legal ya tenía para el crédito (con su contrato) se usa tal cual.
        $legal = Garantia::create(['credit_id' => $this->credit->id, 'client_id' => $this->client->id, 'monto_gravamen' => 9999, 'gps' => false]);
        DB::table('contratos')->insert(['garantia_id' => $legal->id, 'credit_id' => $this->credit->id, 'client_id' => $this->client->id, 'numero' => 'CT-LEGAL-1', 'created_at' => now(), 'updated_at' => now()]);

        GeneradorSigm::registrar($this->client, $this->credit, '2026-905021', '20261027002239830', '2026-10-27');
        $legal->refresh();
        $this->assertSame([9999.0, false, 'vigente'], [(float) $legal->monto_gravamen, (bool) $legal->gps, $legal->estado]);
        $this->assertSame($legal->id, SigmAviso::where('nro_formulario', '2026-905021')->value('garantia_id'));

        GeneradorSigm::quitarRegistro($this->credit);
        $this->assertModelExists($legal);
        $this->assertDatabaseCount('contratos', 1);
        $this->assertNull(GeneradorSigm::constitucionDe($this->credit));
    }
}
