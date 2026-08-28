<?php

namespace Tests\Feature;

use App\Livewire\Clients\Create;
use App\Livewire\Clients\Edit;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Support\Documentos\DomicilioLegal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tramo B — que el alta capture lo que el contrato exige, y que a partir de
 * ahí sea imposible emitir con un hueco.
 *
 *   1. `clients.nacionalidad` existe y se persiste (antes se pintaba readonly
 *      y el insert nunca la escribía: el generador la hardcodeaba a PERUANO).
 *   2. El ubigeo que devuelve la API deja de descartarse (el mapeo solo
 *      listaba direccion y distrito, así que provincia y departamento se
 *      perdían y el asesor retipeaba el domicilio en cada contrato).
 *   3. Callao tiene su giro registral propio: "PROVINCIA CONSTITUCIONAL DEL
 *      CALLAO". No existía en el repo.
 *   4. Un solo DomicilioLegal en vez de tres copias byte-idénticas.
 *   5. El guard de emisión reemplaza al backfill: si un dato exigido por la
 *      guía no fue declarado, el contrato no sale.
 */
class ContratoTramoBTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Headquarter::create(['name' => 'Sede TramoB', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'tramo-b', 'headquarter_id' => $this->sede->id]);
        $this->actingAs($this->user);
    }

    // ── 1 · El domicilio legal ────────────────────────────────────────────

    public function test_lima_colapsa_provincia_y_departamento(): void
    {
        $this->assertSame(
            'AV. AREQUIPA 3400, DISTRITO DE LINCE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            DomicilioLegal::armar('Av. Arequipa 3400', 'Lince', 'LIMA', 'LIMA')
        );
    }

    public function test_callao_usa_su_giro_registral_propio(): void
    {
        $this->assertSame(
            'JR. LOS ALPES 69, DISTRITO DE BELLAVISTA, PROVINCIA CONSTITUCIONAL DEL CALLAO',
            DomicilioLegal::armar('Jr. Los Alpes 69', 'Bellavista', 'CALLAO', 'CALLAO')
        );
    }

    public function test_provincias_distintas_no_se_colapsan(): void
    {
        $this->assertSame(
            'CALLE 1, DISTRITO DE CAYMA, PROVINCIA DE AREQUIPA, DEPARTAMENTO DE AREQUIPA X',
            DomicilioLegal::armar('Calle 1', 'Cayma', 'Arequipa', 'Arequipa X')
        );
    }

    public function test_tramos_ausentes_no_dejan_comas_sueltas(): void
    {
        $this->assertSame('AV. SOLO', DomicilioLegal::armar('Av. Solo', null, null, null));
        $this->assertSame('', DomicilioLegal::armar(null, null, null, null));
    }

    // ── 2 · El alta persiste lo que el contrato exige ─────────────────────

    public function test_el_alta_guarda_nacionalidad_y_ubigeo(): void
    {
        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('documento', '46781301')
            ->set('nombre', 'ROSA LINDA')
            ->set('apellido_pat', 'QUISPE')
            ->set('apellido_mat', 'MAMANI')
            ->set('sexo', 'F')
            ->set('email', 'rosa.b@example.com')
            ->set('ocupacion', 'independiente')
            ->set('estado_civil', 'casado')
            ->set('nacionalidad', 'VENEZOLANO')
            ->set('expediente', 9301)
            ->set('direccion', 'JR. LOS ALPES 69')
            ->set('distrito', 'BELLAVISTA')
            ->set('provincia', 'CALLAO')
            ->set('departamento', 'CALLAO')
            ->call('siguientePaso')
            ->call('save')
            ->assertHasNoErrors();

        $c = Client::where('documento', '46781301')->firstOrFail();

        $this->assertSame('VENEZOLANO', $c->nacionalidad);
        $this->assertSame('BELLAVISTA', $c->distrito);
        $this->assertSame('CALLAO', $c->provincia);
        $this->assertSame('CALLAO', $c->departamento);

        // Y con eso el domicilio legal sale entero, sin retipear nada.
        $this->assertSame(
            'JR. LOS ALPES 69, DISTRITO DE BELLAVISTA, PROVINCIA CONSTITUCIONAL DEL CALLAO',
            DomicilioLegal::deCliente($c)
        );
    }

    public function test_el_alta_exige_el_domicilio_legal_completo(): void
    {
        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('documento', '46781302')
            ->set('nombre', 'JUAN')
            ->set('apellido_pat', 'PEREZ')
            ->set('sexo', 'M')
            ->set('email', 'juan@example.com')
            ->set('expediente', 9302)
            ->set('direccion', null)
            ->set('distrito', null)
            ->call('siguientePaso')
            ->assertHasErrors(['direccion', 'distrito']);
    }

    public function test_editar_cliente_ya_guarda_ocupacion_estado_civil_y_ubigeo(): void
    {
        $c = Client::create([
            'expediente' => '9303', 'nombre' => 'MARIA', 'apellido_pat' => 'LOPEZ',
            'tipo_documento' => 'DNI', 'documento' => '46781303', 'sexo' => 'F',
            'direccion' => 'CALLE VIEJA', 'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        Livewire::test(Edit::class, ['id' => $c->id])
            ->set('distrito', 'ATE')
            ->set('provincia', 'LIMA')
            ->set('departamento', 'LIMA')
            ->set('nacionalidad', 'peruano')
            ->set('email', 'maria@example.com')
            ->set('ocupacion', 'independiente')
            ->set('estado_civil', 'casado')
            ->call('update')
            ->assertHasNoErrors();

        $c->refresh();
        $this->assertSame('ATE', $c->distrito);
        $this->assertSame('LIMA', $c->provincia);
        $this->assertSame('PERUANO', $c->nacionalidad, 'se normaliza a mayúsculas');
        $this->assertSame('maria@example.com', $c->email);
        $this->assertSame('independiente', $c->ocupacion);
        $this->assertSame('casado', $c->estado_civil);
    }

    // ── 3 · El guard de emisión ───────────────────────────────────────────

    /** Cliente completo + crédito con cronograma + vehículo con todos sus datos. */
    private function mundoCompleto(array $overridesCliente = [], array $overridesVehiculo = []): array
    {
        $client = Client::create(array_merge([
            'expediente' => '9400',
            'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781400', 'sexo' => 'F',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'email' => 'rosa.guard@example.com',
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE',
            'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ], $overridesCliente));

        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($n - 1),
                'importe_cuota' => 1250, 'importe_interes' => 50, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }

        $v = Vehiculo::create(array_merge([
            'client_id' => $client->id, 'placa' => 'GRD111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-GRD', 'nro_motor' => 'MOT-GRD', 'valor' => 15000,
        ], $overridesVehiculo));

        return [$client, $credit, $v];
    }

    private function datos(): array
    {
        return ['banco' => 'bcp'];
    }

    public function test_con_la_ficha_completa_el_guard_no_bloquea(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto();

        $errores = GeneradorContrato::validar($client, $credit, [$v->id], 'a1', $this->datos());

        $this->assertSame([], $errores, 'Una ficha completa debe poder emitir: '.implode(' | ', $errores));
    }

    public function test_el_guard_bloquea_al_cliente_que_nunca_declaro_sus_datos(): void
    {
        // Cliente sin correo ni domicilio: el caso típico del migrado.
        [$client, $credit, $v] = $this->mundoCompleto([
            'documento' => '46781401', 'expediente' => '9401',
            'ocupacion' => '', 'estado_civil' => '', 'email' => null,
            'distrito' => null, 'provincia' => null, 'departamento' => null, 'direccion' => null,
        ]);

        $errores = GeneradorContrato::validar($client, $credit, [$v->id], 'a1', $this->datos());
        $texto = implode(' | ', $errores);

        $this->assertNotEmpty($errores);
        $this->assertStringContainsString('ocupación', $texto);
        $this->assertStringContainsString('estado civil', $texto);
        $this->assertStringContainsString('correo', $texto);
        $this->assertStringContainsString('domicilio', $texto);
    }

    /**
     * LÍMITE CONOCIDO del guard, documentado a propósito.
     *
     * `clients.ocupacion` y `clients.estado_civil` son NOT NULL con default
     * 'transportista'/'soltero' (migración 2026_08_28_000001), así que un
     * cliente migrado del legacy NO tiene el dato vacío: tiene un valor falso
     * indistinguible de uno declarado. El guard detecta lo AUSENTE, no lo
     * FALSO, y por eso no lo bloquea.
     *
     * Es aceptable mientras el módulo se use solo con clientes nuevos (el alta
     * los exige de verdad). Cerrarlo requeriría poner las columnas nullable y
     * un UPDATE sobre las filas migradas — decisión de negocio pendiente,
     * anotada en docs/PENDIENTES.md.
     */
    public function test_limite_conocido_el_default_falso_del_legacy_pasa_el_guard(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto([
            'documento' => '46781410', 'expediente' => '9410',
            'ocupacion' => 'transportista', 'estado_civil' => 'soltero',
        ]);

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a1', $this->datos()));

        $this->assertStringNotContainsString('ocupación', $texto);
        $this->assertStringNotContainsString('estado civil', $texto);
    }

    public function test_el_guard_exige_los_datos_del_bien_futuro(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto(['documento' => '46781402', 'expediente' => '9402']);

        // a.1.4 = un bien futuro; sin fecha de acta, kárdex ni notario.
        $errores = GeneradorContrato::validar($client, $credit, [$v->id], 'a14', $this->datos());
        $texto = implode(' | ', $errores);

        $this->assertStringContainsString('fecha de transferencia', $texto);
        $this->assertStringContainsString('kárdex', $texto);
        $this->assertStringContainsString('notario', $texto);

        // Con los tres datos, deja emitir.
        $ok = GeneradorContrato::validar($client, $credit, [$v->id], 'a14', $this->datos() + [
            'bienes' => [$v->id => [
                'es_futuro' => true, 'fecha_acta' => '2026-05-04',
                'kardex' => '0373-2026', 'notario' => 'JULIO BLAS',
            ]],
        ]);
        $this->assertSame([], $ok, implode(' | ', $ok));
    }

    public function test_el_guard_no_pide_datos_de_empresa_a_una_persona_natural(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto(['documento' => '46781403', 'expediente' => '9403']);

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a1', $this->datos()));

        $this->assertStringNotContainsString('partida registral', $texto);
        $this->assertStringNotContainsString('gerente', $texto);
        $this->assertStringNotContainsString('RUC', $texto);
    }

    public function test_el_guard_exige_partida_oficina_y_gerente_a_la_empresa(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto([
            'documento' => '20601234567', 'expediente' => '9404', 'tipo_documento' => 'RUC',
        ]);

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a4', $this->datos()));

        $this->assertStringContainsString('partida registral', $texto);
        $this->assertStringContainsString('oficina registral', $texto);
        $this->assertStringContainsString('gerente general', $texto);
    }

    public function test_el_guard_exige_el_vehiculo_completo_y_su_valor(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto(
            ['documento' => '46781405', 'expediente' => '9405'],
            ['placa' => 'GRD999', 'nro_motor' => null, 'valor' => null],
        );

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a1', $this->datos()));

        $this->assertStringContainsString('N° de motor', $texto);
        $this->assertStringContainsString('valor del vehículo', $texto);
    }

    public function test_el_guard_exige_tantos_vehiculos_como_pide_el_modelo(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto(['documento' => '46781406', 'expediente' => '9406']);

        // a.1.2 exige DOS bienes presentes y solo llega uno.
        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a12', $this->datos()));

        $this->assertStringContainsString('exige 2 vehículo(s) y llegaron 1', $texto);
    }

    public function test_el_guard_exige_el_banco_del_desembolso(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto(['documento' => '46781407', 'expediente' => '9407']);

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a1', []));

        $this->assertStringContainsString('banco del desembolso', $texto);
    }

    public function test_el_guard_corre_tambien_en_la_previsualizacion(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto([
            'documento' => '46781408', 'expediente' => '9408', 'ocupacion' => '',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        GeneradorContrato::previsualizar($client, $credit, [$v->id], 'a1', $this->datos());
    }

    // ── 4 · La nacionalidad va en masculino ───────────────────────────────

    public function test_la_nacionalidad_se_guarda_en_masculino_y_el_contrato_la_flexiona(): void
    {
        [$client, $credit, $v] = $this->mundoCompleto(['documento' => '46781409', 'expediente' => '9409']);

        $this->assertSame('PERUANO', $client->nacionalidad, 'la ficha guarda la base masculina');

        $html = GeneradorContrato::previsualizar($client, $credit, [$v->id], 'a1', $this->datos());

        // El cliente es mujer: el partial flexiona la base masculina.
        $this->assertStringContainsString('PERUANA', $html);
    }
}
