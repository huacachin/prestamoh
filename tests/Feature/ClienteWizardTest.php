<?php

namespace Tests\Feature;

use App\Livewire\Clients\Create;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehiculo;
use App\Support\TiposCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alta de cliente en 2 pasos (28/08): paso 1 datos del cliente (correo,
 * ocupación y estado civil obligatorios), paso 2 vehículos VARIOS y
 * OPCIONALES. Las consultas van contra Factiliza, que ya entrega los
 * nombres separados (sin split posicional).
 */
class ClienteWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('correlativos')->insertOrIgnore([
            'tipo' => 'Cliente', 'correl' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'asesor-wiz', 'headquarter_id' => 1]));
        config(['services.factiliza.token' => 'token-de-prueba']);
    }

    private function paso1(): Testable
    {
        return Livewire::test(Create::class)
            ->set('nombre', 'Juan Carlos')
            ->set('apellido_pat', 'De La Cruz')
            ->set('apellido_mat', 'Rojas')
            ->set('documento', '45678912')
            ->set('email', 'juan@correo.com');
    }

    public function test_el_paso_1_exige_correo_ocupacion_y_estado_civil(): void
    {
        Livewire::test(Create::class)
            ->set('nombre', 'Juan')
            ->set('apellido_pat', 'Perez')
            ->set('documento', '45678912')
            ->set('email', '')
            ->set('ocupacion', '')
            ->set('estado_civil', '')
            ->call('siguientePaso')
            ->assertHasErrors(['email', 'ocupacion', 'estado_civil'])
            ->assertSet('paso', 1);
    }

    public function test_defaults_transportista_y_soltero(): void
    {
        Livewire::test(Create::class)
            ->assertSet('ocupacion', 'transportista')
            ->assertSet('estado_civil', 'soltero')
            ->assertSet('tipo_documento', 'DNI')
            ->assertSet('paso', 1);
    }

    public function test_correo_invalido_no_pasa_de_paso(): void
    {
        $this->paso1()->set('email', 'no-es-correo')
            ->call('siguientePaso')
            ->assertHasErrors('email')
            ->assertSet('paso', 1);
    }

    public function test_se_guarda_el_cliente_si_n_vehiculos(): void
    {
        $this->paso1()
            ->call('siguientePaso')
            ->assertSet('paso', 2)
            ->call('save');

        $cliente = Client::where('documento', '45678912')->first();
        $this->assertNotNull($cliente, 'el cliente se guarda sin registrar vehículos');
        $this->assertSame('juan@correo.com', $cliente->email);
        $this->assertSame('transportista', $cliente->ocupacion);
        $this->assertSame('soltero', $cliente->estado_civil);
        $this->assertSame(0, Vehiculo::where('client_id', $cliente->id)->count());
    }

    public function test_se_guardan_vario_s_vehiculos_con_su_valor(): void
    {
        $comp = $this->paso1()->call('siguientePaso')
            ->call('agregarVehiculo')
            ->call('agregarVehiculo')
            ->set('vehiculos.0.placa', 'abc-123')
            ->set('vehiculos.0.marca', 'Toyota')
            ->set('vehiculos.0.valor', 45000)
            ->set('vehiculos.1.placa', 'XYZ-789')
            ->set('vehiculos.1.marca', 'Hyundai')
            ->set('vehiculos.1.valor', 32000.50);

        $comp->call('save');

        $cliente = Client::where('documento', '45678912')->first();
        $vehiculos = Vehiculo::where('client_id', $cliente->id)->orderBy('id')->get();
        $this->assertCount(2, $vehiculos, 'un cliente puede tener varios vehículos');
        $this->assertSame('ABC-123', $vehiculos[0]->placa, 'la placa se normaliza a mayúsculas');
        $this->assertEqualsWithDelta(45000, (float) $vehiculos[0]->valor, 0.01);
        $this->assertEqualsWithDelta(32000.50, (float) $vehiculos[1]->valor, 0.01);
    }

    public function test_placa_repetida_en_la_lista_no_pasa(): void
    {
        $this->paso1()->call('siguientePaso')
            ->call('agregarVehiculo')
            ->call('agregarVehiculo')
            ->set('vehiculos.0.placa', 'ABC-123')
            ->set('vehiculos.1.placa', 'ABC-123')
            ->call('save')
            ->assertHasErrors('vehiculos.1.placa');

        $this->assertSame(0, Client::where('documento', '45678912')->count());
    }

    public function test_quitar_vehiculo_reindexa_la_lista(): void
    {
        $comp = Livewire::test(Create::class)
            ->call('agregarVehiculo')
            ->call('agregarVehiculo')
            ->set('vehiculos.0.placa', 'AAA-111')
            ->set('vehiculos.1.placa', 'BBB-222')
            ->call('quitarVehiculo', 0);

        $this->assertCount(1, $comp->get('vehiculos'));
        $this->assertSame('BBB-222', $comp->get('vehiculos')[0]['placa']);
    }

    // ─── Consultas Factiliza ────────────────────────────────

    public function test_dni_usa_los_nombres_ya_separados_de_la_api(): void
    {
        // El caso que rompía el split posicional: apellido compuesto
        Http::fake(['*/dni/info/*' => Http::response([
            'status' => 200, 'success' => true, 'message' => 'Exito',
            'data' => [
                'numero' => '27427864', 'nombres' => 'JUAN CARLOS',
                'apellido_paterno' => 'DE LA CRUZ', 'apellido_materno' => 'ROJAS',
                'direccion' => 'AV. SIEMPRE VIVA 123', 'sexo' => 'M',
            ],
        ], 200)]);

        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('docBuscar', '27427864')
            ->call('consultarDocumento')
            ->assertSet('apellido_pat', 'De La Cruz')
            ->assertSet('apellido_mat', 'Rojas')
            ->assertSet('nombre', 'Juan Carlos')
            ->assertSet('documento', '27427864')
            ->assertSet('docMsgType', 'ok');
    }

    public function test_ruc_carga_la_razon_social_en_nombre(): void
    {
        Http::fake(['*/ruc/info/*' => Http::response([
            'status' => 200, 'success' => true,
            'data' => ['numero' => '20552103816', 'nombre_o_razon_social' => 'AGROLIGHT PERU S.A.C.', 'direccion' => 'PJ. JORGE BASADRE 158'],
        ], 200)]);

        Livewire::test(Create::class)
            ->set('tipo_documento', 'RUC')
            ->set('docBuscar', '20552103816')
            ->call('consultarDocumento')
            ->assertSet('nombre', 'AGROLIGHT PERU S.A.C.')
            ->assertSet('apellido_pat', '')
            ->assertSet('docMsgType', 'ok');
    }

    public function test_carne_de_extranjeria_usa_su_endpoint(): void
    {
        Http::fake(['*/cee/info/*' => Http::response([
            'status' => 200, 'success' => true,
            'data' => ['numero' => '001077238', 'nombres' => 'LUZ CELIA KORINA', 'apellido_paterno' => 'RIVADENEIRA', 'apellido_materno' => 'ARCILA'],
        ], 200)]);

        Livewire::test(Create::class)
            ->set('tipo_documento', 'CE')
            ->set('docBuscar', '001077238')
            ->call('consultarDocumento')
            ->assertSet('nombre', 'Luz Celia Korina')
            ->assertSet('apellido_pat', 'Rivadeneira')
            ->assertSet('documento', '001077238')
            ->assertSet('docMsgType', 'ok');
    }

    public function test_documento_no_encontrado_deja_seguir_a_mano(): void
    {
        Http::fake(['*/dni/info/*' => Http::response([
            'status' => 400, 'success' => false, 'message' => 'Bad Request',
        ], 400)]);

        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('docBuscar', '45678912')
            ->call('consultarDocumento')
            ->assertSet('docMsgType', 'err')
            // el documento se propaga igual: no hay que volver a tipearlo
            ->assertSet('documento', '45678912');
    }

    public function test_consulta_de_placa_autocompleta_la_fila(): void
    {
        Http::fake(['*/placa/info/*' => Http::response([
            'status' => 200, 'success' => true,
            'data' => [
                'placa' => 'F3H792', 'marca' => 'FIAT', 'modelo' => 'FIORINO',
                'serie' => '9BD25521A98854312', 'color' => 'BLANCO', 'motor' => '8632404',
            ],
        ], 200)]);

        $comp = Livewire::test(Create::class)
            ->call('agregarVehiculo')
            ->set('vehiculos.0.placa', 'f3h792')
            ->call('consultarPlaca', 0);

        $veh = $comp->get('vehiculos')[0];
        $this->assertSame('Fiat', $veh['marca']);
        $this->assertSame('Fiorino', $veh['modelo']);
        $this->assertSame('8632404', $veh['nro_motor']);
        $this->assertSame('9BD25521A98854312', $veh['nro_serie']);
        $comp->assertSet('vehMsgType', 'ok');
    }

    public function test_marca_en_rojo_solo_los_campos_traidos_por_la_api(): void
    {
        Http::fake(['*/dni/info/*' => Http::response([
            'status' => 200, 'success' => true,
            'data' => [
                'numero' => '27427864', 'nombres' => 'JOSE PEDRO',
                'apellido_paterno' => 'CASTILLO', 'apellido_materno' => 'TERRONES',
                'direccion' => 'CASERIO PUNA',
            ],
        ], 200)]);

        $comp = Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('docBuscar', '27427864')
            ->call('consultarDocumento');

        $auto = $comp->get('autoCliente');
        foreach (['nombre', 'apellido_pat', 'apellido_mat', 'direccion', 'documento'] as $campo) {
            $this->assertContains($campo, $auto, "$campo debe marcarse como traído de la API");
        }
        // Lo que el operador teclea NO se marca
        $this->assertNotContains('giro', $auto);
        $this->assertNotContains('celular1', $auto);
    }

    public function test_marca_en_rojo_los_campos_traidos_por_la_placa(): void
    {
        Http::fake(['*/placa/info/*' => Http::response([
            'status' => 200, 'success' => true,
            'data' => ['placa' => 'F3H792', 'marca' => 'FIAT', 'modelo' => 'FIORINO', 'motor' => '8632404', 'serie' => '9BD255', 'color' => 'BLANCO'],
        ], 200)]);

        $comp = Livewire::test(Create::class)
            ->call('agregarVehiculo')
            ->set('vehiculos.0.placa', 'F3H792')
            ->call('consultarPlaca', 0);

        $auto = $comp->get('autoVehiculo')[0] ?? [];
        foreach (['marca', 'modelo', 'nro_motor', 'nro_serie', 'color'] as $campo) {
            $this->assertContains($campo, $auto);
        }
        // La API no devuelve estos: se quedan sin marcar
        $this->assertNotContains('categoria', $auto);
        $this->assertNotContains('anio_modelo', $auto);
    }

    // ─── T. Crédito (clients.zona → garantía legal) ─────────

    public function test_t_credito_solo_acepta_las_opciones_del_catalogo(): void
    {
        $this->paso1()->set('zona', 'Cualquier Cosa')
            ->call('siguientePaso')
            ->assertHasErrors('zona')
            ->assertSet('paso', 1);

        $this->paso1()->set('zona', 'Gar. Hip.S')
            ->call('siguientePaso')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);
    }

    public function test_t_credito_puede_quedar_vacio(): void
    {
        $this->paso1()->set('zona', '')
            ->call('siguientePaso')
            ->assertHasNoErrors()
            ->assertSet('paso', 2);
    }

    public function test_editar_conserva_el_t_credito_historico(): void
    {
        // Los clientes migrados tienen valores fuera del catálogo
        // ("Demandado Casa", "SIGM.S-Rojo 14/07"): editarlos no debe borrarlos.
        $opciones = TiposCredito::paraValor('SIGM.S-Rojo 14/07');
        $this->assertSame('SIGM.S-Rojo 14/07', $opciones[0]);
        $this->assertContains('SIGM.M', $opciones);
        $this->assertCount(7, $opciones);

        // Un valor del catálogo no se duplica
        $this->assertCount(6, TiposCredito::paraValor('SIGM.M'));
        $this->assertCount(6, TiposCredito::paraValor(null));
    }

    public function test_dni_con_largo_invalido_no_consulta(): void
    {
        Http::fake();

        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('docBuscar', '123')
            ->call('consultarDocumento')
            ->assertSet('docMsgType', 'err');

        Http::assertNothingSent();
    }
}
