<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\MassDeletion;
use App\Models\User;
use App\Services\Printing\TicketPrinter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El recibo del cobro desglosa POR CUOTA: cuánto recibió cada una, con marca
 * de "(amortizada)" cuando quedó sin completar. Antes solo listaba los números
 * de cuota y los totales globales — con 2 cuotas no se sabía cuánto fue a cada.
 */
class TicketDetalleCuotasTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester', 'headquarter_id' => 1]);
    }

    public function test_el_recibo_desglosa_cada_cuota_y_marca_la_amortizada(): void
    {
        $this->actingAs($this->actor());

        // Crédito semanal: 4 cuotas de 250 + 25. Paga 400: cuota 1 completa
        // (275) y quedan 125 amortizados en la cuota 2 (interés 25 + cap 100).
        $client = Client::create(['nombre' => 'Cliente Ticket']);
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

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 400)
            ->call('pagar');

        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();
        $t = app(TicketPrinter::class)->paymentTicketData($masivo);

        $this->assertTrue($t['detalle_cuotas_visible']);
        $this->assertSame([1, 2], array_column($t['detalle_cuotas'], 'num'));

        [$c1, $c2] = $t['detalle_cuotas'];
        $this->assertEqualsWithDelta(275.00, $c1['monto'], 0.01);
        $this->assertFalse($c1['parcial'], 'la cuota 1 quedó completa');
        $this->assertEqualsWithDelta(125.00, $c2['monto'], 0.01);
        $this->assertTrue($c2['parcial'], 'la cuota 2 quedó amortizada');

        // El desglose suma exactamente el total del cobro.
        $this->assertEqualsWithDelta(400.00, $c1['monto'] + $c2['monto'], 0.01);

        // Y el recibo del navegador lo pinta con su marca.
        $html = view('payments.ticket', ['t' => $t, 'autoprint' => false, 'esCopia' => false, 'logo' => null])->render();
        $this->assertStringContainsString('Cuota 1:', $html);
        $this->assertStringContainsString('Cuota 2 (amortizada):', $html);
    }

    public function test_una_sola_cuota_completa_no_repite_el_desglose(): void
    {
        $this->actingAs($this->actor());

        $client = Client::create(['nombre' => 'Cliente Simple']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 250, 'cuotas' => 1, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 25, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->addWeek()->format('Y-m-d'),
            'importe_cuota' => 250, 'importe_interes' => 25, 'pagado' => 0,
        ]);

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 275)
            ->set('decisionTotal', 'no')
            ->call('pagar');

        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();
        $t = app(TicketPrinter::class)->paymentTicketData($masivo);

        // Una cuota completa: el desglose duplicaría los totales — se omite.
        $this->assertFalse($t['detalle_cuotas_visible']);
    }
}
