<?php

namespace Tests\Feature;

use App\Livewire\Credits\Index;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes (ClientesListadoLivianoTest):
 * el listado de préstamos renderizaba a la vez la tabla de escritorio y las
 * tarjetas móviles (100 + 100 filas). Ahora solo sale la versión que se ve,
 * el paginador va arriba y abajo, y al cambiar de página se vuelve al inicio
 * de la lista (data-lista), no de la página entera.
 */
class PaginacionCreditosTest extends TestCase
{
    use RefreshDatabase;

    private const TABLA = '<table class="table table-bordered table-striped table-hover table-autofit" style="font-size: 11px;">';

    /** Un cliente y $creditos préstamos activos; el importe distingue cada fila (1001, 1002, ...). */
    private function mundo(int $creditos): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'pagina-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('creditos');

        $client = Client::create([
            'expediente' => '9100', 'nombre' => 'CLIENTEPAG', 'apellido_pat' => 'PATERNO', 'apellido_mat' => 'MATERNO',
            'tipo_documento' => 'DNI', 'documento' => '45678901', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
        ]);

        // Misma fecha en todos: el orden queda por id desc, así el crédito 1
        // (importe 1,001.00) es el último y cae solo en la página 2 con 101 filas.
        foreach (range(1, $creditos) as $n) {
            Credit::create([
                'client_id' => $client->id, 'fecha_prestamo' => '2026-09-01', 'importe' => 1000 + $n,
                'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 0, 'situacion' => 'Activo', 'estado' => 1,
                'headquarter_id' => $sede->id, 'user_id' => $user->id,
            ]);
        }
    }

    public function test_en_escritorio_solo_se_renderiza_la_tabla_y_en_movil_solo_las_tarjetas(): void
    {
        $this->mundo(3);

        $comp = Livewire::test(Index::class);
        $comp->assertSeeHtml(self::TABLA)
            ->assertDontSeeHtml('class="card mb-2')     // sin tarjetas
            ->assertSee('CLIENTEPAG');

        $comp->set('movil', true)
            ->assertSeeHtml('class="card mb-2')
            ->assertDontSeeHtml(self::TABLA)
            ->assertSee('CLIENTEPAG');
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
        $this->assertSame(1, substr_count($html, 'data-lista>'), 'un solo envoltorio data-lista');
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);

        // Página 1: los 100 más recientes (importes 1,101.00 .. 1,002.00); página 2: solo el 1,001.00.
        $comp->assertSee('1,101.00')->assertDontSee('1,001.00');
        $comp->call('gotoPage', 2)->assertSee('1,001.00')->assertDontSee('1,101.00');
    }

    public function test_con_una_sola_pagina_no_hay_paginador_arriba(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Index::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html);
    }
}
