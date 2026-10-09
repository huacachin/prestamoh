<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 09/10/2026 (Antony, caja2.png en la laptop de 13"): el botón "ir abajo" de
 * los recuadros con scroll propio movía solo el scroll interno; si el recuadro
 * (650 px) no cabe en la ventana, su final quedaba fuera de la vista. Ahora
 * además desplaza la página hasta el final (o el inicio) del recuadro. El
 * handler es compartido (layout/script.blade.php): vale para los 12 botones.
 */
class BotonIrAbajoRecuadroTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_handler_compartido_baja_y_sube_tambien_la_pagina_en_modo_recuadro(): void
    {
        $user = User::factory()->create(['username' => 'scroll-tester']);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('caja.ingresos');
        $this->actingAs($user);

        $html = $this->get(route('cash.incomes'))->assertOk()->getContent();

        // Bajar: scroll interno al final + la página hasta el borde inferior del recuadro.
        $this->assertStringContainsString("el.scrollTop = el.scrollHeight;\n        el.scrollIntoView({ behavior: 'smooth', block: 'end' });", $html);
        // Subir: scroll interno al inicio + la página al inicio del recuadro, bajo la cabecera fija.
        $this->assertStringContainsString("el.scrollTop = 0;\n        // 90 px: la cabecera fija (mismo margen que [data-lista] en app.css).\n        window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 90, behavior: 'smooth' });", $html);
        // Las dos ramas del handler (dirección fija y toggle) usan esas funciones.
        $this->assertStringContainsString("fija === 'up' ? verInicioDelRecuadro() : verFinalDelRecuadro();", $html);
        $this->assertSame(2, substr_count($html, 'verFinalDelRecuadro();'), 'definición aparte: la llamada en la rama toggle');
        $this->assertStringNotContainsString("el.scrollTop = fija === 'up' ? 0 : el.scrollHeight;", $html, 'ya no mueve solo el scroll interno');
        // Y la pantalla de la captura (ingresos) sigue usando el botón en modo recuadro.
        $this->assertStringContainsString('data-scroll-sel="#incomesTable"', $html);
        $this->assertStringContainsString('data-scroll-cont="1"', $html);
    }
}
