<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Interés completo" al cancelar (30/09/2026, pedido de Antony, caso 29377):
 * mensual de 1 cuota a 2 días del vencimiento. Antes del primer vencimiento el
 * interés "a la fecha" es 0, así que el modo estricto recortaba los 3,150 a
 * 3,000 y condonaba el mes entero. Con el switch (el mismo "Cancelar hasta la
 * última cuota" de la tarjeta) el mínimo pasa a ser el cronograma completo, y
 * se puede elegir desde el modal de confirmación o junto a "Cancelado".
 */
class InteresCompletoAlCancelarTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester-interes-completo', 'headquarter_id' => 1]);
    }

    /**
     * Mensual, 1 cuota de 3,000 + 150 que vence en 2 días. Hoy: cancelar =
     * 3,000 (interés devengado 0); el cronograma completo suma 3,150.
     */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Mensual']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(28)->format('Y-m-d'),
            'importe' => 3000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5,
            'interes_total' => 150, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->addDays(2)->format('Y-m-d'),
            'importe_cuota' => 3000, 'importe_interes' => 150, 'pagado' => 0,
        ]);

        return $credit;
    }

    public function test_antes_del_primer_vencimiento_el_interes_a_la_fecha_es_cero_y_recorta_al_capital(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 3150)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si');

        $this->assertEqualsWithDelta(3000.00, (float) $c->get('monto'), 0.001);
        $this->assertSame('3150', (string) $c->get('montoTecleado'));
        $p = $c->get('preview');
        $this->assertEqualsWithDelta(0.00, $p['interes'], 0.01);
        $this->assertEqualsWithDelta(3000.00, $p['monto_si_cancela'], 0.01);
        $this->assertEqualsWithDelta(150.00, $p['condona_si_cancela'], 0.01);
        $this->assertEqualsWithDelta(3150.00, $p['monto_interes_completo'], 0.01);

        // El modal ofrece cobrar el cronograma completo ahí mismo.
        $c->assertSee('Solo se cobra lo necesario')
            ->assertSee('Cobrar el interés completo del cronograma')
            ->assertSee('total S/ 3,150.00');
    }

    public function test_marcar_interes_completo_en_el_modal_devuelve_los_3150_y_desmarcarlo_los_recorta(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 3150)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si')
            ->set('cancelUltimaCuota', true);

        $this->assertEqualsWithDelta(3150.00, (float) $c->get('monto'), 0.001);
        $this->assertNull($c->get('montoTecleado'));
        $p = $c->get('preview');
        $this->assertEqualsWithDelta(150.00, $p['interes'], 0.01);
        $this->assertEqualsWithDelta(3150.00, $p['total'], 0.01);
        $this->assertEqualsWithDelta(3150.00, $p['monto_si_cancela'], 0.01);
        $this->assertEqualsWithDelta(0.00, $p['condona_si_cancela'], 0.01);
        $this->assertTrue($p['hasta_ultima_cuota']);
        $c->assertDontSee('Solo se cobra lo necesario');

        // Cambia de idea: vuelve el recorte, con lo tecleado para el aviso.
        $c->set('cancelUltimaCuota', false);
        $this->assertEqualsWithDelta(3000.00, (float) $c->get('monto'), 0.001);
        $this->assertSame('3150', (string) $c->get('montoTecleado'));
        $this->assertEqualsWithDelta(0.00, $c->get('preview')['interes'], 0.01);
    }

    public function test_marcar_interes_completo_sin_haber_respondido_vale_como_si_cancelar(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // Pagar con 3,150 y sin tocar el switch: el modal pregunta, y la casilla
        // ya está a la vista.
        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 3150)
            ->call('confirmarPago');
        $this->assertSame('', $c->get('decisionTotal'));
        $c->assertSee('Cobrar el interés completo del cronograma');

        $c->set('cancelUltimaCuota', true);
        $this->assertSame('si', $c->get('decisionTotal'));
        $this->assertTrue($c->get('cancel'));
        $this->assertEqualsWithDelta(3150.00, (float) $c->get('monto'), 0.001);
        $this->assertNull($c->get('montoTecleado'));
        $p = $c->get('preview');
        $this->assertTrue($p['cancela']);
        $this->assertEqualsWithDelta(150.00, $p['interes'], 0.01);
        $this->assertEqualsWithDelta(3150.00, $p['total'], 0.01);

        // Con "No" la casilla desaparece: no tiene sentido sin cancelar.
        $c->set('decisionTotal', 'no');
        $c->assertDontSee('Cobrar el interés completo del cronograma');
    }

    public function test_con_3000_tecleados_interes_completo_sube_a_3150_y_lo_avisa(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 3000)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si');
        $this->assertNull($c->get('montoTecleado'));   // 3,000 no se recorta

        $c->set('cancelUltimaCuota', true);
        $this->assertEqualsWithDelta(3150.00, (float) $c->get('monto'), 0.001);
        $this->assertSame('3000', (string) $c->get('montoTecleado'));
        $this->assertEqualsWithDelta(3150.00, $c->get('preview')['total'], 0.01);
        $c->assertSee('Con interés completo se cobra el cronograma entero');

        // Desmarcar devuelve los 3,000 tecleados, sin aviso.
        $c->set('cancelUltimaCuota', false);
        $this->assertEqualsWithDelta(3000.00, (float) $c->get('monto'), 0.001);
        $this->assertNull($c->get('montoTecleado'));
    }

    public function test_con_interes_completo_el_minimo_para_cancelar_es_el_cronograma_entero(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // El switch "Cancelado" de la pantalla exige 3,150 (no 3,000).
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('cancelUltimaCuota', true)
            ->set('monto', 3000)
            ->assertSee('interés completo: 3,150.00')
            ->assertSee('todo el cronograma: 3,150.00');

        // Y el servidor también: 3,100 con el switch no cancela.
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('cancelUltimaCuota', true)
            ->set('monto', 3150)
            ->set('cancel', true)
            ->set('decisionTotal', 'si')
            ->set('monto', 3100)
            ->call('pagar')
            ->assertDispatched('errorAlert', fn ($name, $params) => str_contains($params['message'] ?? ($params[0]['message'] ?? ''), 'todo el cronograma (3,150.00)'));

        $this->assertSame(0, Payment::where('credit_id', $credit->id)->count());
        $this->assertSame('Activo', $credit->fresh()->situacion);
    }

    public function test_cobrar_con_interes_completo_registra_los_150_de_interes_sin_condonar(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('cancelUltimaCuota', true)
            ->set('monto', 3000)
            ->set('cancel', true)
            ->set('decisionTotal', 'si')   // sube a 3,150 (interés completo)
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertEqualsWithDelta(3000.00, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'CAPITAL')->sum('monto'), 0.01);
        $this->assertEqualsWithDelta(150.00, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'INTERES')->sum('monto'), 0.01);
        $this->assertSame('Cancelado', $credit->fresh()->situacion);

        $cuota = CreditInstallment::where('credit_id', $credit->id)->first();
        $this->assertEqualsWithDelta(150.00, (float) $cuota->importe_interes, 0.01);   // nada condonado
        $this->assertEqualsWithDelta(150.00, (float) $cuota->interes_aplicado, 0.01);
        $this->assertSame(1, (int) $cuota->pagado);
        $this->assertDatabaseMissing('activity_log', ['description' => "Cancelación anticipada del crédito {$credit->id}: descuento de interés no devengado 150.00"]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Registró pago de 3150.00 en el crédito #'.$credit->id.' (canceló el crédito) — ajustado desde 3000: cronograma completo (interés completo)']);
    }

    public function test_sin_interes_completo_cancelar_hoy_sigue_condonando_los_150(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 3000)
            ->set('cancel', true)
            ->set('decisionTotal', 'si')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertEqualsWithDelta(0.00, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'INTERES')->sum('monto'), 0.01);
        $this->assertSame('Cancelado', $credit->fresh()->situacion);
        $this->assertEqualsWithDelta(0.00, (float) CreditInstallment::where('credit_id', $credit->id)->value('importe_interes'), 0.01);
        $this->assertDatabaseHas('activity_log', ['description' => "Cancelación anticipada del crédito {$credit->id}: descuento de interés no devengado 150.00"]);
    }

    public function test_la_tarjeta_explica_que_antes_del_primer_vencimiento_no_hay_interes_devengado(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->assertSee('Aún no vence ninguna cuota')
            ->assertSee('Interés completo (cancelar hasta la última cuota)')
            ->assertDontSee('Te adelantas');
    }
}
