<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\AbonosPorCuota;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 17/10/2026 (Antony, crédito 28808): la cuota 21 llevaba tres abonos de
 * S/ 100 y el cronograma del crédito los desglosaba (21.1, 21.2, 21.3), pero
 * la tabla de la pantalla de cobro solo decía "300 pagado, Amortizada". Ahora
 * la pantalla de cobro pinta el mismo desglose, con la misma clase compartida
 * (App\Support\AbonosPorCuota): un renglón por abono con capital, interés,
 * monto, el saldo que iba quedando, fecha+hora y su recibo. También cubre el
 * "saldo de una cuota + amortiza la siguiente" (un cobro que toca dos cuotas).
 */
class CobroAbonosDesglosadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'cobro-abonos', 'headquarter_id' => 1]));
    }

    /** Semanal de 48 cuotas de 425 (312.50 + 112.50), como el 28808, con las N primeras pagadas. */
    private function credito(int $pagadas, int $cuotas = 48): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Abonos', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-01-30', 'importe' => 15000, 'cuotas' => $cuotas,
            'tipo_planilla' => 1, 'interes' => 36, 'interes_total' => 5400, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        for ($n = 1; $n <= $cuotas; $n++) {
            $pagada = $n <= $pagadas;
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-02-06')->addWeeks($n - 1)->format('Y-m-d'),
                'importe_cuota' => 312.50, 'importe_interes' => 112.50, 'importe_excedente' => 0,
                'importe_aplicado' => $pagada ? 312.50 : 0, 'interes_aplicado' => $pagada ? 112.50 : 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => $pagada,
                'fecha_pago' => $pagada ? Carbon::parse('2026-02-06')->addWeeks($n - 1)->format('Y-m-d') : null,
            ]);
        }

        return $credit->refresh();
    }

    private function pago(Credit $c, string $tipo, float $monto, ?int $insId, string $detalle, string $fecha, string $hora): int
    {
        return DB::table('payments')->insertGetId([
            'credit_id' => $c->id, 'installment_id' => $insId, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
            'fecha' => $fecha.' 00:00:00', 'hora' => $hora, 'monto' => $monto, 'detalle' => $detalle, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lote(Credit $c, float $monto, string $fecha, string $hora, array $pagos): int
    {
        $md = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $c->id, 'amount' => $monto, 'date' => $fecha, 'time' => $hora, 'user' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($pagos as [$insId, $payId, $tipo, $importe]) {
            DB::table('mass_deletion_details')->insert([
                'mass_deletion_id' => $md, 'installment_id' => $insId, 'payment_id' => $payId, 'amount' => $importe,
                'fecha' => $fecha, 'tipo' => $tipo, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $md;
    }

    public function test_la_cuota_amortizada_en_tres_abonos_se_desglosa_en_la_pantalla_de_cobro(): void
    {
        $c = $this->credito(pagadas: 20);
        $c21 = CreditInstallment::where('credit_id', $c->id)->where('num_cuota', 21)->first();
        // Interés primero: 100 de interés; luego 12.50 de interés + 87.50 de capital; luego 100 de capital.
        $c21->update(['importe_aplicado' => 187.50, 'interes_aplicado' => 112.50]);
        $p1 = $this->pago($c, 'INTERES', 100, $c21->id, 'Pago : X Interes:  21/48 Dias : 108', '2026-10-12', '10:23:58');
        $p2i = $this->pago($c, 'INTERES', 12.50, $c21->id, 'Pago : X Interes:  21/48 Dias : 110', '2026-10-14', '07:36:40');
        $p2c = $this->pago($c, 'CAPITAL', 87.50, $c21->id, 'Pago : X Cuota:  21/48', '2026-10-14', '07:36:40');
        $p3 = $this->pago($c, 'CAPITAL', 100, $c21->id, 'Pago : X Cuota:  21/48', '2026-10-15', '09:56:44');
        $md1 = $this->lote($c, 100, '2026-10-12', '10:23:58', [[$c21->id, $p1, 'I', 100]]);
        $md2 = $this->lote($c, 100, '2026-10-14', '07:36:40', [[$c21->id, $p2c, 'C', 87.50], [$c21->id, $p2i, 'I', 12.50]]);
        $md3 = $this->lote($c, 100, '2026-10-15', '09:56:44', [[$c21->id, $p3, 'C', 100]]);

        $comp = Livewire::test(Create::class, ['creditId' => $c->id]);
        $abonos = $comp->viewData('abonos');

        $this->assertSame([$c21->id], array_keys($abonos), 'solo la cuota 21 lleva desglose');
        $this->assertSame([100.0, 100.0, 100.0], array_column($abonos[$c21->id], 'monto'));
        $this->assertSame([0.0, 87.5, 100.0], array_column($abonos[$c21->id], 'cap'));
        $this->assertSame([100.0, 12.5, 0.0], array_column($abonos[$c21->id], 'int'));
        $this->assertSame([325.0, 225.0, 125.0], array_column($abonos[$c21->id], 'saldo'), 'el saldo que iba quedando');
        $this->assertSame([$md1, $md2, $md3], array_column($abonos[$c21->id], 'lote'));
        $this->assertSame([true, true, true], array_column($abonos[$c21->id], 'tarde'), 'los tres abonos fueron después del vencimiento (26/06)');

        $html = $comp->html();
        $this->assertSame(3, substr_count($html, 'class="fila-abono"'));
        $this->assertStringContainsString('>21.1</td>', $html);
        $this->assertStringContainsString('>21.3</td>', $html);
        $this->assertStringContainsString('<small>10:23:58</small>', $html);
        $this->assertStringContainsString('12/10/2026', $html);
        $this->assertStringContainsString('Amortizada', $html);
        // Recibos: el de la cuota (su último cobro) + uno por abono, cada uno con su lote.
        $this->assertSame(4, substr_count($html, 'title="Ver recibo" onclick="abrirRecibo('));
        $this->assertStringContainsString('/recibo/'.$md1, (string) $abonos[$c21->id][0]['recibo']);
        $this->assertStringContainsString('/recibo/'.$md3, (string) $abonos[$c21->id][2]['recibo']);
    }

    public function test_un_cobro_que_cierra_una_cuota_y_amortiza_la_siguiente_se_ve_en_las_dos(): void
    {
        $c = $this->credito(pagadas: 13);
        $c14 = CreditInstallment::where('credit_id', $c->id)->where('num_cuota', 14)->first();
        $c15 = CreditInstallment::where('credit_id', $c->id)->where('num_cuota', 15)->first();
        $c14->update(['importe_aplicado' => 312.50, 'interes_aplicado' => 112.50, 'pagado' => true, 'fecha_pago' => '2026-05-26']);
        $c15->update(['importe_aplicado' => 50]);
        // 18/05: cobro de 450 = cuota 13 completa (fuera de este test) + 25 a la 14. Sin installment_id, como los legacy.
        $a = $this->pago($c, 'CAPITAL', 25, null, 'Pago : X Cuota:  14/48', '2026-05-18', '09:54:09');
        // 26/05: cobro de 450 = resto de la 14 (287.50 + 112.50) + 50 a la 15.
        $b1 = $this->pago($c, 'CAPITAL', 287.50, null, 'Pago : X Cuota:  14/48', '2026-05-26', '08:27:10');
        $b2 = $this->pago($c, 'INTERES', 112.50, null, 'Pago : X Interes:  14/48', '2026-05-26', '08:27:10');
        $b3 = $this->pago($c, 'CAPITAL', 50, null, 'Pago : X Cuota:  15/48', '2026-05-26', '08:27:10');
        $md = $this->lote($c, 450, '2026-05-26', '08:27:10', [[$c14->id, $b1, 'C', 287.50], [$c14->id, $b2, 'I', 112.50], [$c15->id, $b3, 'C', 50]]);

        $abonos = AbonosPorCuota::porCredito($c);

        // La 14 se pagó en dos abonos (25 y 400) y los dos se ven; la 15 solo tiene uno todavía: sin desglose.
        $this->assertSame([$c14->id], array_keys($abonos));
        $this->assertSame([25.0, 400.0], array_column($abonos[$c14->id], 'monto'));
        $this->assertSame([400.0, 0.0], array_column($abonos[$c14->id], 'saldo'));
        $this->assertSame([null, $md], array_column($abonos[$c14->id], 'lote'));

        $html = Livewire::test(Create::class, ['creditId' => $c->id])->html();
        $this->assertSame(2, substr_count($html, 'class="fila-abono"'));
        $this->assertStringContainsString('>14.1</td>', $html);
        $this->assertStringContainsString('>14.2</td>', $html);
        $this->assertStringNotContainsString('>15.1</td>', $html);
    }

    public function test_sin_pagos_parciales_la_tabla_no_cambia(): void
    {
        $c = $this->credito(pagadas: 2, cuotas: 4);
        foreach ([1, 2] as $n) {
            $ins = CreditInstallment::where('credit_id', $c->id)->where('num_cuota', $n)->first();
            $this->pago($c, 'CAPITAL', 312.50, $ins->id, "Pago : X Cuota:  {$n}/4", '2026-02-0'.(5 + $n), '09:00:00');
            $this->pago($c, 'INTERES', 112.50, $ins->id, "Pago : X Interes:  {$n}/4", '2026-02-0'.(5 + $n), '09:00:00');
        }

        $comp = Livewire::test(Create::class, ['creditId' => $c->id]);
        $this->assertSame([], $comp->viewData('abonos'));
        $this->assertStringNotContainsString('fila-abono', $comp->html());
    }
}
