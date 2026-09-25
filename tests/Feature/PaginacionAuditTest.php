<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index as AuditIndex;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 25/09/2026: mismo patrón que el listado de clientes aplicado al visor de
 * Auditoría: el paginador va arriba y abajo de la tabla, y al cambiar de
 * página se vuelve al inicio de la lista (data-lista), no de la página entera.
 * El visor no duplica tabla/tarjetas, así que no lleva $movil.
 */
class PaginacionAuditTest extends TestCase
{
    use RefreshDatabase;

    /** El componente pagina de a 30 (Index::render). */
    private const POR_PAGINA = 30;

    private function mundo(int $registros): void
    {
        $user = User::factory()->create(['username' => 'audit-tester']);
        $this->actingAs($user);

        foreach (range(1, $registros) as $n) {
            Audit::log("Creó el registro de prueba #{$n}");
        }
    }

    public function test_con_mas_de_una_pagina_el_paginador_va_arriba_y_abajo_y_vuelve_al_inicio_de_la_lista(): void
    {
        $this->mundo(self::POR_PAGINA + 1);

        $comp = Livewire::test(AuditIndex::class);
        $html = $comp->html();

        $this->assertSame(2, substr_count($html, 'lw-pager-list'), 'un paginador arriba y otro abajo');
        // Las comillas del atributo x-on:click salen como entidades HTML.
        $this->assertStringContainsString('closest(&#039;[data-lista]&#039;)', $html, 'los clics del paginador vuelven al inicio de la lista, no de la página');
        $this->assertStringContainsString('<div data-lista>', $html);
        $this->assertStringNotContainsString('closest(&#039;body&#039;)', $html);

        // Los 31 se crean en el mismo segundo y latest() no desempata por id,
        // así que solo se comprueba el tamaño de cada página, no qué fila cae.
        $this->assertCount(self::POR_PAGINA, $comp->viewData('logs'));
        $comp->call('gotoPage', 2);
        $this->assertCount(1, $comp->viewData('logs'));
        $this->assertSame(2, substr_count($comp->html(), 'lw-pager-list'), 'en la página 2 siguen los dos paginadores');
    }

    public function test_con_una_sola_pagina_no_hay_paginador_arriba_ni_abajo(): void
    {
        $this->mundo(2);

        $html = Livewire::test(AuditIndex::class)->html();
        $this->assertSame(0, substr_count($html, 'lw-pager-list'));
        $this->assertStringContainsString('<div data-lista>', $html, 'el envoltorio existe aunque no haya paginador');
    }
}
