<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visualización por usuario (/users/{id}/perms): los checkboxes MANDAN sobre
 * el rol para el acceso a módulos — desmarcado = no ve el módulo aunque su rol
 * lo tenga. El director siempre ve todo. Los permisos finos (caja.ver-todo,
 * mora manual, etc.) conservan la unión rol + directos.
 */
class PermisosVistaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_asignar_rol_siembra_los_checks_de_vista(): void
    {
        $admin = User::factory()->create(['username' => 'vista-adm']);
        $admin->assignRole('administrador');

        $this->assertTrue($admin->hasDirectPermission('caja.ingresos'));
        $this->assertTrue($admin->hasDirectPermission('clientes'));
        // Lo que el rol no tiene, no se marca
        $this->assertFalse($admin->hasDirectPermission('configuracion.usuarios'));

        $this->actingAs($admin);
        $this->get('/cash/incomes')->assertOk();
    }

    public function test_desmarcar_un_modulo_bloquea_aunque_el_rol_lo_tenga(): void
    {
        $admin = User::factory()->create(['username' => 'vista-adm2']);
        $admin->assignRole('administrador');
        $admin->revokePermissionTo('caja.ingresos');

        $this->actingAs($admin);
        $this->assertFalse($admin->can('caja.ingresos'));
        $this->get('/cash/incomes')->assertForbidden();
        // Los demás módulos siguen
        $this->get('/credits')->assertOk();
    }

    public function test_director_siempre_ve_todo_sin_checks(): void
    {
        $director = User::factory()->create(['username' => 'vista-dir']);
        $director->assignRole('director');

        $this->assertCount(0, $director->permissions);
        $this->actingAs($director);
        $this->get('/cash/incomes')->assertOk();
        $this->get('/users')->assertOk();
        // El permiso restrictivo NO se le enciende por ser director
        $this->assertFalse($director->can('clientes.scope-propio'));
    }

    public function test_los_permisos_finos_conservan_la_union_rol_mas_directos(): void
    {
        $admin = User::factory()->create(['username' => 'vista-adm3']);
        $admin->assignRole('administrador');

        // Finos por rol (no son checkboxes de vista)
        $this->assertTrue($admin->can('caja.ver-todo'));
        $this->assertTrue($admin->can('pagos.mora-manual'));
        $this->assertFalse($admin->can('caja.editar-historico'));

        // Desmarcar vista no toca los finos
        $admin->revokePermissionTo('caja.ingresos');
        $this->assertTrue($admin->can('caja.ver-todo'));
    }
}
