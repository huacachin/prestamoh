<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\AbonosPorCuota;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Support\AbonosPorCuota (17/10/2026): la lógica del desglose de abonos
 * compartida por el cronograma del crédito y la pantalla de cobro.
 *
 *  - RECIBO POR CRÉDITO + FECHA + HORA. Antes se cruzaba por
 *    mass_deletion_details.payment_id sin filtrar por crédito, y los lotes
 *    migrados del legacy guardan ahí el id del ingreso legacy: el botón de
 *    recibo del abono abría el lote de OTRO cliente (verificado en prod con
 *    el crédito 28808). Ese cruce queda solo como último recurso y acotado a
 *    los lotes del propio crédito.
 *  - La cuota amortizada con un solo abono también se desglosa.
 *  - Cada abono dice qué más pagó su cobro (otras cuotas y mora).
 */
class AbonosPorCuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'abonos-clase', 'headquarter_id' => 1]));
    }

    /** Semanal de $cuotas cuotas de 425 (312.50 + 112.50); las primeras $pagadas, pagadas. */
    private function credito(int $cuotas, int $pagadas = 0): Credit
    {
        $client = Client::create(['nombre' => 'Cliente '.uniqid(), 'headquarter_id' => 1, 'status' => 'active']);
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
            ]);
        }

        return $credit->refresh();
    }

    private function cuota(Credit $c, int $n): CreditInstallment
    {
        return CreditInstallment::where('credit_id', $c->id)->where('num_cuota', $n)->firstOrFail();
    }

    private function pago(Credit $c, string $tipo, float $monto, ?int $insId, string $detalle, string $fecha, string $hora): int
    {
        return DB::table('payments')->insertGetId([
            'credit_id' => $c->id, 'installment_id' => $insId, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
            'fecha' => $fecha.' 00:00:00', 'hora' => $hora, 'monto' => $monto, 'detalle' => $detalle, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  list<array{0:int,1:int,2:string,3:float}>  $detalles  [installment_id, payment_id, tipo, monto] */
    private function lote(Credit $c, float $monto, string $fecha, string $hora, array $detalles = []): int
    {
        $md = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $c->id, 'amount' => $monto, 'date' => $fecha, 'time' => $hora, 'user' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($detalles as [$insId, $payId, $tipo, $importe]) {
            DB::table('mass_deletion_details')->insert([
                'mass_deletion_id' => $md, 'installment_id' => $insId, 'payment_id' => $payId, 'amount' => $importe,
                'fecha' => $fecha, 'tipo' => $tipo, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $md;
    }

    public function test_el_recibo_del_abono_es_el_lote_del_propio_credito_aunque_el_payment_id_coincida_con_otro(): void
    {
        $a = $this->credito(2);
        $b = $this->credito(2, pagadas: 1);
        // Crédito B, cuota 1 pagada en dos abonos legacy (sin installment_id).
        $b1 = $this->pago($b, 'CAPITAL', 200, null, 'Pago : B Cuota:  1/2', '2026-02-06', '09:00:00');
        $b2c = $this->pago($b, 'CAPITAL', 112.50, null, 'Pago : B Cuota:  1/2', '2026-02-13', '10:00:00');
        $b2i = $this->pago($b, 'INTERES', 112.50, null, 'Pago : B Interes:  1/2', '2026-02-13', '10:00:00');
        $loteB1 = $this->lote($b, 200, '2026-02-06', '09:00:00');
        $loteB2 = $this->lote($b, 225, '2026-02-13', '10:00:00');
        // Crédito A tiene un lote migrado cuyo detail apunta (ids del legacy) justo al payment_id de B.
        $loteA = $this->lote($a, 999, '2024-12-06', '11:11:11', [[$this->cuota($a, 1)->id, $b1, 'C', 999]]);

        $abonos = AbonosPorCuota::porCredito($b);
        $c1 = $this->cuota($b, 1)->id;

        $this->assertSame([$loteB1, $loteB2], array_column($abonos[$c1], 'lote'), 'cruzado por crédito + fecha + hora');
        $this->assertStringContainsString('/recibo/'.$loteB1, $abonos[$c1][0]['recibo']);
        $this->assertStringNotContainsString('/recibo/'.$loteA, $abonos[$c1][0]['recibo']);
        $this->assertSame([], AbonosPorCuota::porCredito($a), 'el crédito A no tiene pagos: nada que desglosar');
    }

    public function test_con_dos_lotes_en_la_misma_fecha_y_hora_gana_el_que_cuadra_con_el_total_y_sin_lote_se_usa_el_detalle_del_propio_credito(): void
    {
        $c = $this->credito(2);
        $c1 = $this->cuota($c, 1);
        $c1->update(['importe_aplicado' => 200]);
        $p1 = $this->pago($c, 'CAPITAL', 100, $c1->id, 'Pago : X Cuota:  1/2', '2026-02-06', '09:00:00');
        $p2 = $this->pago($c, 'CAPITAL', 100, $c1->id, 'Pago : X Cuota:  1/2', '2026-02-07', '09:30:00');
        $otro = $this->lote($c, 55, '2026-02-06', '09:00:00');
        $cuadra = $this->lote($c, 100, '2026-02-06', '09:00:00');
        // El segundo abono tiene su lote a OTRA hora (reloj distinto), pero su detalle sí apunta al pago.
        $porDetalle = $this->lote($c, 100, '2026-02-07', '09:31:00', [[$c1->id, $p2, 'C', 100]]);

        $abonos = AbonosPorCuota::porCredito($c)[$c1->id];

        $this->assertSame([$cuadra, $porDetalle], array_column($abonos, 'lote'));
        $this->assertNotContains($otro, array_column($abonos, 'lote'));
    }

    public function test_la_cuota_amortizada_con_un_solo_abono_se_desglosa_y_la_nota_dice_que_mas_pago_ese_cobro(): void
    {
        $c = $this->credito(3, pagadas: 1);
        $c2 = $this->cuota($c, 2);
        $c3 = $this->cuota($c, 3);
        $c2->update(['importe_aplicado' => 312.50, 'interes_aplicado' => 112.50, 'pagado' => true]);
        $c3->update(['importe_aplicado' => 50]);
        // Un solo cobro de 500: 25 que faltaban de la 2 + 425 de la 2… mejor: 400 de la 2 + 50 de la 3 + 50 de mora.
        $a = $this->pago($c, 'CAPITAL', 25, $c2->id, 'Cuota:  2/3', '2026-02-20', '08:00:00');
        $b1 = $this->pago($c, 'CAPITAL', 287.50, $c2->id, 'Cuota:  2/3', '2026-02-27', '08:27:10');
        $b2 = $this->pago($c, 'INTERES', 112.50, $c2->id, 'Interes:  2/3', '2026-02-27', '08:27:10');
        $b3 = $this->pago($c, 'CAPITAL', 50, $c3->id, 'Cuota:  3/3', '2026-02-27', '08:27:10');
        DB::table('payments')->insert([
            'credit_id' => $c->id, 'installment_id' => $c2->id, 'modo' => 'CREDITO', 'tipo' => 'MORA', 'documento' => 'MORA',
            'fecha' => '2026-02-27 00:00:00', 'hora' => '08:27:10', 'monto' => 50, 'detalle' => 'Mora', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $md = $this->lote($c, 500, '2026-02-27', '08:27:10');

        $abonos = AbonosPorCuota::porCredito($c);

        // La 2 (dos abonos) y la 3 (amortizada con UN abono) se desglosan; la 1, pagada en un solo cobro, no.
        $this->assertEqualsCanonicalizing([$c2->id, $c3->id], array_keys($abonos));
        $this->assertSame([25.0, 400.0], array_column($abonos[$c2->id], 'monto'));
        $this->assertSame([400.0, 0.0], array_column($abonos[$c2->id], 'saldo'));
        $this->assertSame([50.0], array_column($abonos[$c3->id], 'monto'));
        $this->assertSame([375.0], array_column($abonos[$c3->id], 'saldo'));

        // La nota del cobro partido, vista desde cada cuota, y la mora no es abono pero sí suma al cobro.
        $this->assertSame(['lote' => $md, 'total' => 500.0, 'mora' => 50.0, 'otras' => [['num' => 3, 'monto' => 50.0]]], $abonos[$c2->id][1]['cobro']);
        $this->assertSame(['lote' => $md, 'total' => 500.0, 'mora' => 50.0, 'otras' => [['num' => 2, 'monto' => 400.0]]], $abonos[$c3->id][0]['cobro']);
        $this->assertSame(['lote' => null, 'total' => 25.0, 'mora' => 0.0, 'otras' => []], $abonos[$c2->id][0]['cobro']);
        $this->assertSame($md, $abonos[$c3->id][0]['lote']);
    }

    public function test_los_centavos_no_desglosan_ni_siquiera_en_la_cuota_amortizada(): void
    {
        $c = $this->credito(2, pagadas: 1);
        $c1 = $this->cuota($c, 1);
        $c2 = $this->cuota($c, 2);
        $c2->update(['importe_aplicado' => 0.07]);
        $this->pago($c, 'CAPITAL', 312.50, $c1->id, 'Cuota:  1/2', '2026-02-06', '08:15:13');
        $this->pago($c, 'INTERES', 112.50, $c1->id, 'Interes:  1/2', '2026-02-06', '08:15:13');
        $this->pago($c, 'CAPITAL', 0.07, $c2->id, 'Cuota:  2/2', '2026-02-06', '08:15:13');

        $this->assertSame([], AbonosPorCuota::porCredito($c));
    }
}
