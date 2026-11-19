<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\RenderDocumento;
use App\Support\Documentos\ModelosContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Trello #140 (18/11/2026): los 16 modelos que el área legal agregó a la
 * carpeta "1. GPS. Modelos de contrato SIGM".
 *
 *   - Deudor y deudora, depósito a tercero con dos bienes o con bienes futuros:
 *     a.1.2.1 – a.1.5.1 y a.2.2.1 – a.2.5.1. El documento del tercero pasa a ser
 *     "[DNI O CE]".
 *   - Empresa con dos bienes o con bienes futuros, a su cuenta o a la del
 *     gerente: a.4.2 – a.4.5.1. En bien futuro la que declara es LA
 *     CONSTITUYENTE ("SERÁ LA PROPIETARIA", "A NOMBRE DE LA MISMA") y quien
 *     tiene el vehículo sigue siendo EL DEUDOR.
 *
 * El texto completo de cada modelo lo guardan los goldens
 * (ContratoTramoETest); aquí van las reglas que un golden regenerado de una
 * pasada dejaría perder, y el camino por el asistente.
 */
class ContratoModelosNoviembreTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->sede = Headquarter::create(['name' => 'Sede Noviembre', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'modelos-nov', 'headquarter_id' => $this->sede->id]));
    }

    /**
     * Cliente con un crédito de cuatro cuotas y dos vehículos completos.
     *
     * @param  array<string, mixed>  $ficha  lo que distingue al cliente (empresa o persona, sexo)
     * @return array{0: Client, 1: Credit, 2: Vehiculo, 3: Vehiculo}
     */
    private function mundo(array $ficha): array
    {
        $client = Client::create($ficha + [
            'expediente' => '9930', 'nombre' => 'ROSA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781930', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'comerciante', 'estado_civil' => 'soltero',
            'email' => 'modelos.nov@example.com',
            'direccion' => 'AV. UNO 100', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-11-18',
            'importe' => 20000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 30,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);
        foreach ([1, 2, 3, 4] as $i) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse('2026-11-25')->addWeeks($i - 1),
                'importe_cuota' => 5000, 'importe_interes' => 1500, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }
        $v1 = Vehiculo::create([
            'client_id' => $client->id, 'placa' => 'NOV111', 'marca' => 'JINBEI',
            'modelo' => 'H2L', 'nro_serie' => 'SER-NOV1', 'nro_motor' => 'MOT-NOV1', 'valor' => 10000,
        ]);
        $v2 = Vehiculo::create([
            'client_id' => $client->id, 'placa' => 'NOV222', 'marca' => 'JOYLONG',
            'modelo' => 'HKL6600C', 'nro_serie' => 'SER-NOV2', 'nro_motor' => 'MOT-NOV2', 'valor' => 15000,
        ]);

        return [$client, $credit, $v1, $v2];
    }

    /** @return array{0: Client, 1: Credit, 2: Vehiculo, 3: Vehiculo} */
    private function mundoEmpresa(): array
    {
        return $this->mundo([
            'nombre' => 'S.A.C.', 'apellido_pat' => 'CORPORACION', 'apellido_mat' => 'NOVIEMBRE',
            'tipo_documento' => 'RUC', 'documento' => '20612519260', 'sexo' => 'M',
        ]);
    }

    /** El asistente de una empresa con lo que el guard le exige a ella y a su gerente. */
    private function wizardEmpresa(Client $empresa, Credit $credit, Vehiculo $vehiculo): Testable
    {
        return Livewire::test(Documentos::class, ['id' => $empresa->id])
            ->call('abrirModalContrato')
            ->set('contratoCreditoId', $credit->id)
            ->set('contratoVehiculos.0.vehiculo_id', $vehiculo->id)
            ->set('bancoDesembolso', 'interbank')
            ->set('empresa.partida', '15626194')
            ->set('empresa.oficina_registral', 'LIMA')
            ->set('gerente.nombre', 'JACK BLAS SULLCA')
            ->set('gerente.dni', '46964491')
            ->set('gerente.sexo', 'M')
            ->set('gerente.nacionalidad', 'PERUANO')
            ->set('gerente.ocupacion', 'INDEPENDIENTE')
            ->set('gerente.estado_civil', 'SOLTERO')
            ->set('gerente.domicilio', 'AV. UNO 100, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA');
    }

    /** Los tres datos del acta que la cláusula de declaración cita en un bien futuro. */
    private function conActa(Testable $comp, int $slot): Testable
    {
        return $comp
            ->set("contratoVehiculos.{$slot}.fecha_acta", '2026-11-10')
            ->set("contratoVehiculos.{$slot}.kardex_num", '373')
            ->set("contratoVehiculos.{$slot}.notario", 'PAÚL JHON HINOJOSA CARRILLO');
    }

    private function contratoDe(Client $client): DocumentoCliente
    {
        return DocumentoCliente::where('client_id', $client->id)->where('tipo', 'contrato')->firstOrFail();
    }

    /**
     * El contrato emitido, como texto corrido: sin las negritas con que
     * Enfasis envuelve los términos definidos (EL DEUDOR, LA CONSTITUYENTE…),
     * que partirían cualquier frase que se busque.
     */
    private function texto(DocumentoCliente $doc): string
    {
        $html = RenderDocumento::html($doc->snapshot, 'contrato', 'pdf');

        return (string) preg_replace('/\s+/u', ' ', strip_tags($html));
    }

    // ── 1 · El catálogo ───────────────────────────────────────────────────

    public function test_las_decisiones_resuelven_los_dieciseis_modelos_nuevos(): void
    {
        $esperado = [
            'a121' => [1, 'M', '2presentes', 'tercero'],
            'a131' => [1, 'M', '2futuros', 'tercero'],
            'a141' => [1, 'M', 'futuro', 'tercero'],
            'a151' => [1, 'M', 'futuro_presente', 'tercero'],
            'a221' => [1, 'F', '2presentes', 'tercero'],
            'a231' => [1, 'F', '2futuros', 'tercero'],
            'a241' => [1, 'F', 'futuro', 'tercero'],
            'a251' => [1, 'F', 'futuro_presente', 'tercero'],
            'a42' => ['empresa', null, '2presentes', 'propio'],
            'a421' => ['empresa', null, '2presentes', 'gerente'],
            'a43' => ['empresa', null, '2futuros', 'propio'],
            'a431' => ['empresa', null, '2futuros', 'gerente'],
            'a44' => ['empresa', null, 'futuro', 'propio'],
            'a441' => ['empresa', null, 'futuro', 'gerente'],
            'a45' => ['empresa', null, 'futuro_presente', 'propio'],
            'a451' => ['empresa', null, 'futuro_presente', 'gerente'],
        ];

        foreach ($esperado as $clave => [$personas, $sexo, $bienes, $destino]) {
            $this->assertSame($clave, ModelosContrato::resolver($personas, $sexo, 'gps', $bienes, $destino));
        }
    }

    // ── 2 · La empresa en el asistente ────────────────────────────────────

    public function test_la_empresa_agrega_el_segundo_vehiculo_y_marca_bienes_futuros(): void
    {
        [$empresa, $credit, $v1] = $this->mundoEmpresa();

        $comp = $this->wizardEmpresa($empresa, $credit, $v1);
        $this->assertSame('a4', $comp->get('modeloContrato'));
        // Hasta el 18/11 la empresa no veía ni el botón ni el interruptor.
        $comp->assertSee('Agregar segundo vehículo')
            ->assertSeeHtml('wire:model.live="contratoVehiculos.0.es_futuro"');

        $comp->call('agregarVehiculoContrato');
        $this->assertCount(2, $comp->get('contratoVehiculos'));
        $this->assertSame('a42', $comp->get('modeloContrato'), 'dos presentes = a.4.2');

        $comp->set('destinoContrato', 'gerente');
        $this->assertSame('a421', $comp->get('modeloContrato'));

        $comp->set('contratoVehiculos.0.es_futuro', true);
        $this->assertSame('a451', $comp->get('modeloContrato'), 'futuro y presente = a.4.5.1');

        $comp->set('contratoVehiculos.1.es_futuro', true);
        $this->assertSame('a431', $comp->get('modeloContrato'), 'dos futuros = a.4.3.1');

        $comp->call('quitarVehiculoContrato', 1);
        $this->assertSame('a441', $comp->get('modeloContrato'), 'un futuro = a.4.4.1');

        $comp->set('destinoContrato', 'propio');
        $this->assertSame('a44', $comp->get('modeloContrato'));
    }

    public function test_la_empresa_no_tiene_deposito_a_un_tercero(): void
    {
        [$empresa, $credit, $v1] = $this->mundoEmpresa();

        $comp = $this->wizardEmpresa($empresa, $credit, $v1)
            ->call('agregarVehiculoContrato')
            ->set('destinoContrato', 'tercero');

        $this->assertSame('propio', $comp->get('destinoContrato'), 'su variante es la cuenta del gerente');
        $this->assertSame('a42', $comp->get('modeloContrato'));
    }

    public function test_la_empresa_emite_el_contrato_de_dos_bienes_con_deposito_al_gerente(): void
    {
        [$empresa, $credit, $v1, $v2] = $this->mundoEmpresa();

        $this->wizardEmpresa($empresa, $credit, $v1)
            ->call('agregarVehiculoContrato')
            ->set('contratoVehiculos.1.vehiculo_id', $v2->id)
            ->set('destinoContrato', 'gerente')
            ->call('generarContrato')
            ->assertDispatched('successAlert')
            ->assertNotDispatched('errorAlert');

        $doc = $this->contratoDe($empresa);
        $this->assertSame('a421', $doc->modelo);
        $this->assertSame(['NOV111', 'NOV222'], array_column($doc->snapshot['bienes'], 'placa'));
        Storage::disk('public')->assertExists($doc->pdf_path);

        $html = $this->texto($doc);
        $this->assertStringContainsString('VALOR DEL BIEN AFECTADO 2:', $html);
        $this->assertStringContainsString('SER PROPIETARIA DE LOS BIENES OBJETOS DE GARANTÍA', $html);
        $this->assertStringContainsString('A LA CUENTA DE TITULARIDAD DEL GERENTE GENERAL', $html);
    }

    // ── 3 · Empresa con bien futuro: LA CONSTITUYENTE y EL DEUDOR ─────────

    public function test_la_empresa_con_bien_futuro_declara_en_femenino_y_posee_en_masculino(): void
    {
        [$empresa, $credit, $v1] = $this->mundoEmpresa();

        $comp = $this->wizardEmpresa($empresa, $credit, $v1)->set('contratoVehiculos.0.es_futuro', true);
        $this->conActa($comp, 0)->call('generarContrato')->assertDispatched('successAlert');

        $doc = $this->contratoDe($empresa);
        $this->assertSame('a44', $doc->modelo);

        $html = $this->texto($doc);
        $this->assertStringContainsString('CON PRE-CONSTITUCIÓN DE GARANTÍA MOBILIARIA', $html);
        $this->assertStringContainsString('Y QUE SERÁ LA PROPIETARIA DEL BIEN OBJETO DE GARANTÍA', $html);
        $this->assertStringContainsString('INSCRIPCIÓN REGISTRAL DEL BIEN A NOMBRE DE LA MISMA.', $html);
        $this->assertStringContainsString('EN PODER Y POSESIÓN FÍSICA DE EL DEUDOR', $html);
        $this->assertStringNotContainsString('EL PROPIETARIA', $html);
        $this->assertStringNotContainsString('LA DEUDORA', $html, 'la empresa nunca se redacta como LA DEUDORA');
    }

    public function test_la_empresa_con_bien_futuro_y_presente_lleva_el_titulo_del_mixto(): void
    {
        [$empresa, $credit, $v1, $v2] = $this->mundoEmpresa();

        $comp = $this->wizardEmpresa($empresa, $credit, $v1)
            ->call('agregarVehiculoContrato')
            ->set('contratoVehiculos.1.vehiculo_id', $v2->id)
            ->set('contratoVehiculos.0.es_futuro', true);
        $this->conActa($comp, 0)->call('generarContrato')->assertDispatched('successAlert');

        $doc = $this->contratoDe($empresa);
        $this->assertSame('a45', $doc->modelo);

        // Los Word a.4.5 / a.4.5.1 / a.1.5.1 dejaron el título base en la carátula, pero su
        // propia cláusula OCTAVO cita el del mixto: una sola fuente, como en a.1.5.
        $html = $this->texto($doc);
        $this->assertSame(2, substr_count($html, 'CON CONSTITUCIÓN DE GARANTÍA MOBILIARIA SOBRE BIEN FUTURO Y BIEN PRESENTE EN EL'));
        $this->assertStringContainsString('RESPECTO AL VEHICULO 1, QUE HA REALIZADO LA TRANSFERENCIA DEL VEHICULO CONFORME A LEY, Y QUE SERÁ LA PROPIETARIA', $html);
        $this->assertStringContainsString('POR OTRO LADO, LA CONSTITUYENTE DECLARA BAJO JURAMENTO RESPECTO AL VEHICULO 2 SER PROPIETARIA', $html);
    }

    // ── 3b · Dos vehículos: lo que el navegador manda de una sola vez ─────

    /**
     * 19/11/2026 (reporte del área con INVERSIONES CAMILA'S, a.4.3): "Falta el
     * kárdex del vehículo 1 | Falta el kárdex del vehículo 2" con los dos
     * kárdex escritos. Con DOS vehículos, Livewire 4 manda `contratoVehiculos`
     * ENTERO cuando en el mismo pedido cambian los dos (fecha y número son
     * wire:model.blur: viajan juntos con el siguiente clic), y el kárdex
     * completo "1678-2026" solo se armaba cuando llegaba campo por campo. Con
     * un vehículo nunca pasó.
     */
    public function test_el_kardex_de_los_dos_vehiculos_se_reconoce_cuando_llegan_en_un_mismo_pedido(): void
    {
        [$empresa, $credit, $v1, $v2] = $this->mundoEmpresa();

        $comp = $this->wizardEmpresa($empresa, $credit, $v1)
            ->call('agregarVehiculoContrato')
            ->set('contratoVehiculos.1.vehiculo_id', $v2->id)
            ->set('contratoVehiculos.0.es_futuro', true)
            ->set('contratoVehiculos.1.es_futuro', true);
        $this->assertSame('a43', $comp->get('modeloContrato'));

        $slots = $comp->get('contratoVehiculos');
        $slots[0] = ['fecha_acta' => '2026-11-18', 'kardex_num' => '1678', 'notario' => 'ROQUE ALBERTO DÍAZ DELGADO'] + $slots[0];
        $slots[1] = ['fecha_acta' => '2026-10-27', 'kardex_num' => '1345', 'notario' => 'PAÚL JHON HINOJOSA CARRILLO'] + $slots[1];

        $comp->set('contratoVehiculos', $slots)
            ->call('generarContrato')
            ->assertDispatched('successAlert')
            ->assertNotDispatched('errorAlert');

        $doc = $this->contratoDe($empresa);
        $this->assertSame('a43', $doc->modelo);
        $this->assertSame(['1678-2026', '1345-2026'], array_column($doc->snapshot['bienes'], 'kardex'));
    }

    /** El mismo envío en bloque no puede dejar el modelo ni el valor del bien sin recalcular. */
    public function test_el_modelo_y_el_valor_se_recalculan_cuando_los_dos_vehiculos_cambian_en_un_mismo_pedido(): void
    {
        [$empresa, $credit, $v1, $v2] = $this->mundoEmpresa();

        $comp = $this->wizardEmpresa($empresa, $credit, $v1)->call('agregarVehiculoContrato');
        $this->assertSame('a42', $comp->get('modeloContrato'));

        // Escribe el acta del vehículo 1, lo marca bien futuro y elige el vehículo 2: un solo pedido.
        $slots = $comp->get('contratoVehiculos');
        $slots[0] = ['es_futuro' => true, 'fecha_acta' => '2026-11-18', 'kardex_num' => '1678'] + $slots[0];
        $slots[1] = ['vehiculo_id' => $v2->id] + $slots[1];
        $comp->set('contratoVehiculos', $slots);

        $this->assertSame('a45', $comp->get('modeloContrato'), 'futuro + presente, no el a.4.2 de antes');
        $this->assertSame('25000.00', $comp->get('valorBien'), 'los dos vehículos: 10 000 + 15 000');
        $this->assertSame('1678-2026', $comp->get('contratoVehiculos')[0]['kardex']);
    }

    // ── 4 · Depósito a tercero con dos bienes ─────────────────────────────

    public function test_la_deudora_emite_el_deposito_a_tercero_con_dos_bienes_y_carne_de_extranjeria(): void
    {
        [$deudora, $credit, $v1, $v2] = $this->mundo(['sexo' => 'F']);

        Livewire::test(Documentos::class, ['id' => $deudora->id])
            ->call('abrirModalContrato')
            ->set('contratoCreditoId', $credit->id)
            ->set('contratoVehiculos.0.vehiculo_id', $v1->id)
            ->set('bancoDesembolso', 'bcp')
            ->set('destinoContrato', 'tercero')
            ->call('agregarVehiculoContrato')
            ->set('contratoVehiculos.1.vehiculo_id', $v2->id)
            ->assertSeeHtml('<option value="tercero">Cuenta de un tercero</option>')
            ->assertSeeHtml('wire:model.live="tercero.tipo_documento"')
            ->set('tercero.nombre', 'CARLOS PEREZ GOMEZ')
            ->set('tercero.tipo_documento', 'CE')
            ->set('tercero.dni', '001234567')
            ->set('tercero.cuenta', '19172571532076')
            ->call('generarContrato')
            ->assertDispatched('successAlert')
            ->assertNotDispatched('errorAlert');

        $doc = $this->contratoDe($deudora);
        $this->assertSame('a221', $doc->modelo);
        $this->assertSame('CARNÉ DE EXTRANJERÍA', $doc->snapshot['tercero']['documentoTipo']);

        $html = $this->texto($doc);
        $this->assertStringContainsString('IDENTIFICADO CON CARNÉ DE EXTRANJERÍA N.° 001234567', $html);
        // El asistente no manda el motivo: lo escribe el generador según quién debe.
        $this->assertStringContainsString('DEBIDO A QUE LA DEUDORA PRESENTA PROBLEMAS ADMINISTRATIVOS CON SUS CUENTAS PERSONALES', $html);
        $this->assertStringContainsString('VALOR DEL BIEN AFECTADO 2:', $html);
    }

    public function test_el_documento_del_tercero_solo_puede_ser_dni_o_carne_de_extranjeria(): void
    {
        [$deudor, $credit, $v1] = $this->mundo(['sexo' => 'M']);

        Livewire::test(Documentos::class, ['id' => $deudor->id])
            ->call('abrirModalContrato')
            ->set('contratoCreditoId', $credit->id)
            ->set('contratoVehiculos.0.vehiculo_id', $v1->id)
            ->set('bancoDesembolso', 'bcp')
            ->set('destinoContrato', 'tercero')
            ->set('tercero.nombre', 'CARLOS PEREZ GOMEZ')
            ->set('tercero.tipo_documento', 'RUC')
            ->set('tercero.dni', '20612519260')
            ->call('generarContrato')
            ->assertHasErrors('tercero.tipo_documento');

        $this->assertSame(0, DocumentoCliente::where('client_id', $deudor->id)->count());
    }

    public function test_el_tercero_con_carne_de_extranjeria_se_consulta_en_migraciones(): void
    {
        config(['services.factiliza.token' => 'token-de-prueba']);
        Http::fake(['*/cee/info/*' => Http::response([
            'success' => true,
            'data' => ['nombres' => 'CARLOS JOSE', 'apellido_paterno' => 'PEREZ', 'apellido_materno' => 'GOMEZ'],
        ])]);

        [$deudor] = $this->mundo(['sexo' => 'M']);

        $comp = Livewire::test(Documentos::class, ['id' => $deudor->id])
            ->call('abrirModalContrato')
            ->set('tercero.tipo_documento', 'CE')
            ->set('tercero.dni', '001234567')
            ->call('consultarDocTercero');

        $this->assertSame('CARLOS JOSE PEREZ GOMEZ', $comp->get('tercero')['nombre']);
        Http::assertSentCount(1);
    }
}
