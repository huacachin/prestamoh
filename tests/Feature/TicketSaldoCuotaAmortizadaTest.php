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
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony): en el desglose del voucher "(amortizada)" sale SOLO si
 * la cuota no quedó completa, y debajo va "Cuota n (Saldo)" con lo que le
 * falta a esa cuota. Una cuota completa no lleva ni la marca ni el saldo.
 */
class TicketSaldoCuotaAmortizadaTest extends TestCase
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

    /** Semanal, 4 cuotas de 250 + 25 (+ 0.08 de excedente de cuota uniforme). */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Saldo']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 4) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addWeeks($n)->format('Y-m-d'),
                'importe_cuota' => 250, 'importe_interes' => 25, 'importe_excedente' => 0.08, 'pagado' => 0,
            ]);
        }

        return $credit;
    }

    private function ultimoTicket(Credit $credit): array
    {
        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();

        return app(TicketPrinter::class)->paymentTicketData($masivo);
    }

    public function test_la_amortizada_lleva_su_saldo_debajo_y_la_completa_no_lleva_nada(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // Paga 400.08: cuota 1 completa (275.08) y 125.00 a la cuota 2 (quedan 150.08).
        Livewire::test(Create::class, ['creditId' => $credit->id])->set('monto', 400.08)->call('pagar');
        $t = $this->ultimoTicket($credit);

        [$c1, $c2] = $t['detalle_cuotas'];
        $this->assertFalse($c1['parcial']);
        $this->assertEqualsWithDelta(0, $c1['saldo'], 0.001, 'la completa no debe nada');
        $this->assertTrue($c2['parcial']);
        $this->assertEqualsWithDelta(125.00, $c2['monto'], 0.001);
        $this->assertEqualsWithDelta(150.08, $c2['saldo'], 0.001, 'lo que le falta a la cuota 2');

        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();
        $html = view('payments.ticket', ['t' => $t, 'autoprint' => false, 'esCopia' => false, 'logo' => null])->render();
        $pdf = view('payments.ticket-pdf', ['t' => $t, 'logo' => null])->render();
        $bytes = app(TicketPrinter::class)->buildPaymentTicketBytes($masivo);
        foreach (['web' => $html, 'pdf' => $pdf, 'ticket' => $bytes] as $formato => $salida) {
            $this->assertStringContainsString('Cuota 1:', $salida, $formato);
            $this->assertStringNotContainsString('Cuota 1 (amortizada)', $salida, $formato);
            $this->assertStringNotContainsString('Cuota 1 (Saldo)', $salida, $formato);
            $this->assertStringContainsString('Cuota 2 (amortizada):', $salida, $formato);
            $this->assertStringContainsString('Cuota 2 (Saldo):', $salida, $formato);
            $this->assertStringContainsString('150.08', $salida, $formato);
        }
    }

    /** 16/10 (Antony: "no me sale el amortizado"): la vista previa del cobro trae el mismo desglose que el ticket. */
    public function test_la_vista_previa_del_cobro_trae_el_mismo_desglose(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])->set('monto', 400.08)->call('confirmarPago');
        $preview = $comp->get('preview');
        $this->assertTrue($preview['detalle_cuotas_visible']);
        $this->assertSame([1, 2], array_column($preview['detalle_cuotas'], 'num'));
        [$c1, $c2] = $preview['detalle_cuotas'];
        $this->assertFalse($c1['parcial']);
        $this->assertEqualsWithDelta(275.08, $c1['monto'], 0.001);
        $this->assertTrue($c2['parcial']);
        $this->assertEqualsWithDelta(125.00, $c2['monto'], 0.001);
        $this->assertEqualsWithDelta(150.08, $c2['saldo'], 0.001);

        $html = $comp->html();
        $this->assertStringContainsString('Cuota 1:</span><span>275.08', $html);
        $this->assertStringNotContainsString('Cuota 1 (amortizada)', $html);
        $this->assertStringContainsString('Cuota 2 (amortizada):</span><span>125.00', $html);
        $this->assertStringContainsString('Cuota 2 (Saldo):</span><span>150.08', $html);

        // Y el ticket del cobro real dice exactamente lo mismo (preview == cobro real).
        $comp->call('pagar');
        $t = $this->ultimoTicket($credit);
        $this->assertSame(
            array_map(fn ($c) => [$c['num'], $c['monto'], $c['parcial'], $c['saldo']], $preview['detalle_cuotas']),
            array_map(fn ($c) => [$c['num'], $c['monto'], $c['parcial'], $c['saldo']], $t['detalle_cuotas'])
        );

        // Una sola cuota completa: sin desglose en la vista previa, como en el ticket.
        $comp2 = Livewire::test(Create::class, ['creditId' => $credit->id])->set('monto', 150.08)->call('confirmarPago');
        $this->assertFalse($comp2->get('preview')['detalle_cuotas_visible']);
        $this->assertStringNotContainsString('amortizada', $comp2->html());
    }

    /**
     * El recibo que abre el operador y el público (enlace firmado) pasan por el controlador, que cargaba la
     * relación de cuotas con pocas columnas: el desglose salía sin la amortizada ni su saldo (16/10).
     */
    public function test_el_recibo_por_http_y_con_la_relacion_cargada_a_medias_tambien_muestra_la_amortizada(): void
    {
        $actor = $this->actor();
        // La ruta del recibo del operador pide el permiso del módulo de pagos.
        $actor->givePermissionTo(Permission::findOrCreate('pagos', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($actor);
        $credit = $this->credito();
        Livewire::test(Create::class, ['creditId' => $credit->id])->set('monto', 400.08)->call('pagar');
        $masivo = MassDeletion::where('credit_id', $credit->id)->latest('id')->first();

        // Relación precargada con solo id y num_cuota, como la traía el controlador.
        $aMedias = MassDeletion::with('details.installment:id,num_cuota')->findOrFail($masivo->id);
        $t = app(TicketPrinter::class)->paymentTicketData($aMedias);
        $this->assertTrue($t['detalle_cuotas_visible']);
        $this->assertTrue($t['detalle_cuotas'][1]['parcial']);
        $this->assertEqualsWithDelta(150.08, $t['detalle_cuotas'][1]['saldo'], 0.001);

        foreach ([route('payments.ticket', $masivo->id), URL::signedRoute('recibo.publico', ['massDeletionId' => $masivo->id])] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('Cuota 1:</span><span>275.08', $html, $url);
            $this->assertStringContainsString('Cuota 2 (amortizada):</span><span>125.00', $html, $url);
            $this->assertStringContainsString('Cuota 2 (Saldo):</span><span>150.08', $html, $url);
        }
    }

    public function test_al_completar_la_cuota_amortizada_en_otro_cobro_ya_no_es_amortizada(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();
        Livewire::test(Create::class, ['creditId' => $credit->id])->set('monto', 400.08)->call('pagar');

        // Segundo cobro: los 150.08 que faltaban de la cuota 2.
        Livewire::test(Create::class, ['creditId' => $credit->id])->set('monto', 150.08)->call('pagar');
        $t = $this->ultimoTicket($credit);
        $this->assertCount(1, $t['detalle_cuotas']);
        $this->assertSame(2, $t['detalle_cuotas'][0]['num']);
        $this->assertFalse($t['detalle_cuotas'][0]['parcial'], 'quedó completa: sin marca');
        $this->assertEqualsWithDelta(0, $t['detalle_cuotas'][0]['saldo'], 0.001);
        $this->assertFalse($t['detalle_cuotas_visible'], 'una sola cuota completa: sin desglose');

        $html = view('payments.ticket', ['t' => $t, 'autoprint' => false, 'esCopia' => false, 'logo' => null])->render();
        $this->assertStringNotContainsString('amortizada', $html);
        $this->assertStringNotContainsString('(Saldo)', $html);
    }
}
