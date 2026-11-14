<?php

namespace Tests\Feature;

use App\Livewire\Credits\MassDeleteEdit;
use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\MassDeletion;
use App\Models\Payment;
use App\Models\User;
use App\Services\Printing\TicketPrinter;
use App\Support\MoraExonerada;
use App\Support\RecibosCuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 13/11/2026 (Antony, crédito 29440): la mora de las cuotas ya pagadas con atraso sin cobrarla
 * ("Reserva Mora") solo se cobraba al cancelar. Ahora cualquiera que cobra la cobra con el crédito
 * vigente, eligiendo las cuotas, y cada una completa.
 *
 * Semanal de cuotas de 100 + 20 (mora 0.86 por día). La 1 se pagó 2 días tarde (1.72 de mora
 * acumulada) y la 2, 5 días tarde (4.30). Hoy la 3 lleva 6 días vencida: 5.16 de mora vigente.
 */
class CobrarMoraAcumuladaTest extends TestCase
{
    use RefreshDatabase;

    private User $cajero;

    private Credit $credit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-13 10:00:00');
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Sin permisos especiales: lo cobra cualquiera que cobra.
        $this->cajero = User::factory()->create(['username' => 'cajero-mora-acum', 'headquarter_id' => 1]);
        $this->actingAs($this->cajero);

