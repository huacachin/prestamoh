<?php

namespace Tests\Feature\Legal;

use App\Livewire\Layout\LegalBell;
use App\Models\Client;
use App\Models\ExpedienteJudicial;
use App\Models\PlazoJudicial;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fuente "plazos judiciales" de la campana legal (fase 4): un plazo pendiente
 * ya vencido o dentro de la ventana de PlazoJudicial::DIAS_AVISO pinta el
 * ítem "Plazo judicial — {cliente}" con su descripción y vencimiento; el
 * lejano no aparece y marcarlo cumplido lo saca solo (self-heal). El mundo
 * del test no tiene garantías por renovar ni trámites notariales varados,
 * así que las otras fuentes de la campana no interfieren; descripciones y
 * apellidos llevan marcadores únicos para afirmar sobre el HTML renderizado.
 */
class LegalBellJudicialTest extends TestCase
{
    use RefreshDatabase;

    private User $legal;

    private ExpedienteJudicial $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->legal = User::factory()->create(['username' => 'bell-judicial']);
        $this->legal->assignRole('area-legal');

        $client = Client::create([
            'nombre' => 'CLIENTE', 'apellido_pat' => 'EXPJUDICIAL',
            'documento' => '40000077', 'sexo' => 'M', 'zona' => 'SIGM.S',
        ]);
        $this->expediente = ExpedienteJudicial::create([
            'client_id' => $client->id,
            'nro_expediente' => '04388-2024-0-3209-JP-CI-01',
        ]);
    }

    /** Plazo pendiente del expediente del setUp, con descripción-marcador */
    private function plazo(string $descripcion, string $vencimiento): PlazoJudicial
    {
        return $this->expediente->plazos()->create([
            'descripcion' => $descripcion,
            'fecha_vencimiento' => $vencimiento,
        ]);
    }

    public function test_muestra_el_plazo_vencido_y_oculta_el_lejano(): void
    {
        $this->plazo('PLAZOVENCIDO apelar sentencia', now()->subDay()->toDateString());
        $this->plazo('PLAZOLEJANO audiencia única', now()->addDays(30)->toDateString());

        $this->actingAs($this->legal);

        Livewire::test(LegalBell::class)
            ->assertSee('Plazo judicial')
            ->assertSee('EXPJUDICIAL')
            ->assertSee('PLAZOVENCIDO')
            ->assertSee('VENCIDO hace 1d')
            ->assertDontSee('PLAZOLEJANO');
    }

    public function test_marcar_cumplido_saca_al_plazo_de_la_campana(): void
    {
        $plazo = $this->plazo('PLAZOVENCIDO apelar sentencia', now()->subDay()->toDateString());

        $this->actingAs($this->legal);
        Livewire::test(LegalBell::class)
            ->assertSee('Plazo judicial')
            ->assertSee('PLAZOVENCIDO');

        $plazo->update(['cumplido_at' => now()]);

        Livewire::test(LegalBell::class)
            ->assertDontSee('PLAZOVENCIDO')
            ->assertDontSee('Plazo judicial')
            ->assertSee('Sin alertas legales');
    }
}
