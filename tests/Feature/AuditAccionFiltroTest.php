<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index as AuditIndex;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtro de acciones del visor de Auditoría (réplica del de newtaxivan):
 * el tipo (creación/edición/eliminación/acceso) se deriva del verbo inicial
 * de la descripción — sin migración ni columna nueva.
 */
class AuditAccionFiltroTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create(['username' => 'tester']);
        $this->actingAs($user);

        return $user;
    }

    private function sembrarLog(): void
    {
        Audit::log('Creó el crédito #1');
        Audit::log('Registró pago de 100 en el crédito #1');
        Audit::log('Editó el egreso #5 (monto 200)');
        Audit::log('Eliminó el concepto X');
        Audit::log('Anuló (canceló) el crédito #2');
        Audit::log('Inicio de sesión');
    }

    public function test_filtra_por_tipo_de_accion(): void
    {
        $this->actor();
        $this->sembrarLog();

        $logsDe = fn (string $accion) => Livewire::withQueryParams(['accion' => $accion])
            ->test(AuditIndex::class)->viewData('logs');

        $this->assertCount(6, Livewire::test(AuditIndex::class)->viewData('logs'), 'sin filtro salen todos');
        $this->assertCount(2, $logsDe('creacion'));      // Creó + Registró
        $this->assertCount(1, $logsDe('edicion'));       // Editó
        $this->assertCount(2, $logsDe('eliminacion'));   // Eliminó + Anuló
        $this->assertCount(1, $logsDe('acceso'));        // Inicio de sesión
        $this->assertCount(6, $logsDe('inexistente'), 'un tipo desconocido no filtra nada');
    }

    public function test_todo_el_vocabulario_del_codigo_queda_clasificado(): void
    {
        // Cada verbo usado por los Audit::log del código debe caer en un tipo;
        // si se agrega un verbo nuevo sin mapearlo, este test lo delata.
        $comp = new AuditIndex;
        foreach (['Creó', 'Registró', 'Agregó', 'Aperturó', 'Refinanció',
            'Editó', 'Actualizó', 'Ajustó', 'Reactivó',
            'Eliminó', 'Anuló', 'Desactivó',
            'Inicio de sesión'] as $verbo) {
            $this->assertNotNull($comp->clasificar($verbo.' algo'), "verbo sin clasificar: {$verbo}");
        }
    }

    public function test_la_vista_muestra_el_badge_y_el_select(): void
    {
        $this->actor();
        Audit::log('Eliminó el concepto X');

        Livewire::test(AuditIndex::class)
            ->assertSee('Eliminación')   // badge de la fila (y opción del select)
            ->assertSee('Creación');     // opción del select
    }
}
