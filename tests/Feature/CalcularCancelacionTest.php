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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Calcular cancelación" (23/10/2026, Antony, crédito 28603): el cajero elige hasta
 * qué cuota se cobra el interés y el total para cancelar es
 * interés por cuota × N + capital del crédito − monto pagado. Va al Monto a Pagar
 * y sigue el cobro normal.
 *
 * 06/11/2026 (Antony, crédito 28732): "no debería cancelar el crédito, debería quedar aún como
 * pendiente": ya no enciende Cancelado ni exonera la mora; el crédito queda vigente. Cancelar con la
 * fórmula solo pasa si el cajero enciende a mano el switch Cancelado (tests con ->set('cancel', true)).
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

    /**
     * El pago real de la cuota 1, 3 días después de vencer: deja 3 × 0.86 = 2.58 de mora acumulada.
     * La vigente, con el cálculo, suma la de cada cuota vencida: la 5 (14 días) y la 6 (7 días),
     * (14 + 7) × 0.86 = 18.06; sin el cálculo es solo la de la 5, 12.04.
     */
    private function conCuotaPagadaTarde(Credit $credit): Credit
    {
        DB::table('payments')->insert([
            'credit_id' => $credit->id, 'monto' => 120, 'tipo' => 'CAPITAL', 'documento' => 'CAPITAL',
            'fecha' => now()->subWeeks(6)->addDays(3)->format('Y-m-d'), 'hora' => '10:00:00',
            'headquarter_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

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

    public function test_usar_el_calculo_lleva_el_total_al_monto_y_deja_el_credito_vigente(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirCancelacion')
            ->assertReturned(fn ($plan) => $plan['minimo'] === 5 && $plan['maximo'] === 10 && $plan['sugerida'] === 7)
            ->call('aplicarCancelacion', 8)
            ->assertReturned(true)
            ->assertSet('monto', '675.00')
            ->assertSet('cancel', false)
            ->assertSet('decisionTotal', 'no')
            ->assertSet('cancelHasta', 8)
            ->assertDispatched('monto-usado')
            ->assertSee('calculado hasta la cuota 8 · sin cancelar');
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

    public function test_con_el_switch_cancelado_a_mano_cancela_con_el_monto_del_calculo_y_condona_el_interes_que_sobra(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->set('cancel', true)
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
            ->set('cancel', true)
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
     * 06/11 (Antony, 28732): "no debería cancelar el crédito, debería quedar aún como pendiente". Con el
     * monto del cálculo el cobro no pregunta si se cancela: es un pago normal, nada se exonera y el
     * crédito sigue activo. El recibo lista solo hasta la cuota elegida (23/10) y lo demás en una línea.
     */
    public function test_cobrar_el_monto_del_calculo_es_un_pago_normal_y_el_credito_queda_vigente(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->call('confirmarPago')
            ->assertSet('cancel', false)
            ->assertSet('decisionTotal', 'no')
            ->assertSet('cancelHasta', 8)
            ->assertSee('calculado hasta la cuota 8 · sin cancelar')
            ->assertSeeHtml('y el crédito <b>queda vigente</b>')
            ->assertDontSee('¿Cancelar el crédito?')
            ->assertDontSee('Cancelación hasta la cuota 8');
        $p = $c->get('preview');
        $this->assertFalse($p['cancela']);
        $this->assertTrue($p['solo_monto']);
        $this->assertSame([[5, 6, 7, 8], ['rotulo' => "Cuotas\u{00A0}9\u{00A0}y\u{00A0}10:", 'monto' => 200.0]], [array_column($p['detalle_cuotas'], 'num'), $p['resto_cuotas']]);
        $c->call('pagar')->assertNotDispatched('errorAlert');

        $this->assertSame('Activo', $credit->fresh()->situacion);
        $this->assertDatabaseHas('activity_log', ['description' => "Registró pago de 675.00 en el crédito #{$credit->id} (monto de Calcular pagos → Cancelación con interés hasta la cuota 8; el crédito queda vigente)"]);
        $this->assertEqualsWithDelta(40.00, (float) CreditInstallment::where('credit_id', $credit->id)->get()
            ->sum(fn ($i) => $i->importe_cuota + $i->importe_interes + $i->importe_excedente - $i->importe_aplicado - $i->interes_aplicado - $i->excedente_aplicado), 0.001, 'quedan pendientes los 40 del interés de las cuotas 9 y 10');
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

    public function test_teclear_otro_monto_sale_del_calculo(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->set('monto', 700)
            ->assertSet('cancelHasta', null)
            ->assertSet('cancel', false)
            ->assertSet('decisionTotal', '');
    }

    /**
     * 06/11 (Antony, 28732): la pestaña solo pone el monto. Ya no trae los checks de mora del 28/10 (sin
     * marcar exoneraban al cancelar): informa la mora y el cobro la cobra como siempre; la acumulada
     * (cuotas pagadas tarde) solo se cobra al cancelar, así que aquí no entra.
     */
    public function test_la_pestana_no_exonera_la_mora_se_cobra_como_en_cualquier_cobro(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->conCuotaPagadaTarde($this->credito());

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirCancelacion')
            ->assertReturned(fn ($plan) => $plan['mora'] === 18.06 && $plan['mora_rotulo'] === 'cuotas 5 y 6' && ! array_key_exists('con_mora', $plan))
            ->call('aplicarCancelacion', 8)
            ->assertSet('quitarMora', false)
            ->assertSet('quitarMoraAcum', false)
            ->call('confirmarPago')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertSame('Activo', $credit->fresh()->situacion);
        $this->assertEqualsWithDelta(18.06, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'MORA')->sum('monto'), 0.001);
        $this->assertSame(0, Payment::where('credit_id', $credit->id)->where('documento', 'MORA ACUM.')->count());
        $this->assertEqualsWithDelta(693.06, (float) MassDeletion::where('credit_id', $credit->id)->latest('id')->value('amount'), 0.001);
        $this->assertDatabaseMissing('mora_overrides', ['credit_id' => $credit->id]);
    }

    public function test_con_reserva_mora_la_mora_no_se_cobra_y_el_calculo_no_la_cambia(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->conCuotaPagadaTarde($this->credito());

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('ckmora', true)
            ->call('aplicarCancelacion', 8)
            ->assertSet('ckmora', true)
            ->call('confirmarPago')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertSame('Activo', $credit->fresh()->situacion);
        $this->assertSame(0, Payment::where('credit_id', $credit->id)->where('tipo', 'MORA')->count());
        $this->assertEqualsWithDelta(675.00, (float) MassDeletion::where('credit_id', $credit->id)->latest('id')->value('amount'), 0.001);
    }

    /**
     * 30/10 (Antony, 29427: "al darle en agregar mora siempre se queda la mora de una sola cuota, si quiero pagar
     * todas las cuotas vencidas no suma las moras de esas cuotas"): con el cálculo la mora es la suma de cada
     * cuota vencida, aunque quien cobra tenga el Total Mora editable (precargado con la de la más antigua).
     */
    public function test_con_el_calculo_la_mora_suma_cada_cuota_vencida_y_sin_el_es_la_mas_antigua(): void
    {
        $user = $this->actor();
        $user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->assertSet('moraManual', null)
            ->assertSee('Mora cuotas 5 y 6 (21 días en total)')
            ->call('confirmarPago');
        $this->assertSame(18.06, $c->get('preview')['mora_cobrar']);
        $c->assertSee('Mora cuotas 5 y 6:');

        // Otro monto sale del cálculo: vuelve la regla de siempre, la mora de la impaga más antigua.
        $c->set('monto', 700)->set('moraManual', null)->call('confirmarPago');
        $this->assertSame(12.04, $c->get('preview')['mora_cobrar']);

        $c->call('aplicarCancelacion', 8)->call('confirmarPago')->call('pagar')->assertNotDispatched('errorAlert');
        $this->assertEqualsWithDelta(18.06, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'MORA')->sum('monto'), 0.001);
        $this->assertDatabaseMissing('mora_overrides', ['credit_id' => $credit->id]);
    }

    /** Cancelar a mano con el cálculo ya no exonera la mora por su cuenta: se cobran la mora y la acumulada. */
    public function test_con_el_switch_cancelado_a_mano_la_mora_y_la_acumulada_se_cobran(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->conCuotaPagadaTarde($this->credito());

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->set('cancel', true)
            ->assertSet('quitarMora', false)
            ->assertSet('quitarMoraAcum', false)
            ->assertSet('monto', '675.00')
            ->call('confirmarPago')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertSame('Cancelado', $credit->fresh()->situacion);
        $this->assertEqualsWithDelta(20.64, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'MORA')->sum('monto'), 0.001);
        $this->assertEqualsWithDelta(2.58, (float) Payment::where('credit_id', $credit->id)->where('documento', 'MORA ACUM.')->sum('monto'), 0.001);
        $this->assertEqualsWithDelta(695.64, (float) MassDeletion::where('credit_id', $credit->id)->latest('id')->value('amount'), 0.001);
        $this->assertDatabaseMissing('mora_overrides', ['credit_id' => $credit->id]);
    }

    public function test_interes_completo_reemplaza_el_calculo(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarCancelacion', 8)
            ->set('cancel', true)
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
            ->set('cancel', true)
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
        $alDia = null;
        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirCancelacion')
            ->assertReturned(null)
            ->assertDispatched('errorAlert')
            // 06/11 (Antony, 28732): en vez de mandar al switch Cancelado, la pestaña da lo de cancelar hoy.
            ->call('abrirCalcularPagos')
            ->assertReturned(function ($d) use (&$alDia) {
                $alDia = $d['cancelacion_al_dia'];

                return $d['cancelacion'] === null && $d['sin_cancelacion'] === null;
            });
        $this->assertEqualsWithDelta(3000.0, $alDia['capital'], 0.001);
        $this->assertEqualsWithDelta($alDia['capital'] + $alDia['interes'], $alDia['total'], 0.001);

        // Va al Monto a Pagar sin encender Cancelado: el crédito queda vigente.
        $c->call('aplicarMontoDeCancelacion')
            ->assertReturned(true)
            ->assertSet('monto', number_format($alDia['total'], 2, '.', ''))
            ->assertSet('cancel', false)
            ->assertSet('decisionTotal', 'no')
            ->assertDispatched('monto-usado')
            ->call('confirmarPago')
            ->assertSet('decisionTotal', 'no')
            ->assertSeeHtml('capital e interés a la fecha): se cobra como un pago normal')
            ->assertDontSee('¿Cancelar el crédito?');
        $this->assertTrue($c->get('preview')['solo_monto']);
        $c->call('pagar')->assertNotDispatched('errorAlert');

        $this->assertSame('Activo', $credit->fresh()->situacion);
        $this->assertDatabaseHas('activity_log', ['description' => 'Registró pago de '.number_format($alDia['total'], 2, '.', '')." en el crédito #{$credit->id} (monto de Calcular pagos → Cancelación con capital e interés a la fecha; el crédito queda vigente)"]);

        // Teclear otro monto sale del cálculo: vuelve a preguntar si se cancela cuando cubre el total.
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('aplicarMontoDeCancelacion')
            ->set('monto', 10)
            ->assertSet('montoDeCancelacion', false)
            ->assertSet('decisionTotal', '');
    }

    public function test_la_pantalla_ya_no_tiene_fecha_de_pago_y_trae_los_dos_botones(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->assertDontSee('Fecha de Pago')
            ->assertSeeInOrder(['Monto a Pagar', 'Elegir cuotas', 'Calcular pagos']); // 30/10: antes "Calcular cancelación"
    }
}
