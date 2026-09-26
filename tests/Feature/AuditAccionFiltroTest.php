<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index as AuditIndex;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Filtro de acciones del visor de Auditoría (réplica del de newtaxivan):
 * el tipo (creación/edición/eliminación/acceso) se deriva del verbo inicial
 * de la descripción — sin migración ni columna nueva — y, desde el 25/09,
 * también de la columna `event` de los registros automáticos (trait Auditable).
 */
class AuditAccionFiltroTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $user = User::factory()->create(['username' => 'tester']);
        $this->actingAs($user);
        Activity::query()->delete(); // el alta del usuario ya deja su registro automático

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
        foreach (['Creó', 'Registró', 'Agregó', 'Aperturó', 'Refinanció', 'Generó', 'Vinculó', 'Adjuntó',
            'Editó', 'Actualizó', 'Ajustó', 'Reactivó', 'Re-activó', 'Cambió', 'Cambio de', 'Marcó', 'Condonó',
            'Eliminó', 'Anuló', 'Desactivó', 'Revirtió', 'Borró', 'Quitó',
            'Inicio de sesión', 'Cerró sesión', 'Intento de inicio de sesión fallido'] as $verbo) {
            $this->assertNotNull($comp->clasificar($verbo.' algo'), "verbo sin clasificar: {$verbo}");
        }

        // Los verbos nuevos caen en el tipo correcto
        $this->assertSame('creacion', $comp->clasificar('Vinculó a X como copropietario'));
        $this->assertSame('creacion', $comp->clasificar('Adjuntó 2 foto(s) al reporte'));
        $this->assertSame('edicion', $comp->clasificar('Re-activó el crédito #1'));
        $this->assertSame('edicion', $comp->clasificar('Condonó la mora vigente'));
        $this->assertSame('edicion', $comp->clasificar('Cambio de representante legal'));
        $this->assertSame('acceso', $comp->clasificar('Intento de inicio de sesión fallido'));
    }

    public function test_sin_verbo_conocido_clasifica_por_el_event_del_registro_automatico(): void
    {
        $comp = new AuditIndex;
        $this->assertSame('creacion', $comp->clasificar('Xyz', 'created'));
        $this->assertSame('edicion', $comp->clasificar('Xyz', 'updated'));
        $this->assertSame('eliminacion', $comp->clasificar('Xyz', 'deleted'));
        $this->assertNull($comp->clasificar('Xyz'));
        $this->assertNull($comp->clasificar('Xyz', 'restored'));
        // El verbo manda sobre el event
        $this->assertSame('eliminacion', $comp->clasificar('Eliminó algo', 'updated'));
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
