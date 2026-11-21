<?php

namespace Tests\Feature;

use App\Livewire\Credits\MassDeleteEdit;
use App\Livewire\Credits\Schedule;
use App\Livewire\Credits\Show;
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
                ['num' => 1, 'dias' => 2, 'monto' => 1.72, 'tope' => 1.80, 'valor' => '1.72', 'sel' => true],
                ['num' => 2, 'dias' => 5, 'monto' => 4.30, 'tope' => 4.30, 'valor' => '4.30', 'sel' => true],
            ])
            ->set('moraAcumFilas.0.sel', false)
            ->call('aplicarMoraAcumulada')
            ->assertSet('moraAcumCuotas', [2 => 4.30])
            ->assertSee('Se cobra la mora acumulada de la cuota 2: S/ 4.30')
            ->set('monto', 120)
            // Pagando la cuota 3, su mora vigente sí entra: el aviso de "solo mora acumulada" no aplica.
            ->assertDontSee('Sin Monto a Pagar se cobra solo la mora acumulada')
            ->call('confirmarPago')
            ->assertSet('preview.total', 129.46)
            ->assertSet('preview.mora_vigente_fuera', null)
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

    /** "Pagos realizados" de la ficha: la mora acumulada va en la columna Mora y la fila dice de qué cuotas es. */
    public function test_la_ficha_del_credito_muestra_la_mora_acumulada_del_cobro(): void
    {
        $this->cobrarMoraDeLaCuota2();

        $html = Livewire::test(Show::class, ['id' => $this->credit->id])->html();

        $this->assertStringContainsString('<td>3 · mora acum. 2</td>', $html);
        // Mora del cobro: 5.16 de la vigente + 4.30 de la acumulada.
        $this->assertSame(2, substr_count($html, '<td class="text-end">9.46</td>'), 'la fila del cobro y la de Totales');
    }

    /**
     * 20/11 (Antony, 29404): "he pagado una mora de 416.43, ¿dónde puedo visualizar ese pago? … no se
     * visualiza en el cronograma de cuotas". El recibo de ese cobro se abre desde la fila de su cuota.
     */
    public function test_el_cronograma_abre_el_recibo_de_la_mora_acumulada_desde_su_cuota(): void
    {
        $cobro = $this->cobrarMoraDeLaCuota2();

        $recibos = RecibosCuota::moraAcumuladaPorCuota($this->credit);
        $this->assertSame([$this->cuota(2)->id], array_keys($recibos), 'solo la cuota cuya mora acumulada se cobró');
        $this->assertCount(1, $recibos[$this->cuota(2)->id]);
        $recibo = $recibos[$this->cuota(2)->id][0];
        $this->assertSame([$cobro->id, '13/11/2026', 4.30], [$recibo['lote'], $recibo['fecha'], $recibo['monto']]);
        $this->assertStringContainsString("/recibo/{$cobro->id}?signature=", $recibo['ver']);

        // Un solo botón, en la pantalla de cobro y en el cronograma del crédito.
        $boton = 'Ver recibo de la mora acumulada: S/ 4.30 cobrada el 13/11/2026';
        $this->assertSame(1, substr_count(Livewire::test(Create::class, ['creditId' => $this->credit->id])->html(), $boton));
        $this->assertSame(1, substr_count(Livewire::test(Schedule::class, ['id' => $this->credit->id])->html(), $boton));
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

    /**
     * 13/11 (Antony): "no me deja pagar si no pongo nada en monto a pagar": vacío vale 0.
     * 20/11 (Antony, 29404): "si quiero pagar solo la mora acumulada de la cuota 1, me obliga a pagar
     * la mora de la cuota 2". Sin Monto a Pagar no se paga ninguna cuota: la vigente (5.16, cuota 3) no entra.
     */
    public function test_con_el_monto_vacio_se_cobra_solo_la_mora_acumulada_sin_la_vigente(): void
    {
        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirMoraAcumulada')
            ->call('aplicarMoraAcumulada')
            ->assertSet('moraAcumCuotas', [1 => 1.72, 2 => 4.30])
            ->assertSet('monto', null)
            ->assertSee('Sin Monto a Pagar se cobra solo la mora acumulada: la mora de la cuota 3 (S/ 5.16) no entra en este cobro')
            ->call('confirmarPago')
            ->assertHasNoErrors()
            ->assertSet('preview.total', 6.02)
            ->assertSet('preview.mora', 0.0)
            ->assertSet('preview.mora_vigente_fuera', ['cuota' => 3, 'monto' => 5.16])
            ->assertSee('Solo mora acumulada: la mora de la cuota 3 (S/ 5.16) no se cobra ahora')
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertEqualsWithDelta(6.02, (float) MassDeletion::where('credit_id', $this->credit->id)->sole()->amount, 0.001);
        $this->assertSame(['MORA ACUM.', 'MORA ACUM.'], Payment::where('credit_id', $this->credit->id)->where('tipo', 'MORA')->pluck('documento')->all());
        $this->assertSame([], MoraExonerada::porCuota($this->credit->fresh()));
        // La cuota 3 queda como estaba: impaga y sin mora anotada (la suya se cobra al pagarla).
        $this->assertFalse((bool) $this->cuota(3)->pagado);
        $this->assertEqualsWithDelta(0.0, (float) $this->cuota(3)->importe_mora, 0.001);
        $this->assertDatabaseMissing('dias_mora', ['credit_id' => $this->credit->id]);
    }

    /** Con el campo borrado ("") y "Reserva Mora" marcada tampoco se reserva nada: no se pagó ninguna cuota. */
    public function test_solo_la_mora_acumulada_con_reserva_mora_no_reserva_la_vigente(): void
    {
        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirMoraAcumulada')
            ->call('aplicarMoraAcumulada')
            ->set('monto', '')
            ->set('ckmora', true)
            ->call('confirmarPago')
            ->assertHasNoErrors()
            ->assertSet('preview.total', 6.02)
            ->assertSet('preview.reserva_mora', false)
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertEqualsWithDelta(6.02, (float) MassDeletion::where('credit_id', $this->credit->id)->sole()->amount, 0.001);
        $this->assertSame(['MORA ACUM.', 'MORA ACUM.'], Payment::where('credit_id', $this->credit->id)->where('tipo', 'MORA')->pluck('documento')->all());
        $this->assertSame(0, Payment::where('credit_id', $this->credit->id)->whereIn('tipo', ['CAPITAL', 'INTERES'])->where('fecha', '2026-11-13')->count());
        $this->assertFalse((bool) $this->cuota(3)->pagado);
        $this->assertDatabaseMissing('mora_acumulada', ['credit_id' => $this->credit->id]);
    }

    /** 13/11 (Antony): "el importe de la mora debería ser editable". Hasta lo que debe la cuota; el resto no se perdona. */
    public function test_el_importe_se_edita_hasta_lo_que_debe_y_el_resto_sigue_acumulado(): void
    {
        $pantalla = Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirMoraAcumulada')
            ->set('moraAcumFilas.0.sel', false)
            // Más de lo que debe la cuota 2, aunque el navegador mande otra cifra como "monto".
            ->set('moraAcumFilas.1.monto', 99)
            ->set('moraAcumFilas.1.valor', '4.31')
            ->call('aplicarMoraAcumulada')
            ->assertHasErrors(['moraAcumFilas.1.valor'])
            ->assertSee('Cuota 2: su mora acumulada es S/ 4.30, no se puede cobrar más.')
            ->assertSet('moraAcumCuotas', [])
            ->set('moraAcumFilas.1.valor', '0')
            ->call('aplicarMoraAcumulada')
            ->assertHasErrors(['moraAcumFilas.1.valor'])
            ->set('moraAcumFilas.1.valor', '3')
            ->call('aplicarMoraAcumulada')
            ->assertHasNoErrors()
            ->assertSet('moraAcumCuotas', [2 => 3.0])
            ->assertSee('Se cobra la mora acumulada de la cuota 2: S/ 3.00');

        $pantalla->set('monto', 120)->call('confirmarPago')->call('pagar')->assertNotDispatched('errorAlert');

        $this->assertEqualsWithDelta(128.16, (float) MassDeletion::where('credit_id', $this->credit->id)->sole()->amount, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) $this->cuota(2)->importe_mora, 0.001);
        // De la 2 quedan 1.30 por cobrar (otro día, o al cancelar).
        $this->assertSame([1 => ['monto' => 1.72, 'dias' => 2], 2 => ['monto' => 1.30, 'dias' => 5]], MoraExonerada::porCuota($this->credit->fresh()));
        $this->assertDatabaseHas('activity_log', ['description' => "Cobró la mora acumulada del crédito #{$this->credit->id}: cuota 2 (5 días) S/ 3.00 de 4.30"]);
    }

    /** 13/11 (Antony): "si el cliente tiene 9.52, debe poder cobrar el redondeo de hasta 9.6, más de eso no". */
    public function test_se_puede_cobrar_hasta_el_redondeo_a_diez_centimos_y_no_mas(): void
    {
        $this->assertSame([9.60, 4.80, 4.30, 0.10], array_map(Create::topeMoraAcumulada(...), [9.52, 4.76, 4.30, 0.01]));

        $pantalla = Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirMoraAcumulada')
            ->set('moraAcumFilas.1.sel', false)
            ->set('moraAcumFilas.0.valor', '1.81')
            ->call('aplicarMoraAcumulada')
            ->assertHasErrors(['moraAcumFilas.0.valor'])
            ->assertSee('Cuota 1: su mora acumulada es S/ 1.72, se puede cobrar hasta S/ 1.80 con el redondeo.')
            ->set('moraAcumFilas.0.valor', '1.80')
            ->call('aplicarMoraAcumulada')
            ->assertHasNoErrors()
            ->assertSet('moraAcumCuotas', [1 => 1.80]);

        $pantalla->set('monto', 120)->call('confirmarPago')->call('pagar')->assertNotDispatched('errorAlert');

        // 120 + 5.16 de la vigente + 1.80 de la cuota 1: queda sin mora por cobrar (el 0.08 de más es el redondeo).
        $this->assertEqualsWithDelta(126.96, (float) MassDeletion::where('credit_id', $this->credit->id)->sole()->amount, 0.001);
        $this->assertSame([2 => ['monto' => 4.30, 'dias' => 5]], MoraExonerada::porCuota($this->credit->fresh()));
        $this->assertDatabaseHas('activity_log', ['description' => "Cobró la mora acumulada del crédito #{$this->credit->id}: cuota 1 (2 días) S/ 1.80 (debía 1.72, con redondeo)"]);
    }

    /**
     * 20/11 (Antony, 29404): "en el modal de Calcular pagos, en cancelación, debemos agregarle un check para
     * agregarle la suma de la mora acumulada, con la opción de poder editar la mora acumulada".
     */
    public function test_la_pestana_cancelacion_agrega_la_mora_acumulada_al_cobro(): void
    {
        $pantalla = Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('abrirCalcularPagos')
            // Sin marcar por defecto: propone todo lo que se debe (6.02) y deja cobrar hasta el redondeo (1.80 + 4.30).
            ->assertReturned(fn ($d) => $d['mora_acumulada'] === ['debe' => 6.02, 'tope' => 6.1, 'rotulo' => 'cuotas 1 y 2', 'elegida' => null])
            ->call('aplicarCancelacion', 3, 6.02)
            ->assertReturned(true)
            ->assertSet('monto', '420.00')
            ->assertSet('cancel', false)
            ->assertSet('moraAcumCuotas', [1 => 1.72, 2 => 4.30])
            ->assertSee(['Se cobra la mora acumulada de las cuotas', ': S/ 6.02'])
            // Al reabrir la ventana, la casilla vuelve marcada con lo elegido.
            ->call('abrirCalcularPagos')
            ->assertReturned(fn ($d) => $d['mora_acumulada']['elegida'] === 6.02)
            ->call('confirmarPago')
            ->assertSet('preview.total', 431.18)
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        // 420 del cálculo (interés hasta la cuota 3) + 5.16 de la mora de la cuota 3 + 6.02 de la acumulada.
        $this->assertEqualsWithDelta(431.18, (float) MassDeletion::where('credit_id', $this->credit->id)->sole()->amount, 0.001);
        $this->assertEqualsWithDelta(6.02, (float) Payment::where('credit_id', $this->credit->id)->where('documento', 'MORA ACUM.')->sum('monto'), 0.001);
        $this->assertSame('Activo', $this->credit->fresh()->situacion);
        $this->assertEqualsWithDelta(1.72, (float) $this->cuota(1)->importe_mora, 0.001);
        $this->assertEqualsWithDelta(4.30, (float) $this->cuota(2)->importe_mora, 0.001);
    }

    /** El importe se edita: se reparte desde la cuota más antigua, hasta el redondeo de cada una y no más. */
    public function test_la_mora_acumulada_de_la_cancelacion_se_edita_y_se_reparte_desde_la_cuota_mas_antigua(): void
    {
        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            // Más de lo que se puede cobrar: avisa y no cambia nada (ni el Monto a Pagar).
            ->call('aplicarCancelacion', 3, 6.11)
            ->assertReturned(false)
            ->assertDispatched('errorAlert', ['message' => 'La mora acumulada es S/ 6.02: se puede cobrar hasta S/ 6.10 con el redondeo.'])
            ->assertSet('monto', null)
            ->assertSet('cancelHasta', null)
            ->assertSet('moraAcumCuotas', [])
            ->call('aplicarCancelacion', 3, 0)
            ->assertReturned(false)
            ->assertDispatched('errorAlert', ['message' => 'Escribe un importe de mora acumulada mayor que 0 o desmarca la casilla.'])
            ->assertSet('moraAcumCuotas', [])
            // Con el redondeo de la cuota 1 (1.72 → 1.80).
            ->call('aplicarCancelacion', 3, 6.10)
            ->assertReturned(true)
            ->assertSet('moraAcumCuotas', [1 => 1.80, 2 => 4.30])
            // Menos de lo que se debe: primero la cuota más antigua; lo demás de la 2 sigue acumulado.
            ->call('aplicarCancelacion', 3, 3)
            ->assertSet('moraAcumCuotas', [1 => 1.72, 2 => 1.28])
            ->call('aplicarCancelacion', 3, 1)
            ->assertSet('moraAcumCuotas', [1 => 1.0])
            // Casilla sin marcar: el cobro no lleva mora acumulada.
            ->call('aplicarCancelacion', 3)
            ->assertReturned(true)
            ->assertSet('monto', '420.00')
            ->assertSet('moraAcumCuotas', []);
    }

    /**
     * 21/11 (Antony, 29036): la mora del cobro también lleva su casilla editable en la pestaña. Lo que no se
     * cobra de ella no se pierde: al pagarse la cuota queda como mora acumulada, y se cobra después.
     */
    public function test_la_mora_del_cobro_que_no_se_cobra_en_la_cancelacion_queda_como_mora_acumulada(): void
    {
        $debe = fn () => round(array_sum(array_column(MoraExonerada::porCuota($this->credit->fresh()), 'monto')), 2);
        $this->assertSame(6.02, $debe());

        // Sin marcar (Reserva Mora): la cuota 3 se paga sin su mora de 5.16, que pasa a deberse como acumulada.
        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('aplicarCancelacion', 3, null, false)
            ->call('confirmarPago')
            ->assertSet('preview.total', 420.0)
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        $this->assertSame(0, Payment::where('credit_id', $this->credit->id)->where('tipo', 'MORA')->count());
        $this->assertSame(11.18, $debe());
    }

    public function test_con_el_importe_de_la_mora_rebajado_la_diferencia_queda_como_mora_acumulada(): void
    {
        $this->cajero->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(Create::class, ['creditId' => $this->credit->id])
            ->call('aplicarCancelacion', 3, null, true, 2)
            ->call('confirmarPago')
            ->assertSet('preview.total', 422.0)
            ->call('pagar')
            ->assertNotDispatched('errorAlert');

        // De los 5.16 de la cuota 3 se cobraron 2.00: los otros 3.16 se suman a los 6.02 que ya se debían.
        $this->assertEqualsWithDelta(2.00, (float) Payment::where('credit_id', $this->credit->id)->where('tipo', 'MORA')->sum('monto'), 0.001);
        $this->assertSame(9.18, round(array_sum(array_column(MoraExonerada::porCuota($this->credit->fresh()), 'monto')), 2));
    }

    /** Mensual de una cuota (sin la fórmula por cuota): la misma casilla junto a lo de cancelar hoy. */
    public function test_la_cancelacion_de_un_mensual_de_una_cuota_tambien_agrega_la_mora_acumulada(): void
    {
        $client = Client::create(['nombre' => 'Cliente Mensual Mora Acumulada']);
        $mensual = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-09-14', 'importe' => 3000, 'cuotas' => 1,
            'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 150, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $mensual->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2026-10-14',
            'importe_cuota' => 3000, 'importe_interes' => 150, 'interes_aplicado' => 150, 'pagado' => 0,
        ]);
        // El interés se pagó 10 días tarde sin cobrar la mora: 10 × 5.25 (5% de 3,150 entre 30) = 52.50.
        DB::table('payments')->insert([
            'credit_id' => $mensual->id, 'monto' => 150, 'tipo' => 'INTERES', 'documento' => 'INTERES',
            'fecha' => '2026-10-24', 'hora' => '10:00:00', 'headquarter_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Livewire::test(Create::class, ['creditId' => $mensual->id])
            ->call('abrirCalcularPagos')
            ->assertReturned(fn ($d) => $d['cancelacion'] === null && $d['cancelacion_al_dia'] !== null
                && $d['mora_acumulada'] === ['debe' => 52.5, 'tope' => 52.5, 'rotulo' => 'cuota 1', 'elegida' => null])
            ->call('aplicarMontoDeCancelacion', 52.51)
            ->assertReturned(false)
            ->assertDispatched('errorAlert', ['message' => 'La mora acumulada es S/ 52.50: no se puede cobrar más.'])
            ->assertSet('montoDeCancelacion', false)
            ->call('aplicarMontoDeCancelacion', 40)
            ->assertReturned(true)
            ->assertSet('montoDeCancelacion', true)
            ->assertSet('moraAcumCuotas', [1 => 40.0])
            ->call('aplicarMontoDeCancelacion')
            ->assertSet('moraAcumCuotas', []);
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

        Livewire::test(Create::class, ['creditId' => $this->credit->id])->set('moraAcumCuotas', [3 => 5.16]);
    }
}
