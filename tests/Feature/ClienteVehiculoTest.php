<?php

namespace Tests\Feature;

use App\Livewire\Clients\Create;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vehículos al crear cliente. Desde el wizard de 2 pasos (28/08) la lista es
 * un array (0..N vehículos); estas pruebas cubren el vínculo con el cliente,
 * la normalización de placa y el unique contra la BD. La cobertura del wizard
 * en sí (pasos, campos obligatorios, consultas) vive en ClienteWizardTest.
 */
class ClienteVehiculoTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        // El insert de clientes usa la sede del usuario (FK); el id de la sede
        // no es predecible entre tests (el autoincrement de MySQL no se
        // resetea con RefreshDatabase), así que se asigna explícitamente.
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);

        // Sin rol ni permisos: no tiene clientes.scope-propio, así que puede crear
        $user = User::factory()->create(['username' => 'vehiculo-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);

        return $user;
    }

    private function formularioBase(): array
    {
        return [
            'nombre' => 'Juan Carlos',
            'apellido_pat' => 'Prueba',
            'apellido_mat' => 'Vehicular',
            'documento' => '87654321',
            'sexo' => 'M',
            'email' => 'vehicular@correo.com',
        ];
    }

    public function test_cliente_con_placa_crea_su_vehiculo_vinculado(): void
    {
        $this->operador();

        $c = Livewire::test(Create::class);
        foreach ($this->formularioBase() as $campo => $valor) {
            $c->set($campo, $valor);
        }
        $c->call('agregarVehiculo')
            ->set('vehiculos.0.placa', 'abc123')
            ->set('vehiculos.0.marca', 'Toyota')
            ->set('vehiculos.0.modelo', 'Hiace')
            ->set('vehiculos.0.nro_motor', '2kd123456')
            ->set('vehiculos.0.nro_serie', 'jt123456789')
            ->set('vehiculos.0.categoria', 'M2-C3')
            ->set('vehiculos.0.anio_modelo', '2018')
            ->set('vehiculos.0.carroceria', 'Microbus')
            ->set('vehiculos.0.color', 'Blanco')
            ->set('vehiculos.0.combustible', 'GNV')
            ->set('vehiculos.0.valor', 38000)
            ->call('save')
            ->assertHasNoErrors();

        $cliente = Client::where('documento', '87654321')->firstOrFail();
        $vehiculo = Vehiculo::sole();

        $this->assertSame($cliente->id, $vehiculo->client_id);
        $this->assertSame('ABC123', $vehiculo->placa); // normalizada a mayúsculas
        $this->assertSame('2KD123456', $vehiculo->nro_motor);
        $this->assertSame('JT123456789', $vehiculo->nro_serie);
        $this->assertSame('Toyota', $vehiculo->marca);
        $this->assertSame('2018', $vehiculo->anio_modelo);
        $this->assertEqualsWithDelta(38000, (float) $vehiculo->valor, 0.01);
        $this->assertTrue($cliente->vehiculos->contains($vehiculo));
    }

    public function test_cliente_sin_datos_de_vehiculo_no_crea_ninguno(): void
    {
        $this->operador();

        $c = Livewire::test(Create::class);
        foreach ($this->formularioBase() as $campo => $valor) {
            $c->set($campo, $valor);
        }
        $c->call('save')->assertHasNoErrors();

        $this->assertSame(1, Client::where('documento', '87654321')->count());
        $this->assertSame(0, Vehiculo::count());
    }

    public function test_fila_de_vehiculo_sin_placa_exige_la_placa(): void
    {
        $this->operador();

        $c = Livewire::test(Create::class);
        foreach ($this->formularioBase() as $campo => $valor) {
            $c->set($campo, $valor);
        }
        $c->call('agregarVehiculo')
            ->set('vehiculos.0.marca', 'Toyota')
            ->call('save')
            ->assertHasErrors(['vehiculos.0.placa' => 'required']);

        $this->assertSame(0, Client::where('documento', '87654321')->count());
        $this->assertSame(0, Vehiculo::count());
    }

    public function test_placa_duplicada_se_rechaza(): void
    {
        $this->operador();

        $otro = Client::create([
            'expediente' => '5000', 'nombre' => 'Dueño', 'apellido_pat' => 'Original', 'apellido_mat' => 'Placa',
            'tipo_documento' => 'DNI', 'documento' => '11223344', 'sexo' => 'M', 'status' => 'active',
        ]);
        Vehiculo::create(['client_id' => $otro->id, 'placa' => 'ABC123']);

        $c = Livewire::test(Create::class);
        foreach ($this->formularioBase() as $campo => $valor) {
            $c->set($campo, $valor);
        }
        $c->call('agregarVehiculo')
            ->set('vehiculos.0.placa', 'abc123') // se normaliza a ABC123 antes de validar
            ->call('save')
            ->assertHasErrors(['vehiculos.0.placa' => 'unique']);

        $this->assertSame(0, Client::where('documento', '87654321')->count());
    }
}
