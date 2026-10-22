<?php

namespace Tests\Feature;

use App\Livewire\Credits\Schedule;
use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\MoraExonerada;
use App\Support\MoraPagada;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La mora de un cobro se REPARTE en las celdas de las cuotas que la
 * generaron, cada una con SU parte (17/09, pedido de Antony: "muestra el
 * reparto en las celdas, no en el tooltip" y después "cada cuota tiene
 * diferente mora").
 *
 * El caso que lo motivó es el crédito 28957: Licet pagó las cuotas 18 a 25
 * desde el modal con 839,16 de mora (días de atraso × 3,33 por cuota: 186,48
 * en la 18 bajando hasta 23,31 en la 25). El motor anota el cobro entero en
 * importe_mora de la PRIMERA cuota tocada (la 18) y las pantallas pintaban
 * esa columna tal cual: 839,16 en la 18 y nada en la 19 a 25.
 *
 * El fixture reproduce esa operación tal como queda en la base: pagos C/I
 * por cuota, un pago MORA sin cuota, y en mass_deletion_details las filas
 * C/I de las ocho cuotas más la fila M anclada a la 18. Las cuotas 1 a 17
 * llevan sus pagos puntuales (solo en payments) para que el FIFO del
 * cronograma cuelgue el cobro del 17/09 en la 18 a 25 y no en la 1 a 8.
 *
 * 22/10: el reparto es EN CASCADA (la más antigua primero, cada una hasta su
 * mora teórica al pagar). Caso 29351 (Edwin Leoncio): se cobró solo la mora de
 * la cuota 6 (15 días, 35,70) y el pago tocó la 7 con 0,42 de interés; el
 * prorrateo por días pintaba 23,28 en la 6 y 12,42 en la 7, aunque por la 7
 * no se cobró nada. Con la cascada la 6 muestra sus 35,70 y la 7 nada.
 */
class MoraRepartoEnCeldasTest extends TestCase
{
    use RefreshDatabase;

    private const MORA = 839.16;

    private const FECHA_PAGO = '2026-09-17';

    /** Lo que el modal cobró por cuota al 17/09: días de atraso × 3,33. */
    private const PARTES = [
        18 => [186.48, 56], 19 => [163.17, 49], 20 => [139.86, 42], 21 => [116.55, 35],
        22 => [93.24, 28], 23 => [69.93, 21], 24 => [46.62, 14], 25 => [23.31, 7],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['username' => 'reparto-mora', 'headquarter_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);
    }

    /**
     * Crédito semanal como el 28957. Cuotas 18 a 25 pagadas el 17/09 en UNA
     * operación con la mora anclada a la 18.
     *
     * @return array{credit: Credit, ids: array<int, int>, pagoMora: int, op: int}
     */
    private function operacion(float $mora = self::MORA, float $legacyEnCuota5 = 0, float $legacyEnAncla = 0): array
    {
        $client = Client::create(['nombre' => 'Cliente Reparto', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-03-19',
            'importe' => 14000, 'cuotas' => 48, 'tipo_planilla' => 1, 'interes' => 60,
            'interes_total' => 8400, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);

        $pago = fn (string $tipo, float $monto, ?int $insId, string $detalle, string $fecha, string $hora) => DB::table('payments')->insertGetId([
            'credit_id' => $credit->id, 'installment_id' => $insId, 'modo' => 'CREDITO',
            'tipo' => $tipo, 'documento' => $tipo, 'fecha' => $fecha.' 00:00:00', 'hora' => $hora,
            'monto' => $monto, 'detalle' => $detalle, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $ids = [];
        // Cuota 18 vence el 23/07; las demás cada 7 días. 26-28 pendientes.
        for ($n = 1; $n <= 28; $n++) {
            $venc = Carbon::parse('2026-07-23')->addWeeks($n - 18)->format('Y-m-d');
            $pagada = $n <= 25;
            $ins = CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => $venc,
                'importe_cuota' => 291.67, 'importe_interes' => 175.00, 'importe_excedente' => 0,
                'importe_aplicado' => $pagada ? 291.67 : 0, 'interes_aplicado' => $pagada ? 175.00 : 0,
                'excedente_aplicado' => 0,
                'importe_mora' => match (true) {
                    $n === 18 => $mora + $legacyEnAncla,
                    $n === 5 => $legacyEnCuota5,
                    default => 0,
                },
                'mora_interes' => 0,
                'pagado' => $pagada,
                'fecha_pago' => $pagada ? ($n >= 18 ? self::FECHA_PAGO : $venc) : null,
            ]);
            $ids[$n] = $ins->id;

            // 1 a 17: pagadas puntuales, solo en payments (sin operación).
            if ($n <= 17) {
                $pago('INTERES', 175.00, $ins->id, "Interes: {$n}/48", $venc, '09:00:00');
                $pago('CAPITAL', 291.67, $ins->id, "Cuota: {$n}/48", $venc, '09:00:00');
            }
        }

        $opId = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 3666, 'date' => self::FECHA_PAGO,
            'time' => '10:51:27', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $detalle = fn (int $insId, int $pagoId, float $monto, string $tipo) => DB::table('mass_deletion_details')->insert([
            'mass_deletion_id' => $opId, 'installment_id' => $insId, 'payment_id' => $pagoId,
            'amount' => $monto, 'fecha' => now(), 'tipo' => $tipo, 'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($n = 18; $n <= 25; $n++) {
            $detalle($ids[$n], $pago('INTERES', 175.00, $ids[$n], "Interes: {$n}/48", self::FECHA_PAGO, '10:51:27'), 175.00, 'I');
            $detalle($ids[$n], $pago('CAPITAL', 291.67, $ids[$n], "Cuota: {$n}/48", self::FECHA_PAGO, '10:51:27'), 291.67, 'C');
        }
        // La mora: UN pago sin cuota y la fila M anclada a la primera cuota tocada.
        $pagoMora = $pago('MORA', $mora, null, 'Mora de cuota', self::FECHA_PAGO, '10:51:27');
        $detalle($ids[18], $pagoMora, $mora, 'M');

        return ['credit' => $credit->fresh(), 'ids' => $ids, 'pagoMora' => $pagoMora, 'op' => $opId];
    }

    /**
     * El 29351 tal como quedó el 21/10: cuotas semanales de 333,33 (tarifa 2,38 por
     * día), la 6 vencida el 06/10 (15 días) y la 7 el 13/10 (8 días). El cobro de
     * 333,40 cerró la 6 y dejó 0,42 de interés en la 7; la mora cobrada fue solo la
     * de la 6 (35,70), anclada en ella.
     *
     * @return array{credit: Credit, ids: array<int, int>}
     */
    private function operacionEdwin(float $mora = 35.70): array
    {
        $client = Client::create(['nombre' => 'Edwin Leoncio', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 10000, 'cuotas' => 48, 'tipo_planilla' => 1, 'interes' => 60,
            'interes_total' => 6000, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        $fecha = '2026-10-21';
        $pago = fn (string $tipo, float $monto, ?int $insId, string $detalle) => DB::table('payments')->insertGetId([
            'credit_id' => $credit->id, 'installment_id' => $insId, 'modo' => 'CREDITO',
            'tipo' => $tipo, 'documento' => $tipo, 'fecha' => $fecha.' 00:00:00', 'hora' => '08:55:42',
            'monto' => $monto, 'detalle' => $detalle, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $ids = [];
        for ($n = 1; $n <= 9; $n++) {
            $venc = Carbon::parse('2026-09-01')->addWeeks($n - 1)->format('Y-m-d');
            $ids[$n] = CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => $venc,
                'importe_cuota' => 208.33, 'importe_interes' => 125.00, 'importe_excedente' => 0,
                'importe_aplicado' => $n <= 6 ? 208.33 : 0,
                'interes_aplicado' => $n <= 6 ? 125.00 : ($n === 7 ? 0.42 : 0),
                'excedente_aplicado' => 0,
                'importe_mora' => $n === 6 ? $mora : 0, 'mora_interes' => 0,
                'pagado' => $n <= 6, 'fecha_pago' => $n <= 6 ? ($n === 6 ? $fecha : $venc) : null,
            ])->id;
            if ($n <= 5) {
                $pago('INTERES', 125.00, $ids[$n], "Interes: {$n}/48");
                $pago('CAPITAL', 208.33, $ids[$n], "Cuota: {$n}/48");
            }
        }

        $opId = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => round(333.40 + $mora, 2), 'date' => $fecha,
            'time' => '08:55:42', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $detalle = fn (int $insId, int $pagoId, float $monto, string $tipo) => DB::table('mass_deletion_details')->insert([
            'mass_deletion_id' => $opId, 'installment_id' => $insId, 'payment_id' => $pagoId,
            'amount' => $monto, 'fecha' => now(), 'tipo' => $tipo, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $detalle($ids[6], $pago('CAPITAL', 208.33, $ids[6], 'Cuota: 6/48'), 208.33, 'C');
        $detalle($ids[6], $pago('INTERES', 124.65, $ids[6], 'Interes: 6/48 Dias : 15'), 124.65, 'I');
        $detalle($ids[7], $pago('INTERES', 0.42, $ids[7], 'Interes: 7/48 Dias : 8'), 0.42, 'I');
        $detalle($ids[6], $pago('MORA', $mora, null, 'Mora de cuota'), $mora, 'M');

        return ['credit' => $credit->fresh(), 'ids' => $ids];
    }

    private function sumaRegistrada(Credit $credit): float
    {
        return round((float) $credit->installments->sum(fn ($i) => $i->importe_mora + $i->mora_interes), 2);
    }

    /** Veces que un número aparece como CELDA de mora pagada (no en tooltips ni totales de otra forma). */
    private function celdas(string $html, string $monto): int
    {
        return substr_count($html, 'style="cursor:help;">'.$monto.'</span>');
    }

    // ─── El reparto ────────────────────────────────────────────────────────

    public function test_cada_cuota_muestra_su_mora_y_la_ancla_ya_no_carga_todo(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion();

        $celda = MoraPagada::mostradaPorCuota($credit);

        foreach (self::PARTES as $n => [$monto, $dias]) {
            $this->assertSame($monto, $celda[$ids[$n]]['monto'], "cuota {$n}: su mora, no un reparto parejo");
            $this->assertSame($dias, $celda[$ids[$n]]['dias'], "cuota {$n}: sus días de atraso");
        }
        $this->assertSame(self::MORA, round(array_sum(array_column($celda, 'monto')), 2));
        $this->assertNotEquals(self::MORA, $celda[$ids[18]]['monto'], 'la 18 ya no muestra los 839,16 enteros');
        $this->assertStringContainsString('por las cuotas 18 a 25', $celda[$ids[18]]['detalle']);
        $this->assertStringContainsString('anotado en la cuota 18', $celda[$ids[19]]['detalle']);

        // Las cuotas sin mora no aparecen (la celda pinta 0.00).
        $this->assertArrayNotHasKey($ids[17], $celda);
        $this->assertArrayNotHasKey($ids[26], $celda);
    }

    /** El reparto redistribuye, no inventa: la suma por crédito es la de siempre. */
    public function test_la_suma_de_las_celdas_es_la_mora_registrada(): void
    {
        ['credit' => $credit] = $this->operacion(legacyEnCuota5: 50.00, legacyEnAncla: 20.00);

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame($this->sumaRegistrada($credit), round(array_sum(array_column($celda, 'monto')), 2));
        $this->assertSame(909.16, $this->sumaRegistrada($credit), 'sanity: 839,16 + 50 + 20');
    }

    // ─── La cascada (22/10) ────────────────────────────────────────────────

    /**
     * Caso 29351: se cobró solo la mora de la cuota 6 y el pago tocó la 7 con
     * 0,42 de interés. La 6 muestra sus 35,70 enteros; la 7 no muestra mora
     * (antes: 23,28 y 12,42, prorrateo 15:8 por días).
     */
    public function test_caso_29351_la_mora_cobrada_por_una_cuota_se_queda_en_esa_cuota(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacionEdwin();

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame(35.70, $celda[$ids[6]]['monto']);
        $this->assertSame(15, $celda[$ids[6]]['dias']);
        $this->assertArrayNotHasKey($ids[7], $celda, 'por la 7 no se cobró mora');
        $this->assertStringContainsString('cobro de 35.70', $celda[$ids[6]]['detalle']);
        $this->assertStringNotContainsString('por las cuotas', $celda[$ids[6]]['detalle'], 'el cobro ya no se atribuye a la 7');

        // Y en rojo: la 6 pagó su mora completa, no se le exoneró nada.
        $this->assertArrayNotHasKey(6, MoraExonerada::porCuota($credit, $celda));

        $html = Livewire::test(Create::class, ['creditId' => $credit->id])->html();
        $this->assertSame(2, $this->celdas($html, '35.70'), 'la celda de la 6 y la fila de Totales (único cobro)');
        $this->assertSame(0, $this->celdas($html, '23.28'));
        $this->assertSame(0, $this->celdas($html, '12.42'));
    }

    /** Cobrada la mora de la más antigua entre varias tocadas, la cascada la deja entera ahí. */
    public function test_con_la_mora_de_la_mas_antigua_las_demas_no_muestran_nada(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(mora: 186.48);

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame(186.48, $celda[$ids[18]]['monto']);
        $this->assertSame(56, $celda[$ids[18]]['dias']);
        foreach (range(19, 25) as $n) {
            $this->assertArrayNotHasKey($ids[$n], $celda, "cuota {$n}: no se cobró su mora");
        }
        $this->assertSame(186.48, round(array_sum(array_column($celda, 'monto')), 2));
    }

    /** Entre medias: lo cobrado cubre la 18 y la 19 enteras y parte de la 20; de la 21 en adelante nada. */
    public function test_la_cascada_llena_cuota_por_cuota_y_corta_donde_se_acaba(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(mora: 400.00);

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame(186.48, $celda[$ids[18]]['monto']);
        $this->assertSame(163.17, $celda[$ids[19]]['monto']);
        $this->assertSame(50.35, $celda[$ids[20]]['monto'], '400 − 186,48 − 163,17');
        $this->assertArrayNotHasKey($ids[21], $celda);
        $this->assertSame(400.00, round(array_sum(array_column($celda, 'monto')), 2));
    }

    /** Más que la suma teórica (mora subida a mano): el exceso se queda en la ancla, donde está anotado. */
    public function test_el_exceso_sobre_la_mora_teorica_queda_en_la_ancla(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(mora: 900.00);

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame(round(186.48 + (900 - self::MORA), 2), $celda[$ids[18]]['monto'], 'su parte + el exceso 60,84');
        foreach (self::PARTES as $n => [$monto]) {
            if ($n !== 18) {
                $this->assertSame($monto, $celda[$ids[$n]]['monto'], "cuota {$n}: su mora teórica");
            }
        }
        $this->assertSame(900.00, round(array_sum(array_column($celda, 'monto')), 2));
    }

    /** La mora migrada del legacy no tiene operación: se queda en su propia cuota. */
    public function test_la_mora_sin_operacion_se_queda_en_su_cuota(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(legacyEnCuota5: 50.00);

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame(50.00, $celda[$ids[5]]['monto']);
        $this->assertNull($celda[$ids[5]]['dias']);
        $this->assertStringContainsString('anotada en la propia cuota', $celda[$ids[5]]['detalle']);
    }

    /** Ancla con mora legacy encima del cobro: su parte MÁS lo propio; las demás no se enteran. */
    public function test_la_ancla_con_mora_propia_suma_las_dos_cosas(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(legacyEnAncla: 20.00);

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame(206.48, $celda[$ids[18]]['monto'], '186,48 del reparto + 20 propios');
        $this->assertSame(163.17, $celda[$ids[19]]['monto']);
        $this->assertStringContainsString('anotada en la propia cuota: 20.00', $celda[$ids[18]]['detalle']);
    }

    /**
     * El reparto va en centavos enteros por resto mayor: ninguna parte sale
     * negativa y la suma es exacta, sea cual sea el monto. Redondear parte
     * por parte dejaba a la última con el resto, que podía ser negativo y la
     * celda lo descartaba, rompiendo la suma.
     */
    public function test_ninguna_parte_sale_negativa_y_la_suma_es_exacta(): void
    {
        foreach ([0.01, 0.03, 0.07, 0.10, 0.50, 1.23, 33.33, self::MORA] as $mora) {
            ['credit' => $credit] = $this->operacion(mora: $mora);
            $partes = array_column(MoraPagada::mostradaPorCuota($credit), 'monto');

            $this->assertGreaterThanOrEqual(0, min($partes ?: [0]), "con {$mora} ninguna parte puede ser negativa");
            $this->assertSame($mora, round(array_sum($partes), 2), "con {$mora} la suma debe ser exacta");
        }
    }

    // ─── Defensas contra datos torcidos ────────────────────────────────────

    /**
     * Las filas M migradas del legacy conservan payment_id = id del ingreso
     * legacy, que colisiona con ids de pagos nuevos. Una fila M de OTRO
     * crédito que apunte al pago MORA de éste no puede meter mora fantasma.
     */
    public function test_una_fila_m_de_otro_credito_no_mete_mora_fantasma(): void
    {
        ['credit' => $x, 'pagoMora' => $pagoMoraDeX] = $this->operacion();

        // Crédito Y, ajeno, con una operación legacy cuya fila M "apunta" al
        // pago MORA de X (colisión de ids) por el MISMO monto.
        $clienteY = Client::create(['nombre' => 'Cliente Ajeno', 'headquarter_id' => 1, 'status' => 'active']);
        $y = Credit::create([
            'client_id' => $clienteY->id, 'fecha_prestamo' => '2021-05-01', 'importe' => 3000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 300, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        $cuotaY = CreditInstallment::create([
            'credit_id' => $y->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2021-06-22',
            'importe_cuota' => 750, 'importe_interes' => 75, 'importe_aplicado' => 750, 'interes_aplicado' => 75,
            'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => 1, 'fecha_pago' => '2022-02-14',
        ]);
        $opY = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $y->id, 'amount' => 825, 'date' => '2022-02-14', 'time' => '10:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['C', 750.0, null], ['M', self::MORA, $pagoMoraDeX]] as [$tipo, $monto, $pid]) {
            DB::table('mass_deletion_details')->insert([
                'mass_deletion_id' => $opY, 'installment_id' => $cuotaY->id, 'payment_id' => $pid,
                'amount' => $monto, 'fecha' => now(), 'tipo' => $tipo, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $celdasX = MoraPagada::mostradaPorCuota($x->fresh());
        $this->assertSame(self::MORA, round(array_sum(array_column($celdasX, 'monto')), 2), 'X no cambia');
        $this->assertSame([], MoraPagada::mostradaPorCuota($y->fresh()), 'Y no hereda la mora de X');
    }

    /**
     * Si en la cuota ancla hay anotado MENOS de lo que dice su fila M (una
     * reversa que puso importe_mora en 0 dejando viva la fila M), las celdas
     * se recortan en proporción: nunca suman más que lo registrado.
     */
    public function test_si_la_ancla_tiene_menos_anotado_las_celdas_no_superan_lo_registrado(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(legacyEnCuota5: 50.00);

        // La mitad anotada: cada parte a la mitad (en centavos exactos).
        CreditInstallment::where('id', $ids[18])->update(['importe_mora' => 419.58]);
        $celda = MoraPagada::mostradaPorCuota($credit->fresh());
        $this->assertSame(469.58, round(array_sum(array_column($celda, 'monto')), 2), '419,58 + 50 legacy');
        $this->assertSame(93.24, $celda[$ids[18]]['monto'], 'la mitad de 186,48');

        // Nada anotado: la operación no pinta nada, solo queda la legacy.
        CreditInstallment::where('id', $ids[18])->update(['importe_mora' => 0]);
        $celda = MoraPagada::mostradaPorCuota($credit->fresh());
        $this->assertSame(50.00, round(array_sum(array_column($celda, 'monto')), 2));
        $this->assertArrayNotHasKey($ids[19], $celda);
    }

    // ─── La exonerada (rojo) ───────────────────────────────────────────────

    /**
     * Una cuota que pagó su mora teórica completa no puede salir "exonerada".
     * Antes la exonerada restaba la mora colgada por FIFO en la primera
     * cuota, y la 19 salía "pagó 163,17" y "exonerada 163,17" a la vez.
     */
    public function test_la_exonerada_es_cero_cuando_la_cuota_pago_su_mora(): void
    {
        ['credit' => $credit] = $this->operacion();

        $exon = MoraExonerada::porCuota($credit);
        foreach (range(18, 25) as $n) {
            $this->assertArrayNotHasKey($n, $exon, "cuota {$n} pagó su mora completa: exonerada 0");
        }

        $rows = collect(Livewire::test(Schedule::class, ['id' => $credit->id])->viewData('rows'));
        $this->assertSame(0.0, (float) $rows->whereBetween('n', [18, 25])->sum('mora_exon'), 'el cronograma tampoco pinta rojo');
    }

    /**
     * Con la mora del sistema (solo la más antigua, 186,48) sí queda exonerada,
     * cuota por cuota: la 18 pagó la suya completa (nada en rojo) y de la 19 en
     * adelante se exonera la teórica entera. 22/10: antes, con el prorrateo por
     * días, la 19 salía "pagó 36,26" y "exonerada 126,91" a la vez.
     */
    public function test_la_exonerada_es_la_diferencia_por_cuota_cuando_se_cobro_menos(): void
    {
        ['credit' => $credit] = $this->operacion(mora: 186.48);

        $exon = MoraExonerada::porCuota($credit);

        $this->assertArrayNotHasKey(18, $exon, 'la 18 pagó sus 186,48');
        $this->assertSame(163.17, $exon[19]['monto'], 'teórica 49 × 3,33, no pagó nada de mora');
        $this->assertSame(49, $exon[19]['dias']);
        $this->assertSame(
            round(self::MORA - 186.48, 2),
            round(collect($exon)->only(range(18, 25))->sum('monto'), 2),
            'lo exonerado es exactamente lo que faltó cobrar'
        );
    }

    // ─── Las pantallas ─────────────────────────────────────────────────────

    /** /payments/create: cada CELDA pinta su parte; la ancla ya no pinta el total. */
    public function test_la_pantalla_de_pagos_pinta_el_reparto_en_las_celdas(): void
    {
        ['credit' => $credit] = $this->operacion(legacyEnCuota5: 50.00);

        $html = Livewire::test(Create::class, ['creditId' => $credit->id])->html();

        foreach (self::PARTES as $n => [$monto]) {
            $this->assertSame(1, $this->celdas($html, number_format($monto, 2)), "cuota {$n}: una celda con {$monto}");
        }
        $this->assertSame(1, $this->celdas($html, '50.00'), 'la mora legacy de la cuota 5');
        // 839,16 solo en la fila de Totales (antes también en la celda de la 18).
        $this->assertSame(0, $this->celdas($html, '839.16'), 'ninguna celda de cuota con el total');
        $this->assertStringContainsString('889.16', $html, 'Totales = 839,16 + 50');
        $this->assertSame(0, $this->celdas($html, '104.90'), 'ya no se reparte parejo');
    }

    /** /credits/{id}/schedule: misma columna, mismo reparto, y los totales cuadran con las celdas. */
    public function test_el_cronograma_pinta_el_mismo_reparto_y_cuadra(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(legacyEnCuota5: 50.00);

        $c = Livewire::test(Schedule::class, ['id' => $credit->id]);
        $html = $c->html();

        foreach (self::PARTES as $n => [$monto]) {
            $this->assertSame(1, $this->celdas($html, number_format($monto, 2)), "cuota {$n}: una celda con {$monto}");
        }
        $this->assertSame(0, $this->celdas($html, '104.90'));

        $rows = collect($c->viewData('rows'));
        $otros = collect($c->viewData('otrosRows'));
        $totals = $c->viewData('totals');
        $this->assertSame(889.16, round($rows->sum('mora'), 2), 'celdas = 839,16 + 50 legacy');
        $this->assertSame(round($rows->sum('mora'), 2), round((float) $totals['mora'], 2), 'Totales = suma de celdas');
        $this->assertSame(0.0, round($otros->sum('mora'), 2), 'nada "sin cuota": todo está anotado en cuotas');
        $this->assertSame(186.48, $rows->firstWhere('installment_id', $ids[18])['mora']);
    }

    /** Un cobro que es SOLO mora (sin capital ni interés) se pinta una vez, en su cuota, y no en Otros. */
    public function test_un_cobro_solo_mora_se_pinta_una_vez_en_el_cronograma(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion();

        // Segundo cobro, días después: solo mora de 40 sobre la 26 (vencida
        // para entonces), anclada a ella, sin filas C/I en la operación.
        $op2 = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 40, 'date' => '2026-09-24', 'time' => '11:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pagoMora2 = DB::table('payments')->insertGetId([
            'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => 'MORA', 'documento' => 'MORA',
            'fecha' => '2026-09-24 00:00:00', 'hora' => '11:00:00', 'monto' => 40, 'detalle' => 'Mora de cuota',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mass_deletion_details')->insert([
            'mass_deletion_id' => $op2, 'installment_id' => $ids[26], 'payment_id' => $pagoMora2,
            'amount' => 40, 'fecha' => now(), 'tipo' => 'M', 'created_at' => now(), 'updated_at' => now(),
        ]);
        CreditInstallment::where('id', $ids[26])->update(['importe_mora' => 40]);

        $c = Livewire::test(Schedule::class, ['id' => $credit->id]);
        $rows = collect($c->viewData('rows'));
        $otros = collect($c->viewData('otrosRows'));

        $this->assertSame(40.0, $rows->firstWhere('installment_id', $ids[26])['mora'], 'en su cuota');
        $this->assertSame(0.0, round($otros->sum('mora'), 2), 'NO además en Otros');
        $this->assertSame(1, $this->celdas($c->html(), '40.00'));
        $this->assertSame(879.16, round((float) $c->viewData('totals')['mora'], 2), '839,16 + 40, contado una vez');
    }

    /** La mora que entró a caja sin anotarse en ninguna cuota (MORA ACUM. al cancelar) va aparte y cuadra. */
    public function test_la_mora_sin_cuota_va_en_su_fila_y_los_totales_cuadran(): void
    {
        ['credit' => $credit] = $this->operacion();

        DB::table('payments')->insert([
            'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => 'MORA', 'documento' => 'MORA ACUM.',
            'fecha' => '2026-09-24 00:00:00', 'hora' => '12:00:00', 'monto' => 30, 'detalle' => 'Mora Acumulada Cancelacion',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $c = Livewire::test(Schedule::class, ['id' => $credit->id]);
        $rows = collect($c->viewData('rows'));
        $otros = collect($c->viewData('otrosRows'));

        $this->assertSame(self::MORA, round($rows->sum('mora'), 2), 'las celdas siguen en 839,16');
        $this->assertSame(30.0, round($otros->sum('mora'), 2), 'los 30 salen en la fila sin cuota');
        $this->assertSame(30.0, round((float) $c->viewData('sumOtrosMora'), 2));
        $this->assertSame(
            round(self::MORA + 30, 2),
            round((float) $c->viewData('totals')['mora'] + (float) $c->viewData('sumOtrosMora'), 2),
            'Totales de la columna = celdas + sin cuota'
        );
    }
}
