<?php

namespace Tests\Feature;

use App\Livewire\Cash\CreateExpense;
use App\Models\Concept;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026 (Antony): el Monto del nuevo egreso ofrece una LISTA de los montos
 * usados antes ("como si el navegador lo estuviese recordando"), sacada del
 * historial real de la caja operativa: los del mismo motivo, del más reciente
 * al más viejo y sin repetir; con el detalle escrito, primero los de ese mismo
 * detalle. No rellena nada solo. El factor del concepto, si algún día se
 * carga, sigue proponiendo el monto como en el legacy.
 */
class EgresoMontosSugeridosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'caja-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo(['caja.egresos', 'caja.editar-historico']);

        Concept::create(['code' => 'E1', 'name' => 'Diario', 'type' => 'egreso', 'factor_ingreso' => 0, 'factor_egreso' => 0, 'status' => 'active']);
        Concept::create(['code' => 'E2', 'name' => 'Mensual', 'type' => 'egreso', 'factor_ingreso' => 0, 'factor_egreso' => 0, 'status' => 'active']);
        Concept::create(['code' => 'E3', 'name' => 'Luz', 'type' => 'egreso', 'factor_ingreso' => 0, 'factor_egreso' => 180, 'status' => 'active']);
    }

    private function egreso(string $reason, string $detail, float $total, string $date, int $caja = 1, string $modo = 'Fijos'): Expense
    {
        return Expense::create([
            'date' => $date, 'modo' => $modo, 'documento' => 'GUIA', 'caja' => $caja, 'reason' => $reason, 'detail' => $detail,
            'total' => $total, 'user_id' => $this->user->id, 'headquarter_id' => $this->user->headquarter_id,
        ]);
    }

    /** Las opciones del datalist del Monto, en el orden en que salen. */
    private function opciones(string $html): array
    {
        preg_match('/<datalist id="montos-previos">(.*?)<\/datalist>/s', $html, $m);
        preg_match_all('/<option value="([^"]*)">/', $m[1] ?? '', $o);

        return $o[1];
    }

    public function test_la_lista_trae_los_montos_del_motivo_del_mas_reciente_al_mas_viejo_sin_repetir_y_no_rellena_nada(): void
    {
        $this->mundo();
        $this->egreso('Diario', 'Pasajes', 20, '2026-09-20');
        $this->egreso('Diario', 'Almuerzo', 15, '2026-09-28');
        $this->egreso('Diario', 'Pasajes', 25, '2026-09-25');
        $this->egreso('Diario', 'Taxi', 20, '2026-09-10'); // repetido: sale una sola vez
        $this->egreso('Mensual', 'Alquiler', 900, '2026-09-01');

        $comp = Livewire::test(CreateExpense::class)
            ->set('modo', 'Fijos')
            ->set('reason', 'Diario')
            ->assertSet('total', '') // la lista sugiere, no rellena
            ->assertSeeHtml('list="montos-previos"');
        $this->assertSame(['15.00', '25.00', '20.00'], $this->opciones($comp->html()));

        // Con el detalle escrito, primero los de ese detalle (sin distinguir mayúsculas/espacios).
        $comp->set('detail', '  pasajes ');
        $this->assertSame(['25.00', '20.00', '15.00'], $this->opciones($comp->html()));
        $comp->assertSet('total', '');

        // Otro motivo, otra lista.
        $comp->set('reason', 'Mensual');
        $this->assertSame(['900.00'], $this->opciones($comp->html()));
    }

    public function test_sin_motivo_salen_los_ultimos_de_la_caja_y_lo_escrito_a_mano_queda(): void
    {
        $this->mundo();
        $this->egreso('Diario', 'Pasajes', 20, '2026-09-20');
        $this->egreso('Banco', 'Dep. BCP', 325, '2026-10-02', 1, 'Otros');

        $comp = Livewire::test(CreateExpense::class)->set('modo', 'Otros');
        $this->assertSame(['325.00', '20.00'], $this->opciones($comp->html()), 'sin motivo: los últimos de la caja');

        $comp->set('total', '35')->set('reason', 'Banco')->assertSet('total', '35');
        $this->assertSame(['325.00'], $this->opciones($comp->html()));
        $comp->assertSeeHtml('wire:model.live.blur="reason"')->assertSeeHtml('wire:model.live.blur="detail"');
    }

    public function test_el_factor_del_concepto_sigue_proponiendo_y_solo_cuenta_la_caja_operativa(): void
    {
        $this->mundo();
        $this->egreso('Luz', 'Recibo', 150, '2026-09-15');
        // Caja 3 (espejo) y caja 4 (legal) no aparecen en la lista.
        $this->egreso('Diario', 'Pasajes', 999, '2026-09-29', 3);
        $this->egreso('Diario', 'Pasajes', 777, '2026-09-30', 4);

        $comp = Livewire::test(CreateExpense::class)
            ->set('modo', 'Fijos')
            ->set('reason', 'Luz')
            ->assertSet('total', '180.00');
        $this->assertSame(['150.00'], $this->opciones($comp->html()));

        $comp->set('reason', 'Diario')->assertSet('total', '');
        $this->assertSame([], $this->opciones($comp->html()));
    }
}
