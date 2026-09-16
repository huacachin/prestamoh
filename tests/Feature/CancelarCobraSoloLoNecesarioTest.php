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
 * Modo estricto al cancelar (09/09, regla de Antony): "hay que elegir el modo
 * estricto pero avisando al usuario que solo se van a cobrar lo necesario".
 *
 * Antes, con "Sí, cancelar" y un monto mayor al necesario (capital pendiente
 * + interés a la fecha), el excedente se cobraba como interés —y si el monto
 * traía la mora, esta se cobraba dos veces—. Ahora el monto se ajusta a lo
 * necesario, el modal lo avisa, "No" devuelve lo tecleado, y el servidor lo
 * aplica al registrar aunque la pantalla llegue desfasada.
 */
class CancelarCobraSoloLoNecesarioTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester-estricto', 'headquarter_id' => 1]);
    }

    /**
     * Semanal, 4 cuotas de 250 + 25 (vencen -14, -7, hoy, +7 días). Hoy:
     * cancelar = 1,000 capital + 75 interés devengado = 1,075; el cronograma
     * completo suma 1,100.
     */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Estricto']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subWeeks(3)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach ([1 => -14, 2 => -7, 3 => 0, 4 => 7] as $n => $dias) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addDays($dias)->format('Y-m-d'),
                'importe_cuota' => 250, 'importe_interes' => 25, 'pagado' => 0,
            ]);
        }

        return $credit;
    }

    public function test_al_elegir_si_el_monto_baja_a_lo_necesario_y_el_modal_lo_avisa(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1090)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si');

        $this->assertEqualsWithDelta(1075.00, (float) $c->get('monto'), 0.001);
        $this->assertSame('1090', (string) $c->get('montoTecleado'));

        $p = $c->get('preview');
        $this->assertTrue($p['cancela']);
        $this->assertEqualsWithDelta(1000.00, $p['capital'], 0.01);
        $this->assertEqualsWithDelta(75.00, $p['interes'], 0.01);   // no 90
        $this->assertEqualsWithDelta(25.00, $p['condona'], 0.01);   // todo el interés futuro
        $this->assertEqualsWithDelta(1090.00, $p['monto_tecleado'], 0.01);

        $c->assertSee('Solo se cobra lo necesario para cancelar hoy')
            ->assertSee('S/ 1,075.00')
            ->assertSee('tecleaste S/ 1,090.00');
    }

    public function test_al_volver_a_no_recupera_lo_tecleado(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1090)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si')
            ->set('decisionTotal', 'no');

        $this->assertEqualsWithDelta(1090.00, (float) $c->get('monto'), 0.001);
        $this->assertNull($c->get('montoTecleado'));
        $this->assertEqualsWithDelta(10.00, $c->get('preview')['saldo'], 0.01); // 1,100 − 1,090
        $c->assertDontSee('Solo se cobra lo necesario');
    }

    public function test_si_el_cajero_vuelve_a_teclear_se_olvida_el_ajuste_anterior(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1090)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si')
            ->set('monto', 1085);   // teclea de nuevo

        $this->assertNull($c->get('montoTecleado'));

        // Y al confirmar otra vez con el switch ya marcado, se ajusta desde 1,085.
        $c->call('confirmarPago');
        $this->assertEqualsWithDelta(1075.00, (float) $c->get('monto'), 0.001);
        $this->assertSame('1085', (string) $c->get('montoTecleado'));
    }

    public function test_el_registro_cobra_solo_lo_necesario_aunque_la_pantalla_traiga_mas(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // Camino del switch: cancel=true y monto de más, directo a pagar().
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1090)
            ->set('cancel', true)
            ->set('decisionTotal', 'si')
            ->call('pagar');

        $capInt = Payment::where('credit_id', $credit->id)->whereIn('tipo', ['CAPITAL', 'INTERES'])->sum('monto');
        $this->assertEqualsWithDelta(1075.00, (float) $capInt, 0.01);   // no 1,090
        $this->assertEqualsWithDelta(75.00, (float) Payment::where('credit_id', $credit->id)->where('tipo', 'INTERES')->sum('monto'), 0.01);
        $this->assertSame('Cancelado', $credit->fresh()->situacion);
        $this->assertSame(0, CreditInstallment::where('credit_id', $credit->id)->where('pagado', 0)->count());

        // Bitácora: queda el rastro del ajuste.
        $this->assertDatabaseHas('activity_log', ['description' => 'Registró pago de 1075.00 en el crédito #'.$credit->id.' (canceló el crédito) — ajustado desde 1090: solo lo necesario para cancelar']);
    }

    /**
     * Regresión: el monto tecleado NO debe resucitar al desmarcar el switch
     * Cancelado fuera del modal. Antes, confirmarPago lo restauraba a ciegas:
     * el campo mostraba 1,075 y el ticket cobraba 1,090.
     */
    public function test_desmarcar_el_switch_no_resucita_el_monto_tecleado(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1090)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si');
        $this->assertEqualsWithDelta(1075.00, (float) $c->get('monto'), 0.001);

        // Cierra el modal y desmarca el switch a mano.
        $c->set('cancel', false);
        $this->assertNull($c->get('montoTecleado'));

        // Vuelve a Pagar: se cobra lo que el campo muestra, no los 1,090.
        $c->call('confirmarPago');
        $this->assertEqualsWithDelta(1075.00, (float) $c->get('monto'), 0.001);
        $this->assertEqualsWithDelta(1075.00, $c->get('preview')['monto'], 0.01);
        $this->assertFalse($c->get('preview')['cancela']);
    }

    /**
     * "Cancelar hasta la última cuota" es una elección expresa de cobrar TODO
     * el interés del cronograma: el modo estricto la respeta y no la recorta.
     */
    public function test_cancelar_hasta_la_ultima_cuota_no_se_recorta(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // Sin el switch: 1,100 se recorta a lo devengado (1,075).
        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1100)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si');
        $this->assertEqualsWithDelta(1075.00, (float) $c->get('monto'), 0.001);

        // Con el switch: se cobra el cronograma íntegro, sin ajuste ni aviso.
        $c2 = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('cancelUltimaCuota', true)
            ->set('monto', 1100)
            ->call('confirmarPago')
            ->set('decisionTotal', 'si');
        $this->assertEqualsWithDelta(1100.00, (float) $c2->get('monto'), 0.001);
        $this->assertNull($c2->get('montoTecleado'));
        $p = $c2->get('preview');
        $this->assertEqualsWithDelta(100.00, $p['interes'], 0.01);   // interés completo
        $this->assertEqualsWithDelta(0.00, $p['condona'], 0.01);
        $c2->assertDontSee('Solo se cobra lo necesario');
    }

    public function test_con_menos_de_lo_necesario_sigue_sin_dejar_cancelar(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1000)
            ->set('cancel', true)
            ->set('decisionTotal', 'si')
            ->call('pagar')
            ->assertDispatched('errorAlert');

        $this->assertSame(0, Payment::where('credit_id', $credit->id)->count());
        $this->assertSame('Activo', $credit->fresh()->situacion);
    }
}
