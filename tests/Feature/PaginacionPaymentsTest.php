<?php

namespace Tests\Feature;

use App\Livewire\Payments\Index;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes. Pagos/Crédito
 * renderizaba a la vez la tabla de escritorio y las tarjetas móviles
 * (100 + 100 filas). Ahora solo sale la versión que se ve, el paginador va
 * arriba y abajo, y al cambiar de página se vuelve al inicio de la lista.
 */
class PaginacionPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private const TABLA = '<table class="table table-bordered table-striped table-hover table-autofit" style="font-size: 11px;">';

    private function mundo(int $creditos): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'pagos-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('pagos');

        $client = Client::create([
            'expediente' => '1001', 'nombre' => 'NOMBREPAGO', 'apellido_pat' => 'PATERNO', 'apellido_mat' => 'MATERNO',
            'tipo_documento' => 'DNI', 'documento' => '40000001', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
        ]);

        // Filas mínimas: el listado no necesita cuotas ni pagos, solo créditos activos.
        foreach (range(1, $creditos) as $n) {
            Credit::create([
                'client_id' => $client->id, 'fecha_prestamo' => '2026-09-01', 'importe' => 1000 + $n,
                'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
                'headquarter_id' => $sede->id,
            ]);
        }
    }

    public function test_en_escritorio_solo_se_renderiza_la_tabla_y_en_movil_solo_las_tarjetas(): void
    {
        $this->mundo(3);

        $comp = Livewire::test(Index::class);
        $comp->assertSeeHtml(self::TABLA)
            ->assertDontSeeHtml('class="card mb-2')     // sin tarjetas
            ->assertSee('NOMBREPAGO');

        $comp->set('movil', true)
            ->assertSeeHtml('class="card mb-2')
            ->assertDontSeeHtml(self::TABLA)
            ->assertSee('NOMBREPAGO');
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(101);

        $comp = Livewire::test(Index::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        // Las comillas del atributo x-on:click salen como entidades HTML.
        $this->assertStringContainsString('closest(&#039;[data-lista]&#039;)', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);

        // Página 2: solo el crédito 101 (ordenados por id asc, 100 por página).
        $comp->call('gotoPage', 2)
            ->assertSeeHtml('Mostrando <b>101</b>–<b>101</b> de <b>101</b>');
    }

    public function test_con_una_sola_pagina_no_hay_paginador_arriba(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Index::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
    }
}
