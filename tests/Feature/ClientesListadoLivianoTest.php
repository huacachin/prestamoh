<?php

namespace Tests\Feature;

use App\Livewire\Clients\Index;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: el listado de clientes renderizaba a la vez la tabla de
 * escritorio y las tarjetas móviles (100 + 100 filas, ~560 KB por cambio de
 * página). Ahora solo sale la versión que se ve, el paginador va arriba y
 * abajo, y al cambiar de página se vuelve al inicio de la lista.
 */
class ClientesListadoLivianoTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(int $clientes): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'lista-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('clientes');

        foreach (range(1, $clientes) as $n) {
            Client::create([
                'expediente' => (string) (1000 + $n), 'nombre' => "NOMBRE{$n}", 'apellido_pat' => 'PATERNO', 'apellido_mat' => 'MATERNO',
                'tipo_documento' => 'DNI', 'documento' => (string) (40000000 + $n), 'sexo' => 'M',
                'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
            ]);
        }
    }

    public function test_en_escritorio_solo_se_renderiza_la_tabla_y_en_movil_solo_las_tarjetas(): void
    {
        $this->mundo(3);

        $comp = Livewire::test(Index::class);
        $comp->assertSeeHtml('<table class="table table-bordered table-striped table-hover table-autofit clients-legacy">')
            ->assertDontSeeHtml('class="card mb-2')     // sin tarjetas
            ->assertSee('NOMBRE1');

        $comp->set('movil', true)
            ->assertSeeHtml('class="card mb-2')
            ->assertDontSeeHtml('<table class="table table-bordered table-striped table-hover table-autofit clients-legacy">')
            ->assertSee('NOMBRE1');
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(101);

        $comp = Livewire::test(Index::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        $this->assertStringContainsString('#lista-clientes', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringNotContainsString("closest('body')", $html);

        $comp->call('gotoPage', 2)->assertSee('NOMBRE101')->assertDontSee('NOMBRE1 ');
    }

    public function test_con_una_sola_pagina_no_hay_paginador_arriba(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Index::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
    }
}