        $client = Client::create(['nombre' => 'Cliente Mora Acumulada']);
        $this->credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-10-17', 'importe' => 600, 'cuotas' => 6,
            'tipo_planilla' => 1, 'interes' => 20, 'interes_total' => 120, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 6) as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->setDate(2026, 10, 24)->addWeeks($n - 1)->format('Y-m-d'),
                'importe_cuota' => 100, 'importe_interes' => 20,
                'importe_aplicado' => $n <= 2 ? 100 : 0, 'interes_aplicado' => $n <= 2 ? 20 : 0, 'pagado' => $n <= 2 ? 1 : 0,
            ]);
        }
        foreach (['2026-10-26', '2026-11-05'] as $fecha) {
            DB::table('payments')->insert([
                'credit_id' => $this->credit->id, 'monto' => 120, 'tipo' => 'CAPITAL', 'documento' => 'CAPITAL',
                'fecha' => $fecha, 'hora' => '10:00:00', 'headquarter_id' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function cuota(int $num): CreditInstallment
    {
        return CreditInstallment::where('credit_id', $this->credit->id)->where('num_cuota', $num)->sole();
    }

    private function cobrarMoraDeLaCuota2(): MassDeletion
    {
        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->assertSee('Cobrar mora acumulada (S/ 6.02)')
            ->call('abrirMoraAcumulada')
            ->assertSet('moraAcumFilas', [
                ['num' => 1, 'dias' => 2, 'monto' => 1.72, 'sel' => true],
                ['num' => 2, 'dias' => 5, 'monto' => 4.30, 'sel' => true],
            ])
            ->set('moraAcumFilas.0.sel', false)
            ->call('aplicarMoraAcumulada')
            ->assertSet('moraAcumCuotas', [2])
            ->assertSee('Se cobra la mora acumulada de la cuota 2: S/ 4.30')
            ->set('monto', 120)
            ->call('confirmarPago')
            ->assertSet('preview.total', 129.46)
            ->assertSee('Mora acum. cuota 2:')
            ->call('pagar')
            ->assertNotDispatched('errorAlert')
            ->assertSet('moraAcumCuotas', []);

        return MassDeletion::where('credit_id', $this->credit->id)->latest('id')->firstOrFail();
    }

    public function test_cobra_la_mora_acumulada_de_las_cuotas_elegidas_con_el_credito_vigente(): void
    {
        $cobro = $this->cobrarMoraDeLaCuota2();

        // Cuota 3 (120) + su mora vigente (5.16) + la acumulada de la 2 (4.30).
        $this->assertEqualsWithDelta(129.46, (float) $cobro->amount, 0.001);
        $this->assertSame('Activo', $this->credit->fresh()->situacion);
        $acum = Payment::where('credit_id', $this->credit->id)->where('documento', 'MORA ACUM.')->sole();
        $this->assertEqualsWithDelta(4.30, (float) $acum->monto, 0.001);
        $this->assertStringContainsString('Mora Acumulada Cuota 2 Dias : 5', $acum->detalle);

        // Queda anotada en la propia cuota 2, que sigue pagada: ya no es mora por cobrar.
        $this->assertEqualsWithDelta(4.30, (float) $this->cuota(2)->importe_mora, 0.001);
        $this->assertTrue((bool) $this->cuota(2)->pagado);
        $this->assertSame([1 => ['monto' => 1.72, 'dias' => 2]], MoraExonerada::porCuota($this->credit->fresh()));

        // El recibo la lista aparte de la mora vigente; el "Rec." de la cuota 2 no pasa a ser este cobro.
        $ticket = app(TicketPrinter::class)->paymentTicketData($cobro);
        $this->assertEqualsWithDelta(5.16, $ticket['mora'], 0.001);
        $this->assertSame(['rotulo' => 'Mora acum. cuota 2', 'monto' => 4.30], $ticket['mora_acumulada']);
        $this->assertArrayNotHasKey($this->cuota(2)->id, RecibosCuota::porCuota($this->credit));
        $this->assertDatabaseHas('activity_log', ['description' => "Cobró la mora acumulada del crédito #{$this->credit->id}: cuota 2 (5 días) S/ 4.30"]);
    }

    public function test_revertir_el_cobro_devuelve_la_mora_a_acumulada_sin_reabrir_la_cuota(): void
    {
        $cobro = $this->cobrarMoraDeLaCuota2();
        $this->cajero->givePermissionTo(Permission::findOrCreate('registro.eliminar-masivo.revertir', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(MassDeleteEdit::class, ['id' => $cobro->id])->call('reverse');

        $this->assertEqualsWithDelta(0.0, (float) $this->cuota(2)->importe_mora, 0.001);
        $this->assertTrue((bool) $this->cuota(2)->pagado);
        $this->assertFalse((bool) $this->cuota(3)->pagado);
        $this->assertSame(0, Payment::where('credit_id', $this->credit->id)->where('tipo', 'MORA')->count());
        $this->assertSame([1 => ['monto' => 1.72, 'dias' => 2], 2 => ['monto' => 4.30, 'dias' => 5]], MoraExonerada::porCuota($this->credit->fresh()));
    }

    public function test_se_cobra_sola_con_el_monto_en_cero(): void
    {
        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirMoraAcumulada')
            ->call('aplicarMoraAcumulada')
            ->assertSet('moraAcumCuotas', [1, 2])
            ->set('monto', 0)
            ->call('confirmarPago')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        // Con monto 0 entra la mora vigente de la cuota 3 (5.16) y la acumulada de la 1 y la 2 (6.02).
        $this->assertEqualsWithDelta(11.18, (float) MassDeletion::where('credit_id', $this->credit->id)->sole()->amount, 0.001);
        $this->assertEqualsWithDelta(6.02, (float) Payment::where('credit_id', $this->credit->id)->where('documento', 'MORA ACUM.')->sum('monto'), 0.001);
        $this->assertSame([], MoraExonerada::porCuota($this->credit->fresh()));
        $this->assertFalse((bool) $this->cuota(3)->pagado);
    }

    public function test_si_otra_caja_ya_la_cobro_no_se_vuelve_a_cobrar(): void
    {
        $pantalla = Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirMoraAcumulada')
            ->call('aplicarMoraAcumulada');
        $this->cuota(2)->update(['importe_mora' => 4.30]);

        $pantalla->set('monto', 120)
            ->call('confirmarPago')
            ->assertDispatched('errorAlert', ['message' => 'La mora acumulada elegida ya no está pendiente (¿se cobró en otra caja?). Revisa y vuelve a elegirla.'])
            ->assertSet('moraAcumCuotas', [])
            ->assertSet('preview', []);
        $this->assertSame(0, MassDeletion::count());
    }

    public function test_las_cuotas_elegidas_no_se_pueden_cambiar_desde_el_navegador(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(Create::class, ['creditId' => $this->credit->id])->set('moraAcumCuotas', [3]);
    }
}
