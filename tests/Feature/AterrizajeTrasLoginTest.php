<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MenuLateral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Aterrizaje tras iniciar sesión (14/09). El login mandaba a /dashboard y, sin
 * ese permiso, el controlador desviaba a /credits con la ruta escrita a mano:
 * el usuario Marvin (rol area-legal, solo ve Clientes) rebotaba entre
 * pantallas prohibidas. Ahora se aterriza en el primer módulo que el usuario
 * SÍ puede abrir, siguiendo el orden del menú.
 */
class AterrizajeTrasLoginTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon(array $permisos, string $username): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $u = User::factory()->create(['username' => $username, 'headquarter_id' => 1]);
        foreach ($permisos as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    public function test_con_dashboard_aterriza_en_el_dashboard(): void
    {
        $u = $this->usuarioCon(['dashboard', 'clientes'], 'con-dashboard');
        $this->assertSame('dashboard.index', MenuLateral::rutaInicio($u));
        $this->actingAs($u)->get('/dashboard')->assertOk();
    }

    /** El caso de Marvin: sin dashboard ni créditos, pero con clientes. */
    public function test_sin_dashboard_aterriza_en_el_primer_modulo_accesible(): void
    {
        $u = $this->usuarioCon(['clientes'], 'solo-clientes');

        $this->assertSame('clients.index', MenuLateral::rutaInicio($u));
        // Y el desvío del dashboard lo lleva ahí, no a créditos (403).
        $this->actingAs($u)->get('/dashboard')->assertRedirect(route('clients.index'));
    }

    public function test_respeta_el_orden_del_menu_cuando_no_hay_preferida(): void
    {
        // Caja va antes que Reportes en el menú.
        $u = $this->usuarioCon(['reportes.cartera', 'caja.egresos'], 'caja-y-reportes');
        $this->assertSame('cash.expenses', MenuLateral::rutaInicio($u));

        // Solo reportes: cae en el primero de ese grupo que tenga.
        $u2 = $this->usuarioCon(['reportes.cartera'], 'solo-cartera');
        $this->assertSame('reports.portfolio', MenuLateral::rutaInicio($u2));
    }

    /**
     * Las preferidas mandan sobre el orden del menú: el analista de créditos
     * ve Clientes y Prestamo, y "Cliente" va antes en Registro, pero su sitio
     * es su listado de créditos (decisión de negocio previa, no se pisa).
     */
    public function test_las_preferidas_mandan_sobre_el_orden_del_menu(): void
    {
        $u = $this->usuarioCon(['clientes', 'creditos'], 'analista-like');
        $this->assertSame('credits.index', MenuLateral::rutaInicio($u));
        $this->actingAs($u)->get('/dashboard')->assertRedirect(route('credits.index'));
    }

    public function test_sin_ningun_modulo_va_a_una_pagina_que_lo_explica(): void
    {
        $u = $this->usuarioCon([], 'sin-nada');

        $this->assertSame('sin-accesos', MenuLateral::rutaInicio($u));
        $this->actingAs($u)->get('/dashboard')->assertRedirect(route('sin-accesos'));
        $this->actingAs($u)->get('/sin-accesos')
            ->assertOk()
            ->assertSee('Sin módulos asignados');
    }

    public function test_el_director_aterriza_en_auditoria_si_no_tuviera_nada_mas(): void
    {
        $u = $this->usuarioCon([], 'director-pelado');
        $u->assignRole(Role::findOrCreate('director', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // El Gate exime al director en los permisos de visualización, así que
        // aterriza en el primer módulo del menú.
        $this->assertSame('dashboard.index', MenuLateral::rutaInicio($u->fresh()));
    }

    public function test_todas_las_rutas_del_menu_existen(): void
    {
        $faltan = [];
        foreach (MenuLateral::items() as $item) {
            foreach (array_merge([$item], $item['children'] ?? []) as $nodo) {
                if (! empty($nodo['route']) && ! Route::has($nodo['route'])) {
                    $faltan[] = $nodo['route'];
                }
            }
        }
        $this->assertSame([], $faltan, 'Rutas del menú que no existen: '.implode(', ', $faltan));
    }
}
