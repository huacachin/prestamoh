<?php

namespace Tests\Feature;

use App\Livewire\Reports\CashGeneral3;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Caja General 3: mismo defecto y mismo arreglo que Caja General 2 (18/09).
 * El legacy (sistema/reporte2a3.php:249 y :253) imprime en el TOTAL del día
 * $totalImporte0 —el saldo de ayer más los ingresos de hoy—, así que allí
 * TOTAL − EGRESO = SALDO en todos los renglones; nosotros publicábamos solo
 * los ingresos del día y la resta cuadraba únicamente el día 1.
 */
class CajaGeneral3SaldoTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(): void
    {
        $user = User::factory()->create(['username' => 'caja-g3']);
        $this->actingAs($user);

        // Caja 3 no lee apertura de mes: arranca en 0.
        // Día 1: ingresa 1500, sale 200 → saldo 1300
        // Día 2: ingresa  300, sale 100 → saldo 1500
        // Día 3: ingresa    0, sale 400 → saldo 1100
        foreach ([['2026-08-01', 1500], ['2026-08-02', 300]] as [$fecha, $monto]) {
            Income::create([
                'date' => $fecha, 'total' => $monto, 'reason' => 'OTROS',
                'detail' => 'prueba', 'asesor' => '', 'user_id' => $user->id,
                'caja' => 3, 'modo' => 'Otros',
            ]);
        }
        foreach ([['2026-08-01', 200], ['2026-08-02', 100], ['2026-08-03', 400]] as [$fecha, $monto]) {
            Expense::create([
                'date' => $fecha, 'total' => $monto, 'reason' => 'OTROS',
                'detail' => 'prueba', 'user_id' => $user->id,
                'caja' => 3, 'modo' => 'Otros',
            ]);
        }
    }

    /** @return list<array{date:string,total_ingreso:float,total_egreso:float,saldo:float}> */
    private function dias(): array
    {
        // month/year son propiedades #[Url]; mount() no las recibe por
        // parámetro, así que se fijan sobre el componente ya montado.
        $datos = Livewire::test(CashGeneral3::class)
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
