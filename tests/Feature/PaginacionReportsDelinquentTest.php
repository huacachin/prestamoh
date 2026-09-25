<?php

namespace Tests\Feature;

use App\Livewire\Reports\Delinquent;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes aplicado a Pendientes
 * por Cobrar (reports/delinquent): el paginador va arriba y abajo de la
 * tabla, y al cambiar de página se vuelve al inicio de la lista (data-lista),
 * no de la página entera.
 *
 * El paginador es manual (LengthAwarePaginator armado en el componente) con
 * 100 cuotas por página; el reporte lista CUOTAS pendientes, así que basta
 * un crédito con N cuotas sin pagar para llenar más de una página.
 */
class PaginacionReportsDelinquentTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(int $cuotas): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'moro-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('reportes.morosidad');

        $client = Client::create([
            'expediente' => '7001', 'nombre' => 'MOROSO', 'apellido_pat' => 'PATERNO', 'apellido_mat' => 'MATERNO',
            'tipo_documento' => 'DNI', 'documento' => '47000001', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-01-05', 'fecha_actualizacion' => '2026-01-05',
            'importe' => 1000, 'cuotas' => $cuotas, 'tipo_planilla' => 4, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        foreach (range(1, $cuotas) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => '2026-01-05', 'fecha_pago' => null,
                'importe_cuota' => 10, 'importe_interes' => 1, 'pagado' => false,
            ]);
        }
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(101);

        $comp = Livewire::test(Delinquent::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        // Las comillas del atributo x-on:click salen como entidades HTML.
        $this->assertStringContainsString('closest(&#039;[data-lista]&#039;)', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);

        // La segunda página trae la cuota 101 (correlativo global N° = 101).
        $comp->call('gotoPage', 2)->assertSeeHtml('<td class="text-center">101</td>')
            ->assertDontSeeHtml('<td class="text-center">1</td>');
    }

    public function test_con_una_sola_pagina_no_hay_paginador_arriba(): void
    {
        $this->mundo(2);

        $html = Livewire::test(Delinquent::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html);
    }
}
