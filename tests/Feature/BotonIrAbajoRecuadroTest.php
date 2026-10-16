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

        // Bajar: la página hasta el FINAL del documento (como el .abajo del legacy) y, al terminar (scrollend o
        // 700 ms), el scroll interno al final, suave; con un remate sin animación si algo cortó la animación (16/10).
        $this->assertStringContainsString("window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });", $html);
        $this->assertStringContainsString("el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });", $html);
        $this->assertStringContainsString("document.addEventListener('scrollend', una);\n        setTimeout(una, 700);", $html);
        $this->assertStringContainsString("if (el.scrollTop < el.scrollHeight - el.clientHeight - 2) el.scrollTop = el.scrollHeight;", $html);
        $this->assertStringContainsString("if (window.scrollY < fin - 2) window.scrollTo(0, fin);", $html);
        // Si el recuadro ya cabe a la vista (pantalla grande), solo se mueve por dentro.
        $this->assertStringContainsString("var cabia = el.getBoundingClientRect().bottom <= window.innerHeight + 1;", $html);
        $this->assertStringContainsString("if (cabia) { dentro(); return; }", $html);
        // Subir: la página al inicio del recuadro, bajo la cabecera fija, y luego el scroll interno al inicio.
        $this->assertStringContainsString("// 90 px: la cabecera fija (mismo margen que [data-lista] en app.css).\n        var destino = el.getBoundingClientRect().top + window.scrollY - 90;", $html);
        $this->assertStringContainsString("window.scrollTo({ top: destino, behavior: 'smooth' });", $html);
        $this->assertStringContainsString("el.scrollTo({ top: 0, behavior: 'smooth' });", $html);
        // Las dos ramas del handler (dirección fija y toggle) usan esas funciones.
        $this->assertStringContainsString("fija === 'up' ? verInicioDelRecuadro() : verFinalDelRecuadro();", $html);
        $this->assertSame(2, substr_count($html, 'verFinalDelRecuadro();'), 'definición aparte: la llamada en la rama toggle');
        $this->assertStringNotContainsString("el.scrollTop = fija === 'up' ? 0 : el.scrollHeight;", $html, 'ya no mueve solo el scroll interno');
        // Y la pantalla de la captura (ingresos) sigue usando el botón en modo recuadro.
        $this->assertStringContainsString('data-scroll-sel="#incomesTable"', $html);
        $this->assertStringContainsString('data-scroll-cont="1"', $html);
    }
}
