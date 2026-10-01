<?php

namespace Tests\Feature;

use App\Livewire\Reports\CashGeneral1;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 01/10/2026: Caja General 1 colgaba las PCs de pocos recursos ("la página
 * no responde"). Un mes en Detalle son ~2 MB de HTML (~9,000 celdas); los
 * selects con wire:model.live re-renderizaban todo con cada cambio, "Consultar"
 * repetía la petición y Livewire morfeaba ese DOM 2–3 veces seguidas. Ahora:
 *  1. los filtros son un formulario GET sin .live: solo "Consultar" dispara;
 *  2. "Consultar", Resumen/Detalle y el clic en un día recargan la página
 *     (parsear HTML nuevo es mucho más barato que diffear el existente);
 *  3. los tooltips se crean bajo demanda, no uno por fila al cargar.
 */
class CajaGeneral1SinMorphTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $sede = Headquarter::create(['name' => 'Sede Caja', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'caja1-tester', 'headquarter_id' => $sede->id]));

        $client = Client::create([
            'expediente' => '9996', 'nombre' => 'CAJA', 'apellido_pat' => 'UNO',
            'tipo_documento' => 'DNI', 'documento' => '49000002', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-01',
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        Payment::create([
            'credit_id' => $credit->id, 'fecha' => '2026-08-15', 'tipo' => 'CAPITAL',
            'documento' => 'CAPITAL', 'monto' => 250, 'detalle' => 'Pago : X Cuota: 1/4',
            'user_id' => auth()->id(), 'headquarter_id' => $sede->id,
        ]);
    }

    public function test_los_filtros_son_un_formulario_get_sin_wire_model_live(): void
    {
        $c = Livewire::withQueryParams(['mes' => '08', 'anio' => '2026'])
            ->test(CashGeneral1::class);

        $c->assertSeeHtml('method="GET"')
            ->assertSeeHtml('name="mes"')
            ->assertSeeHtml('value="08" selected>')
            ->assertSeeHtml('name="anio"')
            ->assertSeeHtml('value="2026" selected>')
            ->assertSeeHtml('name="tipo"')
            ->assertSeeHtml('value="0000" selected>')
            ->assertDontSeeHtml('wire:model')
            ->assertDontSeeHtml('wire:submit')
            ->assertDontSeeHtml('wire:click');

        // El componente ya no expone acciones de Livewire para filtrar.
        $this->assertFalse(method_exists(CashGeneral1::class, 'search'));
        $this->assertFalse(method_exists(CashGeneral1::class, 'verDia'));
    }

    public function test_resumen_detalle_y_el_dia_son_enlaces_que_recargan_la_pagina(): void
    {
        $c = Livewire::withQueryParams(['mes' => '08', 'anio' => '2026', 'vista' => 'resumen'])
            ->test(CashGeneral1::class);

        // Botones de vista: enlaces con los filtros vigentes.
        $c->assertSeeHtml('mes=08&amp;anio=2026&amp;tipo=0000&amp;vista=resumen')
            ->assertSeeHtml('<input type="hidden" name="vista" value="resumen">');

        // La tarjeta del día es un enlace al detalle anclado en ese día.
        $c->assertSeeHtml('mes=08&amp;anio=2026&amp;tipo=0000&amp;dia=2026-08-15')
            ->assertSeeHtml('class="caja1-card');

        // En Detalle el formulario no arrastra vista=resumen.
        Livewire::withQueryParams(['mes' => '08', 'anio' => '2026'])
            ->test(CashGeneral1::class)
            ->assertDontSeeHtml('name="vista"');
    }

    public function test_el_filtro_por_tipo_se_conserva_en_los_enlaces_y_en_el_select(): void
    {
        Livewire::withQueryParams(['mes' => '08', 'anio' => '2026', 'tipo' => '1'])
            ->test(CashGeneral1::class)
            ->assertSeeHtml('value="1" selected>')
            ->assertSeeHtml('mes=08&amp;anio=2026&amp;tipo=1&amp;vista=resumen');
    }

    public function test_los_tooltips_se_crean_bajo_demanda_y_el_grafico_una_sola_vez(): void
    {
        Livewire::withQueryParams(['mes' => '08', 'anio' => '2026'])
            ->test(CashGeneral1::class)
            ->assertSeeHtml('tooltipBajoDemanda')
            ->assertDontSeeHtml("Livewire.hook('commit'")
            ->assertDontSeeHtml('caja1-ver-dia');
    }
}
