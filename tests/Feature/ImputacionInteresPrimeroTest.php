<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Services\Payments\ImputacionInteresPrimero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regla de imputación INTERÉS PRIMERO (decisión de negocio 12/08/2026):
 * todo pago se imputa interés → capital → excedente, cuota por cuota, y es
 * independiente del camino (N operaciones == una sola armada).
 */
class ImputacionInteresPrimeroTest extends TestCase
{
    use RefreshDatabase;

    // ─── Unidad: el motor puro ──────────────────────────────────────────

    /** @return object cuota sintética */
    private function cuota(int $num, float $cap, float $int, float $exc = 0, float $capApl = 0, float $intApl = 0, float $excApl = 0): object
    {
        return (object) [
            'num_cuota' => $num,
            'importe_cuota' => $cap, 'importe_interes' => $int, 'importe_excedente' => $exc,
            'importe_aplicado' => $capApl, 'interes_aplicado' => $intApl, 'excedente_aplicado' => $excApl,
        ];
    }

    public function test_interes_primero_en_pago_parcial(): void
    {
        $motor = new ImputacionInteresPrimero;

        // Cuota de 250 + 25: pagar 100 → interés completo (25) y 75 a capital.
        $d = $motor->distribuir(100, [$this->cuota(1, 250, 25)]);

        $this->assertEqualsWithDelta(25.0, $d['rows'][0]['int'], 0.001);
        $this->assertEqualsWithDelta(75.0, $d['rows'][0]['cap'], 0.001);
    }

    public function test_cascada_multicuota_con_excedente_al_final(): void
    {
        $motor = new ImputacionInteresPrimero;

        // Dos cuotas de 100 + 10 + 0.50 de excedente: pagar 150.
        // Cuota 1: I 10 → C 100 → E 0.50; quedan 39.50 → cuota 2: I 10 → C 29.50.
        $d = $motor->distribuir(150, [
            $this->cuota(1, 100, 10, 0.50),
            $this->cuota(2, 100, 10, 0.50),
        ]);

        $this->assertEqualsWithDelta(10.0, $d['rows'][0]['int'], 0.001);
        $this->assertEqualsWithDelta(100.0, $d['rows'][0]['cap'], 0.001);
        $this->assertEqualsWithDelta(0.50, $d['rows'][0]['exc'], 0.001);
        $this->assertEqualsWithDelta(10.0, $d['rows'][1]['int'], 0.001);
        $this->assertEqualsWithDelta(29.50, $d['rows'][1]['cap'], 0.001);
    }

    public function test_independencia_del_camino_en_el_motor(): void
    {
        $motor = new ImputacionInteresPrimero;

        // Estado real del caso Obregón: cuota con cap 1870 + int 450 pendientes.
        $armadaUnica = $motor->distribuir(2325, [
            $this->cuota(10, 1870, 450),
            $this->cuota(11, 1875, 450),
        ]);

        // Mismo total en dos operaciones (2000 y luego 325), aplicando el
        // estado intermedio entre ambas.
        $op1 = $motor->distribuir(2000, [
            $this->cuota(10, 1870, 450),
            $this->cuota(11, 1875, 450),
        ]);
        $c10 = $this->cuota(10, 1870, 450, 0, $op1['rows'][0]['cap'], $op1['rows'][0]['int']);
        $op2 = $motor->distribuir(325, [$c10, $this->cuota(11, 1875, 450)]);

        $this->assertEqualsWithDelta(
            $armadaUnica['capital'],
            round($op1['capital'] + $op2['capital'], 2), 0.001,
            'el capital total debe ser el mismo en 1 o 2 armadas'
        );
        $this->assertEqualsWithDelta(
            $armadaUnica['interes'],
            round($op1['interes'] + $op2['interes'], 2), 0.001,
            'el interés total debe ser el mismo en 1 o 2 armadas'
        );
        // Y el sobrante fue al INTERÉS de la cuota siguiente, no a capital.
        $this->assertEqualsWithDelta(1870.0, $armadaUnica['capital'], 0.001);
        $this->assertEqualsWithDelta(455.0, $armadaUnica['interes'], 0.001);
    }

    // ─── Integración: el cobro completo por la pantalla ─────────────────

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester', 'headquarter_id' => 1]);
    }

    /** Crédito semanal 1000 al 10%: 4 cuotas de 250 + 25, todas por vencer. */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente '.uniqid()]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 4) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addWeeks($n)->format('Y-m-d'),
                'importe_cuota' => 250, 'importe_interes' => 25, 'pagado' => 0,
            ]);
        }

        return $credit;
    }

    public function test_el_cobro_registra_interes_primero(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->call('pagar');

        $pagos = DB::table('payments')->where('credit_id', $credit->id)
            ->orderBy('id')->get(['tipo', 'monto']);

        // Interés completo de la cuota 1 primero, el resto a su capital.
        $this->assertSame('INTERES', $pagos[0]->tipo);
        $this->assertEqualsWithDelta(25.0, (float) $pagos[0]->monto, 0.001);
        $this->assertSame('CAPITAL', $pagos[1]->tipo);
        $this->assertEqualsWithDelta(75.0, (float) $pagos[1]->monto, 0.001);

        // Y el cronograma refleja lo mismo (una sola verdad).
        $c1 = DB::table('credit_installments')->where('credit_id', $credit->id)->where('num_cuota', 1)->first();
        $this->assertEqualsWithDelta(25.0, (float) $c1->interes_aplicado, 0.001);
        $this->assertEqualsWithDelta(75.0, (float) $c1->importe_aplicado, 0.001);
    }

    public function test_dos_armadas_igual_que_una_sola(): void
    {
        $this->actingAs($this->actor());

        // Crédito A: una armada de 260. Crédito B: 200 y luego 60.
        $a = $this->credito();
        $b = $this->credito();

        Livewire::test(Create::class, ['creditId' => $a->id])
            ->set('monto', 260)->call('pagar');
        Livewire::test(Create::class, ['creditId' => $b->id])
            ->set('monto', 200)->call('pagar');
        Livewire::test(Create::class, ['creditId' => $b->id])
            ->set('monto', 60)->call('pagar');

        $porTipo = fn ($cid) => DB::table('payments')->where('credit_id', $cid)
            ->selectRaw('tipo, ROUND(SUM(monto),2) t')->groupBy('tipo')->pluck('t', 'tipo');

        $this->assertEquals($porTipo($a->id), $porTipo($b->id),
            'partir el cobro no puede cambiar el desglose');

        // El cronograma también queda idéntico cuota por cuota.
        $crono = fn ($cid) => DB::table('credit_installments')->where('credit_id', $cid)
            ->orderBy('num_cuota')->get(['importe_aplicado', 'interes_aplicado'])
            ->map(fn ($c) => [(float) $c->importe_aplicado, (float) $c->interes_aplicado])->all();

        $this->assertEquals($crono($a->id), $crono($b->id));
    }

    public function test_rollback_a_la_regla_legacy_con_el_flag(): void
    {
        config(['prestamos.imputacion' => 'legacy']);

        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->call('pagar');

        // Regla vieja (semanal = rama A/B del legacy): CAPITAL primero.
        $pagos = DB::table('payments')->where('credit_id', $credit->id)
            ->orderBy('id')->get(['tipo', 'monto']);
        $this->assertSame('CAPITAL', $pagos[0]->tipo);
        $this->assertEqualsWithDelta(100.0, (float) $pagos[0]->monto, 0.001);
    }
}
