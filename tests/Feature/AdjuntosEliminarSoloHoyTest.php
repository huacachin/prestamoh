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
use App\Support\HorarioEliminacion;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    /**
     * 09/10/2026 (Antony): "el rol de Licet debe poder eliminar adjuntos de
     * ingresos y egresos del mismo día": a cualquier hora, no solo de 6 a 11.
     * Los adjuntos de caja saltan la ventana horaria (SinHorarioDeEliminacion);
     * la regla del mismo día y el permiso siguen igual, y todo lo demás sigue
     * con horario.
     */
    public function test_el_administrador_elimina_adjuntos_de_caja_de_hoy_a_cualquier_hora_pero_no_los_de_ayer(): void
    {
        $this->mundo();
        config(['auditoria.eliminar_horario.activo' => true, 'auditoria.eliminar_mismo_dia.activo' => true]);
        Carbon::setTestNow(Carbon::parse('2026-10-09 18:20', 'America/Lima')); // fuera de la ventana de 6 a 11
        $admin = $this->usuario('licet-admin', ['caja.egresos', 'caja.ingresos', 'caja.ver-todo', 'caja.eliminar']);
        $this->actingAs($admin);
        [$egreso, $att] = $this->egresoConFoto(now()->toDateString());
        [$ingreso, $attIng] = $this->ingresoConFoto(now()->toDateString());

        // El botón lleva la marca para que horario-eliminar.js no lo apague fuera de la ventana.
        $comp = Livewire::test(ExpenseGallery::class, ['id' => $egreso->id])->assertSet('puedeEliminar', true);
        $this->assertStringContainsString('data-creado="2026-10-09" data-sin-horario="1"', $comp->html());
        $this->assertStringContainsString("ventanaCerrada && !el.hasAttribute('data-sin-horario')", file_get_contents(public_path('assets/js/horario-eliminar.js')));

        // A las 18:20 pasan la llamada directa (hook) y la que llega como evento tras el SweetAlert (trait).
        $comp->call('questionDelete', $att->id)->assertDispatched('questionDelete')->assertNotDispatched('errorAlert');
        $comp->call('deleteAttachment', $att->id)->assertNotDispatched('errorAlert');
        $this->assertNull($att->fresh());
        Livewire::test(IncomeGallery::class, ['id' => $ingreso->id])->call('deleteAttachment', $attIng->id)->assertNotDispatched('errorAlert');
        $this->assertNull($attIng->fresh());
        $this->assertSame(0, Activity::where('description', 'like', 'Intentó eliminar%')->count());

        // Adjunto subido ayer a un egreso de hoy: la regla del mismo día sigue.
        [$egresoDos, $attViejo] = $this->egresoConFoto(now()->toDateString());
        ExpenseAttachment::whereKey($attViejo->id)->update(['created_at' => now()->subDay()]);
        Livewire::test(ExpenseGallery::class, ['id' => $egresoDos->id])->call('deleteAttachment', $attViejo->id)->assertDispatched('errorAlert');
        $this->assertNotNull($attViejo->fresh());
        $this->assertSame(1, Activity::where('description', 'like', 'Intentó eliminar un registro de otro día%')->count());

        // Y lo que no es adjunto de caja sigue con la ventana: el egreso mismo, no.
        [$egresoTres, $attTres] = $this->egresoConFoto(now()->toDateString());
        $this->assertNull(HorarioEliminacion::motivoBloqueo($attTres, $admin));
        $this->assertSame(HorarioEliminacion::mensajeMismoDia(), HorarioEliminacion::motivoBloqueo($attViejo->fresh(), $admin));
        $this->assertSame(HorarioEliminacion::mensaje(), HorarioEliminacion::motivoBloqueo($egresoTres, $admin));
        $this->assertStringNotContainsString('data-sin-horario', (string) HorarioEliminacion::atributoCreado($egresoTres));
    }
}
