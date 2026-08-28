<?php

namespace Tests\Feature\Legal;

use App\Livewire\Layout\LegalBell;
use App\Models\Papeleta;
use App\Models\PapeletaRecurso;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fuentes de fase 5 de la campana legal: un recurso de papeleta PENDIENTE ya
 * vencido o dentro de la ventana de PapeletaRecurso::DIAS_AVISO pinta el ítem
 * "Recurso de papeleta — {entidad} {nro} ({placa})" y resolverlo lo saca solo
 * (self-heal); los vencimientos documentarios de la flota (SOAT / revisión
 * técnica / habilitación ATU) alertan solo en vehículos ACTIVOS de empresa o
 * tercero y dentro de los 15 días. El mundo del test no tiene garantías por
 * renovar, trámites notariales varados ni plazos judiciales, así que las
 * otras fuentes de la campana no interfieren; placas y números de papeleta
 * llevan marcadores únicos para afirmar sobre el HTML renderizado.
 */
class LegalBellPapeletasTest extends TestCase
{
    use RefreshDatabase;

    private User $legal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->legal = User::factory()->create(['username' => 'bell-papeletas']);
        $this->legal->assignRole('area-legal');
    }

    /** Vehículo de flota propia (empresa), activo salvo que se indique */
    private function vehiculo(string $placa, array $attrs = []): Vehiculo
    {
        return Vehiculo::create(array_merge([
            'placa' => $placa,
            'propietario_tipo' => 'empresa',
        ], $attrs));
    }

    /** Papeleta con un recurso pendiente que vence en la fecha dada */
    private function recurso(string $entidad, string $nro, string $placa, string $plazoVence): PapeletaRecurso
    {
        $papeleta = Papeleta::create([
            'vehiculo_id' => $this->vehiculo($placa)->id,
            'entidad' => $entidad,
            'nro_papeleta' => $nro,
        ]);

        return $papeleta->recursos()->create([
            'tipo' => 'descargo',
            'fecha_presentacion' => now()->subDays(10)->toDateString(),
            'plazo_vence' => $plazoVence,
        ]);
    }

    public function test_muestra_el_recurso_vencido_y_oculta_el_lejano(): void
    {
        $this->recurso('SAT', 'RECVENCIDO1', 'REC-111', now()->subDay()->toDateString());
        $this->recurso('ATU', 'RECLEJANO99', 'REC-222', now()->addDays(30)->toDateString());

        $this->actingAs($this->legal);

        Livewire::test(LegalBell::class)
            ->assertSee('Recurso de papeleta')
            ->assertSee('RECVENCIDO1')
            ->assertSee('REC-111')
            ->assertDontSee('RECLEJANO99');
    }

    public function test_resolver_el_recurso_lo_saca_de_la_campana(): void
    {
        $recurso = $this->recurso('SAT', 'RECVENCIDO1', 'REC-111', now()->subDay()->toDateString());

        $this->actingAs($this->legal);
        Livewire::test(LegalBell::class)
            ->assertSee('Recurso de papeleta')
            ->assertSee('RECVENCIDO1');

        $recurso->update(['resultado' => 'atendido', 'resuelto_at' => now()->toDateString()]);

        Livewire::test(LegalBell::class)
            ->assertDontSee('RECVENCIDO1')
            ->assertDontSee('Recurso de papeleta')
            ->assertSee('Sin alertas legales');
    }

    public function test_vencimientos_de_flota_solo_en_activos_y_dentro_de_la_ventana(): void
    {
        // Aparece: activo, de empresa, SOAT vencido ayer
        $this->vehiculo('VEN-111', ['soat_vence' => now()->subDay()->toDateString()]);
        // No aparece: su SOAT recién vence en 60 días
        $this->vehiculo('LEJ-222', ['soat_vence' => now()->addDays(60)->toDateString()]);
        // No aparece: vendido, aunque su SOAT esté vencido hace un mes
        $this->vehiculo('BAJ-333', ['estado' => 'vendido', 'soat_vence' => now()->subDays(30)->toDateString()]);

        $this->actingAs($this->legal);

        Livewire::test(LegalBell::class)
            ->assertSee('SOAT')
            ->assertSee('VEN-111')
            ->assertSee('VENCIDO hace 1d')
            ->assertDontSee('LEJ-222')
            ->assertDontSee('BAJ-333');
    }
}
