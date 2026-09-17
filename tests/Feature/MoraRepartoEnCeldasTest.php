<?php

namespace Tests\Feature;

use App\Livewire\Credits\Schedule;
use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
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
 * generaron (17/09, pedido de Antony: "muestra el reparto en las celdas,
 * no en el tooltip").
 *
 * El caso que lo motivó es el crédito 28957: Licet pagó las cuotas 18 a 25
 * desde el modal con 839,16 de mora. El motor anota el cobro entero en
 * importe_mora de la PRIMERA cuota tocada (la 18) y las pantallas pintaban
 * esa columna tal cual: 839,16 en la 18 y nada en la 19 a 25, con el reparto
 * escondido en un tooltip. Antony leyó "la mora solo se aplicó a la 18".
 *
 * Este fixture reproduce esa operación tal como queda en la base: pagos
 * C/I por cuota, un pago MORA sin cuota, y en mass_deletion_details las
 * filas C/I de las ocho cuotas más la fila M anclada a la 18.
 */
class MoraRepartoEnCeldasTest extends TestCase
{
    use RefreshDatabase;

    private const MORA = 839.16;

    private const FECHA_PAGO = '2026-09-17';

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
     * operación con la mora anclada a la 18. Opcionalmente una cuota con mora
     * "propia" (legacy, sin operación) y mora legacy encima de la ancla.
     *
     * @return array{credit: Credit, ids: array<int, int>} ids por num_cuota
     */
    private function operacion(float $legacyEnCuota5 = 0, float $legacyEnAncla = 0): array
    {
        $client = Client::create(['nombre' => 'Cliente Reparto', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-03-19',
            'importe' => 14000, 'cuotas' => 48, 'tipo_planilla' => 1, 'interes' => 60,
            'interes_total' => 8400, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);

        $ids = [];
        // Cuota 18 vence el 23/07; las demás cada 7 días. 1-17 ya pagadas antes,
        // 26-28 pendientes: contexto para que la pantalla se parezca a la real.
        for ($n = 1; $n <= 28; $n++) {
            $venc = Carbon::parse('2026-07-23')->addWeeks($n - 18);
            $pagada = $n <= 25;
            $ins = CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => $venc->format('Y-m-d'),
                'importe_cuota' => 291.67, 'importe_interes' => 175.00, 'importe_excedente' => 0,
                'importe_aplicado' => $pagada ? 291.67 : 0, 'interes_aplicado' => $pagada ? 175.00 : 0,
                'excedente_aplicado' => 0,
                'importe_mora' => match (true) {
                    $n === 18 => self::MORA + $legacyEnAncla,
                    $n === 5 => $legacyEnCuota5,
                    default => 0,
                },
                'mora_interes' => 0,
                'pagado' => $pagada,
                'fecha_pago' => $pagada ? ($n >= 18 ? self::FECHA_PAGO : $venc->format('Y-m-d')) : null,
            ]);
            $ids[$n] = $ins->id;
        }

        $opId = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 3666, 'date' => self::FECHA_PAGO,
            'time' => '10:51:27', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $pago = fn (string $tipo, float $monto, ?int $insId, string $detalle) => DB::table('payments')->insertGetId([
            'credit_id' => $credit->id, 'installment_id' => $insId, 'modo' => 'CREDITO',
            'tipo' => $tipo, 'documento' => $tipo, 'fecha' => self::FECHA_PAGO.' 00:00:00', 'hora' => '10:51:27',
            'monto' => $monto, 'detalle' => $detalle, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $detalle = fn (int $insId, int $pagoId, float $monto, string $tipo) => DB::table('mass_deletion_details')->insert([
            'mass_deletion_id' => $opId, 'installment_id' => $insId, 'payment_id' => $pagoId,
            'amount' => $monto, 'fecha' => now(), 'tipo' => $tipo, 'created_at' => now(), 'updated_at' => now(),
        ]);

        for ($n = 18; $n <= 25; $n++) {
            $detalle($ids[$n], $pago('INTERES', 175.00, $ids[$n], "Interes: {$n}/48"), 175.00, 'I');
            $detalle($ids[$n], $pago('CAPITAL', 291.67, $ids[$n], "Cuota: {$n}/48"), 291.67, 'C');
        }
        // La mora: UN pago sin cuota y la fila M anclada a la primera cuota tocada.
        $detalle($ids[18], $pago('MORA', self::MORA, null, 'Mora de cuota'), self::MORA, 'M');

        return ['credit' => $credit->fresh(), 'ids' => $ids];
    }

    public function test_cada_cuota_muestra_su_parte_y_la_ancla_ya_no_carga_todo(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion();

        $celda = MoraPagada::mostradaPorCuota($credit);

        // 839,16 entre 8 cuotas de 7 días cada una: 104,90 y el resto en la última.
        foreach (range(18, 24) as $n) {
            $this->assertSame(104.90, $celda[$ids[$n]]['monto'], "cuota {$n} debe mostrar su parte");
            $this->assertSame(7, $celda[$ids[$n]]['dias']);
        }
        $this->assertSame(104.86, $celda[$ids[25]]['monto'], 'la última se lleva el redondeo');

        // LA CLAVE: la cuota 18 ya no muestra los 839,16 enteros.
        $this->assertNotEquals(self::MORA, $celda[$ids[18]]['monto']);
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
        $registrada = (float) $credit->installments->sum(fn ($i) => $i->importe_mora + $i->mora_interes);

        $this->assertSame(round($registrada, 2), round(array_sum(array_column($celda, 'monto')), 2));
        $this->assertSame(909.16, round($registrada, 2), 'sanity: 839,16 + 50 + 20');
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

    /**
     * Ancla con mora legacy encima del cobro: muestra su parte del reparto
     * MÁS lo propio, y las demás cuotas del cobro no se enteran.
     */
    public function test_la_ancla_con_mora_propia_suma_las_dos_cosas(): void
    {
        ['credit' => $credit, 'ids' => $ids] = $this->operacion(legacyEnAncla: 20.00);

        $celda = MoraPagada::mostradaPorCuota($credit);

        $this->assertSame(124.90, $celda[$ids[18]]['monto'], '104,90 del reparto + 20 propios');
        $this->assertSame(104.90, $celda[$ids[19]]['monto']);
        $this->assertStringContainsString('anotada en la propia cuota: 20.00', $celda[$ids[18]]['detalle']);
    }

    /** /payments/create: las celdas de la 18 a la 25 pintan el reparto. */
    public function test_la_pantalla_de_pagos_pinta_el_reparto_en_las_celdas(): void
    {
        ['credit' => $credit] = $this->operacion(legacyEnCuota5: 50.00);

        $html = Livewire::test(Create::class, ['creditId' => $credit->id])->html();

        $this->assertGreaterThanOrEqual(7, substr_count($html, '104.90'), 'siete cuotas con 104,90');
        $this->assertStringContainsString('104.86', $html);
        $this->assertStringContainsString('50.00', $html, 'la mora legacy de la cuota 5');
        // El total del crédito sigue siendo el registrado: 839,16 + 50.
        $this->assertStringContainsString('889.16', $html);
    }

    /** /credits/{id}/schedule: misma columna, mismo reparto. */
    public function test_el_cronograma_pinta_el_mismo_reparto(): void
    {
        ['credit' => $credit] = $this->operacion();

        $html = Livewire::test(Schedule::class, ['id' => $credit->id])->html();

        $this->assertGreaterThanOrEqual(7, substr_count($html, '104.90'));
        $this->assertStringContainsString('104.86', $html);
    }
}
