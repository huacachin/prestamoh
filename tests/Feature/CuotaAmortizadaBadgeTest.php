<?php

namespace Tests\Feature;

use App\Livewire\Credits\Schedule;
use App\Livewire\Credits\Show;
use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony, crédito 28808 vencido el 26/06): en las tablas de cuotas
 * (pantalla de pagos, ficha del crédito y cronograma) la cuota con pago
 * parcial lleva el badge "Amortizada" además de su estado (Vencida/Pendiente).
 */
class CuotaAmortizadaBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_cuota_con_pago_parcial_sale_como_amortizada_en_las_tres_tablas(): void
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'tester', 'headquarter_id' => 1]));

        $client = Client::create(['nombre' => 'Cliente Amortiza']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subWeeks(2)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        // Cuota 1 (hace 8 días) pagada; cuota 2 (ayer, vencida) amortizada: interés completo + 50 de capital; 3 y 4 pendientes.
        // (El cronograma solo cuenta como vencida la fecha ANTERIOR a hoy; pagos y ficha usan isPast().)
        foreach (range(1, 4) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->subWeeks(2)->subDay()->addWeeks($n)->format('Y-m-d'),
                'importe_cuota' => 250, 'importe_interes' => 25,
                'importe_aplicado' => $n === 1 ? 250 : ($n === 2 ? 50 : 0),
                'interes_aplicado' => $n <= 2 ? 25 : 0,
                'pagado' => $n === 1 ? 1 : 0,
            ]);
        }

        foreach ([
            'pagos' => Livewire::test(Create::class, ['creditId' => $credit->id])->html(),
            'ficha' => Livewire::test(Show::class, ['id' => $credit->id])->html(),
            'cronograma' => Livewire::test(Schedule::class, ['id' => $credit->id])->html(),
        ] as $pantalla => $html) {
            $this->assertSame(1, substr_count($html, 'Amortizada</span>'), "{$pantalla}: solo la cuota 2 lleva el badge");
            $this->assertStringContainsString('>Vencida</span>', $html, $pantalla);
            $this->assertStringContainsString('>Pendiente</span>', $html, $pantalla);
            $this->assertStringContainsString('>Pagado</span>', $html, $pantalla);
            // El badge va junto a Vencida (misma celda), no en una cuota pagada o sin abonos.
            $this->assertMatchesRegularExpression('/>Vencida<\/span>(?:\s|<!--.*?-->)*<span class="badge bg-info text-dark"[^>]*>Amortizada<\/span>/s', $html, $pantalla);
        }
    }
}
