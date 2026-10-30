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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Módulo SIGM de la pestaña Documentos (30/10/2026, Agregado 03 del Área Legal): correo previo que se
 * abre en Gmail, resumen de constitución, registro de la inscripción (formulario, folio y fecha) y
 * resumen de cancelación, todo desde el contrato emitido del crédito.
 */
class SigmDocumentosTest extends TestCase
{
    use RefreshDatabase;

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
    private function contratoCon(array $deudores, array $bienes, bool $gps = true, bool $custodia = false, bool $juridica = false): DocumentoCliente
    {
        DocumentoCliente::query()->delete();

        return DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $this->credit->id, 'tipo' => 'contrato',
            'modelo' => 'a3', 'version' => 1, 'estado' => 'emitido',
            'snapshot' => [
                'fecha' => '15/10/2026', 'gps' => $gps, 'custodia' => $custodia, 'conjunto' => ['juridica' => $juridica],
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

        $this->assertStringStartsWith('https://mail.google.com/mail/?', $correo['gmail']);
        parse_str(parse_url($correo['gmail'], PHP_URL_QUERY), $q);
        $this->assertSame(['cm', '1', 'legalhuacachin@gmail.com', 'rosa.p@example.com', $correo['asunto'], $correo['cuerpo']],
            [$q['view'], $q['fs'], $q['authuser'], $q['to'], $q['su'], $q['body']]);
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
        $this->assertSame('gps-futuro', $ctx['resumen']);
        $this->assertSame('Comunicación sobre la Pre-constitución de garantía mobiliaria en el SIGM – PLACA N° FUT444.', $correo['asunto']);
        $this->assertStringContainsString('realizar la pre-constitución de la garantía mobiliaria correspondiente al Contrato de Crédito Vehicular con Pre-constitución', $correo['cuerpo']);

        // Dos vehículos con GPS: resumen con el valor de cada uno; el correo y la cancelación no tienen modelo.
        $this->contratoCon([$rosa], [$this->bien('UNO555', valor: 12000), $this->bien('DOS666', valor: 18000)]);
        $ctx = $this->contexto();
        $this->assertSame('gps-2vehiculos', $ctx['resumen']);
        $this->assertSame(['S/ 12,000.00 (DOCE MIL CON 00/100 SOLES)', 'S/ 18,000.00 (DIECIOCHO MIL CON 00/100 SOLES)'], $ctx['valores']);
        $resumen = GeneradorSigm::resumen($ctx);
        $this->assertStringContainsString('S/ 12,000.00 (DOCE MIL CON 00/100 SOLES)', $resumen);
        $this->assertStringContainsString('S/ 18,000.00 (DIECIOCHO MIL CON 00/100 SOLES)', $resumen);
        $this->assertSame('No hay modelo para esta combinación de vehículos del contrato.', $ctx['sinCorreo']);
        $this->assertNotNull($ctx['sinCancelacion']);

        // Sin modelo del área (decisión de Antony: quedan fuera): empresa, custodia y dos vehículos sin GPS.
        $this->contratoCon([$rosa], [$this->bien('EMP777')], juridica: true);
        $this->assertSame(['No hay modelo para empresas.', 'No hay modelo para empresas.'], [$this->contexto()['sinCorreo'], $this->contexto()['sinResumen']]);
        $this->contratoCon([$rosa], [$this->bien('CUS888')], custodia: true);
        $this->assertSame('No hay modelo para contratos con custodia.', $this->contexto()['sinResumen']);
        $this->contratoCon([$rosa], [$this->bien('SN1999'), $this->bien('SN2000')], gps: false);
        $this->assertNull($this->contexto()['resumen']);
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
            ->assertSee('Primero registra la inscripción de la constitución')
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
        $c->assertSee('INSCRITA MEDIANTE FORMULARIO N° 2026-905021')->assertSee('Inscrita 27/10/2026');

        // Corregir: el mismo aviso, no uno nuevo.
        $c->set('sigmFecha', '2026-10-28')->call('guardarRegistroSigm')->assertHasNoErrors();
        $this->assertSame('2026-10-28', SigmAviso::sole()->fecha_presentacion->toDateString());
        $this->assertDatabaseHas('activity_log', ['description' => "Corrigió la inscripción SIGM del crédito #{$this->credit->id}: formulario 2026-905021, folio 20261027002239830, del 28/10/2026 (placa SIG111)"]);

        // Quitar el registro borra el aviso y la garantía que quedó vacía.
        $c->call('quitarRegistroSigm')->assertDispatched('successAlert')->assertSet('sigmFormulario', '');
        $this->assertSame([0, 0], [SigmAviso::count(), Garantia::count()]);
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
