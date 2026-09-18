<?php

namespace Tests\Feature;

use App\Livewire\Reports\CashGeneral2;
use App\Models\CashOpening;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La columna SALDO del reporte de Caja General 2 arrastra el día anterior, y
 * el TOTAL de la fila de cada día es ACUMULADO, no el ingreso suelto.
 *
 * El legacy (sistema/reporte2a.php) imprime en esa celda $totalImporte0 —el
 * saldo de ayer más los ingresos de hoy— y en la de al lado $totgdescuento,
 * que es exactamente lo que el SALDO resta (líneas 265, 277 y 281). Por eso
 * allí se cumple TOTAL − EGRESO = SALDO en todos los renglones.
 *
 * Nosotros publicábamos solo los ingresos del día, así que la resta cuadraba
 * el día 1 y ninguno más: eso es lo que Antony vio el 18/09 como "no está
 * sumando el saldo del día con el del día anterior".
 */
class CajaGeneral2SaldoTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(): void
    {
        $user = User::factory()->create(['username' => 'caja-g2']);
        $this->actingAs($user);

        // Apertura del mes: el saldo que viene del mes anterior.
        CashOpening::create(['fecha' => '2026-08-01', 'saldo_inicial' => 1000]);

        // Día 1: ingresa 500, sale 200  → saldo 1300
        // Día 2: ingresa 300, sale 100  → saldo 1500
        // Día 3: ingresa   0, sale 400  → saldo 1100
        foreach ([['2026-08-01', 500], ['2026-08-02', 300]] as [$fecha, $monto]) {
            Income::create([
                // El reporte solo mira la caja operativa (caja 1) y los
                // modos Otros/Fijos: sin eso la fila no entra.
                'date' => $fecha, 'total' => $monto, 'reason' => 'OTROS',
                'detail' => 'prueba', 'asesor' => '', 'user_id' => $user->id,
                'caja' => 1, 'modo' => 'Otros',
            ]);
        }
        foreach ([['2026-08-01', 200], ['2026-08-02', 100], ['2026-08-03', 400]] as [$fecha, $monto]) {
            Expense::create([
                'date' => $fecha, 'total' => $monto, 'reason' => 'OTROS',
                'detail' => 'prueba', 'user_id' => $user->id,
                'caja' => 1, 'modo' => 'Otros',
            ]);
        }
    }

    /** @return list<array{date:string,total_ingreso:float,total_egreso:float,saldo:float}> */
    private function dias(): array
    {
        // month/year son propiedades #[Url]; mount() no las recibe por
        // parámetro, así que se fijan sobre el componente ya montado.
        $datos = Livewire::test(CashGeneral2::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->viewData('report');

        return $datos['days'];
    }

    public function test_el_saldo_de_cada_dia_arrastra_el_del_dia_anterior(): void
    {
        $this->travelTo('2026-09-18');
        $this->mundo();

        $dias = $this->dias();

        $this->assertCount(3, $dias, 'deben salir los tres días con movimiento');
        $this->assertSame(
            ['2026-08-01' => 1300.0, '2026-08-02' => 1500.0, '2026-08-03' => 1100.0],
            array_combine(array_column($dias, 'date'), array_map(fn ($d) => round($d['saldo'], 2), $dias))
        );
    }

    /**
     * La invariante del legacy, que es lo que se ve en pantalla: en CADA
     * renglón, TOTAL menos EGRESO tiene que dar el SALDO. Antes solo cuadraba
     * el primer día y la diferencia era justo el saldo del día anterior.
     */
    public function test_en_cada_dia_el_total_menos_el_egreso_da_el_saldo(): void
    {
        $this->travelTo('2026-09-18');
        $this->mundo();

        foreach ($this->dias() as $d) {
            $this->assertEqualsWithDelta(
                $d['saldo'], $d['total_ingreso'] - $d['total_egreso'], 0.001,
                "el {$d['date']}: TOTAL ({$d['total_ingreso']}) − EGRESO ({$d['total_egreso']}) ".
                "debe dar el SALDO ({$d['saldo']})"
            );
        }
    }

    /** Y el TOTAL es, explícitamente, el saldo de ayer más lo que entró hoy. */
    public function test_el_total_del_dia_es_el_saldo_de_ayer_mas_los_ingresos_de_hoy(): void
    {
        $this->travelTo('2026-09-18');
        $this->mundo();

        $dias = $this->dias();
        $ingresosDelDia = ['2026-08-01' => 1500.0, '2026-08-02' => 300.0, '2026-08-03' => 0.0];

        $saldoAyer = 0.0;
        foreach ($dias as $d) {
            $this->assertEqualsWithDelta(
                $saldoAyer + $ingresosDelDia[$d['date']], $d['total_ingreso'], 0.001,
                "el {$d['date']}: el TOTAL debe ser el saldo de ayer más los ingresos del día"
            );
            $saldoAyer = $d['saldo'];
        }
    }
}
