<?php

namespace Tests\Feature;

use App\Livewire\Legal\Vehiculos\Index;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes aplicado a los
 * vehículos del Área Legal: el paginador va arriba y abajo de la tabla y al
 * cambiar de página se vuelve al inicio de la lista (data-lista), no de la
 * página entera. Este listado NO duplica tabla y tarjetas móviles, así que
 * no lleva $movil.
 */
class PaginacionLegalVehiculosTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(int $vehiculos): void
    {
        $user = User::factory()->create(['username' => 'vehiculos-pager']);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('legal.garantias');

        // Flota propia: no exige cliente. La placa con ceros a la izquierda
        // mantiene el orden del listado (orderBy placa) predecible.
        foreach (range(1, $vehiculos) as $n) {
            Vehiculo::create([
                'placa' => sprintf('P%05d', $n),
                'propietario_tipo' => 'empresa',
                'marca' => "MARCA{$n}",
                'estado' => 'activo',
            ]);
        }
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(26);

        $comp = Livewire::test(Index::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        // Las comillas del atributo x-on:click salen como entidades HTML.
        $this->assertStringContainsString('closest(&#039;[data-lista]&#039;)', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);
        $this->assertSame(1, substr_count($html, 'data-lista>'), 'un único envoltorio data-lista por componente');

        $comp->call('gotoPage', 2)->assertSee('P00026')->assertDontSee('P00001');
    }

    public function test_con_una_sola_pagina_no_hay_paginador(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Index::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertStringContainsString('P00001', $html);
    }
}
