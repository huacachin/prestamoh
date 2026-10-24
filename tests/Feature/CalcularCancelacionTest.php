<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\MassDeletion;
use App\Models\Payment;
use App\Models\User;
use App\Services\Printing\TicketPrinter;
use App\Support\CancelacionHastaCuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Calcular cancelación" (23/10/2026, Antony, crédito 28603): el cajero elige hasta
 * qué cuota se cobra el interés y el total para cancelar es
 * interés por cuota × N + capital del crédito − monto pagado. Va al Monto a Pagar
 * con Cancelado encendido y sigue el cobro normal.
 */
class CalcularCancelacionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester-calcular-cancelacion', 'headquarter_id' => 1]);
    }

    /**
     * Semanal de 1,000 en 10 cuotas de 100 + 20 de interés. La cuota 7 vence hoy.
     * Pagadas la 1 a la 4; la 5 tiene 5 de interés abonado. Pagado: 485.
     *
     * @param  float  $capitalUltima  capital de la cuota 10 (100.32 = cronograma con 0.32 de más, como el 28603)
     * @param  array{0: float, 1: float}  $aplicadoCuota5  [capital, interés] ya aplicados a la cuota 5
     */
    private function credito(float $capitalUltima = 100.0, array $aplicadoCuota5 = [0.0, 5.0]): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Semanal']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subWeeks(7)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 10, 'tipo_planilla' => 1, 'interes' => 20,
            'interes_total' => 200, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 10) as $n) {
            $pagada = $n <= 4;
            [$capital5, $interes5] = $n === 5 ? $aplicadoCuota5 : [0.0, 0.0];
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addWeeks($n - 7)->format('Y-m-d'),
                'importe_cuota' => $n === 10 ? $capitalUltima : 100, 'importe_interes' => 20,
                'importe_aplicado' => $pagada ? 100 : $capital5, 'interes_aplicado' => $pagada ? 20 : $interes5,
                'pagado' => $pagada ? 1 : 0,
            ]);
        }

        return $credit;
    }

    public function test_el_total_es_interes_por_cuota_por_n_mas_capital_menos_lo_pagado(): void
    {
        $plan = CancelacionHastaCuota::plan($this->credito());

        $this->assertSame([20.0, 1000.0, 485.0, 4, 10, 7], [$plan['interes_cuota'], $plan['capital'], $plan['pagado'], $plan['pagadas'], $plan['maximo'], $plan['vencidas']]);
        // La cuota 5 ya tiene interés abonado: el mínimo es la 5, no la 4 (las pagadas).
        $this->assertSame(5, $plan['minimo']);
        $this->assertSame(675.0, CancelacionHastaCuota::monto($plan, 8));   // 20 × 8 + 1,000 − 485
        $this->assertSame(715.0, CancelacionHastaCuota::monto($plan, 10));  // todas: el cronograma entero
    }

    public function test_usar_el_calculo_lleva_el_total_al_monto_y_enciende_cancelado(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirCancelacion')
            ->assertReturned(fn ($plan) => $plan['minimo'] === 5 && $plan['maximo'] === 10 && $plan['sugerida'] === 7)
            ->call('aplicarCancelacion', 8)
            ->assertReturned(true)
            ->assertSet('monto', '675.00')
            ->assertSet('cancel', true)
            ->assertSet('cancelHasta', 8)
            ->assertDispatched('monto-usado')
            ->assertSee('cancela · interés hasta la cuota 8');
    }

    public function test_no_deja_elegir_una_cuota_fuera_del_rango(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        foreach ([4, 11] as $cuota) {
            Livewire::test(Create::class, ['creditId' => $credit->id])
                ->call('aplicarCancelacion', $cuota)
                ->assertReturned(false)
                ->assertDispatched('errorAlert', fn ($name, $params) => ($params['message'] ?? ($params[0]['message'] ?? '')) === 'Elige una cuota entre la 5 y la 10.')
                ->assertSet('monto', null)
                ->assertSet('cancel', false)
                ->assertSet('cancelHasta', null);
        }
    }

    public function test_cobrar_cancela_el_credito_con_el_monto_del_calculo_y_condona_el_interes_que_sobra(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->call('confirmarPago');

        // El modo estricto no lo baja al interés a la fecha: 675 se queda.
        $this->assertSame('675.00', $c->get('monto'));
        $p = $c->get('preview');
        $this->assertTrue($p['cancela']);
        $this->assertSame(['cuota' => 8, 'interes_cuota' => 20.0, 'interes' => 160.0, 'capital' => 1000.0, 'pagado' => 485.0, 'total' => 675.0, 'ultima' => 10, 'exonerado' => 40.0], $p['cancelacion_hasta']);
        $this->assertEqualsWithDelta(40.00, $p['condona_si_cancela'], 0.001);
        $c->assertSee('Con interés hasta la cuota')
            ->assertSee('Interés hasta la cuota 8 (S/ 20.00 × 8)')
            // La vista previa del recibo ya trae las líneas que llevará el voucher.
            ->assertSee('Cancelación hasta la cuota 8')
            ->assertSee("Se exonera el interés de las cuotas\u{00A0}9\u{00A0}y\u{00A0}10")
            ->assertSee('Interés exonerado:');

        $c->call('pagar')->assertNotDispatched('errorAlert');

        $this->assertSame('Cancelado', $credit->fresh()->situacion);
        $this->assertEqualsWithDelta(600.00, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'CAPITAL')->sum('monto'), 0.001);
        $this->assertEqualsWithDelta(75.00, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'INTERES')->sum('monto'), 0.001);
        $this->assertSame(0, CreditInstallment::where('credit_id', $credit->id)->where('pagado', 0)->count());
        // Se condona el interés de las cuotas 9 y 10.
        $this->assertSame([0.0, 0.0], CreditInstallment::where('credit_id', $credit->id)->whereIn('num_cuota', [9, 10])->pluck('importe_interes')->map(fn ($v) => (float) $v)->all());
        $this->assertDatabaseHas('activity_log', ['description' => "Cancelación anticipada del crédito {$credit->id}: descuento de interés no devengado 40.00 (interés hasta la cuota 8)"]);
        $this->assertDatabaseHas('activity_log', ['description' => "Registró pago de 675.00 en el crédito #{$credit->id} (canceló el crédito) con interés hasta la cuota 8"]);
        $c->assertSet('cancelHasta', null);
    }

    /** 23/10 (Antony): "en el voucher hay que indicar que se está cancelando hasta la cuota 50 y que las demás cuotas se están exonerando". */
    public function test_el_recibo_dice_hasta_que_cuota_se_cobro_el_interes_y_lo_exonerado(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->call('confirmarPago')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();
        $this->assertSame([8, '40.00'], [$masivo->hasta_cuota, $masivo->interes_exonerado]);

        // 23/10 (Antony: "debe aparecer solo las cuotas que estoy colocando"): una por una hasta la 8 y
        // el capital de la 9 y la 10 en una sola línea.
        $this->get(URL::signedRoute('recibo.publico', ['massDeletionId' => $masivo->id]))
            ->assertOk()
            ->assertSeeInOrder(['Cuota 8:', "Capital cuotas\u{00A0}9\u{00A0}y\u{00A0}10:", '200.00', 'Cancelación hasta la cuota 8', "Se exonera el interés de las cuotas\u{00A0}9\u{00A0}y\u{00A0}10", 'Interés exonerado:', '40.00', 'TOTAL'])
            ->assertDontSee('Cuota 9:');

        $ticket = app(TicketPrinter::class)->buildPaymentTicketBytes($masivo);
        $this->assertMatchesRegularExpression('/Capital cuotas 9 y 10: +200\.00/', $ticket);
        $this->assertStringNotContainsString('Cuota 9:', $ticket);
        $this->assertStringContainsString('Cancelacion hasta la cuota 8', $ticket);
        $this->assertStringContainsString('Se exonera el interes de las cuotas 9 y 10', $ticket);
        $this->assertMatchesRegularExpression('/Interes exonerado: +40\.00/', $ticket);
    }

    public function test_una_cancelacion_sin_el_calculo_no_lleva_esas_lineas_en_el_recibo(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 715)
            ->set('cancel', true)
            ->set('decisionTotal', 'si')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();
        $this->assertSame('Cancelado', $credit->fresh()->situacion);
        $this->assertSame([null, null], [$masivo->hasta_cuota, $masivo->interes_exonerado]);
        $this->get(URL::signedRoute('recibo.publico', ['massDeletionId' => $masivo->id]))
            ->assertOk()
            ->assertSee('Cuota 9:')
            ->assertDontSee('Cancelación hasta la cuota');
    }

    /**
     * 23/10 (Antony: "ojo que en este caso es cuando también le dé a no dejarlo vigente al darle en
     * cobrar"): las líneas del recibo van solo si al cobrar se cancela. Con "No, dejarlo vigente" el
     * monto es un pago normal: nada se exonera y el crédito sigue activo. Volver a "Sí" recupera el
     * cálculo (antes el modo estricto lo bajaba al interés a la fecha).
     */
    public function test_no_dejarlo_vigente_cobra_sin_exonerar_y_volver_a_si_recupera_el_calculo(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->call('confirmarPago')
            ->set('decisionTotal', 'no')
            ->assertSet('cancel', false)
            ->assertSet('cancelHasta', 8)
            ->assertSee('calculado hasta la cuota 8 · sin cancelar')
            ->assertDontSee('Cancelación hasta la cuota 8')
            ->set('decisionTotal', 'si')
            ->assertSet('cancel', true)
            ->assertSet('monto', '675.00');
        $p = $c->get('preview');
        $this->assertTrue($p['cancela']);
        $this->assertSame([[5, 6, 7, 8], ['rotulo' => "Capital cuotas\u{00A0}9\u{00A0}y\u{00A0}10:", 'monto' => 200.0]], [array_column($p['detalle_cuotas'], 'num'), $p['resto_cuotas']]);
        $c->assertSee('Cancelación hasta la cuota 8');

        // Con "No" la vista previa también lista solo hasta la 8; lo demás llega en orden a la 9 y la 10.
        $c->set('decisionTotal', 'no');
        $p = $c->get('preview');
        $this->assertSame([[5, 6, 7, 8], ['rotulo' => "Cuotas\u{00A0}9\u{00A0}y\u{00A0}10:", 'monto' => 200.0]], [array_column($p['detalle_cuotas'], 'num'), $p['resto_cuotas']]);
        $c->call('pagar')->assertNotDispatched('errorAlert');

        $this->assertSame('Activo', $credit->fresh()->situacion);
        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();
        $this->assertSame([8, null], [$masivo->hasta_cuota, $masivo->interes_exonerado]);
        // Nada condonado: el interés de las cuotas 9 y 10 sigue en el cronograma y queda pendiente.
        $this->assertSame([20.0, 20.0], CreditInstallment::where('credit_id', $credit->id)->whereIn('num_cuota', [9, 10])->pluck('importe_interes')->map(fn ($v) => (float) $v)->all());
        $this->get(URL::signedRoute('recibo.publico', ['massDeletionId' => $masivo->id]))
            ->assertOk()
            ->assertSeeInOrder(['Cuota 8:', "Cuotas\u{00A0}9\u{00A0}y\u{00A0}10:", '200.00', 'TOTAL'])
            ->assertDontSee('Cuota 9:')
            ->assertDontSee('Cancelación hasta la cuota')
            ->assertDontSee('Se exonera el interés');
    }

    public function test_teclear_otro_monto_sale_del_calculo_y_apaga_cancelado(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->set('monto', 700)
            ->assertSet('cancelHasta', null)
            ->assertSet('cancel', false);
    }

    public function test_interes_completo_reemplaza_el_calculo(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->set('cancelUltimaCuota', true)
            ->assertSet('cancelHasta', null)
            ->assertSet('cancel', true)
            ->assertSet('monto', '715.00');
    }

    public function test_la_cuota_elegida_no_se_puede_cambiar_desde_el_navegador(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Create::class, ['creditId' => $credit->id])->set('cancelHasta', 1);
    }

    /**
     * Cronograma con 0.32 de capital de más en la última cuota (como el 28603) y la cuota 5 con su
     * interés pagado y capital a medias. La fórmula usa el capital del préstamo: al cancelar se
     * corrigen esos 0.32 en la cuota 10, lo exonerado es justo el interés de las cuotas siguientes
     * y el cobro deja todo en cero.
     */
    public function test_los_centimos_de_capital_de_mas_se_corrigen_al_cancelar(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito(100.32, [10.0, 20.0]);

        $plan = CancelacionHastaCuota::plan($credit);
        $this->assertSame([0.32, 5, 590.0, 610.0], [$plan['capital_de_mas'], $plan['minimo'], CancelacionHastaCuota::monto($plan, 5), CancelacionHastaCuota::monto($plan, 6)]);

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 6)
            ->call('confirmarPago')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertSame(100.0, (float) CreditInstallment::where('credit_id', $credit->id)->where('num_cuota', 10)->value('importe_cuota'));
        $this->assertSame('80.00', MassDeletion::where('credit_id', $credit->id)->latest('id')->value('interes_exonerado'));
        $this->assertSame('Cancelado', $credit->fresh()->situacion);
        $this->assertSame(0, CreditInstallment::where('credit_id', $credit->id)->where('pagado', 0)->count());
        $saldo = CreditInstallment::where('credit_id', $credit->id)->get()
            ->sum(fn ($c) => $c->importe_cuota + $c->importe_interes + $c->importe_excedente - $c->importe_aplicado - $c->interes_aplicado - $c->excedente_aplicado);
        $this->assertEqualsWithDelta(0.0, $saldo, 0.001);
    }

    public function test_los_mensuales_de_una_cuota_no_tienen_calculo(): void
    {
        $this->actingAs($this->actor());
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

        $this->assertNull(CancelacionHastaCuota::plan($credit));
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->assertSee('En los créditos mensuales de una cuota el interés corre por mes')
            ->call('abrirCancelacion')
            ->assertReturned(null)
            ->assertDispatched('errorAlert');
    }

    public function test_la_pantalla_ya_no_tiene_fecha_de_pago_y_trae_los_dos_botones(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->assertDontSee('Fecha de Pago')
            ->assertSeeInOrder(['Monto a Pagar', 'Elegir cuotas', 'Calcular cancelación']);
    }
}
