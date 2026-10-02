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
 * 02/10/2026 (Antony): el Monto del nuevo egreso se propone solo. Todos los
 * conceptos de egreso tienen factor 0 (en el legacy también), así que la
 * propuesta sale del ÚLTIMO egreso de caja con el mismo motivo, afinada por
 * detalle cuando ya se registró uno igual. Un monto escrito a mano nunca se
 * pisa, y el factor del concepto manda si algún día se carga.
 */
class EgresoUltimoMontoTest extends TestCase
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

    private function egreso(string $reason, string $detail, float $total, string $date, int $caja = 1): Expense
    {
        return Expense::create([
            'date' => $date, 'modo' => 'Fijos', 'documento' => 'GUIA', 'caja' => $caja, 'reason' => $reason, 'detail' => $detail,
            'total' => $total, 'user_id' => $this->user->id, 'headquarter_id' => $this->user->headquarter_id,
        ]);
    }

    public function test_al_elegir_el_concepto_propone_el_ultimo_monto_y_el_detalle_lo_afina(): void
    {
        $this->mundo();
        $this->egreso('Diario', 'Pasajes', 20, '2026-09-20');
        $this->egreso('Diario', 'Almuerzo', 15, '2026-09-28');
        $this->egreso('Diario', 'Pasajes', 25, '2026-09-25');
        $this->egreso('Mensual', 'Alquiler', 900, '2026-09-01');

        $comp = Livewire::test(CreateExpense::class)
            ->set('modo', 'Fijos')
            ->assertSet('total', '')
            ->set('reason', 'Diario')
            // El más reciente por fecha (28/09), no por id.
            ->assertSet('total', '15.00')
            ->assertSet('montoPropuesto', '15.00')
            ->assertSet('origenPropuesta', 'último egreso de «Diario» del 28/09/2026')
            ->assertSee('Monto propuesto: S/ 15.00')
            // Con el detalle escrito, el último con ESE detalle (25/09, no el de 20/09), sin distinguir mayúsculas.
            ->set('detail', '  pasajes ')
            ->assertSet('total', '25.00')
            ->assertSet('origenPropuesta', 'último egreso de «Diario · Pasajes» del 25/09/2026')
            // Un detalle nuevo vuelve a la propuesta por concepto.
            ->set('detail', 'Taxi')
            ->assertSet('total', '15.00');

        // Cambiar de concepto cambia la propuesta.
        $comp->set('reason', 'Mensual')->assertSet('total', '900.00')
            ->assertSet('origenPropuesta', 'último egreso de «Mensual» del 01/09/2026');
    }

    public function test_un_monto_escrito_a_mano_no_se_pisa_y_al_quitar_el_motivo_se_limpia_la_propuesta(): void
    {
        $this->mundo();
        $this->egreso('Diario', 'Pasajes', 20, '2026-09-20');

        $comp = Livewire::test(CreateExpense::class)
            ->set('modo', 'Fijos')
            ->set('reason', 'Diario')
            ->assertSet('total', '20.00')
            ->set('total', '35')
            ->set('detail', 'Pasajes')
            ->assertSet('total', '35') // seguía la propuesta por detalle (20.00), pero el usuario ya escribió
            ->set('reason', 'Mensual')
            ->assertSet('total', '35'); // tampoco al cambiar de concepto

        // Si el campo trae la propuesta intacta y se quita el motivo, se vacía con su aviso.
        Livewire::test(CreateExpense::class)
            ->set('modo', 'Fijos')->set('reason', 'Diario')->assertSet('total', '20.00')
            ->set('reason', '')
            ->assertSet('total', '')->assertSet('origenPropuesta', '')
            ->assertDontSee('Monto propuesto');
        $comp->set('modo', 'Otros')->assertSet('total', '')->assertSet('origenPropuesta', '');
    }

    public function test_el_factor_del_concepto_manda_y_solo_cuenta_la_caja_operativa(): void
    {
        $this->mundo();
        $this->egreso('Luz', 'Recibo', 150, '2026-09-15');
        // Caja 3 (espejo) y caja 4 (legal) no proponen nada.
        $this->egreso('Diario', 'Pasajes', 999, '2026-09-29', 3);
        $this->egreso('Diario', 'Pasajes', 777, '2026-09-30', 4);

        Livewire::test(CreateExpense::class)
            ->set('modo', 'Fijos')
            ->set('reason', 'Luz')
            ->assertSet('total', '180.00')
            ->assertSet('origenPropuesta', 'monto fijo del concepto «Luz»')
            ->set('reason', 'Diario')
            ->assertSet('total', '')
            ->assertSet('origenPropuesta', '');
    }

    public function test_en_otros_el_motivo_libre_tambien_propone_el_ultimo_monto_al_salir_del_campo(): void
    {
        $this->mundo();
        Expense::create([
            'date' => '2026-09-10', 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => 1, 'reason' => 'Recarga teléfono', 'detail' => 'Claro',
            'total' => 30, 'user_id' => $this->user->id, 'headquarter_id' => $this->user->headquarter_id,
        ]);

        Livewire::test(CreateExpense::class)
            ->set('modo', 'Otros') // el formulario solo se pinta con el tipo elegido
            ->assertSeeHtml('wire:model.live.blur="detail"')
            ->assertSeeHtml('wire:model.live.blur="reason"')
            ->set('reason', 'Recarga teléfono')
            ->assertSet('total', '30.00')
            ->assertSet('origenPropuesta', 'último egreso de «Recarga teléfono» del 10/09/2026')
            ->set('reason', 'Proveedor nuevo')
            ->assertSet('total', '');
    }
}
