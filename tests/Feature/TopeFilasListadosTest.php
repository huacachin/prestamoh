<?php

namespace Tests\Feature;

use App\Livewire\Cash\Expenses;
use App\Livewire\Cash\Incomes;
use App\Livewire\Credits\MassDelete;
use App\Livewire\Payments\Monthly;
use App\Livewire\Reports\Cancelled;
use App\Livewire\Reports\Cash as ReporteCaja;
use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use App\Support\FiltrosSeguros;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 23/10 (Antony, "haz un barrido y analiza otras tablas que funcionen así"):
 * los listados que pintan tablas grandes sin paginar colgaban el navegador de
 * la oficina. Patrón aplicado: fechas saneadas (vacía o a medias nunca abre
 * toda la historia), filtros diferidos (Buscar / Enter) donde eran live, y un
 * tope de filas en pantalla con aviso; el Excel sigue trayendo todo (sinTope).
 */
class TopeFilasListadosTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-10-23';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->travelTo(self::HOY.' 10:00:00');
        $user = User::factory()->create(['username' => 'tope-rep', 'headquarter_id' => 1]);
        foreach (['caja.ver-todo', 'caja.editar-historico', 'acceso.cross-headquarter'] as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);
        // Topes chicos para no insertar miles de filas en el test.
        config(['prestamos.tope_filas' => ['caja' => 3, 'lotes' => 3, 'cancelados' => 3, 'periodo' => 3, 'reporte_caja' => 3]]);
    }

    // ─── Fechas saneadas ───────────────────────────────────────────────────

    public function test_fechas_saneadas(): void
    {
        $this->assertNull(FiltrosSeguros::fecha(''));
        $this->assertNull(FiltrosSeguros::fecha('2026-1'));
        $this->assertNull(FiltrosSeguros::fecha('2026-13-40'));
        $this->assertNull(FiltrosSeguros::fecha(null));
        $this->assertSame('2026-10-23', FiltrosSeguros::fecha('2026-10-23'));

        $this->assertSame([self::HOY, self::HOY], FiltrosSeguros::rango('', ''), 'sin fechas: hoy');
        $this->assertSame(['2026-10-01', '2026-10-01'], FiltrosSeguros::rango('2026-10-01', '2026-1'), 'hasta inválido cae al desde');
        $this->assertSame(['2026-10-05', '2026-10-10'], FiltrosSeguros::rango('2026-10-10', '2026-10-05'), 'cruzadas se intercambian');
        $this->assertSame([null, '2026-10-10'], FiltrosSeguros::rangoLegacy('abc', '2026-10-10'), 'legacy: inválida = sin fecha');
        $this->assertSame(['2026-10-05', '2026-10-10'], FiltrosSeguros::rangoLegacy('2026-10-10', '2026-10-05'));
    }

    // ─── Caja: egresos e ingresos ──────────────────────────────────────────

    private function egresos(int $n, string $fecha, string $detalle = 'Pago a proveedor'): void
    {
        for ($i = 0; $i < $n; $i++) {
            DB::table('expenses')->insert([
                'reason' => 'Diario', 'total' => 10, 'date' => $fecha, 'modo' => 'Otros', 'detail' => "$detalle $i",
                'headquarter_id' => 1, 'caja' => 1, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_egresos_con_texto_y_fecha_vacia_no_pintan_toda_la_historia(): void
    {
        $this->egresos(5, '2020-01-15', 'Historia');
        $this->egresos(1, self::HOY, 'Historia');

        // Texto + fecha vacía = búsqueda sin fecha (legacy): 6 > tope 3 → aviso y sin filas.
        $c = Livewire::test(Expenses::class)->set('tipo', '2')->set('compra', 'Historia')->set('fei', '')->set('fef', '');
        $this->assertCount(0, $c->viewData('expenses'));
        $this->assertSame(['total' => 6, 'tope' => 3], $c->viewData('topeFilas'));
        $c->assertSee('6 registros')->assertSee('el Excel sí trae el resultado completo');

        // Con fechas acotadas cabe: se pintan.
        $c = Livewire::test(Expenses::class)->set('tipo', '2')->set('compra', 'Historia')->set('fei', self::HOY)->set('fef', self::HOY);
        $this->assertCount(1, $c->viewData('expenses'));
        $this->assertNull($c->viewData('topeFilas'));
        $c->assertDontSee('registros');
    }

    public function test_egresos_con_fecha_a_medias_no_rompen_ni_abren_el_rango(): void
    {
        $this->egresos(5, '2020-01-15');
        $this->egresos(1, self::HOY);

        // Sin texto y una fecha a medias: cae a hoy (antes: "Incorrect DATE value" o 0 filas).
        $c = Livewire::test(Expenses::class)->set('fei', '2026-1')->set('fef', self::HOY);
        $this->assertCount(1, $c->viewData('expenses'));
        // Cruzadas: se intercambian.
        $c = Livewire::test(Expenses::class)->set('fei', self::HOY)->set('fef', '2020-01-01');
        $this->assertSame(['total' => 6, 'tope' => 3], $c->viewData('topeFilas'), 'rango 2020..hoy supera el tope');
    }

    public function test_ingresos_cuentan_ingresos_y_pagos_contra_el_tope(): void
    {
        $client = Client::create(['nombre' => 'Cliente Tope', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-25', 'importe' => 1000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        for ($i = 0; $i < 4; $i++) {
            DB::table('payments')->insert([
                'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => 'CAPITAL', 'documento' => 'CAPITAL',
                'fecha' => self::HOY.' 00:00:00', 'hora' => '09:00:00', 'monto' => 10, 'detalle' => 'Pago Tope',
                'headquarter_id' => 1, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $c = Livewire::test(Incomes::class)->set('fei', self::HOY)->set('fef', self::HOY);
        $this->assertSame(['total' => 4, 'tope' => 3], $c->viewData('topeFilas'));
        $this->assertCount(0, $c->viewData('rows'));
        $c->assertSee('4 registros');
    }

    // ─── Eliminar masivo ───────────────────────────────────────────────────

    public function test_eliminar_masivo_filtros_diferidos_y_tope(): void
    {
        $client = Client::create(['nombre' => 'Cliente Lotes', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2021-01-01', 'importe' => 1000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        for ($i = 0; $i < 4; $i++) {
            DB::table('mass_deletions')->insert([
                'credit_id' => $credit->id, 'amount' => 100, 'date' => '2021-03-0'.($i + 1), 'time' => '10:00:00',
                'user' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $c = Livewire::test(MassDelete::class);
        $c->assertDontSeeHtml('wire:model.live')->assertSeeHtml('wire:model="compra"')->assertSeeHtml('wire:model="fei"');

        // Texto + fecha vacía (universo): 4 > 3 → aviso.
        $c->set('tipo', '1')->set('compra', (string) $credit->id)->set('fei', '')->set('fef', '');
        $this->assertSame(['total' => 4, 'tope' => 3], $c->viewData('topeFilas'));
        $this->assertCount(0, $c->viewData('records'));
        $c->assertSee('4 registros');

        // Acotado cabe.
        $c->set('fei', '2021-03-01')->set('fef', '2021-03-02');
        $this->assertNull($c->viewData('topeFilas'));
        $this->assertCount(2, $c->viewData('records'));
    }

    // ─── Cancelados y Crédito Mensual: el universo "Cancelado" ─────────────

    private function cancelados(int $n, int $tipoPlanilla = 3): void
    {
        $client = Client::create(['nombre' => 'Cliente Cancelado', 'headquarter_id' => 1, 'status' => 'active']);
        for ($i = 0; $i < $n; $i++) {
            Credit::create([
                'client_id' => $client->id, 'fecha_prestamo' => '2025-01-01', 'importe' => 1000, 'cuotas' => 4,
                'tipo_planilla' => $tipoPlanilla, 'interes' => 10, 'interes_total' => 100, 'situacion' => 'Cancelado', 'estado' => 0,
                'fecha_cancelacion' => '2025-0'.(($i % 6) + 1).'-15', 'headquarter_id' => 1,
            ]);
        }
    }

    public function test_cancelados_mes_seleccione_avisa_y_el_excel_trae_todo(): void
    {
        $this->cancelados(5);

        $c = Livewire::test(Cancelled::class)->set('selemes', '00')->set('selecano', '2025')->call('search');
        $c->assertDontSeeHtml('wire:model.live')->assertSeeHtml('wire:model="cnombre" wire:keydown.enter="search"');
        $this->assertSame(['total' => 5, 'tope' => 3], $c->viewData('topeFilas'));
        $this->assertCount(0, $c->viewData('rows'));
        $c->assertSee('5 registros');

        // Un mes concreto cabe.
        $c->set('selemes', '01')->call('search');
        $this->assertNull($c->viewData('topeFilas'));
        $this->assertCount(1, $c->viewData('rows'));

        // El Excel (sinTope) trae todo aunque supere el tope.
        $comp = new Cancelled;
        $comp->selemes = '00';
        $comp->selecano = '2025';
        $comp->sinTope = true;
        $this->assertCount(5, $comp->render()->getData()['rows']);
    }

    public function test_credito_mensual_estado_cancelado_avisa_y_el_excel_trae_todo(): void
    {
        $this->cancelados(4, 3);

        $c = Livewire::test(Monthly::class)->set('eestado', 'Cancelado')->call('search');
        $this->assertSame(['total' => 4, 'tope' => 3], $c->viewData('topeFilas'));
        $this->assertCount(0, $c->viewData('rows'));
        $c->assertSee('4 registros');

        $comp = new Monthly;
        $comp->eestado = 'Cancelado';
        $comp->sinTope = true;
        $this->assertCount(4, $comp->render()->getData()['rows']);
    }

    // ─── Reporte de caja por rango ─────────────────────────────────────────

    public function test_reporte_de_caja_sanea_el_rango_y_respeta_el_tope(): void
    {
        $this->egresos(4, '2026-10-20');

        $c = Livewire::test(ReporteCaja::class)->set('fecha_desde', '2026-10-25')->set('fecha_hasta', '2026-10-01')->call('search');
        $this->assertSame(['total' => 4, 'tope' => 3], $c->viewData('topeFilas'), 'cruzadas se intercambian y el tope aplica');
        $this->assertCount(0, $c->viewData('expenses'));

        config(['prestamos.tope_filas.reporte_caja' => 10]);
        $c = Livewire::test(ReporteCaja::class)->set('fecha_desde', '')->set('fecha_hasta', '2026-10-20')->call('search');
        $this->assertCount(4, $c->viewData('expenses'), 'desde vacío cae al hasta: ese día');
        $this->assertNull($c->viewData('topeFilas'));
    }
}
