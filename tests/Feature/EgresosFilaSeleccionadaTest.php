<?php

namespace Tests\Feature;

use App\Livewire\Cash\Expenses;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026 (Antony): en el listado de egresos, al abrir las fotos de una
 * fila, esa fila se queda con el color de hover ("congelada") aunque el visor
 * se cierre, y se suelta con un clic en cualquier otro lado. Aquí se prueba el
 * cableado que pinta la vista (la selección vive en Alpine, en el navegador).
 */
class EgresosFilaSeleccionadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_fila_con_fotos_lleva_la_clase_de_seleccion_y_el_clic_fuera_la_suelta(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'caja-fila', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['caja.egresos', 'caja.ver-todo']);

        $conFoto = Expense::create([
            'date' => now()->format('Y-m-d'), 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => 1, 'reason' => 'Banco',
            'detail' => 'Dep. BCP', 'total' => 100, 'user_id' => $user->id, 'headquarter_id' => $sede->id, 'image_path' => 'expenses/x.jpg',
        ]);
        $sinFoto = Expense::create([
            'date' => now()->format('Y-m-d'), 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => 1, 'reason' => 'Banco',
            'detail' => 'Sin foto', 'total' => 50, 'user_id' => $user->id, 'headquarter_id' => $sede->id,
        ]);

        $html = Livewire::test(Expenses::class)->html();

        // Cada fila sabe si es la seleccionada; la cámara pasa el id de su fila al visor.
        $this->assertStringContainsString(':class="{ \'fila-sel\': selectedId === '.$conFoto->id.' }"', $html);
        $this->assertStringContainsString(':class="{ \'fila-sel\': selectedId === '.$sinFoto->id.' }"', $html);
        // Js::from pinta la lista como JSON.parse('…') y la URL de gestión como un string '…'.
        $this->assertMatchesRegularExpression('/<a href="#" data-foto-fila\s+@click\.prevent="openLightbox\(JSON\.parse\(\'[^\']*\'\), \'[^\']*gallery\', '.$conFoto->id.'\)"/', $html);
        // El clic en cualquier otro lado suelta la fila; el visor y la cámara no cuentan.
        $this->assertStringContainsString('@click.window="deselect($event)"', $html);
        $this->assertStringContainsString("e.target.closest('.huac-lb') || e.target.closest('[data-foto-fila]')", $html);
        // El color se impone al background inline que restaura el mouseout.
        $this->assertStringContainsString('.expenses-legacy tbody tr.fila-sel { background-color: #CCFF66 !important; }', $html);
    }
}
