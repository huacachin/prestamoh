<?php

namespace Tests\Feature;

use App\Livewire\Legal\Garantias\Index;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes — el paginador va
 * arriba y abajo de la tabla de garantías, y al cambiar de página se vuelve
 * al inicio de la lista (data-lista), no de la página entera.
 */
class PaginacionLegalGarantiasTest extends TestCase
{
    use RefreshDatabase;

    /** Un cliente, un crédito y N garantías (credit_id no es único: basta uno). */
    private function mundo(int $garantias): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'legal-pager', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('legal.garantias');

        $client = Client::create([
            'nombre' => 'JUAN', 'apellido_pat' => 'PEREZ', 'apellido_mat' => 'GARANTIA',
            'tipo_documento' => 'DNI', 'documento' => '45678912', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id,
            'fecha_prestamo' => now()->toDateString(),
            'importe' => 10000, 'cuotas' => 12, 'tipo_planilla' => 3,
            'user_id' => $user->id, 'headquarter_id' => $sede->id,
        ]);

        foreach (range(1, $garantias) as $n) {
            Garantia::create([
                'credit_id' => $credit->id,
                'client_id' => $client->id,
                'monto_gravamen' => 1000 + $n,
                'registrado_por' => $user->id,
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

        // Orden: id DESC → la garantía 1 (gravamen 1,001.00) queda sola en la página 2.
        $comp->call('gotoPage', 2)->assertSee('1,001.00')->assertDontSee('1,026.00');
    }

    public function test_con_una_sola_pagina_no_hay_paginador(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Index::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html);
    }
}
