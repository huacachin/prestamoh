<?php

namespace Tests\Feature\Legal;

use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ACL del módulo legal: las rutas legal/* van tras permission:legal.garantias
 * (y legal.configuracion para settings). El rol area-legal trae los 7
 * permisos legal.* del catálogo; un analista-creditos no tiene ninguno y
 * recibe 403.
 */
class AclLegalTest extends TestCase
{
    use RefreshDatabase;

    /** Los 7 permisos del módulo legal (PermissionCatalogSeeder) */
    private const PERMISOS_LEGAL = [
        'legal.garantias', 'legal.contratos', 'legal.notaria', 'legal.judicial',
        'legal.papeletas', 'legal.caja', 'legal.configuracion',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_area_legal_accede_a_garantias_y_a_configuracion(): void
    {
        $legal = User::factory()->create(['username' => 'acl-legal']);
        $legal->assignRole('area-legal');

        $this->actingAs($legal);
        $this->get(route('legal.garantias.index'))->assertOk();
        $this->get(route('legal.settings'))->assertOk();
        $this->get(route('legal.notaria'))->assertOk();
    }

    public function test_analista_creditos_recibe_403_en_ambas(): void
    {
        $analista = User::factory()->create(['username' => 'acl-analista']);
        $analista->assignRole('analista-creditos');

        $this->actingAs($analista);
        $this->get(route('legal.garantias.index'))->assertForbidden();
        $this->get(route('legal.settings'))->assertForbidden();
        $this->get(route('legal.notaria'))->assertForbidden();
    }

    public function test_los_siete_permisos_legal_existen_en_bd(): void
    {
        $enBd = Permission::where('guard_name', 'web')
            ->where('name', 'like', 'legal.%')
            ->pluck('name')->sort()->values()->all();

        $esperados = self::PERMISOS_LEGAL;
        sort($esperados);

        $this->assertSame($esperados, $enBd);
    }

    public function test_el_rol_area_legal_tiene_los_siete_permisos(): void
    {
        $rol = Role::where('name', 'area-legal')->where('guard_name', 'web')->firstOrFail();

        foreach (self::PERMISOS_LEGAL as $permiso) {
            $this->assertTrue($rol->hasPermissionTo($permiso), "area-legal sin '{$permiso}'");
        }
    }
}
