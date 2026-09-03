<?php

namespace Tests\Feature;

use App\Livewire\Clients\Gps;
use App\Models\Client;
use App\Models\User;
use App\Support\Coordenadas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Pestaña GPS de /clients/{id}/edit (28/08): reemplaza las columnas "C." y
 * "N." del listado con la misma funcionalidad (pegar coordenadas o un enlace
 * de Google Maps) pero legible y usable desde el celular.
 */
class ClienteGpsTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(): Client
    {
        $this->actingAs(User::factory()->create(['username' => 'gps-tester']));

        return Client::create([
            'expediente' => '950', 'nombre' => 'Cliente', 'apellido_pat' => 'Con', 'apellido_mat' => 'Gps',
            'tipo_documento' => 'DNI', 'documento' => '33445566', 'sexo' => 'M', 'status' => 'active',
        ]);
    }

    public function test_parser_acepta_coordenadas_y_enlaces_de_maps(): void
    {
        $this->assertSame([-12.014431, -76.824936], Coordenadas::parse('-12.014431, -76.824936'));
        $this->assertSame([-12.014431, -76.824936], Coordenadas::parse('(-12.014431 -76.824936)'));
        // URL de Google Maps: ignora el zoom "17z"
        $this->assertSame([-12.0464, -77.0428], Coordenadas::parse('https://www.google.com/maps/@-12.0464,-77.0428,17z'));
        // Sin decimales o fuera de rango
        $this->assertNull(Coordenadas::parse('Av. Arequipa 3400'));
        $this->assertNull(Coordenadas::parse('99.5, -300.2'));
    }

    public function test_guarda_casa_y_negocio_por_separado(): void
    {
        $c = $this->cliente();

        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('pegado.casa', '-12.014431, -76.824936')
            ->call('guardar', 'casa')
            ->assertSet('msgType', 'ok');

        $c->refresh();
        $this->assertEqualsWithDelta(-12.014431, (float) $c->latitud, 0.0000001);
        $this->assertEqualsWithDelta(-76.824936, (float) $c->longitud, 0.0000001);
        $this->assertNull($c->latitud2, 'guardar Casa no toca Negocio');

        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('pegado.negocio', 'https://www.google.com/maps/@-12.0464,-77.0428,17z')
            ->call('guardar', 'negocio');

        $c->refresh();
        $this->assertEqualsWithDelta(-12.0464, (float) $c->latitud2, 0.0000001);
    }

    public function test_formato_invalido_no_guarda(): void
    {
        $c = $this->cliente();

        Livewire::test(Gps::class, ['id' => $c->id])
            ->set('pegado.casa', 'por la esquina del mercado')
            ->call('guardar', 'casa')
            ->assertSet('msgType', 'err');

        $this->assertNull($c->fresh()->latitud);
    }

    public function test_borrar_deja_la_ubicacion_vacia(): void
    {
        $c = $this->cliente();
        $c->update(['latitud' => -12.1, 'longitud' => -76.9]);

        Livewire::test(Gps::class, ['id' => $c->id])->call('borrar', 'casa');

        $c->refresh();
        $this->assertNull($c->latitud);
        $this->assertNull($c->longitud);
    }

    public function test_analista_no_entra_a_cliente_ajeno(): void
    {
        $otro = User::factory()->create(['username' => 'duenio-gps']);
        $c = Client::create([
            'expediente' => '951', 'nombre' => 'Ajeno', 'apellido_pat' => 'Gps', 'apellido_mat' => 'Otro',
            'tipo_documento' => 'DNI', 'documento' => '77889900', 'sexo' => 'M', 'status' => 'active',
            'asesor_id' => $otro->id,
        ]);

        $analista = User::factory()->create(['username' => 'analista-gps']);
        $analista->givePermissionTo(Permission::findOrCreate('clientes.scope-propio', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($analista);

        $this->expectException(HttpException::class);
        (new Gps)->mount($c->id);
    }
}
