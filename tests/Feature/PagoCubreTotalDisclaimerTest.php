<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Disclaimer de cancelación (08/09). Caso real (crédito 28943): la pantalla
 * decía "Saldo Pendiente 15,000" y, con 10,875 tecleados, el modal decía
 * "cubre el TOTAL" — dos verdades que parecían contradecirse. El saldo es
 * del cronograma completo (con interés futuro); cancelar hoy cuesta capital
 * pendiente + interés devengado a la fecha, y el interés futuro se condona
 * SOLO si se cancela (es lo mismo que quedaría pendiente si se deja vigente).
 *
 * Este test fija que la pantalla lo diga en los tres sitios: pie de la
 * tarjeta, aviso del modal y "Saldo si no cancela" del recibo.
 */
class PagoCubreTotalDisclaimerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester-disclaimer', 'headquarter_id' => 1]);
    }

    /**
     * Semanal, 4 cuotas de 250 + 25. Vencen: hace 14 días, hace 7, hoy y en
     * 7 días. A hoy: capital pendiente 1,000 + interés devengado 75 (3
     * cuotas vencidas, 0 días adicionales) = 1,075 para cancelar; el saldo
     * del cronograma es 1,100 (queda 25 de interés futuro).
     */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Disclaimer']);
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

    public function test_el_aviso_explica_la_cuenta_y_lo_que_se_condona(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // 1,080 supera el umbral (1,075) pero no el cronograma (1,100).
        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1080)
            ->call('confirmarPago');

        $p = $c->get('preview');
        $this->assertTrue($p['cubre_total']);
        $this->assertEqualsWithDelta(1000.00, $p['cap_pendiente_total'], 0.01);
        $this->assertEqualsWithDelta(75.00, $p['int_cancelar'], 0.01);
        $this->assertEqualsWithDelta(1075.00, $p['cancelar_cap_int'], 0.01);
        $this->assertEqualsWithDelta(1100.00, $p['saldo_pendiente'], 0.01);
        // Lo que se condona si cancela == lo que queda si no cancela.
        $this->assertEqualsWithDelta(20.00, $p['condona'], 0.01);

        $c->assertSee('alcanza para CANCELAR el crédito hoy')
            ->assertDontSee('cubre el TOTAL')
            // Recibo: antes de decidir, el saldo mostrado es el de "No".
            ->assertSee('Saldo si no cancela:')
            // Tarjeta: este monto alcanza para cancelar.
            ->assertSee('alcanza para cancelar hoy');

        // La cuenta completa, en orden: capital + interés a la fecha = umbral,
        // y la explicación de lo que se condona / lo que queda.
        $this->assertEnOrden($c->html(), ['Capital pendiente', 'S/ 1,000.00', 'interés al '.now()->format('d/m/Y'), 'S/ 75.00', 'S/ 1,075.00']);
        // Cada rama con SU cifra: "Sí" cobra 1,075 y condona 25; "No" aplica
        // los 1,080 tecleados y deja 20 pendientes.
        $this->assertEnOrden($c->html(), [
            'porque incluye interés futuro',
            'Si cancela:', 'S/ 1,075.00', '(de los S/ 1,080.00 tecleados)', 'se condonan S/ 25.00 de interés futuro',
            'Si lo deja vigente:', 'S/ 1,080.00', 'quedan S/ 20.00 pendientes',
        ]);
    }

    /**
     * assertSeeInOrder de Livewire compara contra el JSON de la respuesta
     * (con "/" y tildes escapados), no contra el HTML: se comprueba el orden
     * sobre el HTML renderizado.
     */
    private function assertEnOrden(string $html, array $trozos): void
    {
        $pos = 0;
        foreach ($trozos as $t) {
            $i = mb_strpos($html, $t, $pos);
            $this->assertNotFalse($i, "No aparece (en orden) '{$t}' en el HTML.");
            $pos = $i + mb_strlen($t);
        }
    }

    /**
     * El aviso era un muro de texto justo donde el cajero decide (09/09):
     * ahora solo se ven las dos cifras y el resto vive tras "Ver detalles".
     */
    public function test_el_detalle_viene_colapsado_detras_de_ver_detalles(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1080)
            ->call('confirmarPago');

        // Resumen visible: una línea por respuesta.
        $c->assertSee('Si cancela:')
            ->assertSee('S/ 1,075.00')
            ->assertSee('Si lo deja vigente:')
            ->assertSee('Ver detalles')
            // El detalle está en el HTML pero colapsado (no otro modal).
            ->assertSeeHtml('x-show="det" style="display:none;"')
            ->assertSeeHtml('x-on:click="det = ! det"');
    }

    public function test_el_recibo_cambia_de_etiqueta_segun_la_decision(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1080)
            ->call('confirmarPago');

        $c->set('decisionTotal', 'no');
        $this->assertEqualsWithDelta(20.00, $c->get('preview')['saldo'], 0.01);
        $c->assertSee('Saldo si no cancela:');

        $c->set('decisionTotal', 'si');
        $this->assertEqualsWithDelta(0.00, $c->get('preview')['saldo'], 0.01);
        $c->assertSee('Saldo restante:')
            ->assertDontSee('Saldo si no cancela:')
            // Y el aviso sigue visible, con la misma cuenta.
            ->assertSee('alcanza para CANCELAR el crédito hoy')
            ->assertSee('alcanza para cancelar hoy');
    }

    public function test_con_monto_insuficiente_la_tarjeta_sigue_diciendo_cuanto_cuesta_cancelar(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // 500 no alcanza: la tarjeta muestra saldo − monto (600) y el pie
        // debe seguir diciendo el costo de cancelar hoy, no "cronograma".
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 500)
            ->assertSee('600.00')
            ->assertSee('cancelar hoy: S/ 1,075.00')
            ->assertDontSee('alcanza para cancelar hoy');
    }

    /**
     * Sin interés futuro (todas las cuotas vencidas o vencen hoy), un monto
     * un centavo por debajo del cronograma dispara el aviso por la tolerancia
     * de 0.01, pero pagar() NO condona ese centavo: el aviso tampoco debe
     * prometerlo.
     */
    public function test_el_centavo_de_tolerancia_no_se_anuncia_como_condonacion(): void
    {
        $this->actingAs($this->actor());

        $client = Client::create(['nombre' => 'Cliente Centavo']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subWeeks(2)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach ([1 => -7, 2 => 0] as $n => $dias) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addDays($dias)->format('Y-m-d'),
                'importe_cuota' => 500, 'importe_interes' => 50, 'pagado' => 0,
            ]);
        }

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', '1099.99')
            ->call('confirmarPago');

        $p = $c->get('preview');
        $this->assertTrue($p['cubre_total']);
        $this->assertEqualsWithDelta(0.0, $p['condona'], 0.001);
        $c->assertSee('Con este monto se paga el cronograma completo')
            ->assertDontSee('porque incluye interés futuro');
    }

    public function test_sin_monto_la_tarjeta_dice_cuanto_cuesta_cancelar_hoy(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->assertSee('cancelar hoy: S/ 1,075.00')
            ->assertDontSee('con este monto puede cancelar hoy');
    }
}
