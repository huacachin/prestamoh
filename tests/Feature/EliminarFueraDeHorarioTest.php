<?php

namespace Tests\Feature;

use App\Livewire\Cash\CreateExpense;
use App\Livewire\Cash\EditExpense;
use App\Livewire\Credits\Index as CreditsIndex;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\User;
use App\Support\Audit;
use App\Support\HorarioEliminacion;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 09/10/2026 (Antony): quien no es director solo puede eliminar de 6:00 a
 * 11:00 de la mañana. Bloqueado en el servidor (hook de Livewire sobre todo
 * método delete…, destroy, eliminar…, borrar o anular, por si la página quedó
 * abierta desde antes) y en pantalla (horario-eliminar.js, con la bandera que
 * pinta el layout).
 */
class EliminarFueraDeHorarioTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'horario-tester', 'headquarter_id' => $this->sede->id]);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo(['caja.egresos', 'caja.eliminar', 'caja.editar-historico', 'caja.ver-todo']);
        $this->actingAs($this->user);
        // En el resto de la suite el bloqueo va apagado (phpunit.xml); aquí se prueba encendido.
        config(['auditoria.eliminar_horario.activo' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function egreso(): Expense
    {
        return Expense::create([
            'date' => now()->format('Y-m-d'), 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => 1, 'reason' => 'Banco',
            'detail' => 'Dep.', 'total' => 10, 'user_id' => $this->user->id, 'headquarter_id' => $this->sede->id,
        ]);
    }

    public function test_la_ventana_es_de_6_a_11_hora_de_lima(): void
    {
        config(['auditoria.eliminar_horario.activo' => false]);
        $this->assertFalse(HorarioEliminacion::bloqueado($this->user, Carbon::parse('2026-10-09 15:00', 'America/Lima')), 'apagado: nunca bloquea');
        config(['auditoria.eliminar_horario.activo' => true]);
        $this->assertTrue(HorarioEliminacion::bloqueado($this->user, Carbon::parse('2026-10-09 15:00', 'America/Lima')));
        foreach (['05:59' => false, '06:00' => true, '10:59' => true, '11:00' => false, '15:30' => false, '02:00' => false] as $hora => $abierta) {
            $this->assertSame($abierta, HorarioEliminacion::enVentana(Carbon::parse("2026-10-09 {$hora}", 'America/Lima')), "a las {$hora}");
        }
        foreach (['delete', 'deleteAttachment', 'destroy', 'eliminar', 'eliminarFoto', 'borrar', 'anular'] as $m) {
            $this->assertTrue(HorarioEliminacion::esMetodoDeEliminar($m), $m);
        }
        foreach (['save', 'update', 'removeFile', 'quitarVehiculo', 'reverse', 'pagar'] as $m) {
            $this->assertFalse(HorarioEliminacion::esMetodoDeEliminar($m), $m);
        }
    }

    public function test_fuera_de_horario_el_servidor_no_elimina_avisa_y_lo_deja_en_la_auditoria(): void
    {
        $egreso = $this->egreso();
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Activity::query()->delete();

        Livewire::test(EditExpense::class, ['id' => $egreso->id])
            ->call('destroy', $egreso->id)
            ->assertDispatched('errorAlert')
            ->assertNoRedirect();

        $this->assertNotNull($egreso->fresh(), 'el egreso sigue');
        $this->assertStringContainsString(HorarioEliminacion::mensaje(), json_encode(Livewire::test(EditExpense::class, ['id' => $egreso->id])->call('destroy', $egreso->id)->effects['dispatches'] ?? [], JSON_UNESCAPED_UNICODE));
        $intento = Activity::query()->where('log_name', Audit::LOG)->where('description', 'like', 'Intentó eliminar fuera de horario%')->first();
        $this->assertNotNull($intento);
        $this->assertStringContainsString('EditExpense::destroy', $intento->description);
    }

    public function test_dentro_de_horario_elimina_y_el_director_elimina_a_cualquier_hora(): void
    {
        $egreso = $this->egreso();
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:30', 'America/Lima'));
        Livewire::test(EditExpense::class, ['id' => $egreso->id])->call('destroy', $egreso->id)->assertNotDispatched('errorAlert');
        $this->assertNull($egreso->fresh(), 'a las 8:30 sí se elimina');

        $otro = $this->egreso();
        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Livewire::test(EditExpense::class, ['id' => $otro->id])->call('destroy', $otro->id)->assertNotDispatched('errorAlert');
        $this->assertNull($otro->fresh(), 'el director elimina a cualquier hora');
    }

    public function test_el_hook_no_toca_las_acciones_que_no_eliminan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 15:00', 'America/Lima'));
        Livewire::test(CreateExpense::class)
            ->set('modo', 'Otros')->set('reason', 'Banco')->set('detail', 'Dep.')->set('total', '10')
            ->call('save')
            ->assertNotDispatched('errorAlert');
        $this->assertSame(1, Expense::where('caja', 1)->count(), 'guardar sigue funcionando a cualquier hora');

        // Y el delete de créditos (otro componente, otro nombre de método) sí queda bloqueado.
        $this->user->givePermissionTo('creditos');
        Livewire::test(CreditsIndex::class)->call('delete', 999999)->assertDispatched('errorAlert');
    }

    public function test_el_layout_entrega_la_bandera_y_el_script_a_quien_no_es_director(): void
    {
        $html = $this->get(route('cash.expenses.create'))->assertOk()->getContent();
        $this->assertStringContainsString('window.HorarioEliminacion = {"activo":true,"director":false,"desde":6,"hasta":11,', $html);
        $this->assertStringContainsString('assets/js/horario-eliminar.js', $html);

        $this->user->syncRoles([Role::findOrCreate('director', 'web')]);
        $this->assertStringContainsString('"director":true', $this->get(route('cash.expenses.create'))->getContent());
    }
}
