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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Modal "Pagar por cuotas" en /payments/create: el cajero selecciona qué
 * cuotas pagar (prefijo FIFO — el motor imputa a la más antigua primero,
 * sin saltos), la suma de saldos arma el Monto a Pagar y la mora editable
 * por fila (pagos.mora-manual) suma al Total Mora (circuito del override).
 */
class MoraPorCuotaModalTest extends TestCase
{
    use RefreshDatabase;

    private function actor(bool $conPermiso = true): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::factory()->create(['username' => 'mora-cuota-tester', 'headquarter_id' => 1]);
        if ($conPermiso) {
            $user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /**
     * Crédito SEMANAL, 4 cuotas: pagada (-21d, fuera del modal), vencida
     * hace 14 días con abono parcial de 100 (saldo 670), vencida hace 7
     * (saldo 770) y futura +7 (saldo 770). Tarifa: 5% de 770 ÷ 7 = 5.50/día.
     */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Mora Cuotas']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(28)->format('Y-m-d'),
            'importe' => 2800, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 280, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        $mk = fn (int $num, int $diasDesdeHoy, int $pagado, float $apli = 0) => CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => $num,
            'fecha_vencimiento' => now()->addDays($diasDesdeHoy)->format('Y-m-d'),
            'importe_cuota' => 700, 'importe_interes' => 70, 'pagado' => $pagado,
            'importe_aplicado' => $apli,
        ]);
        $mk(1, -21, 1);        // pagada: fuera del modal
        $mk(2, -14, 0, 100);   // saldo 670
        $mk(3, -7, 0);         // saldo 770
        $mk(4, 7, 0);          // futura: saldo 770, 0 días

        return $credit->refresh();
    }

    public function test_lista_pendientes_con_saldo_dias_mora_y_preseleccion_de_vencidas(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->assertDispatched('mora-cuotas-open')
            ->assertCount('moraCuotas', 3)
            ->assertSet('moraCuotas.0.num', 2)
            ->assertSet('moraCuotas.0.saldo', 670.00)
            ->assertSet('moraCuotas.0.dias', 14)
            ->assertSet('moraCuotas.0.calc', 77.00)   // 14 × 5.50
            ->assertSet('moraCuotas.0.sel', true)
            ->assertSet('moraCuotas.1.num', 3)
            ->assertSet('moraCuotas.1.saldo', 770.00)
            ->assertSet('moraCuotas.1.dias', 7)
            ->assertSet('moraCuotas.1.calc', 38.50)   // 7 × 5.50
            ->assertSet('moraCuotas.1.sel', true)
            ->assertSet('moraCuotas.2.num', 4)
            ->assertSet('moraCuotas.2.dias', 0)
            ->assertSet('moraCuotas.2.calc', 0.0)
            ->assertSet('moraCuotas.2.sel', false);   // futura: no preseleccionada
    }

    public function test_la_seleccion_es_prefijo_fifo_sin_saltos(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas');

        // Marcar la futura arrastra todas las anteriores.
        $comp->call('toggleCuota', 2)
            ->assertSet('moraCuotas.0.sel', true)
            ->assertSet('moraCuotas.1.sel', true)
            ->assertSet('moraCuotas.2.sel', true);

        // Desmarcar la más antigua desmarca todo lo que sigue (sin huecos).
        $comp->call('toggleCuota', 0)
            ->assertSet('moraCuotas.0.sel', false)
            ->assertSet('moraCuotas.1.sel', false)
            ->assertSet('moraCuotas.2.sel', false);
    }

    /**
     * El checkbox va con wire:model: el update llega como set directo de
     * `sel` y el hook updatedMoraCuotas impone el prefijo en el servidor
     * (regresión: con wire:click el navegador marcaba el checkbox por su
     * cuenta y dejaba elegir saltando cuotas).
     */
    public function test_un_set_directo_del_checkbox_tambien_respeta_el_prefijo(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->call('toggleCuota', 0);          // deja todo desmarcado

        // Marcar la futura (índice 2) directo → arrastra las dos anteriores.
        $comp->set('moraCuotas.2.sel', true)
            ->assertSet('moraCuotas.0.sel', true)
            ->assertSet('moraCuotas.1.sel', true)
            ->assertSet('moraCuotas.2.sel', true);

        // Desmarcar la del medio directo → suelta también la futura.
        $comp->set('moraCuotas.1.sel', false)
            ->assertSet('moraCuotas.0.sel', true)
            ->assertSet('moraCuotas.1.sel', false)
            ->assertSet('moraCuotas.2.sel', false);
    }

    public function test_aplicar_arma_monto_y_total_mora_con_lo_seleccionado(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')          // preselección: cuotas 2 y 3 (670 + 770)
            ->set('moraCuotas.0.valor', '10')
            ->set('moraCuotas.1.valor', '5.50')
            ->call('aplicarCuotas')
            ->assertDispatched('mora-cuotas-close')
            ->assertSet('monto', '1440.00')
            ->assertSet('moraManual', '15.50');
    }

    public function test_la_mora_de_una_cuota_desmarcada_no_suma(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->call('toggleCuota', 1)           // deja solo la cuota 2 (saldo 670, mora 77.00)
            ->call('aplicarCuotas')
            ->assertSet('monto', '670.00')
            ->assertSet('moraManual', '77.00');
    }

    public function test_sin_permiso_aplica_el_monto_pero_no_toca_la_mora(): void
    {
        $this->actingAs($this->actor(conPermiso: false));
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->set('moraCuotas.0.valor', '999')
            ->call('aplicarCuotas')
            ->assertSet('monto', '1440.00')    // seleccionar cuotas sí (es escribir el monto)
            ->assertSet('moraManual', null);   // el override de mora no
    }

    /**
     * El motivo pre-escrito "Mora exonerada" es para REBAJAS; si el override
     * SUBE la mora (aplicar el desglose del modal, o tipear a mano) queda en
     * blanco para que el responsable lo explique con sus palabras (04/09).
     */
    public function test_el_motivo_queda_en_blanco_cuando_la_mora_sube_y_vuelve_cuando_baja(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        // Calculada global: 14 días × 5.50 = 77.00 (desde la más antigua).
        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')          // suma del modal: 77.00 + 38.50 = 115.50 > 77.00
            ->call('aplicarCuotas')
            ->assertSet('moraManual', '115.50')
            ->assertSet('moraMotivo', '');     // aumento → en blanco

        // A mano hacia abajo: vuelve el default histórico de rebaja.
        $comp->set('moraManual', '20')
            ->assertSet('moraMotivo', 'Mora exonerada');

        // Un motivo tipeado por el usuario NO se pisa al volver a subir.
        $comp->set('moraMotivo', 'Acuerdo con el cliente')
            ->set('moraManual', '150')
            ->assertSet('moraMotivo', 'Acuerdo con el cliente');
    }

    /**
     * E2E: lo que se marca en el modal es EXACTAMENTE lo que se cobra.
     * Aplicar (cuotas 2 y 3, mora editada) → motivo → pagar: el motor FIFO
     * liquida esas dos cuotas, la mora cobrada es la del modal y el override
     * queda trazado contra la calculada global.
     */
    public function test_e2e_aplicar_y_pagar_liquida_las_cuotas_marcadas_con_la_mora_del_modal(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')              // preselección: cuotas 2 y 3
            ->set('moraCuotas.0.valor', '10')
            ->set('moraCuotas.1.valor', '5.50')
            ->call('aplicarCuotas')
            ->assertSet('monto', '1440.00')
            ->set('moraMotivo', 'Acuerdo con el cliente')
            ->call('pagar');

        // Las dos cuotas marcadas quedaron liquidadas; la futura, intacta.
        $this->assertSame(1, (int) CreditInstallment::where('credit_id', $credit->id)->where('num_cuota', 2)->value('pagado'));
        $this->assertSame(1, (int) CreditInstallment::where('credit_id', $credit->id)->where('num_cuota', 3)->value('pagado'));
        $this->assertSame(0, (int) CreditInstallment::where('credit_id', $credit->id)->where('num_cuota', 4)->value('pagado'));

        // Cobro: 1440.00 de cuotas + 15.50 de mora, ni un céntimo más.
        $pagos = DB::table('payments')->where('credit_id', $credit->id)->get();
        $this->assertEqualsWithDelta(15.50, (float) $pagos->where('documento', 'MORA')->sum('monto'), 0.01);
        $this->assertEqualsWithDelta(1440.00, (float) $pagos->where('documento', '<>', 'MORA')->sum('monto'), 0.01);

        // Trazabilidad del override: calculada global 77.00 (14 días × 5.50
        // de la cuota más antigua), cobrada 15.50.
        $ov = DB::table('mora_overrides')->where('credit_id', $credit->id)->first();
        $this->assertNotNull($ov);
        $this->assertEqualsWithDelta(77.00, (float) $ov->mora_calculada, 0.01);
        $this->assertEqualsWithDelta(15.50, (float) $ov->mora_cobrada, 0.01);
    }

    /** Mensual: tarifa ÷30 y el excedente entra al saldo y a la tarifa. */
    public function test_mensual_con_excedente_usa_tarifa_sobre_30_y_saldo_completo(): void
    {
        $this->actingAs($this->actor());
        $client = Client::create(['nombre' => 'Cliente Mensual']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(60)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subDays(30)->format('Y-m-d'),
            'importe_cuota' => 1000, 'importe_interes' => 100, 'importe_excedente' => 30, 'pagado' => 0,
        ]);

        // Tarifa: 5% de 1130 ÷ 30 = 1.88/día → 30 días = 56.40; saldo 1130.
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->assertSet('moraCuotas.0.saldo', 1130.00)
            ->assertSet('moraCuotas.0.dias', 30)
            ->assertSet('moraCuotas.0.calc', 56.40)
            ->assertSet('moraCuotas.0.sel', true);
    }

    /** Diario: mantiene su tarifa histórica mora1 (no la regla del 5%). */
    public function test_diario_usa_su_mora1_historico(): void
    {
        $this->actingAs($this->actor());
        $client = Client::create(['nombre' => 'Cliente Diario']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(30)->format('Y-m-d'),
            'importe' => 500, 'cuotas' => 1, 'tipo_planilla' => 4, 'interes' => 10,
            'interes_total' => 50, 'mora1' => 12.34, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subDays(5)->format('Y-m-d'),
            'importe_cuota' => 500, 'importe_interes' => 50, 'pagado' => 0,
        ]);

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->assertSet('moraCuotas.0.dias', 5)
            ->assertSet('moraCuotas.0.rate', 12.34)
            ->assertSet('moraCuotas.0.calc', 61.70); // 5 × 12.34
    }

    /** Valores no numéricos o negativos en la mora editada no ensucian la suma. */
    public function test_entradas_invalidas_en_la_mora_suman_cero(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->set('moraCuotas.0.valor', 'abc')
            ->set('moraCuotas.1.valor', '-5')
            ->call('aplicarCuotas')
            ->assertSet('monto', '1440.00')
            ->assertSet('moraManual', '0.00'); // 'abc' → 0, '-5' → clamp a 0
    }

    /** Desmarcar todo y aplicar limpia el cobro (no deja montos fantasma). */
    public function test_aplicar_sin_seleccion_limpia_monto_y_mora(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->call('toggleCuota', 0)           // desmarca todo (prefijo)
            ->call('aplicarCuotas')
            ->assertSet('monto', null)
            ->assertSet('moraManual', null);
    }

    /** Crédito sin cuotas pendientes: modal vacío y aplicar no revienta. */
    public function test_sin_cuotas_pendientes_el_modal_queda_vacio(): void
    {
        $this->actingAs($this->actor());
        $client = Client::create(['nombre' => 'Cliente Al Dia']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(30)->format('Y-m-d'),
            'importe' => 500, 'cuotas' => 1, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 50, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subDays(7)->format('Y-m-d'),
            'importe_cuota' => 500, 'importe_interes' => 50, 'pagado' => 1,
        ]);

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->assertCount('moraCuotas', 0)
            ->call('aplicarCuotas')
            ->assertSet('monto', null);
    }

    /** Un índice fuera de rango no revienta (llamada Livewire manipulada). */
    public function test_toggle_fuera_de_rango_es_inofensivo(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->call('toggleCuota', 99)
            ->assertCount('moraCuotas', 3)
            ->assertSet('moraCuotas.0.sel', true);
    }

    /** Reabrir el modal descarta ediciones y vuelve al estado calculado. */
    public function test_reabrir_resetea_seleccion_y_moras_editadas(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->set('moraCuotas.0.valor', '1.00')
            ->call('toggleCuota', 2)           // marca también la futura
            ->call('abrirMoraCuotas')          // reabrir = recalcular
            ->assertSet('moraCuotas.0.valor', '77.00')
            ->assertSet('moraCuotas.2.sel', false);
    }
}
