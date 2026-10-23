<?php

namespace Tests\Feature;

use App\Livewire\Reports\CashGeneral1;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\User;
use App\Services\CajaDailyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 23/10/2026 (Antony): la columna "N° CUOTAS" de Caja General 1 muestra el
 * mismo motivo detallado que el reporte de pagos (App\Support\MotivoCobro) en
 * vez del detalle legacy del primer movimiento, y los Excel lo reciben en una
 * línea. Las estadísticas que comparten el servicio no consultan las cuotas.
 */
class CajaGeneral1MotivoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-08-20 10:00:00');
        $sede = Headquarter::create(['name' => 'Sede Caja', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'caja1-motivo', 'headquarter_id' => $sede->id]));

        $client = Client::create(['nombre' => 'CAJA', 'apellido_pat' => 'UNO', 'headquarter_id' => $sede->id, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-01', 'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1,
            'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        $cuota = CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2026-08-08', 'importe_cuota' => 250, 'importe_interes' => 25,
            'importe_excedente' => 0, 'importe_aplicado' => 250, 'interes_aplicado' => 25, 'excedente_aplicado' => 0,
            'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => true, 'fecha_pago' => '2026-08-08',
        ]);
        foreach ([['CAPITAL', 250, 'Pago : X Cuota: 1/4'], ['INTERES', 25, 'Pago : X Interes: 1/4']] as [$tipo, $monto, $detalle]) {
            Payment::create([
                'credit_id' => $credit->id, 'installment_id' => $cuota->id, 'fecha' => '2026-08-15', 'tipo' => $tipo, 'documento' => $tipo,
                'monto' => $monto, 'detalle' => $detalle, 'user_id' => auth()->id(), 'headquarter_id' => $sede->id,
            ]);
        }
    }

    public function test_el_detalle_muestra_en_n_cuotas_el_motivo_del_reporte_de_pagos_con_el_atraso_en_rojo(): void
    {
        Livewire::withQueryParams(['mes' => '08', 'anio' => '2026'])
            ->test(CashGeneral1::class)
            ->assertSee('Cuota 1 de 4')
            ->assertSee('Capital 250.00 · Interés 25.00')
            ->assertSeeHtml('<span class="text-danger fw-bold">Atraso 7 días</span>')
            ->assertDontSee('Pago : X Cuota: 1/4');
    }

    public function test_el_nombre_del_cliente_en_ingresos_y_egresos_enlaza_a_su_ficha_solo_si_puede_verla(): void
    {
        Credit::query()->update(['fecha_actualizacion' => '2026-08-01']); // también sale como egreso del mes
        $ficha = 'href="'.route('clients.show', Client::firstOrFail()->id).'"';

        $sinPermiso = Livewire::withQueryParams(['mes' => '08', 'anio' => '2026'])->test(CashGeneral1::class)->html();
        $this->assertSame(0, substr_count($sinPermiso, $ficha));

        auth()->user()->givePermissionTo(Permission::findOrCreate('clientes', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $conPermiso = Livewire::withQueryParams(['mes' => '08', 'anio' => '2026'])->test(CashGeneral1::class)->html();
        $this->assertSame(2, substr_count($conPermiso, $ficha), 'un enlace en INGRESOS y otro en EGRESOS');
    }

    public function test_el_excel_recibe_el_motivo_en_una_linea_y_las_estadisticas_siguen_con_el_detalle_legacy(): void
    {
        $c = new CashGeneral1;
        $c->selemes = '08';
        $c->selecano = '2026';
        $ingreso = $c->render()->getData()['days'][0]['ingresos'][0];
        $this->assertSame('Cuota 1 de 4 · Capital 250.00 · Interés 25.00 · Atraso 7 días', $ingreso['nro_cuotas']);

        $sinMotivo = app(CajaDailyService::class)->ingresosPorDia(2026, 8, null, '2026-08-20')['2026-08-15'][0];
        $this->assertSame('Pago : X Cuota: 1/4', $sinMotivo['nro_cuotas']);
        $this->assertNull($sinMotivo['motivo']);
    }
}
