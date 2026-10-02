<?php

namespace Tests\Feature;

use App\Livewire\Cash\CreateIncome;
use App\Livewire\Cash\Incomes;
use App\Models\Concept;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026 (Antony): "hazlo igual en ingresos, igual que el monto". Lo mismo
 * que en egresos: en el listado, la fila cuyas fotos se abren se queda con el
 * color de hover hasta hacer clic en otro lado; en el formulario, el Monto
 * ofrece la lista de montos usados antes para el mismo motivo (datalist), sin
 * rellenar nada solo.
 */
class IngresosFilaYMontosTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Headquarter $sede;

    private function mundo(): void
    {
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'caja-ing', 'headquarter_id' => $this->sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo(['caja.ingresos', 'caja.editar-historico', 'caja.ver-todo']);
        Concept::create(['code' => 'I1', 'name' => 'Diario', 'type' => 'ingreso', 'factor_ingreso' => 0, 'factor_egreso' => 0, 'status' => 'active']);
    }

    private function ingreso(string $reason, string $detail, float $total, string $date, int $caja = 1, array $extra = []): Income
    {
        return Income::create($extra + [
            'date' => $date, 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => $caja, 'reason' => $reason, 'detail' => $detail,
            'total' => $total, 'user_id' => $this->user->id, 'headquarter_id' => $this->sede->id,
        ]);
    }

    private function opciones(string $html): array
    {
        preg_match('/<datalist id="montos-previos-ingreso">(.*?)<\/datalist>/s', $html, $m);
        preg_match_all('/<option value="([^"]*)">/', $m[1] ?? '', $o);

        return $o[1];
    }

    public function test_en_el_listado_la_fila_con_fotos_se_congela_y_el_clic_fuera_la_suelta(): void
    {
        $this->mundo();
        $conFoto = $this->ingreso('Banco', 'Dep. BCP', 100, now()->format('Y-m-d'), 1, ['image_path' => 'incomes/x.jpg']);
        $sinFoto = $this->ingreso('Banco', 'Sin foto', 50, now()->format('Y-m-d'));

        $html = Livewire::test(Incomes::class)->html();

        $this->assertStringContainsString(':class="{ \'fila-sel\': selectedId === '.$conFoto->id.' }"', $html);
        $this->assertStringContainsString(':class="{ \'fila-sel\': selectedId === '.$sinFoto->id.' }"', $html);
        $this->assertMatchesRegularExpression('/<a href="#" data-foto-fila\s+@click\.prevent="openLightbox\(JSON\.parse\(\'[^\']*\'\), \'[^\']*gallery\', '.$conFoto->id.'\)"/', $html);
        $this->assertStringContainsString('@click.window="deselect($event)"', $html);
        $this->assertStringContainsString("e.target.closest('.huac-lb') || e.target.closest('[data-foto-fila]')", $html);
        $this->assertStringContainsString('.incomes-legacy tbody tr.fila-sel { background-color: #CCFF66 !important; }', $html);
    }

    public function test_en_el_formulario_el_monto_lista_los_montos_previos_del_motivo_sin_rellenar(): void
    {
        $this->mundo();
        $this->ingreso('Banco', 'Yape', 20, '2026-09-20');
        $this->ingreso('Banco', 'Plin', 15, '2026-09-28');
        $this->ingreso('Banco', 'Yape', 25, '2026-09-25');
        $this->ingreso('Banco', 'Otro', 20, '2026-09-10'); // repetido: una sola vez
        $this->ingreso('Banco', 'Legal', 999, '2026-09-30', 4); // caja legal: no cuenta
        $this->ingreso('Caja chica', 'x', 7, '2026-09-01');

        $comp = Livewire::test(CreateIncome::class)->set('modo', 'Otros');
        $comp->assertSeeHtml('wire:model.live.blur="reason"')->assertSeeHtml('wire:model.live.blur="detail"')
            ->assertSeeHtml('list="montos-previos-ingreso"')->assertSeeHtml('inputmode="decimal"');
        $this->assertSame(['15.00', '25.00', '20.00', '7.00'], $this->opciones($comp->html()), 'sin motivo: los últimos de la caja');

        $comp->set('reason', 'Banco')->assertSet('total', '');
        $this->assertSame(['15.00', '25.00', '20.00'], $this->opciones($comp->html()));

        $comp->set('detail', ' yape ')->assertSet('total', '');
        $this->assertSame(['25.00', '20.00', '15.00'], $this->opciones($comp->html()), 'con detalle: primero los de ese detalle');
    }
}
