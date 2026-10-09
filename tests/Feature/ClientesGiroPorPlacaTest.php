<?php

namespace Tests\Feature;

use App\Livewire\Clients\Index;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): en /clients el filtro Giro encuentra al cliente también
 * por la placa de cualquier vehículo que tenga registrado (pestaña Vehículos)
 * o en el que sea copropietario, no solo por la placa tipeada en el giro
 * ("Com.-AWI132"); sin guiones, espacios ni mayúsculas. Y al pasar el mouse
 * por el giro salen todas las placas de sus vehículos. El Excel filtra igual.
 */
class ClientesGiroPorPlacaTest extends TestCase
{
    use RefreshDatabase;

    private Client $conGiro;

    private Client $conVehiculo;

    private Client $copro;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'giro-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['clientes']);

        $crear = fn (string $exp, string $nombre, string $doc, ?string $giro) => Client::create([
            'expediente' => $exp, 'nombre' => $nombre, 'apellido_pat' => 'Cliente', 'apellido_mat' => 'Test',
            'tipo_documento' => 'DNI', 'documento' => $doc, 'sexo' => 'M', 'status' => 'active',
            'giro' => $giro, 'headquarter_id' => $sede->id, 'asesor_id' => $user->id,
        ]);
        $this->conGiro = $crear('101', 'Giro', '41000101', 'Com.-AWI132');      // placa solo en el giro
        $this->conVehiculo = $crear('102', 'Vehiculo', '41000102', 'Cus.-X');     // placa solo en Vehículos
        $this->copro = $crear('103', 'Copro', '41000103', null);                 // copropietario de un vehículo ajeno
        $crear('104', 'Nadie', '41000104', 'Trai.-ZZZ999');

        Vehiculo::create(['client_id' => $this->conVehiculo->id, 'placa' => 'B8T492', 'marca' => 'HYUNDAI', 'modelo' => 'H1', 'valor' => 20000]);
        $v2 = Vehiculo::create(['client_id' => $this->conVehiculo->id, 'placa' => 'T1T779', 'marca' => 'TOYOTA', 'valor' => 15000]);
        $v2->copropietarios()->attach($this->copro->id, ['rol' => 'copropietario']);
    }

    public function test_el_filtro_giro_encuentra_por_el_texto_del_giro_o_por_la_placa_de_sus_vehiculos(): void
    {
        $this->mundo();
        $busca = fn (string $texto) => Livewire::test(Index::class)->set('giro', $texto);

        // Como antes: por el texto del giro (y "Com" sigue sacando las combis).
        $busca('AWI132')->assertSee('Cliente Test Giro')->assertDontSee('Cliente Test Vehiculo')->assertDontSee('Cliente Test Copro');
        $busca('Com')->assertSee('Cliente Test Giro')->assertDontSee('Cliente Test Nadie');

        // Por la placa de un vehículo registrado en la pestaña Vehículos, aunque el giro no la lleve.
        $busca('B8T492')->assertSee('Cliente Test Vehiculo')->assertDontSee('Cliente Test Giro')
            ->assertDontSeeHtml(route('clients.edit', $this->copro->id)); // el nombre del copropietario solo aparece en el tooltip del titular
        // Sin guiones, espacios ni mayúsculas, y también contra el giro.
        $busca('b8t-492')->assertSee('Cliente Test Vehiculo');
        $busca('awi 132')->assertSee('Cliente Test Giro');
        // Por una placa en la que es copropietario: sale el titular y el copropietario.
        $busca('T1T779')->assertSee('Cliente Test Vehiculo')->assertSee('Cliente Test Copro')->assertDontSee('Cliente Test Giro');
        // Lo que no existe no trae nada.
        $busca('QQQ000')->assertDontSee('Cliente Test');
    }

    public function test_al_pasar_el_mouse_por_el_giro_salen_todas_las_placas_del_cliente(): void
    {
        $this->mundo();
        $html = Livewire::test(Index::class)->html();

        // Tooltip Bootstrap en la celda del giro, con las placas y la marca; el copropietario marcado.
        $this->assertStringContainsString('title="&lt;b&gt;2 vehículos:&lt;/b&gt;&lt;br&gt;B8T492 &lt;small&gt;HYUNDAI H1&lt;/small&gt;&lt;br&gt;T1T779 &lt;small&gt;TOYOTA&lt;/small&gt;"', $html);
        $this->assertStringContainsString('title="&lt;b&gt;Vehículo:&lt;/b&gt;&lt;br&gt;T1T779 &lt;small&gt;TOYOTA · copropietario&lt;/small&gt;"', $html);
        $this->assertMatchesRegularExpression('/<td class="text-center"\s+data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" data-bs-custom-class="tip-copro" title="[^"]*" style="cursor: help;"\s*>Cus\.-X<\/td>/', $html);
        // Sin vehículos, el giro va sin tooltip.
        $this->assertMatchesRegularExpression('/<td class="text-center"\s*>Com\.-AWI132<\/td>/', $html);
        $this->assertMatchesRegularExpression('/<td class="text-center"\s*>Trai\.-ZZZ999<\/td>/', $html);
        // El tooltip bajo demanda cubre todos los del listado (giro e icono de copropietarios).
        $this->assertStringContainsString("e.target.closest('[wire\\\\:name=\"clients\"] [data-bs-toggle=\"tooltip\"]')", $html);
    }

    public function test_el_excel_filtra_por_giro_con_el_mismo_criterio(): void
    {
        $this->mundo();

        $this->get(route('exports.clients', ['giro' => 'b8t-492']))->assertOk();
        $fuente = file_get_contents(app_path('Http/Controllers/ClientController.php'));
        $this->assertStringContainsString('$query->giroOPlaca($giro);', $fuente);
        $this->assertStringNotContainsString("where('giro', 'like'", $fuente);

        // El scope, directo: mismos resultados que la pantalla.
        $ids = fn (string $t) => Client::query()->giroOPlaca($t)->pluck('expediente')->sort()->values()->all();
        $this->assertSame(['102'], $ids('b8t-492'));
        $this->assertSame(['102', '103'], $ids('T1T779'));
        $this->assertSame(['101'], $ids('awi 132'));
    }
}
