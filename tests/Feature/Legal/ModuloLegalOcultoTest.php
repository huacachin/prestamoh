<?php

namespace Tests\Feature\Legal;

use App\Livewire\Users\Create as UsersCreate;
use App\Livewire\Users\Edit as UsersEdit;
use App\Livewire\Users\Perms;
use App\Models\User;
use App\Support\MenuLateral;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 11/10/2026 (Antony): "hay que ocultar el módulo del área legal en todo lo
 * que competa: permisos, notificaciones, menú". Con config legal.activo =
 * false (valor por defecto; phpunit.xml lo enciende para el resto de tests del
 * módulo) desaparecen el menú, la campana, el grupo de permisos y el rol de
 * los selectores, y las rutas legal/* dan 404. Nada se borra: con true vuelve.
 */
class ModuloLegalOcultoTest extends TestCase
{
    use RefreshDatabase;

    private const RUTAS_LEGAL = [
        'legal.garantias.index', 'legal.vehiculos', 'legal.settings', 'legal.notaria',
        'legal.papeletas', 'legal.caja', 'legal.expedientes.index',
    ];

    private User $legal;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->legal = User::factory()->create(['username' => 'marvin-legal']);
        $this->legal->assignRole('area-legal');
        $this->director = User::factory()->create(['username' => 'director-oculto']);
        $this->director->assignRole('director');
    }

    public function test_con_el_modulo_oculto_desaparecen_menu_campana_permisos_rol_y_rutas(): void
    {
        config(['legal.activo' => false]);

        // Menú lateral: sin el ítem "Área Legal", y quien solo tenía eso ya no aterriza ahí.
        $this->assertNotContains('legal', array_column(MenuLateral::items(), 'id'));
        $this->assertStringStartsNotWith('legal.', MenuLateral::rutaInicio($this->legal));

        // Rutas: 404 aunque el usuario conserve los permisos legal.*.
        $this->actingAs($this->legal);
        $this->assertTrue($this->legal->can('legal.garantias'), 'los permisos siguen asignados');
        foreach (self::RUTAS_LEGAL as $ruta) {
            $this->get(route($ruta))->assertNotFound();
        }

        // Campana: el layout no la monta ni para el director.
        $this->actingAs($this->director);
        $html = $this->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('legal-bell', $html);
        $this->assertStringNotContainsString('Área Legal', $html);

        // Permisos: el grupo "Área Legal" no se expone en /users/{id}/perms.
        $perms = Livewire::test(Perms::class, ['id' => $this->legal->id]);
        $this->assertArrayNotHasKey('legal', $perms->get('aclGroups'));
        $this->assertArrayHasKey('reportes', $perms->get('aclGroups'));

        // Rol: no se ofrece en los selectores, salvo que sea el rol actual del usuario editado.
        $this->assertNotContains('area-legal', collect(Livewire::test(UsersCreate::class)->get('roles'))->pluck('name')->all());
        $this->assertNotContains('area-legal', collect(Livewire::test(UsersEdit::class, ['id' => $this->director->id])->get('roles'))->pluck('name')->all());
        $this->assertContains('area-legal', collect(Livewire::test(UsersEdit::class, ['id' => $this->legal->id])->get('roles'))->pluck('name')->all(), 'Marvin conserva su rol');
        $this->assertContains('area-legal', collect($perms->get('roles'))->pluck('name')->all());
    }

    public function test_con_el_modulo_activo_todo_vuelve(): void
    {
        config(['legal.activo' => true]);

        $this->assertContains('legal', array_column(MenuLateral::items(), 'id'));
        // El rol area-legal también ve Clientes, así que aterriza ahí; lo que importa es que el módulo vuelve al menú.
        $this->assertNotSame(MenuLateral::RUTA_RESPALDO, MenuLateral::rutaInicio($this->legal));

        $this->actingAs($this->legal);
        $this->get(route('legal.garantias.index'))->assertOk();

        $this->actingAs($this->director);
        $this->assertStringContainsString('layout.legal-bell', $this->get(route('dashboard.index'))->assertOk()->getContent());
        $this->assertArrayHasKey('legal', Livewire::test(Perms::class, ['id' => $this->legal->id])->get('aclGroups'));
        $this->assertContains('area-legal', collect(Livewire::test(UsersCreate::class)->get('roles'))->pluck('name')->all());
    }
}
