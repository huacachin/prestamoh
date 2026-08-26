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
 * Condonación de mora desglosada al cancelar (26/08): "Quitar mora" y
 * "Quitar mora acumulada" son switches independientes, exigen motivo y dejan
 * bitácora tipificada en mora_overrides. El caso de regresión es el 29058:
 * override manual + quitar acumulada — antes el switch único anulaba el
 * override en silencio y la bitácora registraba un cobro que no ocurrió.
 */
class CondonacionMoraTest extends TestCase
{
    use RefreshDatabase;

    private function cajero(bool $conMoraManual = true): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['username' => 'cajero-cond', 'headquarter_id' => 1]);
        if ($conMoraManual) {
            $user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    /**
     * Crédito mensual de 2 cuotas: la 1 pagada 30 días TARDE (genera mora
     * acumulada/exonerada) y la 2 vencida hace 3 días e impaga (mora vigente).
     */
    private function creditoConAmbasMoras(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Condonacion']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(100)->format('Y-m-d'),
            'importe' => 2000, 'cuotas' => 2, 'tipo_planilla' => 3, 'interes' => 10,
            'interes_total' => 200, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subDays(70)->format('Y-m-d'),
            'importe_cuota' => 1000, 'importe_interes' => 100,
            'importe_aplicado' => 1000, 'interes_aplicado' => 100, 'pagado' => 1,
            'fecha_pago' => now()->subDays(40)->format('Y-m-d'),
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 2,
            'fecha_vencimiento' => now()->subDays(3)->format('Y-m-d'),
            'importe_cuota' => 1000, 'importe_interes' => 100, 'pagado' => 0,
        ]);
        // El pago tardío real de la cuota 1 (30 días después del vencimiento):
        // de aquí MoraExonerada reconstruye la mora acumulada no cobrada.
        DB::table('payments')->insert([
            'credit_id' => $credit->id, 'monto' => 1100, 'tipo' => 'CAPITAL',
            'documento' => 'CAPITAL', 'fecha' => now()->subDays(40)->format('Y-m-d'),
            'hora' => '10:00:00', 'headquarter_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $credit->refresh();
    }

    public function test_caso_29058_quitar_solo_acumulada_respeta_el_override_manual(): void
    {
        $this->cajero();
        $credit = $this->creditoConAmbasMoras();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1300)
            ->set('decisionTotal', 'si')          // cancela el crédito
            ->set('quitarMora', false)            // APAGA el default para cobrar la vigente
            ->set('moraManual', 15)               // mora vigente negociada
            ->set('moraMotivo', 'Acuerdo con el cliente')
            ->set('quitarMoraAcum', true)         // perdona SOLO la acumulada
            ->set('condonarMotivo', 'Condonada por cancelación negociada')
            ->call('pagar');

        // La mora negociada SÍ se cobra; la acumulada NO
        $moraCobrada = (float) DB::table('payments')->where('credit_id', $credit->id)
            ->where('tipo', 'MORA')->sum('monto');
        $this->assertEqualsWithDelta(15.0, $moraCobrada, 0.01, 'el override de 15 debe cobrarse');
        $this->assertSame(0, DB::table('payments')->where('credit_id', $credit->id)
            ->where('documento', 'MORA ACUM.')->count(), 'la acumulada quedó condonada');

        // Bitácora honesta y tipificada
        $ajuste = DB::table('mora_overrides')->where('credit_id', $credit->id)->where('tipo', 'ajuste')->first();
        $this->assertNotNull($ajuste);
        $this->assertEqualsWithDelta(15.0, (float) $ajuste->mora_cobrada, 0.01, 'mora_cobrada = lo que entró a caja');

        $cond = DB::table('mora_overrides')->where('credit_id', $credit->id)->where('tipo', 'condonacion-acumulada')->first();
        $this->assertNotNull($cond, 'la condonación de la acumulada deja rastro');
        $this->assertGreaterThan(0, (float) $cond->mora_calculada);
        $this->assertEqualsWithDelta(0.0, (float) $cond->mora_cobrada, 0.001);
        $this->assertSame('Condonada por cancelación negociada', $cond->motivo);

        $this->assertSame('Cancelado', $credit->fresh()->situacion);
    }

    public function test_quitar_ambas_condona_todo_con_dos_filas_de_bitacora(): void
    {
        $this->cajero(conMoraManual: false);   // sin editable: el rol futuro del administrador
        $credit = $this->creditoConAmbasMoras();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1300)
            ->set('decisionTotal', 'si')
            ->set('quitarMora', true)
            ->set('quitarMoraAcum', true)
            ->set('condonarMotivo', 'Cancelación sin moras autorizada')
            ->call('pagar');

        $this->assertSame(0, DB::table('payments')->where('credit_id', $credit->id)
            ->where('tipo', 'MORA')->count(), 'ninguna mora cobrada');
        $this->assertSame(1, DB::table('mora_overrides')->where('credit_id', $credit->id)
            ->where('tipo', 'condonacion-vigente')->count());
        $this->assertSame(1, DB::table('mora_overrides')->where('credit_id', $credit->id)
            ->where('tipo', 'condonacion-acumulada')->count());

        // El total de la operación = solo el monto (sin moras)
        $this->assertEqualsWithDelta(1300.0, (float) DB::table('mass_deletions')
            ->where('credit_id', $credit->id)->value('amount'), 0.01);
        $this->assertSame('Cancelado', $credit->fresh()->situacion);
    }

    /** Paridad estricta: el TOTAL del modal == amount cobrado, con exoneración de acumulada. */
    public function test_el_total_del_modal_es_el_cobrado_al_exonerar_acumulada(): void
    {
        $this->cajero(conMoraManual: false);
        $credit = $this->creditoConAmbasMoras();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1300)
            ->set('decisionTotal', 'si')
            ->set('quitarMoraAcum', true)
            ->call('confirmarPago');

        $preview = $comp->get('preview');
        $this->assertEqualsWithDelta(0.0, (float) $preview['mora_acum_cobrar'], 0.001, 'el modal no debe sumar la acumulada exonerada');
        $this->assertGreaterThan(0, (float) $preview['condonada_acum']);

        $comp->call('pagar');

        $cobrado = (float) DB::table('mass_deletions')->where('credit_id', $credit->id)->value('amount');
        $this->assertEqualsWithDelta((float) $preview['total'], $cobrado, 0.005,
            'el TOTAL del modal debe ser exactamente lo cobrado');
        $this->assertSame(0, DB::table('payments')->where('credit_id', $credit->id)
            ->where('documento', 'MORA ACUM.')->count());
    }

    public function test_condonar_sin_motivo_no_deja_cobrar(): void
    {
        $this->cajero(conMoraManual: false);
        $credit = $this->creditoConAmbasMoras();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1300)
            ->set('decisionTotal', 'si')
            ->set('quitarMora', true)
            ->set('condonarMotivo', '')
            ->call('pagar');

        $this->assertSame(0, DB::table('mass_deletions')->where('credit_id', $credit->id)->count(),
            'sin motivo no hay cobro');
        $this->assertSame('Activo', $credit->fresh()->situacion);
    }

    public function test_encender_quitar_mora_limpia_el_override_y_el_preview_lo_declara(): void
    {
        $this->cajero();
        $credit = $this->creditoConAmbasMoras();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 1300)
            ->set('decisionTotal', 'si')
            ->set('quitarMora', false)            // apaga el default para poder editar
            ->set('moraManual', 15)
            ->set('quitarMora', true)             // precedencia: limpia el override
            ->set('quitarMoraAcum', false)        // la acumulada SÍ se cobra (veredicto mixto)
            ->set('condonarMotivo', 'Condonación total autorizada')
            ->call('confirmarPago');

        $this->assertNull($comp->get('moraManual'), 'el switch limpia el override');
        $preview = $comp->get('preview');
        $this->assertEqualsWithDelta(0.0, (float) $preview['mora'], 0.001, 'el ticket no cobra mora');
        $this->assertGreaterThan(0, (float) $preview['condonada_vigente'], 'el modal declara la condonación');
        $comp->assertSee('Exoneración al cancelar');

        // Veredicto por mora en el bloque de la decisión (antes: "Mora pendiente")
        $comp->assertSee('Mora: se exonera al cancelar');
        $comp->assertSee('Mora acumulada: se cobra');
        $comp->assertDontSee('Mora pendiente');
    }
}
