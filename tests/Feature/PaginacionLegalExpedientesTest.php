<?php

namespace Tests\Feature;

use App\Livewire\Legal\Expedientes\Index;
use App\Models\ExpedienteJudicial;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes aplicado a los
 * expedientes judiciales: el paginador va arriba y abajo de la tabla, y al
 * cambiar de página se vuelve al inicio de la lista (data-lista), no de la
 * página entera. Este blade NO duplica tabla y tarjetas móviles, así que no
 * lleva la bandera $movil.
 */
class PaginacionLegalExpedientesTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(int $expedientes): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'legal-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('legal.judicial');

        foreach (range(1, $expedientes) as $n) {
            // Solo nro_expediente es obligatorio (unique, formato del PJ); el
            // resto de FK son nullable. Cuaderno principal por defecto.
            ExpedienteJudicial::create([
                'nro_expediente' => sprintf('%05d-2024-0-3209-JP-CI-01', $n),
                'exp_interno' => (string) (1000 + $n),
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

        // Orden desc por id: la página 2 solo tiene el expediente 1 (el más antiguo).
        $comp->call('gotoPage', 2)->assertSee('00001-2024-0-3209-JP-CI-01')->assertDontSee('00026-2024-0-3209-JP-CI-01');
    }

    public function test_con_una_sola_pagina_no_hay_paginador(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Index::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html);
    }
}
