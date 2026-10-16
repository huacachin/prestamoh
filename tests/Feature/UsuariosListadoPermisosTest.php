<?php

namespace Tests\Feature;

use App\Livewire\Users\Index;
use App\Models\User;
use App\Support\PermisosVista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 16/10/2026 (Antony): en /users todos salían con "27 permisos" porque el badge
 * contaba los permisos DIRECTOS (los checks de módulos, iguales para todos) y
 * no los del rol. Ahora muestra los permisos efectivos y, aparte, los módulos
 * del menú que ve cada uno.
 */
class UsuariosListadoPermisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_listado_muestra_los_permisos_efectivos_del_rol_y_los_modulos_que_ve(): void
    {
        $finos = ['configuracion.usuarios', 'usuarios.gestionar-permisos', 'caja.ver-todo'];
        $modulos = ['dashboard', 'clientes', 'creditos', 'pagos', 'caja.ingresos'];
        foreach (array_merge($finos, $modulos) as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findOrCreate('director', 'web')->syncPermissions(array_merge($finos, $modulos));
        Role::findOrCreate('administrador', 'web')->syncPermissions(array_merge(['configuracion.usuarios'], $modulos));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $director = User::factory()->create(['username' => 'dir', 'name' => 'Aa Director']);
        $director->syncRoles(['director']);
        $admin = User::factory()->create(['username' => 'adm', 'name' => 'Bb Admin']);
        $admin->syncRoles(['administrador']);
        // Al administrador le desmarcaron dos módulos; además tiene un permiso fino directo que NO cuenta como módulo.
        $admin->syncPermissions(['dashboard', 'clientes', 'creditos', 'caja.ver-todo']);
        $this->actingAs($director);

        $html = Livewire::test(Index::class)->html();

        // Director: todos sus permisos (8) y TODOS los módulos del menú (el Gate lo exime de los checks).
        $this->assertStringContainsString('8 permisos</span>', $html);
        $this->assertStringContainsString(count(PermisosVista::visibles()).' módulos</span>', $html);
        // Administrador: 6 del rol + 1 directo fino = 7 permisos; 3 módulos marcados.
        $this->assertStringContainsString('7 permisos</span>', $html);
        $this->assertStringContainsString('3 módulos</span>', $html);
        $this->assertSame(3, PermisosVista::modulosQueVe($admin->fresh()));
        $this->assertStringNotContainsString('4 permisos', $html, 'ya no se cuentan los directos');
    }

    public function test_los_modulos_visibles_no_incluyen_el_area_legal_oculta(): void
    {
        config(['legal.activo' => false]);
        $this->assertNotContains('legal.garantias', PermisosVista::visibles());
        $this->assertSame(29, count(PermisosVista::visibles()));

        config(['legal.activo' => true]);
        $this->assertContains('legal.garantias', PermisosVista::visibles());
        $this->assertSame(count(PermisosVista::LISTA), count(PermisosVista::visibles()));
    }
}
