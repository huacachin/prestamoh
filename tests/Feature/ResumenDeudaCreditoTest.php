<?php

namespace Tests\Feature;

use App\Livewire\Clients\Show;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Support\ResumenDeudaCredito;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 23/10/2026 (Antony): en la ficha del cliente, el clic en el código del
 * crédito abre un modal con el resumen de cobranza para copiar. La regla del
 * crédito mensual es la de su ejemplo: 16 800 al 5 % del 28/01/2026 →
 * "Retraso 9 meses", 9 × 840 = 7 560, "Total de deuda indicado" 24 360.
 */
class ResumenDeudaCreditoTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-23 10:00:00'));
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'resumen-tester', 'headquarter_id' => $this->sede->id]);
    }

    private function cliente(string $nombre = 'Mauro', string $pat = 'Janampa', string $mat = 'Cahuana'): Client
    {
        return Client::create(['nombre' => $nombre, 'apellido_pat' => $pat, 'apellido_mat' => $mat, 'headquarter_id' => $this->sede->id, 'status' => 'active']);
    }

    private function credito(Client $client, array $datos): Credit
    {
        return Credit::create($datos + [
            'client_id' => $client->id, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id, 'user_id' => $this->user->id,
        ]);
    }

    private function cuota(Credit $credit, int $n, string $venc, float $cap, float $int, bool $pagada = false, ?float $capAplicado = null, ?float $intAplicado = null): CreditInstallment
    {
        return CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => $venc, 'importe_cuota' => $cap, 'importe_interes' => $int, 'importe_excedente' => 0,
            'importe_aplicado' => $capAplicado ?? ($pagada ? $cap : 0), 'interes_aplicado' => $intAplicado ?? ($pagada ? $int : 0), 'excedente_aplicado' => 0,
            'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => $pagada, 'fecha_pago' => $pagada ? $venc : null,
        ]);
    }

    public function test_credito_mensual_de_una_cuota_sin_pagos_cobra_un_interes_por_cada_mes_iniciado_como_el_ejemplo(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-01-28', 'importe' => 16800, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 840, 'fecha_vencimiento' => '2026-02-28']);
        $this->cuota($credit, 1, '2026-02-28', 16800, 840);

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);

        $this->assertSame([9, 'meses', 840.0, 7560.0, 24360.0, 16800.0, '5'], [$r['retraso'], $r['unidad'], $r['interes'], $r['monto_retraso'], $r['total'], $r['capital_pendiente'], $r['interes_pct']]);
        $this->assertSame(implode("\n", [
            'DATOS DEL CRÉDITO', '',
            'Janampa Cahuana Mauro', '',
            'Fecha del crédito 28/01/2026', '',
            'Capital prestado', 'S/ 16,800.00', '',
            'Interés del 5% S/ 840.00', '',
            'Retraso 9 meses', '',
            'Monto Retraso S/ 7,560.00', '',
            'Total de deuda indicado', 'S/ 24,360.00',
        ]), $r['texto']);
    }

    public function test_los_meses_iniciados_cuentan_entero_el_mes_en_curso_y_cero_el_mismo_dia(): void
    {
        $this->assertSame(9, ResumenDeudaCredito::mesesIniciados(Carbon::parse('2026-01-28'), Carbon::parse('2026-10-23')));
        $this->assertSame(8, ResumenDeudaCredito::mesesIniciados(Carbon::parse('2026-01-28'), Carbon::parse('2026-09-28')), 'el día del vencimiento del 8.º mes no abre el 9.º');
        $this->assertSame(1, ResumenDeudaCredito::mesesIniciados(Carbon::parse('2026-10-22'), Carbon::parse('2026-10-23')));
        $this->assertSame(0, ResumenDeudaCredito::mesesIniciados(Carbon::parse('2026-10-23'), Carbon::parse('2026-10-23')));
        $this->assertSame(2, ResumenDeudaCredito::mesesIniciados(Carbon::parse('2026-01-31'), Carbon::parse('2026-03-05')), 'fin de mes sin desbordar: 28/02 y 31/03');
    }

    public function test_credito_mensual_en_cuotas_descuenta_los_meses_de_interes_pagados_y_muestra_el_capital_pendiente(): void
    {
        $client = $this->cliente('Henry Nestor', 'Acosta', 'Cumpa');
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-01-03', 'importe' => 10000, 'cuotas' => 12, 'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 6000, 'fecha_vencimiento' => '2027-01-03']);
        for ($n = 1; $n <= 12; $n++) {
            $this->cuota($credit, $n, Carbon::parse('2026-01-03')->addMonthsNoOverflow($n)->format('Y-m-d'), $n === 12 ? 833.37 : 833.33, 500, pagada: $n <= 7, capAplicado: $n <= 7 ? null : 0, intAplicado: $n <= 7 ? null : ($n === 8 ? 0.49 : 0));
        }

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);

        // 10 meses iniciados (03/01 → 23/10) menos 7 pagados = 3; capital pendiente 10 000 − 7 × 833.33.
        $this->assertSame([3, 1500.0, 4166.69, 5666.69], [$r['retraso'], $r['monto_retraso'], $r['capital_pendiente'], $r['total']]);
        $this->assertStringContainsString("Capital prestado\nS/ 10,000.00\n\nCapital pendiente\nS/ 4,166.69\n\nInterés del 5% S/ 500.00\n\nRetraso 3 meses\n\nMonto Retraso S/ 1,500.00\n\nTotal de deuda indicado\nS/ 5,666.69", $r['texto']);
    }

    public function test_credito_semanal_cuenta_cuotas_vencidas_y_saldo_del_cronograma_y_el_cancelado_no_debe_nada(): void
    {
        $client = $this->cliente('Mariela', 'Chagua', 'Chavez');
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-09-18', 'importe' => 833.32, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 60, 'interes_total' => 499.99]);
        foreach (['2026-09-25', '2026-10-02', '2026-10-13', '2026-10-30'] as $i => $venc) {
            $this->cuota($credit, $i + 1, $venc, 208.33, 125.00, pagada: $i < 2);
        }

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);
        $this->assertSame([1, 'cuota', 333.33, 666.66, 416.66], [$r['retraso'], $r['unidad'], $r['monto_retraso'], $r['total'], $r['capital_pendiente']]);
        $this->assertStringContainsString("Retraso 1 cuota\n\nMonto Retraso S/ 333.33\n\nTotal de deuda indicado\nS/ 666.66", $r['texto']);

        $credit->update(['situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-10-20']);
        $c = ResumenDeudaCredito::de($credit->fresh(), $client);
        $this->assertSame([0, 0.0, 0.0, '20/10/2026'], [$c['retraso'], $c['monto_retraso'], $c['total'], $c['cancelado']]);
        $this->assertStringEndsWith("Total de deuda indicado\nS/ 0.00\n\nCrédito cancelado el 20/10/2026", $c['texto']);
    }

    public function test_el_codigo_de_la_ficha_abre_el_resumen_solo_de_los_creditos_de_ese_cliente(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $this->actingAs($this->user);
        $client = $this->cliente();
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-01-28', 'importe' => 16800, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 840]);
        $this->cuota($credit, 1, '2026-02-28', 16800, 840);
        $ajeno = $this->credito($this->cliente('Otro', 'Perez', 'Lopez'), ['fecha_prestamo' => '2026-01-28', 'importe' => 100, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 5]);

        $ficha = Livewire::test(Show::class, ['id' => $client->id])
            ->assertSeeHtml('wire:click.prevent="verResumen('.$credit->id.')"')
            ->call('verResumen', $credit->id)
            ->assertDispatched('resumen-credito-listo')
            ->assertSee('Retraso 9 meses')
            ->assertSee('S/ 24,360.00');
        $this->assertSame($credit->id, $ficha->get('resumen')['codigo']);

        $ficha->call('verResumen', $ajeno->id)->assertDispatched('errorAlert')->assertNotDispatched('resumen-credito-listo');
    }
}
