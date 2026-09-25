<?php

namespace Tests\Feature;

use App\Livewire\Legal\Papeletas\Index;
use App\Models\Papeleta;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes aplicado a las
 * papeletas de tránsito: el paginador va arriba y abajo de la tabla y al
 * cambiar de página se vuelve al inicio de la lista (data-lista), no de la
 * página entera. Este listado no duplica tabla/tarjetas móviles, así que no
 * lleva $movil.
 */
class PaginacionLegalPapeletasTest extends TestCase
{
    use RefreshDatabase;

    /** Usuario con legal.papeletas y N papeletas mínimas sobre un vehículo */
    private function mundo(int $papeletas): void
    {
        $user = User::factory()->create(['username' => 'pap-tester']);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('legal.papeletas');

        $vehiculo = Vehiculo::create(['placa' => 'TST-001']);

        foreach (range(1, $papeletas) as $n) {
            Papeleta::create([
                'vehiculo_id' => $vehiculo->id,
                'entidad' => 'SAT',
                'nro_papeleta' => sprintf('M%06d', $n),
                'fecha_infraccion' => now()->subDays($n)->toDateString(),
            ]);
        }
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(26); // porPagina = 25

        $comp = Livewire::test(Index::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        // Las comillas del atributo x-on:click salen como entidades HTML.
        $this->assertStringContainsString('closest(&#039;[data-lista]&#039;)', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);

        // Orden: fecha_infraccion DESC → M000001 (la más reciente) va primero
        // y M000026 (la más antigua) cae a la página 2.
        $comp->assertSee('M000001')->assertDontSee('M000026');
        $comp->call('gotoPage', 2)->assertSee('M000026')->assertDontSee('M000001');
    }

    public function test_con_una_sola_pagina_no_hay_paginador_ni_arriba_ni_abajo(): void
    {
        $this->mundo(3);

        $html = Livewire::test(Index::class)->html();

        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html, 'el envoltorio existe aunque no haya paginador');
    }
}
