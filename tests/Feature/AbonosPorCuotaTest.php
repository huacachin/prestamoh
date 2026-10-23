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

    public function test_con_dos_lotes_en_la_misma_fecha_y_hora_gana_el_que_cuadra_con_el_total_y_sin_lote_en_esa_clave_no_hay_recibo(): void
    {
        $c = $this->credito(2);
        $c1 = $this->cuota($c, 1);
        $c1->update(['importe_aplicado' => 200]);
        $p1 = $this->pago($c, 'CAPITAL', 100, $c1->id, 'Pago : X Cuota:  1/2', '2026-02-06', '09:00:00');
        $p2 = $this->pago($c, 'CAPITAL', 100, $c1->id, 'Pago : X Cuota:  1/2', '2026-02-07', '09:30:00');
        $otro = $this->lote($c, 55, '2026-02-06', '09:00:00');
        $cuadra = $this->lote($c, 100, '2026-02-06', '09:00:00');
        // El segundo abono tiene su lote a OTRA hora y un detalle que apunta al pago: NO se usa
        // (en los lotes migrados ese id es del legacy); mejor sin recibo que con uno equivocado.
        $porDetalle = $this->lote($c, 100, '2026-02-07', '09:31:00', [[$c1->id, $p2, 'C', 100]]);

        $abonos = AbonosPorCuota::porCredito($c)[$c1->id];

        $this->assertSame([$cuadra, null], array_column($abonos, 'lote'));
        $this->assertNull($abonos[1]['recibo']);
        $this->assertNotContains($otro, array_column($abonos, 'lote'));
        $this->assertNotContains($porDetalle, array_column($abonos, 'lote'));
    }

    public function test_la_fila_de_cero_no_es_abono_la_mora_interes_tampoco_y_se_avisa_cuando_la_caja_no_cuadra_con_lo_aplicado(): void
    {
        $c = $this->credito(2, pagadas: 1);
        $c1 = $this->cuota($c, 1);
        // Caja legacy: 350 + 112.50 = 462.50 atribuidos a una cuota de 425 (caso real de la cuota 19 del 28808),
        // más una fila 'CAPITAL 0.00' suelta y una 'MORA INTERES' con tipo INTERES.
        $this->pago($c, 'CAPITAL', 312.50, null, 'Cuota:  1/2', '2026-06-23', '16:35:48');
        $this->pago($c, 'INTERES', 37.50, null, 'Interes:  1/2', '2026-06-23', '16:35:48');
        $this->pago($c, 'CAPITAL', 0, null, 'Cuota:  1/2', '2026-06-30', '21:06:29');
        $this->pago($c, 'INTERES', 112.50, null, 'Interes:  1/2', '2026-06-30', '21:06:29');
        $this->pago($c, 'CAPITAL', 0, null, 'Cuota:  1/2', '2026-07-02', '10:00:00');
        DB::table('payments')->insert([
            'credit_id' => $c->id, 'installment_id' => $c1->id, 'modo' => 'CREDITO', 'tipo' => 'INTERES', 'documento' => 'MORA INTERES',
            'fecha' => '2026-06-30 00:00:00', 'hora' => '21:06:29', 'monto' => 9.99, 'detalle' => 'Mora', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $abonos = AbonosPorCuota::porCredito($c)[$c1->id];

        $this->assertSame([350.0, 112.5], array_column($abonos, 'monto'), 'la fila 0.00 suelta del 02/07 no es un abono');
        $this->assertSame([75.0, 0.0], array_column($abonos, 'saldo'), 'el saldo se acota a 0 aunque la caja sume de más');
        $this->assertSame([true, true], array_column($abonos, 'segun_caja'));
        $this->assertSame(9.99, $abonos[1]['cobro']['mora'], 'MORA INTERES cuenta como mora del cobro, no como abono');
        $this->assertSame(122.49, $abonos[1]['cobro']['total']);
    }

    public function test_la_cuota_con_un_solo_abono_no_se_desglosa_y_la_nota_dice_que_mas_pago_ese_cobro(): void
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

        // Solo la 2 (dos abonos) se desglosa; la 3, amortizada con UN abono, no (su recibo va en su
        // propia fila: "si es un solo abono, debe ir en la misma cuota"); la 1, pagada en un solo cobro, tampoco.
        $this->assertSame([$c2->id], array_keys($abonos));
        $this->assertSame([25.0, 400.0], array_column($abonos[$c2->id], 'monto'));
        $this->assertSame([400.0, 0.0], array_column($abonos[$c2->id], 'saldo'));

        // La nota del cobro partido, y la mora no es abono pero sí suma al cobro.
        $this->assertSame(['lote' => $md, 'total' => 500.0, 'mora' => 50.0, 'otras' => [['num' => 3, 'monto' => 50.0]]], $abonos[$c2->id][1]['cobro']);
        $this->assertSame(['lote' => null, 'total' => 25.0, 'mora' => 0.0, 'otras' => []], $abonos[$c2->id][0]['cobro']);
        $this->assertSame($md, $abonos[$c2->id][1]['lote']);
    }

    public function test_los_saldos_al_cobro_van_descontando_abono_a_abono_y_el_del_credito_cierra_con_el_actual(): void
    {
        $c = $this->credito(2);
        $c1 = $this->cuota($c, 1);
        $c1->update(['importe_aplicado' => 87.50, 'interes_aplicado' => 112.50]); // hoy: 200 pagados de 425
        $this->pago($c, 'INTERES', 100, $c1->id, 'Interes:  1/2', '2026-10-12', '10:23:58');
        $this->pago($c, 'INTERES', 12.50, $c1->id, 'Interes:  1/2', '2026-10-14', '07:36:40');
        $this->pago($c, 'CAPITAL', 87.50, $c1->id, 'Cuota:  1/2', '2026-10-14', '07:36:40');
        DB::table('payments')->insert([
            'credit_id' => $c->id, 'installment_id' => $c1->id, 'modo' => 'CREDITO', 'tipo' => 'MORA', 'documento' => 'MORA',
            'fecha' => '2026-10-14 00:00:00', 'hora' => '07:36:40', 'monto' => 30, 'detalle' => 'Mora', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $primero = AbonosPorCuota::saldosAlCobro($c->id, '2026-10-12', '10:23:58');
        $segundo = AbonosPorCuota::saldosAlCobro($c->id, '2026-10-14', '07:36:40');

        $this->assertSame(325.0, $primero['cuotas'][$c1->id], 'tras el primer abono faltaban 325');
        $this->assertSame(225.0, $segundo['cuotas'][$c1->id], 'tras el segundo, 225');
        $this->assertSame(425.0, $segundo['cuotas'][$this->cuota($c, 2)->id], 'la cuota 2 sigue entera');
        // Crédito: 850 en total; hoy faltan 650; al primer cobro faltaban 650 + 100 cobrados después.
        $this->assertSame(650.0, $segundo['credito']);
        $this->assertSame(750.0, $primero['credito']);
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

    /** 23/10 (Antony: "está muy grande"): la nota agrupa por monto las otras cuotas del mismo cobro. */
    public function test_la_nota_del_cobro_partido_agrupa_las_otras_cuotas_por_monto(): void
    {
        $siete = array_map(fn ($n) => ['num' => $n, 'monto' => 552.0], range(1, 7));
        $this->assertSame(
            'Cobro #121309 de S/ 4,000.00 · además cuotas 1 a 7 (S/ 552.00 c/u)',
            AbonosPorCuota::nota(['lote' => 121309, 'total' => 4000.0, 'mora' => 0.0, 'otras' => $siete]),
        );
        $this->assertSame(
            'Cobro #9 de S/ 1,262.00 · además cuotas 1 y 2 (S/ 552.00 c/u), cuota 3 (S/ 100.00) · S/ 5.80 de mora',
            AbonosPorCuota::nota(['lote' => 9, 'total' => 1262.0, 'mora' => 5.8, 'otras' => [['num' => 1, 'monto' => 552.0], ['num' => 2, 'monto' => 552.0], ['num' => 3, 'monto' => 100.0]]]),
        );
        $this->assertSame('Cobro de S/ 60.00 · S/ 10.00 de mora', AbonosPorCuota::nota(['lote' => null, 'total' => 60.0, 'mora' => 10.0, 'otras' => []]));
        $this->assertSame('', AbonosPorCuota::nota(['lote' => 1, 'total' => 50.0, 'mora' => 0.0, 'otras' => []]));
    }
}
