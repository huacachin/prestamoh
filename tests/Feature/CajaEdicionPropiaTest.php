<?php

namespace Tests\Feature;

use App\Livewire\Cash\EditExpense;
use App\Livewire\Cash\EditIncome;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Regla 04/09 (Antony): el director (caja.editar-historico) es el ÚNICO que
 * edita registros de todos (y de cualquier fecha); administrador hacia
 * abajo edita SOLO lo hecho por él mismo y SOLO del día. caja.ver-todo da
 * visibilidad de todas las cajas, ya no edición ajena.
 */
class CajaEdicionPropiaTest extends TestCase
{
    use RefreshDatabase;

    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->dueno = User::factory()->create(['username' => 'operador-dueno', 'headquarter_id' => 1]);
    }

    private function actorCon(array $permisos, string $username): User
    {
        $user = User::factory()->create(['username' => $username, 'headquarter_id' => 1]);
        foreach ($permisos as $p) {
            $user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function ingresoDe(User $user, string $fecha): Income
    {
        return Income::create([
            'date' => $fecha, 'reason' => 'Ingreso Prueba', 'detail' => 'test',
            'total' => 100, 'modo' => 'Otros', 'caja' => 1,
            'user_id' => $user->id, 'headquarter_id' => 1,
        ]);
    }

    private function egresoDe(User $user, string $fecha): Expense
    {
        return Expense::create([
            'date' => $fecha, 'reason' => 'Egreso Prueba', 'detail' => 'test',
            'total' => 50, 'modo' => 'Otros', 'caja' => 1,
            'user_id' => $user->id, 'headquarter_id' => 1,
        ]);
    }

    public function test_con_ver_todo_ya_no_se_edita_el_movimiento_ajeno_del_dia(): void
    {
        // El perfil del administrador: ver-todo pero sin editar-historico.
        $this->actingAs($this->actorCon(['caja.ver-todo', 'caja.ingresos'], 'admin-like'));
        $ingreso = $this->ingresoDe($this->dueno, now()->format('Y-m-d'));

        // Livewire 4 convierte el abort del mount en respuesta 403.
        Livewire::test(EditIncome::class, ['id' => $ingreso->id])->assertStatus(403);
    }

    public function test_el_dueno_si_edita_su_movimiento_del_dia(): void
    {
        $this->actingAs($this->dueno);
        $ingreso = $this->ingresoDe($this->dueno, now()->format('Y-m-d'));

        Livewire::test(EditIncome::class, ['id' => $ingreso->id])
            ->set('detail', 'corregido por su dueño')
            ->call('update');

        $this->assertSame('corregido por su dueño', $ingreso->refresh()->detail);
    }

    public function test_el_dueno_no_edita_su_propio_movimiento_de_ayer(): void
    {
        $this->actingAs($this->dueno);
        $ingreso = $this->ingresoDe($this->dueno, now()->subDay()->format('Y-m-d'));

        Livewire::test(EditIncome::class, ['id' => $ingreso->id])->assertStatus(403);
    }

    public function test_el_director_edita_lo_de_todos_y_de_cualquier_fecha(): void
    {
        $this->actingAs($this->actorCon(['caja.editar-historico', 'caja.ver-todo'], 'director-like'));
        $ingreso = $this->ingresoDe($this->dueno, now()->subDays(10)->format('Y-m-d'));

        Livewire::test(EditIncome::class, ['id' => $ingreso->id])
            ->set('detail', 'corregido por el director')
            ->call('update');

        $this->assertSame('corregido por el director', $ingreso->refresh()->detail);
    }

    public function test_la_misma_regla_aplica_a_egresos(): void
    {
        $this->actingAs($this->actorCon(['caja.ver-todo', 'caja.egresos'], 'admin-like-2'));
        $egreso = $this->egresoDe($this->dueno, now()->format('Y-m-d'));

        Livewire::test(EditExpense::class, ['id' => $egreso->id])->assertStatus(403);
    }
}
