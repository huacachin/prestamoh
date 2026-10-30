<?php

namespace Tests\Feature;

use App\Livewire\Clients\Vehiculos;
use App\Models\Client;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Pestaña Vehículos de /clients/{id}/edit (28/08). Antes los vehículos solo
 * se podían crear en el alta: no había forma de agregar uno nuevo, corregir
 * una placa mal tecleada ni borrar.
 */
class ClienteVehiculosTabTest extends TestCase
{
    use RefreshDatabase;

    private function cliente(): Client
    {
        $this->actingAs(User::factory()->create(['username' => 'editor-veh']));

        return Client::create([
            'expediente' => '900', 'nombre' => 'Cliente', 'apellido_pat' => 'Con', 'apellido_mat' => 'Vehiculos',
            'tipo_documento' => 'DNI', 'documento' => '99887766', 'sexo' => 'M', 'status' => 'active',
        ]);
    }

    public function test_agrega_un_vehiculo_al_cliente(): void
    {
        $c = $this->cliente();

        // 16/10 (Antony): no se guarda si falta algún campo; en amarillo los que la consulta de placa no rellena.
        $comp = Livewire::test(Vehiculos::class, ['id' => $c->id])
            ->call('nuevo')
            ->set('placa', 'nue-001')
            ->set('marca', 'Toyota')
            ->set('valor', 25000)
            ->call('guardar')
            ->assertHasErrors(['modelo', 'nro_motor', 'nro_serie', 'categoria', 'anio_modelo', 'carroceria', 'color', 'combustible']);
        $this->assertSame(0, Vehiculo::where('client_id', $c->id)->count(), 'incompleto: no se guarda');
        $html = $comp->html();
        foreach (['valor', 'categoria', 'anio_modelo', 'carroceria', 'color', 'combustible'] as $campo) {
            $this->assertMatchesRegularExpression('/class="[^"]*campo-obligatorio[^"]*"[^>]*wire:model\.defer="'.$campo.'"/', $html, "{$campo} en amarillo");
        }
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*campo-obligatorio[^"]*"[^>]*wire:model\.defer="marca"/', $html, 'la marca la rellena la consulta');
        $comp->set('valor', 0)->set('modelo', 'Hiace')->set('nro_motor', 'm1')->set('nro_serie', 's1')->set('categoria', 'M1')
            ->set('anio_modelo', '2018')->set('carroceria', 'Minibús')->set('color', 'Blanco')->set('combustible', 'Diesel')
            ->call('guardar')->assertHasErrors(['valor' => 'gt']);
        $comp->set('valor', 25000)->call('guardar')->assertHasNoErrors();

        $v = Vehiculo::where('client_id', $c->id)->sole();
        $this->assertSame('NUE-001', $v->placa, 'la placa se normaliza');
        $this->assertEqualsWithDelta(25000, (float) $v->valor, 0.01);
    }

    public function test_edita_y_elimina_un_vehiculo(): void
    {
        $c = $this->cliente();
        $v = Vehiculo::create([
            'client_id' => $c->id, 'placa' => 'OLD-999', 'marca' => 'Nissan', 'modelo' => 'Urvan', 'nro_motor' => 'M9', 'nro_serie' => 'S9',
            'categoria' => 'M2', 'anio_modelo' => '2015', 'carroceria' => 'Minibús', 'color' => 'Gris', 'combustible' => 'Diesel', 'valor' => 30000,
        ]);

        // Editar: el unique no debe chocar consigo mismo
        Livewire::test(Vehiculos::class, ['id' => $c->id])
            ->call('editar', $v->id)
            ->assertSet('placa', 'OLD-999')
            ->set('marca', 'Hyundai')
            ->call('guardar')
            ->assertHasNoErrors();
        $this->assertSame('Hyundai', $v->fresh()->marca);

        Livewire::test(Vehiculos::class, ['id' => $c->id])
            ->call('eliminar', $v->id);
        $this->assertNull($v->fresh());
    }

    /** 30/10 (Antony): el aviso de placa registrada nombra al cliente y enlaza su ficha, al guardar y al buscar. */
    public function test_no_permite_placa_de_otro_cliente_y_dice_de_quien_es(): void
    {
        $otro = Client::create([
            'expediente' => '901', 'nombre' => 'Otro', 'apellido_pat' => 'Duenio', 'apellido_mat' => 'Placa',
            'tipo_documento' => 'DNI', 'documento' => '11223399', 'sexo' => 'M', 'status' => 'active',
        ]);
        Vehiculo::create(['client_id' => $otro->id, 'placa' => 'DUP111']);
        $c = $this->cliente();
        $aviso = 'La placa DUP111 ya está registrada en el cliente Duenio Placa Otro.';
        $enlace = route('clients.edit', ['id' => $otro->id, 'tab' => 'vehiculos']);

        // Al guardar, también escrita con guion y en minúsculas.
        Livewire::test(Vehiculos::class, ['id' => $c->id])
            ->call('nuevo')
            ->set('placa', 'dup-111')
            ->call('guardar')
            ->assertHasErrors(['placa'])
            ->assertSee($aviso)
            ->assertSeeHtml('href="'.e($enlace).'"');
        $this->assertSame(0, Vehiculo::where('client_id', $c->id)->count());

        // Al buscarla: el aviso sale sin consultar la API.
        config(['services.factiliza.token' => 'token-de-prueba']);
        Http::fake();
        Livewire::test(Vehiculos::class, ['id' => $c->id])
            ->call('nuevo')
            ->set('placa', 'DUP111')
            ->call('consultarPlaca')
            ->assertHasErrors(['placa'])
            ->assertSee($aviso)
            ->assertSeeHtml('href="'.e($enlace).'"')
            ->assertSet('marca', '');
        Http::assertNothingSent();
    }

    public function test_la_placa_repetida_en_el_mismo_cliente_no_lleva_enlace(): void
    {
        $c = $this->cliente();
        Vehiculo::create(['client_id' => $c->id, 'placa' => 'MIA-222']);

        Livewire::test(Vehiculos::class, ['id' => $c->id])
            ->call('nuevo')
            ->set('placa', 'MIA-222')
            ->call('guardar')
            ->assertHasErrors(['placa'])
            ->assertSee('La placa MIA-222 ya está registrada en este cliente.')
            ->assertDontSee('Ver cliente');
    }

    public function test_consulta_de_placa_autocompleta_el_formulario(): void
    {
        config(['services.factiliza.token' => 'token-de-prueba']);
        Http::fake(['*/placa/info/*' => Http::response([
            'status' => 200, 'success' => true,
            'data' => ['placa' => 'F3H792', 'marca' => 'FIAT', 'modelo' => 'FIORINO', 'motor' => '8632404', 'serie' => '9BD255', 'color' => 'BLANCO'],
        ], 200)]);

        $c = $this->cliente();

        Livewire::test(Vehiculos::class, ['id' => $c->id])
            ->call('nuevo')
            ->set('placa', 'f3h792')
            ->call('consultarPlaca')
            ->assertSet('marca', 'Fiat')
            ->assertSet('modelo', 'Fiorino')
            ->assertSet('nro_motor', '8632404')
            ->assertSet('msgType', 'ok');
    }

    public function test_analista_no_puede_editar_vehiculos_ajenos(): void
    {
        $otroAsesor = User::factory()->create(['username' => 'duenio-cartera']);
        $c = Client::create([
            'expediente' => '902', 'nombre' => 'Ajeno', 'apellido_pat' => 'Cartera', 'apellido_mat' => 'Otra',
            'tipo_documento' => 'DNI', 'documento' => '55667788', 'sexo' => 'M', 'status' => 'active',
            'asesor_id' => $otroAsesor->id,
        ]);

        $analista = User::factory()->create(['username' => 'analista-veh']);
        $analista->givePermissionTo(Permission::findOrCreate('clientes.scope-propio', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($analista);

        // Livewire::test no propaga los abort() de mount(), así que se verifica
        // sobre el componente directamente.
        $this->expectException(HttpException::class);
        (new Vehiculos)->mount($c->id);
    }
}
