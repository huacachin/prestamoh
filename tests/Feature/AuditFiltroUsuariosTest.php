<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index as AuditIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtro de usuarios del visor de Auditoría (28/10/2026, Antony: "están saliendo
 * usuarios inactivos"): solo lista los usuarios activos.
 */
class AuditFiltroUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_filtro_de_usuarios_solo_lista_los_activos(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Director', 'username' => 'director', 'status' => 'active']));
        User::factory()->create(['name' => 'Cajera Activa', 'username' => 'activa', 'status' => 'active']);
        User::factory()->create(['name' => 'Asesor Inactivo', 'username' => 'inactivo', 'status' => 'inactive']);

        $usuarios = Livewire::test(AuditIndex::class)->instance()->usuarios;

        $this->assertSame(['activa', 'director'], $usuarios->pluck('username')->all());
    }
}
