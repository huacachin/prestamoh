<?php

namespace Tests\Feature;

use App\Livewire\Legal\Notaria\Index;
use App\Models\Headquarter;
use App\Models\TramiteNotarial;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes aplicado al tablero
 * notarial: el paginador va arriba y abajo de la tabla, y al cambiar de
 * página se vuelve al inicio de la lista (envoltorio data-lista), no de la
 * página entera. Este blade NO duplica tabla y tarjetas móviles, así que no
 * lleva la propiedad $movil.
 */
class PaginacionLegalNotariaTest extends TestCase
{
    use RefreshDatabase;

    /** Filas por página del componente (Index::render → paginate(25)). */
    private const POR_PAGINA = 25;

    private function mundo(int $tramites): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'notaria-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('legal.notaria');

        // Trámite suelto (sin garantía ni cliente): todas las FK son nullable.
        foreach (range(1, $tramites) as $n) {
            TramiteNotarial::create([
                'tipo' => 'carta_notarial',
                'descripcion' => "TRAMITE{$n}",
                'estado' => 'firmado_oficina',
                'estado_desde' => now()->toDateString(),
            ]);
        }
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(self::POR_PAGINA + 1);

        $comp = Livewire::test(Index::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        // Las comillas del atributo x-on:click salen como entidades HTML.
        $this->assertStringContainsString('closest(&#039;[data-lista]&#039;)', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertSame(1, substr_count($html, 'data-lista>'), 'un solo envoltorio data-lista por componente');
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);

        // Orden: abiertos por estado_desde asc y luego id desc → el #1 cae en la página 2.
        $comp->call('gotoPage', 2)->assertSee('TRAMITE1')->assertDontSee('TRAMITE26');
    }

    public function test_con_una_sola_pagina_no_hay_paginador(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Index::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('TRAMITE1', $html);
    }
}
