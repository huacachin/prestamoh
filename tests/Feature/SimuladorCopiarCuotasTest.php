<?php

namespace Tests\Feature;

use App\Livewire\Reports\Simulator;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): en el Detalle de Pago del simulador, debajo de cada
 * columna (mensual, semanal, diario) un botón que copia al portapapeles
 * "24 Cuotas semanales de 379.20", con la cuota redondeada hacia arriba a
 * 0.10 (379.18 → 379.20), como la cuota uniforme del cronograma real.
 */
class SimuladorCopiarCuotasTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_detalle_lleva_un_boton_copiar_por_columna_con_el_texto_y_el_redondeo(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'sim-tester']));
        $html = Livewire::test(Simulator::class)
            ->set('nombre', 'Prueba')->set('capital', 10000)->set('interes', 10)
            ->call('simulate')
            ->html();

        // Tres botones, uno por columna, y la función que arma el texto.
        $this->assertSame(3, substr_count($html, 'class="btn btn-xs btn-outline-primary copiar-cuotas"'));
        foreach (['mensual', 'semanal', 'diario'] as $tipo) {
            $this->assertStringContainsString("@click=\"copiar('{$tipo}')\"", $html);
        }
        $this->assertStringContainsString("x-text=\"copiado === 'semanal' ? 'Copiado' : 'Copiar'\"", $html);

        // El x-data llega entero al navegador (sin comillas dobles adentro) con el redondeo y las palabras.
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $raiz = $xp->query('//div[contains(@class, "container-fluid") and @x-data]')->item(0);
        $this->assertNotNull($raiz);
        $xdata = $raiz->getAttribute('x-data');
        $this->assertStringContainsString('redondear(v) { return Math.ceil(Math.round(v / 0.10 * 1000000) / 1000000) * 0.10; }', $xdata, 'hacia arriba a 0.10');
        $this->assertStringContainsString("'mensual' : 'mensuales'", $xdata);
        $this->assertStringContainsString("'semanal' : 'semanales'", $xdata);
        $this->assertStringContainsString("'diaria' : 'diarias'", $xdata);
        $this->assertStringContainsString("(n === 1 ? 'Cuota' : 'Cuotas') + ' ' + adj + ' de ' + this.fmt(this.redondear(c))", $xdata);
        $this->assertStringContainsString('navigator.clipboard.writeText(t)', $xdata);
        $this->assertStringNotContainsString('"', $xdata);
    }
}
