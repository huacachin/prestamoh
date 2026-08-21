<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\DiasAtraso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Propuesta preview-mora-detallada: la mora del preview lleva su fórmula
 * (N días × tarifa) y ES exactamente lo que se cobra al pagar. El conteo de
 * días vive en un único helper (DiasAtraso) para que cronograma, exonerada,
 * pagada y cobro no puedan divergir.
 */
class PreviewMoraParidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dias_atraso_helper(): void
    {
        // Mensual (tipo 3): días calendario
        $this->assertSame(3, DiasAtraso::entre(3, '2026-08-14', '2026-08-17'));
        // Semanal/diario: salta sábado y domingo (viernes → lunes = 1 día)
        $this->assertSame(1, DiasAtraso::entre(1, '2026-08-14', '2026-08-17'));
        $this->assertSame(1, DiasAtraso::entre(4, '2026-08-14', '2026-08-17'));
        // Aún no vence o vence hoy: 0
        $this->assertSame(0, DiasAtraso::entre(3, '2026-08-17', '2026-08-17'));
        $this->assertSame(0, DiasAtraso::entre(3, '2026-08-20', '2026-08-17'));
    }

    private function creditoVencidoTresDias(): Credit
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'cajero', 'headquarter_id' => 1]));

        $client = Client::create(['nombre' => 'Cliente Paridad']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subMonths(2)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subDays(3)->format('Y-m-d'),
            'importe_cuota' => 1000, 'importe_interes' => 100, 'pagado' => 0,
        ]);

        return $credit->refresh();
    }

    public function test_preview_muestra_la_mora_total_con_su_formula(): void
    {
        $credit = $this->creditoVencidoTresDias();
        // Mensual: tarifa = 5% de 1100 / 30 = 1.83/día; 3 días = 5.49
        $rate = round(1100 * 5 / 100 / 30, 2);
        $totalMora = round($rate * 3, 2);

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->call('confirmarPago');

        $preview = $comp->get('preview');
        $this->assertEqualsWithDelta($totalMora, (float) $preview['mora'], 0.005, 'el preview lleva la mora TOTAL');
        $this->assertSame(3, $preview['mora_dias']);
        $this->assertEqualsWithDelta($rate, (float) $preview['mora_rate'], 0.005);

        // La fórmula se pinta en el ticket del modal y bajo el campo Total Mora
        $comp->assertSee('3 días × '.number_format($rate, 2));
        $comp->assertSee('= 3 días × '.number_format($rate, 2));
    }

    public function test_lo_previsualizado_es_exactamente_lo_cobrado(): void
    {
        $credit = $this->creditoVencidoTresDias();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->call('confirmarPago');
        $preview = $comp->get('preview');

        $comp->call('pagar');

        $moraCobrada = (float) DB::table('payments')
            ->where('credit_id', $credit->id)->where('tipo', 'MORA')->sum('monto');
        $this->assertEqualsWithDelta((float) $preview['mora'], $moraCobrada, 0.005,
            'la mora cobrada debe ser idéntica a la del preview');

        $totalOperacion = (float) DB::table('mass_deletions')
            ->where('credit_id', $credit->id)->value('amount');
        $this->assertEqualsWithDelta((float) $preview['total'], $totalOperacion, 0.005,
            'el total de la operación debe ser idéntico al del preview');
    }

    public function test_con_mora_ajustada_no_se_pinta_formula_sino_el_aviso(): void
    {
        $credit = $this->creditoVencidoTresDias();
        auth()->user()->givePermissionTo(
            Permission::findOrCreate('pagos.mora-manual', 'web')
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->set('moraManual', 2)
            ->set('moraMotivo', 'Acuerdo con el cliente')
            ->call('confirmarPago');

        // La fórmula del form desaparece (las tarjetas de escenario siguen
        // mostrando la calculada: son otra cosa) y el ticket no la etiqueta.
        $comp->assertDontSee('= 3 días × '.number_format(round(1100 * 5 / 100 / 30, 2), 2));
        $this->assertFalse($comp->get('preview')['mora_es_calculada']);
        $comp->assertSee('Mora ajustada a mano');
    }
}
