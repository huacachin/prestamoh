<?php

namespace Tests\Feature;

use App\Livewire\Credits\Schedule;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026 (Antony): "en semanal está desglosado los pagos pero en mensual
 * no". El semanal paga una cuota por pago, así que cada fila ya es un pago; el
 * mensual de una cuota se paga en muchos abonos que caían todos en una sola
 * fila. Ahora, debajo de la cuota pagada en varias veces, sale un renglón por
 * abono con fecha, hora, capital/interés, monto, saldo que iba quedando y su
 * recibo. El centavo que un pago semanal deja caer en la cuota siguiente no
 * desglosa nada.
 */
class CronogramaAbonosDesglosadosTest extends TestCase
{
    use RefreshDatabase;

    private Credit $credit;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'abonos-tester', 'headquarter_id' => 1]));
    }

    private function credito(int $tipo, int $cuotas): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Abonos', 'headquarter_id' => 1, 'status' => 'active']);

        return Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-19', 'importe' => 1000, 'cuotas' => $cuotas,
            'tipo_planilla' => $tipo, 'interes' => 10, 'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
    }

    private function pago(Credit $c, string $tipo, float $monto, ?int $insId, string $detalle, string $fecha, string $hora): int
    {
        return DB::table('payments')->insertGetId([
            'credit_id' => $c->id, 'installment_id' => $insId, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
            'fecha' => $fecha.' 00:00:00', 'hora' => $hora, 'monto' => $monto, 'detalle' => $detalle, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_la_cuota_mensual_pagada_en_abonos_se_desglosa_con_saldo_y_recibo(): void
    {
        $c = $this->credito(3, 1);
        $ins = CreditInstallment::create([
            'credit_id' => $c->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2026-09-19',
            'importe_cuota' => 1000, 'importe_interes' => 100, 'importe_excedente' => 0,
            'importe_aplicado' => 1000, 'interes_aplicado' => 100, 'excedente_aplicado' => 0, 'importe_mora' => 0, 'mora_interes' => 0,
            'pagado' => true, 'fecha_pago' => '2026-09-19',
        ]);
        // Tres abonos: interés (sin installment_id, como los migrados), capital parcial y el resto.
        $this->pago($c, 'INTERES', 100, null, 'Pago : X Interes:  1/1', '2026-08-22', '09:51:05');
        $p2 = $this->pago($c, 'CAPITAL', 400, $ins->id, 'Pago : X Cuota:  1/1', '2026-09-05', '10:00:00');
        $this->pago($c, 'CAPITAL', 600, $ins->id, 'Pago : X Cuota:  1/1', '2026-09-19', '11:30:00');
        // El segundo abono tiene recibo (cobro registrado por lotes).
        $md = DB::table('mass_deletions')->insertGetId(['credit_id' => $c->id, 'amount' => 400, 'date' => '2026-09-05', 'time' => '10:00:00', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mass_deletion_details')->insert(['mass_deletion_id' => $md, 'installment_id' => $ins->id, 'payment_id' => $p2, 'amount' => 400, 'fecha' => '2026-09-05', 'tipo' => 'C', 'created_at' => now(), 'updated_at' => now()]);

        $comp = Livewire::test(Schedule::class, ['id' => $c->id]);
        $abonos = $comp->viewData('abonos')[$ins->id];

        $this->assertCount(3, $abonos);
        $this->assertSame(['2026-08-22', '2026-09-05', '2026-09-19'], array_column($abonos, 'fecha'), 'en orden cronológico');
        $this->assertSame([0.0, 400.0, 600.0], array_column($abonos, 'cap'));
        $this->assertSame([100.0, 0.0, 0.0], array_column($abonos, 'int'));
        $this->assertSame([1000.0, 600.0, 0.0], array_column($abonos, 'saldo'), 'el saldo que iba quedando');
        $this->assertNull($abonos[0]['recibo']);
        $this->assertStringContainsString('/recibo/'.$md, (string) $abonos[1]['recibo']);

        $html = $comp->html();
        $this->assertSame(3, substr_count($html, 'class="fila-abono"'));
        $this->assertStringContainsString('↳ abono 1/3', $html);
        $this->assertStringContainsString('<small>09:51:05</small>', $html);
        $this->assertStringContainsString('Ver recibo de este abono', $html);
    }

    public function test_el_centavo_que_cae_en_la_cuota_siguiente_no_desglosa_y_una_cuota_de_un_solo_pago_tampoco(): void
    {
        $c = $this->credito(1, 2);
        $ids = [];
        foreach ([1, 2] as $n) {
            $ids[$n] = CreditInstallment::create([
                'credit_id' => $c->id, 'num_cuota' => $n, 'fecha_vencimiento' => '2026-08-'.(19 + 7 * ($n - 1)),
                'importe_cuota' => 333.33, 'importe_interes' => 120, 'importe_excedente' => 0,
                'importe_aplicado' => 333.33, 'interes_aplicado' => 120, 'excedente_aplicado' => 0, 'importe_mora' => 0, 'mora_interes' => 0,
                'pagado' => true, 'fecha_pago' => '2026-08-26',
            ])->id;
        }
        // Pago 1 (453.40): cuota 1 completa + 0.07 que cae en la cuota 2. Pago 2: el resto de la cuota 2.
        $this->pago($c, 'CAPITAL', 333.33, $ids[1], 'Cuota:  1/2', '2026-08-19', '08:15:13');
        $this->pago($c, 'INTERES', 120, $ids[1], 'Interes:  1/2', '2026-08-19', '08:15:13');
        $this->pago($c, 'CAPITAL', 0.07, $ids[2], 'Cuota:  2/2', '2026-08-19', '08:15:13');
        $this->pago($c, 'CAPITAL', 333.26, $ids[2], 'Cuota:  2/2', '2026-08-26', '08:09:01');
        $this->pago($c, 'INTERES', 120, $ids[2], 'Interes:  2/2', '2026-08-26', '08:09:01');

        $comp = Livewire::test(Schedule::class, ['id' => $c->id]);
        $this->assertSame([], $comp->viewData('abonos'));
        $this->assertStringNotContainsString('fila-abono', $comp->html());
    }
}
