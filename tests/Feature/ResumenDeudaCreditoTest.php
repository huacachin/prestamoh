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
use Illuminate\Support\Facades\DB;
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
        $this->assertSame([237, 6967.8, 31187.8], [$r['mora_dias'], $r['mora'], $r['total_con_mora']]);
        $this->assertSame([['cuota' => 1, 'dias' => 237, 'por_dia' => 29.4, 'monto' => 6967.8]], $r['mora_cuotas']);
        // Compacto (23/10): una línea por dato, sin líneas en blanco; se copia igual.
        $this->assertSame(implode("\n", [
            'DATOS DEL CRÉDITO #'.$credit->id,
            'Janampa Cahuana Mauro',
            'Fecha del crédito: 28/01/2026',
            'Fecha de corte: 23/10/2026',
            'Capital prestado: S/ 16,800.00',
            'Interés del 5%: S/ 840.00',
            'Retraso: 8 meses y 25 días · S/ 7,420.00',
            'Por 8 meses: S/ 6,720.00 · Por 25 días (S/ 28.00 x día): S/ 700.00',
            'Mora cuota 1: 237 días (S/ 29.40 x día) · S/ 6,967.80',
            'Total de deuda sin mora: S/ 24,220.00',
            'Total de deuda con mora: S/ 31,187.80',
        ]), $r['texto']);
        $this->assertSame($r['texto'], $r['texto_copia']);
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
        // Mora por cuota (23/10): la 8 (1 333.33) venció el 03/09 → 50 días × 2.22 = 111.00; la 9 el 03/10 → 20 × 2.22 = 44.40.
        $this->assertSame([70, 155.4, 5655.49], [$r['mora_dias'], $r['mora'], $r['total_con_mora']]);
        // Amortizado = prestado − pendiente (7 cuotas de 833.33 pagadas).
        $this->assertSame([5833.31, 4166.69], [$r['capital_amortizado'], $r['capital_pendiente']]);
        $this->assertStringContainsString(implode("\n", [
            'Capital prestado: S/ 10,000.00',
            'Capital amortizado: S/ 5,833.31',
            'Capital pendiente: S/ 4,166.69',
            'Interés del 5%: S/ 500.00',
            'Retraso: 2 meses y 20 días · S/ 1,333.40',
            'Por 2 meses: S/ 1,000.00 · Por 20 días (S/ 16.67 x día): S/ 333.40',
            'Mora por cuota (S/ 2.22 x día):',
            'Cuota 8: 50 días · S/ 111.00',
            'Cuota 9: 20 días · S/ 44.40',
            'Mora total: 70 días · S/ 155.40',
            'Total de deuda sin mora: S/ 5,500.09',
            'Total de deuda con mora: S/ 5,655.49',
        ]), $r['texto']);
    }

    public function test_credito_semanal_cuenta_cuotas_vencidas_y_saldo_del_cronograma_y_el_cancelado_no_debe_nada(): void
    {
        $client = $this->cliente('Mariela', 'Chagua', 'Chavez');
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-09-18', 'importe' => 833.32, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 60, 'interes_total' => 499.99]);
        $ids = [];
        foreach (['2026-09-25', '2026-10-02', '2026-10-13', '2026-10-30'] as $i => $venc) {
            $ids[$i + 1] = $this->cuota($credit, $i + 1, $venc, 208.33, 125.00, pagada: $i < 2)->id;
        }
        // Cobros reales: la cuota 1 a tiempo y la 2 el 05/10, 3 días tarde (mora que no se cobró: queda acumulada).
        foreach ([['2026-09-25', 1], ['2026-10-05', 2]] as [$fecha, $n]) {
            foreach ([['INTERES', 125.00], ['CAPITAL', 208.33]] as [$tipo, $monto]) {
                DB::table('payments')->insert(['credit_id' => $credit->id, 'installment_id' => $ids[$n], 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
                    'fecha' => $fecha, 'hora' => '09:00:00', 'monto' => $monto, 'detalle' => "Pago : {$credit->id} {$tipo}: {$n}/4", 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);
        $this->assertSame(['1 cuota', 333.33, 666.66, 416.66], [$r['retraso_texto'], $r['monto_retraso'], $r['total'], $r['capital_pendiente']]);
        // Mora semanal: la cuota 3 (333.33) venció el 13/10 → 10 días × (333.33 × 5 % ÷ 7 = 2.38) = 23.80.
        $this->assertSame([10, 23.8, 690.46], [$r['mora_dias'], $r['mora'], $r['total_con_mora']]);
        // Acumulada: la cuota 2 se pagó 3 días tarde → 3 × 2.38 = 7.14; informativa, no entra en los totales.
        $this->assertSame(['dias' => 3, 'monto' => 7.14], $r['mora_acumulada']);
        $this->assertStringContainsString(implode("\n", [
            'Retraso: 1 cuota · S/ 333.33',
            'Mora cuota 3: 10 días (S/ 2.38 x día) · S/ 23.80',
            'Mora acumulada (cuotas ya pagadas): 3 días · S/ 7.14',
            'Total de deuda sin mora: S/ 666.66',
            'Total de deuda con mora: S/ 690.46',
        ]), $r['texto'], 'por cuotas no hay desglose de meses y días');

        $credit->update(['situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-10-20']);
        $c = ResumenDeudaCredito::de($credit->fresh(), $client);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, '20/10/2026'], [$c['monto_retraso'], $c['mora'], $c['total'], $c['total_con_mora'], $c['cancelado']]);
        $this->assertStringEndsWith("Mora: S/ 0.00\nTotal de deuda sin mora: S/ 0.00\nTotal de deuda con mora: S/ 0.00\nCrédito cancelado el 20/10/2026", $c['texto']);
    }

    /** 23/10 (Antony, cliente 1406): la mora va por cada cuota vencida y al final la suma. */
    public function test_con_varias_cuotas_vencidas_la_mora_va_por_cuota_y_se_suma(): void
    {
        $client = $this->cliente('Jose Miguel', 'Liza', 'Jaramillo');
        $credit = $this->credito($client, ['fecha_prestamo' => '2026-08-18', 'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 30, 'interes_total' => 300]);
        foreach (['2026-09-29', '2026-10-06', '2026-10-13', '2026-10-27'] as $i => $venc) {
            $this->cuota($credit, $i + 1, $venc, 250, 75, intAplicado: $i === 0 ? 10 : 0);
        }

        $r = ResumenDeudaCredito::de($credit->fresh(), $client);

        // 325 × 5 % ÷ 7 = 2.32 por día; la 1 vence el 29/09 (24 días, aunque tenga un abono), la 2 el 06/10 (17), la 3 el 13/10 (10).
        $this->assertSame([[1, 24, 55.68], [2, 17, 39.44], [3, 10, 23.2]], array_map(fn ($m) => [$m['cuota'], $m['dias'], $m['monto']], $r['mora_cuotas']));
        $this->assertSame([51, 118.32], [$r['mora_dias'], $r['mora']]);
        $this->assertStringContainsString(implode("\n", [
            'Mora por cuota (S/ 2.32 x día):',
            'Cuota 1: 24 días · S/ 55.68',
            'Cuota 2: 17 días · S/ 39.44',
            'Cuota 3: 10 días · S/ 23.20',
            'Mora total: 51 días · S/ 118.32',
        ]), $r['texto']);
        $this->assertSame(round($r['total'] + 118.32, 2), $r['total_con_mora'], 'el total con mora suma la mora de todas las cuotas vencidas');
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
            ->assertSee('Retraso: 8 meses y 25 días')
            ->assertSee('Total de deuda sin mora: S/ 24,220.00')
            ->assertSee('Total de deuda con mora: S/ 31,187.80')
            ->assertSeeHtml("data-copia=\"DATOS DEL CRÉDITO #{$credit->id}\nJanampa Cahuana Mauro\nFecha del crédito: 28/01/2026\nFecha de corte: 23/10/2026\n")
            ->assertSeeHtml("x-data=\"Object.assign(modalFlotante('resumen'), resumenCredito())\""); // ventana movible (23/10)
        $this->assertSame($credit->id, $ficha->get('resumen')['codigo']);

        $ficha->call('verResumen', $ajeno->id)->assertDispatched('errorAlert')->assertNotDispatched('resumen-credito-listo');
    }
}
