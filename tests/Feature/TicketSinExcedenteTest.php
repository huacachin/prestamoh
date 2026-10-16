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
 * 16/10/2026 (Antony): "en todos los vouchers que se generan como imagen o
 * en PDF o pagos, hay que quitar el campo excedente". El redondeo de la cuota
 * uniforme sigue cobrándose (va dentro de cada cuota y del TOTAL), pero ya no
 * sale como línea aparte en el recibo web, el PDF, el ticket de impresora ni
 * la vista previa del cobro.
 */
class TicketSinExcedenteTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester', 'headquarter_id' => 1]);
    }

    /** Crédito de cuota uniforme: cada cuota lleva 0.07 de excedente de redondeo. */
    private function creditoConExcedente(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Uniforme']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 4) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addWeeks($n)->format('Y-m-d'),
                'importe_cuota' => 250, 'importe_interes' => 25, 'importe_excedente' => 0.07, 'pagado' => 0,
            ]);
        }

        return $credit;
    }

    public function test_ningun_voucher_muestra_la_linea_excedente_aunque_se_cobre(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->creditoConExcedente();

        // Vista previa del cobro (modal de confirmación): sin "Excedente".
        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])->set('monto', 275.07)->call('confirmarPago');
        $preview = $comp->get('preview');
        $this->assertEqualsWithDelta(0.07, $preview['excedente'], 0.001, 'el excedente se sigue cobrando');
        // (La pantalla de pagos sí tiene una columna "Excedente" en el cronograma; lo que no debe salir es la
        // línea "Excedente:" del voucher.)
        $this->assertStringNotContainsString('Excedente:', $comp->html());
        $this->assertStringContainsString('<span>TOTAL</span><span>S/ 275.07</span>', $comp->html());

        $comp->call('pagar');
        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();
        $this->assertNotNull($masivo);
        $t = app(TicketPrinter::class)->paymentTicketData($masivo);
        $this->assertEqualsWithDelta(0.07, $t['excedente'], 0.001);
        $this->assertEqualsWithDelta(275.07, $t['total'], 0.001, 'el TOTAL incluye el excedente');

        // Recibo web (imagen / "Ver recibo"), PDF y ticket ESC/POS: sin la línea.
        $html = view('payments.ticket', ['t' => $t, 'autoprint' => false, 'esCopia' => false, 'logo' => null])->render();
        $this->assertStringNotContainsString('Excedente', $html);
        $this->assertStringContainsString('275.07', $html);

        $pdf = view('payments.ticket-pdf', ['t' => $t, 'logo' => null])->render();
        $this->assertStringNotContainsString('Excedente', $pdf);
        $this->assertStringContainsString('275.07', $pdf);

        $bytes = app(TicketPrinter::class)->buildPaymentTicketBytes($masivo);
        $this->assertStringNotContainsString('Excedente', $bytes);
        $this->assertStringContainsString('275.07', $bytes);
    }
}
