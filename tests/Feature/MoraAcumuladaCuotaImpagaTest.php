<?php

namespace Tests\Feature;

use App\Livewire\Credits\Schedule;
use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\MoraExonerada;
use App\Support\ResumenDeudaCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La "mora acumulada (cuotas ya pagadas)" cuenta solo las cuotas YA pagadas
 * (23/10/2026, Antony, cliente 89, crédito 29413). Antes una cuota impaga con
 * abonos tomaba como fecha de pago la del último abono y salía "pagada tarde":
 * sus días se contaban otra vez en la mora vigente, que corre desde el
 * vencimiento de la impaga más antigua, y al cancelar se cobraban las dos.
 */
class MoraAcumuladaCuotaImpagaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'mora-acumulada', 'headquarter_id' => 1]));
    }

    private function pago(Credit $c, string $tipo, float $monto, int $haceDias): void
    {
        DB::table('payments')->insert([
            'credit_id' => $c->id, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
            'fecha' => now()->subDays($haceDias)->format('Y-m-d').' 00:00:00', 'hora' => '10:00:00', 'monto' => $monto,
            'detalle' => "Pago : {$c->id}", 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Como el 29413: mensual de una cuota (3,000 al 5 %) vencida hace 60 días, impaga, con dos abonos
     * de interés de 50 después del vencimiento.
     */
    private function mensualDeUnaCuota(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Mensual']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(90)->format('Y-m-d'),
            'importe' => 3000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5,
            'interes_total' => 150, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => now()->subDays(60)->format('Y-m-d'),
            'importe_cuota' => 3000, 'importe_interes' => 150, 'interes_aplicado' => 100, 'pagado' => 0,
        ]);
        $this->pago($credit, 'INTERES', 50, 30);
        $this->pago($credit, 'INTERES', 50, 10);

        return $credit;
    }

    public function test_el_mensual_de_una_cuota_con_abonos_no_tiene_mora_acumulada(): void
    {
        $credit = $this->mensualDeUnaCuota();

        $this->assertSame([], MoraExonerada::porCuota($credit), 'la cuota sigue impaga: su atraso va en la mora vigente');

        // Resumen de la ficha: la mora de la cuota corre desde el vencimiento y no hay línea de acumulada.
        $r = ResumenDeudaCredito::de($credit, $credit->client);
        $this->assertSame(['dias' => 0, 'monto' => 0.0], $r['mora_acumulada']);
        $this->assertStringContainsString('Mora cuota 1: 60 días', $r['texto']);
        $this->assertStringNotContainsString('Mora acumulada', $r['texto']);

        // Cronograma: la cuota no pinta exonerada en rojo.
        $rows = collect(Livewire::test(Schedule::class, ['id' => $credit->id])->viewData('rows'));
        $this->assertSame(0.0, (float) $rows->firstWhere('n', 1)['mora_exon']);
    }

    /**
     * Un crédito cerrado no tiene mora vigente que repita los días: su cronograma se ve como antes,
     * aunque la cuota quedara sin marcar como pagada (cancelaciones del legacy y refinanciaciones).
     */
    public function test_un_credito_cancelado_sigue_mostrando_su_acumulada(): void
    {
        $credit = $this->mensualDeUnaCuota();
        $credit->update(['situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => now()->subDays(10)->format('Y-m-d')]);

        $this->assertSame([1 => ['monto' => 262.5, 'dias' => 50]], MoraExonerada::porCuota($credit->fresh()));
        $rows = collect(Livewire::test(Schedule::class, ['id' => $credit->id])->viewData('rows'));
        $this->assertSame(262.5, (float) $rows->firstWhere('n', 1)['mora_exon']);
    }

    /** Al cancelar ya no se cobra la acumulada encima de la vigente: eran los mismos días. */
    public function test_cancelar_no_cobra_como_acumulada_los_dias_de_la_cuota_impaga(): void
    {
        $credit = $this->mensualDeUnaCuota();

        $c = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 3050)
            ->set('cancel', true)
            ->call('confirmarPago');

        $p = $c->get('preview');
        $this->assertTrue($p['cancela']);
        $this->assertSame(0.0, $p['mora_acum_cobrar']);
        $this->assertGreaterThan(0, $p['mora_cobrar'], 'la mora vigente de la cuota se sigue cobrando');
    }

    /**
     * Semanal: la cuota 1 se pagó completa 7 días tarde y sigue contando; la 2, vencida y con un abono
     * de interés, sigue impaga y no entra.
     */
    public function test_la_cuota_pagada_tarde_cuenta_y_la_impaga_con_abono_no(): void
    {
        $client = Client::create(['nombre' => 'Cliente Semanal']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(28)->format('Y-m-d'),
            'importe' => 300, 'cuotas' => 3, 'tipo_planilla' => 1, 'interes' => 20,
            'interes_total' => 60, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach ([1 => [21, 100, 20, 1], 2 => [14, 0, 20, 0], 3 => [-7, 0, 0, 0]] as $n => [$vencioHace, $capAp, $intAp, $pagado]) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => now()->subDays($vencioHace)->format('Y-m-d'),
                'importe_cuota' => 100, 'importe_interes' => 20, 'importe_aplicado' => $capAp, 'interes_aplicado' => $intAp, 'pagado' => $pagado,
            ]);
        }
        $this->pago($credit, 'CAPITAL', 100, 14);
        $this->pago($credit, 'INTERES', 20, 14);
        $this->pago($credit, 'INTERES', 20, 7);

        // 7 días × 0.86 (5 % de 120 ÷ 7).
        $this->assertSame([1 => ['monto' => 6.02, 'dias' => 7]], MoraExonerada::porCuota($credit));

        $rows = collect(Livewire::test(Schedule::class, ['id' => $credit->id])->viewData('rows'));
        $this->assertSame([6.02, 0.0], [(float) $rows->firstWhere('n', 1)['mora_exon'], (float) $rows->firstWhere('n', 2)['mora_exon']]);
    }
}
