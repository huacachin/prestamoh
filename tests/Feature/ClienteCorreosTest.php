<?php

namespace Tests\Feature;

use App\Livewire\Clients\Create;
use App\Livewire\Clients\Edit;
use App\Models\Client;
use App\Models\ClientEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Correos múltiples por cliente (04/09). Invariante: máximo UN principal y
 * `clients.email` es SIEMPRE su espejo — contratos (cláusula de
 * notificaciones, guard, Anexo 1) y exports leen esa columna sin cambios.
 */
class ClienteCorreosTest extends TestCase
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
        $this->actingAs(User::factory()->create(['username' => 'correos-tester', 'headquarter_id' => 1]));
    }

    private function clienteConCorreos(array $correos): Client
    {
        $client = Client::create([
            'expediente' => '9990', 'nombre' => 'CORREO', 'apellido_pat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => '47000001', 'sexo' => 'M',
            'ocupacion' => 'transportista', 'estado_civil' => 'soltero',
            'headquarter_id' => 1, 'status' => 'active',
        ]);
        foreach ($correos as $i => $email) {
            ClientEmail::create(['client_id' => $client->id, 'email' => $email, 'principal' => $i === 0]);
        }
        ClientEmail::espejar($client->id);

        return $client->refresh();
    }

    public function test_el_alta_crea_el_correo_como_principal_y_lo_espeja(): void
    {
        Livewire::test(Create::class)
            ->set('nombre', 'Juan')->set('apellido_pat', 'Perez')
            ->set('documento', '47000002')->set('correos.0.email', 'juan@correo.com')
            ->set('direccion', 'AV. LIMA 1')->set('distrito', 'LINCE')
            ->call('siguientePaso')->assertHasNoErrors()
            ->call('save')->assertHasNoErrors();

        $c = Client::where('documento', '47000002')->firstOrFail();
        $this->assertSame('juan@correo.com', $c->email, 'espejo en clients.email');
        $fila = ClientEmail::where('client_id', $c->id)->get();
        $this->assertCount(1, $fila);
        $this->assertTrue($fila[0]->principal);
        $this->assertSame('juan@correo.com', $fila[0]->email);
    }

    public function test_editar_agrega_correos_y_el_principal_manda_en_el_espejo(): void
    {
        $c = $this->clienteConCorreos(['uno@x.com']);

        Livewire::test(Edit::class, ['id' => $c->id])
            ->call('agregarCorreo')
            ->set('correos.1.email', 'dos@x.com')
            ->call('marcarPrincipal', 1)
            ->call('update')
            ->assertHasNoErrors();

        $c->refresh();
        $this->assertSame('dos@x.com', $c->email, 'el espejo sigue al nuevo principal');
        $this->assertSame(2, ClientEmail::where('client_id', $c->id)->count());
        $this->assertSame(1, ClientEmail::where('client_id', $c->id)->where('principal', 1)->count(), 'un solo principal');
        $this->assertSame('dos@x.com', ClientEmail::where('client_id', $c->id)->where('principal', 1)->value('email'));
    }

    public function test_quitar_el_principal_promueve_al_siguiente(): void
    {
        $c = $this->clienteConCorreos(['uno@x.com', 'dos@x.com']);

        Livewire::test(Edit::class, ['id' => $c->id])
            ->call('quitarCorreo', 0)          // quita el principal
            ->assertSet('correos.0.principal', true)  // auto-promoción en la UI
            ->call('update')
            ->assertHasNoErrors();

        $c->refresh();
        $this->assertSame('dos@x.com', $c->email);
        $this->assertSame(1, ClientEmail::where('client_id', $c->id)->count());
    }

    public function test_el_unico_correo_no_se_puede_quitar(): void
    {
        $c = $this->clienteConCorreos(['unico@x.com']);

        Livewire::test(Edit::class, ['id' => $c->id])
            ->call('quitarCorreo', 0)
            ->assertCount('correos', 1)        // sigue ahí
            ->assertDispatched('errorAlert');
    }

    public function test_correos_repetidos_no_se_guardan(): void
    {
        $c = $this->clienteConCorreos(['uno@x.com']);

        Livewire::test(Edit::class, ['id' => $c->id])
            ->call('agregarCorreo')
            ->set('correos.1.email', 'UNO@x.com') // mismo, distinto casing
            ->call('update')
            ->assertHasErrors('correos');

        $this->assertSame(1, ClientEmail::where('client_id', $c->id)->count());
    }

    public function test_espejar_repara_el_invariante(): void
    {
        $c = $this->clienteConCorreos(['a@x.com', 'b@x.com']);
        // Estado corrupto: dos principales
        ClientEmail::where('client_id', $c->id)->update(['principal' => 1]);

        ClientEmail::espejar($c->id);

        $this->assertSame(1, ClientEmail::where('client_id', $c->id)->where('principal', 1)->count());
        $this->assertSame('a@x.com', $c->refresh()->email, 'gana el más antiguo');
    }
}
