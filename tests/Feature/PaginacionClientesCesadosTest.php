<?php

namespace Tests\Feature;

use App\Livewire\Clients\Ceased;
use App\Models\Client;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes (ClientesListadoLivianoTest)
 * aplicado a Clientes Cesados: solo se renderiza la tabla o las tarjetas (según
 * $movil), el paginador va arriba y abajo, y al cambiar de página se vuelve al
 * inicio de la lista (data-lista), no de la página entera.
 */
class PaginacionClientesCesadosTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(int $clientes): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'cesados-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('registro.cesados');

        foreach (range(1, $clientes) as $n) {
            Client::create([
                'expediente' => (string) (1000 + $n), 'nombre' => "CESADO{$n}", 'apellido_pat' => 'PATERNO', 'apellido_mat' => 'MATERNO',
                'tipo_documento' => 'DNI', 'documento' => (string) (40000000 + $n), 'sexo' => 'M',
                'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'inactive',
            ]);
        }
    }

    public function test_en_escritorio_solo_se_renderiza_la_tabla_y_en_movil_solo_las_tarjetas(): void
    {
        $this->mundo(3);

        $comp = Livewire::test(Ceased::class);
        $comp->assertSeeHtml('<table class="table table-bordered table-striped table-hover table-autofit" style="font-size: 11px;">')
            ->assertDontSeeHtml('class="card mb-2')     // sin tarjetas
            ->assertSee('CESADO1');

        $comp->set('movil', true)
            ->assertSeeHtml('class="card mb-2')
            ->assertDontSeeHtml('<table class="table table-bordered table-striped table-hover table-autofit" style="font-size: 11px;">')
            ->assertSee('CESADO1');
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(101);

        $comp = Livewire::test(Ceased::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        // Las comillas del atributo x-on:click salen como entidades HTML.
        $this->assertStringContainsString('closest(&#039;[data-lista]&#039;)', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertSame(1, substr_count($html, 'data-lista>'), 'un solo envoltorio data-lista');
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);

        $comp->call('gotoPage', 2)->assertSee('CESADO101')->assertDontSee('CESADO2');
    }

    public function test_con_una_sola_pagina_no_hay_paginador_arriba(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Ceased::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html);
    }
}
