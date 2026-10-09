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
 * 10/10/2026 (Antony): en el simulador, el Detalle de Pago se abre en
 * VENTANITAS movibles, varias a la vez (una por mes) para comparar sin perder
 * la anterior; y debajo de cada columna (mensual, semanal, diario) un botón
 * que copia al portapapeles "24 Cuotas semanales de 379.20", con la cuota
 * redondeada hacia arriba a 0.10 (379.18 → 379.20), como la cuota uniforme
 * del cronograma real.
 */
class SimuladorCopiarCuotasTest extends TestCase
{
    use RefreshDatabase;

    private function html(): string
    {
        $this->actingAs(User::factory()->create(['username' => 'sim-tester']));

        return Livewire::test(Simulator::class)
            ->set('nombre', 'Prueba')->set('capital', 10000)->set('interes', 10)
            ->call('simulate')
            ->assertDispatched('simulacion-nueva')
            ->html();
    }

    public function test_los_detalles_son_ventanitas_movibles_y_se_abren_varias_a_la_vez(): void
    {
        $html = $this->html();

        // Cada "Pagar" abre una ventana (no reemplaza la anterior); las ventanas salen de un x-for.
        $this->assertSame(60, substr_count($html, '@click.prevent="abrir('));
        $this->assertStringNotContainsString('openDetalle(', $html);
        $this->assertStringContainsString('<template x-for="v in ventanas" :key="v.id">', $html);
        $this->assertStringContainsString('x-data="ventanaLibre({ x: v.x, y: v.y, w: 440 })" class="card shadow sim-ventana"', $html);
        $this->assertStringContainsString('x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"', $html, 'se arrastra desde la cabecera');
        $this->assertStringContainsString(':style="{ zIndex: v.z }" x-on:pointerdown="alFrente(v)"', $html, 'la que se toca pasa al frente');
        $this->assertStringContainsString('Detalle de Pago · Mes <span x-text="v.meses"></span>', $html);
        $this->assertStringContainsString('x-on:click.prevent="cerrar(v.id)"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="cerrarUltima()"', $html);
        $this->assertStringContainsString('x-on:simulacion-nueva.window="cerrarTodas()"', $html, 'Procesar cierra las de la simulación anterior');
        $this->assertStringContainsString('Cerrar ventanas (<span x-text="ventanas.length"></span>)', $html);
        $this->assertStringContainsString('.sim-ventana { position: fixed; width: 440px;', $html);
        $this->assertStringNotContainsString('background:rgba(0,0,0,.5)', $html, 'sin fondo oscuro: la tabla sigue viva');
        $this->assertStringContainsString("window.Alpine.data('ventanaLibre', ventanaLibre)", file_get_contents(public_path('assets/js/ventanas-flotantes.js')));
    }

    public function test_cada_ventana_lleva_un_boton_copiar_por_columna_con_el_texto_y_el_redondeo(): void
    {
        $html = $this->html();

        $this->assertSame(3, substr_count($html, 'class="btn btn-xs btn-outline-primary copiar-cuotas"'));
        foreach (['mensual', 'semanal', 'diario'] as $tipo) {
            $this->assertStringContainsString("x-on:click=\"copiar(v, '{$tipo}')\"", $html);
        }
        $this->assertStringContainsString("x-text=\"copiado === v.id + ':semanal' ? 'Copiado' : 'Copiar'\"", $html);

        // El x-data llega entero al navegador (sin comillas dobles adentro) con la cascada, el redondeo y las palabras.
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $raiz = $xp->query('//div[contains(@class, "container-fluid") and @x-data]')->item(0);
        $this->assertNotNull($raiz);
        $xdata = $raiz->getAttribute('x-data');
        $this->assertStringContainsString('const ya = this.ventanas.find(v => v.meses === meses);', $xdata, 'el mismo mes no se duplica: viene al frente');
        $this->assertStringContainsString('x: 90 + k * 30, y: 90 + k * 30, z: ++this.topZ', $xdata, 'en cascada');
        $this->assertStringContainsString('redondear(v) { return Math.ceil(Math.round(v / 0.10 * 1000000) / 1000000) * 0.10; }', $xdata, 'hacia arriba a 0.10');
        $this->assertStringContainsString("'mensual' : 'mensuales'", $xdata);
        $this->assertStringContainsString("'semanal' : 'semanales'", $xdata);
        $this->assertStringContainsString("'diaria' : 'diarias'", $xdata);
        $this->assertStringContainsString("(n === 1 ? 'Cuota' : 'Cuotas') + ' ' + adj + ' de ' + this.fmt(this.redondear(c))", $xdata);
        $this->assertStringContainsString('navigator.clipboard.writeText(t)', $xdata);
        $this->assertStringNotContainsString('"', $xdata);
    }
}
