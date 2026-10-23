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
 * crédito abre un modal con el resumen de cobranza para copiar. El retraso
 * va EXACTO en meses y días (Antony: "no lo redondees"), y el monto suma
 * meses × interés mensual + días × interés diario (interés mensual ÷ 30).
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

    public function test_credito_mensual_de_una_cuota_sin_pagos_cuenta_el_retraso_exacto_en_meses_y_dias_desde_la_fecha_del_credito(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-01-28', 'importe' => 16800, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 840, 'fecha_vencimiento' => '2026-02-28']);
        $this->cuota($credit, 1, '2026-02-28', 16800, 840);

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);

        // 28/01 → 23/10 = 8 meses y 25 días; 8 × 840 = 6 720 más 25 × 28 = 700 → 7 420; total 16 800 + 7 420.
        // Mora: la cuota (17 640) venció el 28/02 → 237 días × (17 640 × 5 % ÷ 30 = 29.40) = 6 967.80.
        $this->assertSame([8, 25, '8 meses y 25 días', 840.0, 28.0, 6720.0, 700.0, 7420.0, 24220.0, 16800.0, '5'], [
            $r['retraso_meses'], $r['retraso_dias'], $r['retraso_texto'], $r['interes'], $r['interes_diario'], $r['monto_meses'], $r['monto_dias'], $r['monto_retraso'], $r['total'], $r['capital_pendiente'], $r['interes_pct'],
        ]);
        $this->assertSame([237, 29.4, 6967.8, 31187.8], [$r['mora_dias'], $r['mora_por_dia'], $r['mora'], $r['total_con_mora']]);
        $this->assertSame(implode("\n", [
            'DATOS DEL CRÉDITO #'.$credit->id, '',
            'Janampa Cahuana Mauro', '',
            'Fecha del crédito 28/01/2026',
            'Fecha de corte 23/10/2026', '',
            'Capital prestado', 'S/ 16,800.00', '',
            'Interés del 5% S/ 840.00', '',
            'Retraso 8 meses y 25 días', '',
            'Monto Retraso S/ 7,420.00',
            'Por 8 meses S/ 6,720.00',
            'Por 25 días (S/ 28.00 x día) S/ 700.00', '',
            'Mora 237 días (S/ 29.40 x día) S/ 6,967.80', '',
            'Total de deuda sin mora', 'S/ 24,220.00', '',
            'Total de deuda con mora', 'S/ 31,187.80',
        ]), $r['texto']);
        // Lo que se copia: las mismas líneas, sin las vacías.
        $this->assertSame(implode("\n", [
            'DATOS DEL CRÉDITO #'.$credit->id, 'Janampa Cahuana Mauro', 'Fecha del crédito 28/01/2026', 'Fecha de corte 23/10/2026', 'Capital prestado', 'S/ 16,800.00',
            'Interés del 5% S/ 840.00', 'Retraso 8 meses y 25 días', 'Monto Retraso S/ 7,420.00', 'Por 8 meses S/ 6,720.00',
            'Por 25 días (S/ 28.00 x día) S/ 700.00', 'Mora 237 días (S/ 29.40 x día) S/ 6,967.80',
            'Total de deuda sin mora', 'S/ 24,220.00', 'Total de deuda con mora', 'S/ 31,187.80',
        ]), $r['texto_copia']);
    }

    public function test_los_meses_y_dias_se_cuentan_como_a_mano_y_el_texto_omite_lo_que_es_cero(): void
    {
        $this->assertSame([8, 25], ResumenDeudaCredito::mesesYDias(Carbon::parse('2026-01-28'), Carbon::parse('2026-10-23')));
        $this->assertSame([8, 0], ResumenDeudaCredito::mesesYDias(Carbon::parse('2026-01-28'), Carbon::parse('2026-09-28')));
        $this->assertSame([0, 1], ResumenDeudaCredito::mesesYDias(Carbon::parse('2026-10-22'), Carbon::parse('2026-10-23')));
        $this->assertSame([0, 0], ResumenDeudaCredito::mesesYDias(Carbon::parse('2026-10-23'), Carbon::parse('2026-10-23')));
        $this->assertSame([13, 2], ResumenDeudaCredito::mesesYDias(Carbon::parse('2025-09-21'), Carbon::parse('2026-10-23')), 'más de un año se expresa en meses');

        $this->assertSame('8 meses y 25 días', ResumenDeudaCredito::textoRetraso(8, 25));
        $this->assertSame('1 mes y 1 día', ResumenDeudaCredito::textoRetraso(1, 1));
        $this->assertSame('8 meses', ResumenDeudaCredito::textoRetraso(8, 0));
        $this->assertSame('25 días', ResumenDeudaCredito::textoRetraso(0, 25));
        $this->assertSame('0 días', ResumenDeudaCredito::textoRetraso(0, 0));
    }

    public function test_credito_mensual_en_cuotas_empieza_a_contar_tras_los_meses_de_interes_pagados_y_muestra_el_capital_pendiente(): void
    {
        $client = $this->cliente('Henry Nestor', 'Acosta', 'Cumpa');
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-01-03', 'importe' => 10000, 'cuotas' => 12, 'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 6000, 'fecha_vencimiento' => '2027-01-03']);
        for ($n = 1; $n <= 12; $n++) {
            $this->cuota($credit, $n, Carbon::parse('2026-01-03')->addMonthsNoOverflow($n)->format('Y-m-d'), $n === 12 ? 833.37 : 833.33, 500, pagada: $n <= 7, capAplicado: $n <= 7 ? null : 0, intAplicado: $n <= 7 ? null : ($n === 8 ? 0.49 : 0));
        }

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);

        // 7 meses pagados → el retraso corre desde el 03/08: hasta el 23/10 son 2 meses y 20 días;
        // 2 × 500 + 20 × 16.67 = 1 333.40; capital pendiente 10 000 − 7 × 833.33 = 4 166.69.
        $this->assertSame([2, 20, 16.67, 1000.0, 333.4, 1333.4, 4166.69, 5500.09], [$r['retraso_meses'], $r['retraso_dias'], $r['interes_diario'], $r['monto_meses'], $r['monto_dias'], $r['monto_retraso'], $r['capital_pendiente'], $r['total']]);
        // Mora: la cuota 8 (1 333.33) venció el 03/09 → 50 días × 2.22 = 111.00.
        $this->assertSame([50, 2.22, 111.0, 5611.09], [$r['mora_dias'], $r['mora_por_dia'], $r['mora'], $r['total_con_mora']]);
        $this->assertStringContainsString("Capital prestado\nS/ 10,000.00\n\nCapital pendiente\nS/ 4,166.69\n\nInterés del 5% S/ 500.00\n\nRetraso 2 meses y 20 días\n\nMonto Retraso S/ 1,333.40\nPor 2 meses S/ 1,000.00\nPor 20 días (S/ 16.67 x día) S/ 333.40\n\nMora 50 días (S/ 2.22 x día) S/ 111.00\n\nTotal de deuda sin mora\nS/ 5,500.09\n\nTotal de deuda con mora\nS/ 5,611.09", $r['texto']);
    }

    public function test_credito_semanal_cuenta_cuotas_vencidas_y_saldo_del_cronograma_y_el_cancelado_no_debe_nada(): void
    {
        $client = $this->cliente('Mariela', 'Chagua', 'Chavez');
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-09-18', 'importe' => 833.32, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 60, 'interes_total' => 499.99]);
        foreach (['2026-09-25', '2026-10-02', '2026-10-13', '2026-10-30'] as $i => $venc) {
            $this->cuota($credit, $i + 1, $venc, 208.33, 125.00, pagada: $i < 2);
        }

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);
        $this->assertSame(['1 cuota', 333.33, 666.66, 416.66], [$r['retraso_texto'], $r['monto_retraso'], $r['total'], $r['capital_pendiente']]);
        // Mora semanal: la cuota 3 (333.33) venció el 13/10 → 10 días × (333.33 × 5 % ÷ 7 = 2.38) = 23.80.
        $this->assertSame([10, 2.38, 23.8, 690.46], [$r['mora_dias'], $r['mora_por_dia'], $r['mora'], $r['total_con_mora']]);
        $this->assertStringContainsString("Retraso 1 cuota\n\nMonto Retraso S/ 333.33\n\nMora 10 días (S/ 2.38 x día) S/ 23.80\n\nTotal de deuda sin mora\nS/ 666.66\n\nTotal de deuda con mora\nS/ 690.46", $r['texto'], 'por cuotas no hay desglose de meses y días');

        $credit->update(['situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-10-20']);
        $c = ResumenDeudaCredito::de($credit->fresh(), $client);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, '20/10/2026'], [$c['monto_retraso'], $c['mora'], $c['total'], $c['total_con_mora'], $c['cancelado']]);
        $this->assertStringEndsWith("Mora S/ 0.00\n\nTotal de deuda sin mora\nS/ 0.00\n\nTotal de deuda con mora\nS/ 0.00\n\nCrédito cancelado el 20/10/2026", $c['texto']);
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
            ->assertSee('Retraso 8 meses y 25 días')
            ->assertSee('Total de deuda sin mora')
            ->assertSee('S/ 24,220.00')
            ->assertSee('S/ 31,187.80')
            ->assertSeeHtml("data-copia=\"DATOS DEL CRÉDITO #{$credit->id}\nJanampa Cahuana Mauro\nFecha del crédito 28/01/2026\nFecha de corte 23/10/2026\n");
        $this->assertSame($credit->id, $ficha->get('resumen')['codigo']);

        $ficha->call('verResumen', $ajeno->id)->assertDispatched('errorAlert')->assertNotDispatched('resumen-credito-listo');
    }
}
