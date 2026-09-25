<?php

namespace Tests\Feature;

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
 * 26/09/2026: el botón "Usar … en Monto a Pagar" queda lejos del campo y los
 * usuarios nuevos no veían que ya se llenó. Ahora el componente avisa al
 * navegador (evento monto-usado) para llevar la vista al campo, darle el
 * foco y resaltarlo.
 */
class UsarMontoResaltaCampoTest extends TestCase
{
    use RefreshDatabase;

    private function credito(): Credit
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'cobra-tester', 'headquarter_id' => 1]));

        $client = Client::create(['nombre' => 'Cliente '.uniqid()]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 4) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addWeeks($n)->format('Y-m-d'),
                'importe_cuota' => 250, 'importe_interes' => 25, 'pagado' => 0,
            ]);
        }

        return $credit;
    }

    public function test_usar_monto_llena_el_campo_y_avisa_al_navegador(): void
    {
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('usarMonto', 275);

        $comp->assertSet('monto', '275.00')
            ->assertDispatched('monto-usado', monto: '275.00')
            ->assertSeeHtml('id="monto-a-pagar"')
            ->assertSeeHtml('x-on:monto-usado.window')
            ->assertSeeHtml('saldo-resaltado');
    }

    public function test_el_monto_usado_se_recorta_al_saldo_pendiente(): void
    {
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('usarMonto', 99999)
            ->assertSet('monto', '1100.00')
            ->assertDispatched('monto-usado', monto: '1100.00');
    }
}
