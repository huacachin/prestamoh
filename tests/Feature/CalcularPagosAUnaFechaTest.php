<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\PagosAUnaFecha;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Calcular pagos" (30/10/2026, Antony, crédito 29393): el botón "Calcular cancelación" pasa a "Calcular
 * pagos" y su ventana suma la pestaña "Pagos a una fecha": cualquier fecha, lo que el cliente debe pagar
 * ese día (cuotas que vencen hasta entonces con su mora por días), el texto para mandarle y la opción de
 * llevarlo al Monto a Pagar.
 */
class CalcularPagosAUnaFechaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Credit $credit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-30 10:00:00');
        DB::table('headquarters')->insertOrIgnore(['id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->user = User::factory()->create(['username' => 'calcular-pagos', 'headquarter_id' => 1]);
        $this->actingAs($this->user);

        // Como el 29393: semanal de 500 (312.50 + 187.50), cuotas 1 a 3 pagadas; la 4 venció el 05/10.
        $client = Client::create(['nombre' => 'Cristhian Zosimo', 'apellido_pat' => 'Quispe', 'apellido_mat' => 'Garcia', 'headquarter_id' => 1, 'status' => 'active']);
        $this->credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-09-07', 'importe' => 3125, 'cuotas' => 10, 'tipo_planilla' => 1,
            'interes' => 60, 'interes_total' => 1875, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        foreach (range(1, 10) as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-14')->addWeeks($n - 1)->toDateString(),
                'importe_cuota' => 312.50, 'importe_interes' => 187.50,
                'importe_aplicado' => $n <= 3 ? 312.50 : 0, 'interes_aplicado' => $n <= 3 ? 187.50 : 0, 'pagado' => $n <= 3,
            ]);
        }
    }

    public function test_lo_que_debe_pagar_a_una_fecha_con_el_texto_para_el_cliente(): void
    {
        $calculo = PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-10-30'));

        $this->assertSame([4, 5, 6, 7], array_column($calculo['filas'], 'num'));
        // Mora por días calendario × tarifa de la cuota (5 % × 500 ÷ 7 = 3.57), como el cobro.
        $this->assertSame([25, 18, 11, 4], array_column($calculo['filas'], 'dias'));
        $this->assertSame([89.25, 64.26, 39.27, 14.28], array_column($calculo['filas'], 'mora'));
        $this->assertSame([2000.0, 207.06, 2207.06], [$calculo['saldo'], $calculo['mora'], $calculo['total']]);
        $this->assertSame(
            "Cliente: *Quispe Garcia Cristhian Zosimo*\n"
            ."Pagos al 30/10/2026\n"
            ."Mora por día: S/ 3.57 por cuota vencida\n"
            ."\n"
            ."1-4    05/10/2026    500.00    0.00    Vencida    Mora 25 Dias 89.25\n"
            ."2-5    12/10/2026    500.00    0.00    Vencida    Mora 18 Dias 64.26\n"
            ."3-6    19/10/2026    500.00    0.00    Vencida    Mora 11 Dias 39.27\n"
            ."4-7    26/10/2026    500.00    0.00    Vencida    Mora 4 Dias 14.28\n"
            ."Total sin mora 2,000.00\n"
            .'Total con mora 2,207.06',
            $calculo['texto']
        );
    }

    public function test_solo_las_cuotas_marcadas_van_al_total_y_al_texto(): void
    {
        // El 31/10 lista las 4 vencidas; se marcan las 2 más antiguas.
        $calculo = PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-10-31'), 2);

        $this->assertSame([true, true, false, false], array_column($calculo['filas'], 'sel'));
        $this->assertSame([2, 1000.0, 160.65, 1160.65], [$calculo['cuantas'], $calculo['saldo'], $calculo['mora'], $calculo['total']]);
        $this->assertStringContainsString("1-4    05/10/2026    500.00    0.00    Vencida    Mora 26 Dias 92.82\n2-5    12/10/2026    500.00    0.00    Vencida    Mora 19 Dias 67.83\nTotal sin mora 1,000.00\nTotal con mora 1,160.65", $calculo['texto']);
        $this->assertStringNotContainsString('3-6', $calculo['texto']);

        // Ninguna marcada: totales en 0; fuera de rango se acota.
        $this->assertSame([0, 0.0], [PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-10-31'), 0)['cuantas'], PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-10-31'), 0)['total']]);
        $this->assertSame(4, PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-10-31'), 99)['cuantas']);
    }

    public function test_la_cuota_que_vence_ese_dia_va_sin_mora_y_lo_abonado_se_descuenta(): void
    {
        CreditInstallment::where('credit_id', $this->credit->id)->where('num_cuota', 4)->update(['interes_aplicado' => 187.50]);

        // El 02/11 vence la cuota 8: entra sin mora, como "Vence".
        $calculo = PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-11-02'));

        $this->assertSame([4, 5, 6, 7, 8], array_column($calculo['filas'], 'num'));
        $this->assertSame(['Vence', 0, 0.0], [$calculo['filas'][4]['estado'], $calculo['filas'][4]['dias'], $calculo['filas'][4]['mora']]);
        $this->assertSame([187.50, 312.50], [$calculo['filas'][0]['pagado'], $calculo['filas'][0]['saldo']]);
        $this->assertStringContainsString("1-4    05/10/2026    500.00    187.50    Vencida    Mora 28 Dias 99.96\n", $calculo['texto']);
        $this->assertStringContainsString("5-8    02/11/2026    500.00    0.00    Vence\nTotal sin mora ", $calculo['texto']);

        // Antes del primer vencimiento impago no debe nada.
        $this->assertSame([[], 0.0], [PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-10-04'))['filas'], PagosAUnaFecha::calcular($this->credit, Carbon::parse('2026-10-04'))['total']]);
    }

    public function test_la_ventana_calcular_pagos_y_llevarlo_al_monto_a_pagar(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->assertSee('Calcular pagos')
            ->assertDontSee('Calcular cancelación</button>', false)
            ->call('abrirCalcularPagos')
            ->assertReturned(fn ($d) => $d['cancelacion']['minimo'] === 3 && $d['cancelacion']['mora'] === 207.06 && $d['sin_cancelacion'] === null && $d['hoy'] === '2026-10-30'
                && count($d['vencimientos']) === 10 && $d['vencimientos'][0] === ['num' => 1, 'fecha' => '2026-09-14', 'pagada' => true]
                && $d['puede_mora'] === true)
            ->call('calcularPagosAl', '2026-10-30')
            ->assertReturned(fn ($c) => $c['total'] === 2207.06 && $c['fecha'] === '30/10/2026')
            ->call('calcularPagosAl', '30/10/2026')
            ->assertReturned(null)
            ->call('calcularPagosAl', '2026-10-30', 3)
            ->assertReturned(fn ($c) => $c['cuantas'] === 3 && $c['total'] === 1692.78)
            ->call('usarPagosAl', '2026-10-30')
            ->assertReturned(true)
            ->assertSet('monto', '2000.00')
            ->assertSet('moraManual', '207.06')
            ->assertSet('moraCuotasAplicadas', [4, 5, 6, 7])
            ->assertDispatched('monto-usado')
            // Solo las marcadas: las 2 más antiguas.
            ->call('usarPagosAl', '2026-10-30', 2)
            ->assertReturned(true)
            ->assertSet('monto', '1000.00')
            ->assertSet('moraManual', '153.51')
            ->assertSet('moraCuotasAplicadas', [4, 5])
            ->call('usarPagosAl', '2026-10-30', 0)
            ->assertReturned(false);

        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('usarPagosAl', '2026-10-04')
            ->assertReturned(false)
            ->assertDispatched('errorAlert', fn ($n, $p) => str_contains($p[0]['message'] ?? $p['message'] ?? '', 'no hay cuotas por pagar'));
    }

    public function test_sin_calculo_de_cancelacion_la_ventana_abre_igual_en_pagos_a_una_fecha(): void
    {
        // Mensual de una cuota: la cancelación no aplica (el interés corre por mes), los pagos a una fecha sí.
        $this->credit->update(['tipo_planilla' => 3, 'cuotas' => 1]);
        CreditInstallment::where('credit_id', $this->credit->id)->where('num_cuota', '>', 1)->delete();
        CreditInstallment::where('credit_id', $this->credit->id)->update(['pagado' => 0, 'importe_aplicado' => 0, 'interes_aplicado' => 0]);

        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirCalcularPagos')
            ->assertReturned(fn ($d) => $d['cancelacion'] === null && str_contains($d['sin_cancelacion'], 'usa el switch Cancelado'))
            ->assertNotDispatched('errorAlert')
            ->call('calcularPagosAl', '2026-10-30')
            ->assertReturned(fn ($c) => count($c['filas']) === 1 && $c['filas'][0]['dias'] === 46);
    }
}
