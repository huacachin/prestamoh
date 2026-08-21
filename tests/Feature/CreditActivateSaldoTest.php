<?php

namespace Tests\Feature;

use App\Livewire\Credits\Activate;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * /credits/activate: un crédito con saldo pendiente NO se puede re-activar.
 * Quedó cancelado/refinanciado con deuda a propósito (interés condonado o
 * saldo trasladado al refinanciar) y re-activarlo la reabriría.
 */
class CreditActivateSaldoTest extends TestCase
{
    use RefreshDatabase;

    private function credito(float $aplicadoCuota2): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Activar']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 500, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 50, 'situacion' => 'Cancelado', 'estado' => 0,
            'fecha_cancelacion' => now()->format('Y-m-d'),
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subWeek()->format('Y-m-d'),
            'importe_cuota' => 250, 'importe_interes' => 25,
            'importe_aplicado' => 250, 'interes_aplicado' => 25, 'pagado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 2,
            'fecha_vencimiento' => now()->format('Y-m-d'),
            'importe_cuota' => 250, 'importe_interes' => 25,
            'importe_aplicado' => $aplicadoCuota2,
            'interes_aplicado' => $aplicadoCuota2 > 0 ? 25 : 0,
            'pagado' => $aplicadoCuota2 >= 250 ? 1 : 0,
        ]);

        return $credit;
    }

    public function test_con_saldo_pendiente_no_se_reactiva(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        $credit = $this->credito(0); // cuota 2 impaga: saldo 275

        Livewire::test(Activate::class)
            ->set('selectedId', $credit->id)
            ->call('activate')
            ->assertDispatched('errorAlert');

        $this->assertSame('Cancelado', $credit->fresh()->situacion);
    }

    public function test_sin_saldo_se_reactiva(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tester']));
        $credit = $this->credito(250); // todo pagado: saldo 0

        Livewire::test(Activate::class)
            ->set('selectedId', $credit->id)
            ->call('activate')
            ->assertDispatched('successAlert');

        $this->assertSame('Activo', $credit->fresh()->situacion);
    }
}
