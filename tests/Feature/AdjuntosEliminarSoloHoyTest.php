<?php

namespace Tests\Feature;

use App\Livewire\Cash\ExpenseGallery;
use App\Livewire\Cash\IncomeGallery;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\IncomeAttachment;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026 (Antony): "el rol de Licet (administrador) sí puede eliminar
 * adjuntos, pero solo del mismo día". Quien tiene caja.eliminar sin
 * caja.editar-historico borra adjuntos únicamente de los movimientos de HOY
 * (sean de quien sean); el director sigue sin límite y el operador de caja
 * sigue sin poder eliminar.
 */
class AdjuntosEliminarSoloHoyTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private User $rosa;

    private function mundo(): void
    {
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->seed(PermissionCatalogSeeder::class);
        $this->rosa = User::factory()->create(['username' => 'rosa-caja', 'headquarter_id' => $this->sede->id]);
        Storage::fake('public');
    }

    private function usuario(string $username, array $permisos): User
    {
        $u = User::factory()->create(['username' => $username, 'headquarter_id' => $this->sede->id]);
        $u->givePermissionTo($permisos);

        return $u;
    }

    private function egresoConFoto(string $fecha): array
    {
        $e = Expense::create([
            'date' => $fecha, 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => 1, 'reason' => 'Banco', 'detail' => 'Dep.',
            'total' => 100, 'user_id' => $this->rosa->id, 'headquarter_id' => $this->sede->id,
        ]);
        Storage::disk('public')->put("expenses/{$e->id}/a.jpg", 'x');
        $att = ExpenseAttachment::create([
            'expense_id' => $e->id, 'filename' => 'a.jpg', 'original_name' => 'voucher.jpg', 'path' => "expenses/{$e->id}/a.jpg",
            'thumb_path' => null, 'mime' => 'image/jpeg', 'size' => 1, 'uploaded_by' => $this->rosa->id,
        ]);

        return [$e, $att];
    }

    private function ingresoConFoto(string $fecha): array
    {
        $i = Income::create([
            'date' => $fecha, 'modo' => 'Otros', 'documento' => 'GUIA', 'caja' => 1, 'reason' => 'Banco', 'detail' => 'Dep.',
            'total' => 100, 'user_id' => $this->rosa->id, 'headquarter_id' => $this->sede->id,
        ]);
        Storage::disk('public')->put("incomes/{$i->id}/a.jpg", 'x');
        $att = IncomeAttachment::create([
            'income_id' => $i->id, 'filename' => 'a.jpg', 'original_name' => 'voucher.jpg', 'path' => "incomes/{$i->id}/a.jpg",
            'thumb_path' => null, 'mime' => 'image/jpeg', 'size' => 1, 'uploaded_by' => $this->rosa->id,
        ]);

        return [$i, $att];
    }

    public function test_administrador_elimina_adjuntos_de_egresos_de_hoy_aunque_sean_de_otro_pero_no_de_ayer(): void
    {
        $this->mundo();
        $admin = $this->usuario('licet-admin', ['caja.egresos', 'caja.ingresos', 'caja.ver-todo', 'caja.eliminar']);
        $this->actingAs($admin);
        [$hoy, $attHoy] = $this->egresoConFoto(now()->toDateString());
        [$ayer, $attAyer] = $this->egresoConFoto(now()->subDay()->toDateString());

        // Hoy: puede eliminar (no subir, porque no es suyo), y el aviso lo dice.
        Livewire::test(ExpenseGallery::class, ['id' => $hoy->id])
            ->assertSet('puedeEditar', false)
            ->assertSet('puedeEliminar', true)
            ->assertSee('Puedes eliminar los adjuntos de este egreso porque es de hoy')
            ->call('deleteAttachment', $attHoy->id);
        $this->assertNull($attHoy->fresh());
        Storage::disk('public')->assertMissing("expenses/{$hoy->id}/a.jpg");

        // Ayer: solo mirar.
        Livewire::test(ExpenseGallery::class, ['id' => $ayer->id])
            ->assertSet('puedeEliminar', false)
            ->assertSet('eliminaSoloHoy', true)
            ->assertSee('eliminar es solo para egresos de hoy')
            ->call('deleteAttachment', $attAyer->id);
        $this->assertNotNull($attAyer->fresh());
        Storage::disk('public')->assertExists("expenses/{$ayer->id}/a.jpg");
    }

    public function test_el_operador_de_caja_sigue_sin_eliminar_y_el_director_elimina_cualquier_dia(): void
    {
        $this->mundo();
        [$hoy, $attHoy] = $this->egresoConFoto(now()->toDateString());
        [$ayer, $attAyer] = $this->egresoConFoto(now()->subDay()->toDateString());

        $this->actingAs($this->usuario('operador', ['caja.egresos', 'caja.ingresos']));
        Livewire::test(ExpenseGallery::class, ['id' => $hoy->id])
            ->assertSet('puedeEliminar', false)
            ->assertSet('eliminaSoloHoy', false)
            ->assertSee('No tienes permiso para subir o eliminar')
            ->call('deleteAttachment', $attHoy->id);
        $this->assertNotNull($attHoy->fresh());

        $this->actingAs($this->usuario('director', ['caja.egresos', 'caja.ingresos', 'caja.editar-historico', 'caja.ver-todo', 'caja.eliminar']));
        Livewire::test(ExpenseGallery::class, ['id' => $ayer->id])
            ->assertSet('puedeEditar', true)
            ->assertSet('puedeEliminar', true)
            ->call('deleteAttachment', $attAyer->id);
        $this->assertNull($attAyer->fresh());
    }

    public function test_en_ingresos_rige_la_misma_regla(): void
    {
        $this->mundo();
        $this->actingAs($this->usuario('licet-admin', ['caja.egresos', 'caja.ingresos', 'caja.ver-todo', 'caja.eliminar']));
        [$hoy, $attHoy] = $this->ingresoConFoto(now()->toDateString());
        [$ayer, $attAyer] = $this->ingresoConFoto(now()->subDay()->toDateString());

        Livewire::test(IncomeGallery::class, ['id' => $hoy->id])
            ->assertSet('puedeEliminar', true)
            ->assertSee('Puedes eliminar los adjuntos de este ingreso porque es de hoy')
            ->call('deleteAttachment', $attHoy->id);
        $this->assertNull($attHoy->fresh());

        Livewire::test(IncomeGallery::class, ['id' => $ayer->id])
            ->assertSet('puedeEliminar', false)
            ->assertSee('eliminar es solo para ingresos de hoy')
            ->call('deleteAttachment', $attAyer->id);
        $this->assertNotNull($attAyer->fresh());
    }
}
