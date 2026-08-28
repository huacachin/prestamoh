<?php

namespace Tests\Feature\Legal;

use App\Livewire\Layout\LegalBell;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\SigmAviso;
use App\Models\TramiteNotarial;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Campana legal del navbar (query derivada, self-heal): junta garantías
 * vigentes por renovar ("Renovar aviso SIGM", ventana de 7 días) y trámites
 * notariales varados ("Trámite notarial", 15 días en el mismo estado
 * abierto). Un ítem sale solo al corregirse la causa — p. ej. registrar la
 * renovación SIGM — y para un usuario sin legal.garantias la campana va
 * vacía. Los apellidos/descripciones llevan marcadores únicos para
 * afirmar presencia/ausencia sobre el HTML renderizado.
 */
class LegalBellTest extends TestCase
{
    use RefreshDatabase;

    private User $legal;

    private Headquarter $sede;

    private int $documento = 40000001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->legal = User::factory()->create(['username' => 'bell-legal']);
        $this->legal->assignRole('area-legal');
        $this->sede = Headquarter::create(['name' => 'Sede Central']);
    }

    /** Cliente con apellido-marcador único para assertSee/assertDontSee */
    private function cliente(string $apellido): Client
    {
        return Client::create([
            'nombre' => 'CLIENTE', 'apellido_pat' => $apellido,
            'documento' => (string) $this->documento++, 'sexo' => 'M', 'zona' => 'SIGM.S',
        ]);
    }

    /** Garantía vigente de un cliente-marcador, con la vigencia indicada */
    private function garantiaVigente(string $apellido, string $vigenciaHasta): Garantia
    {
        $client = $this->cliente($apellido);
        $credit = Credit::create([
            'client_id' => $client->id,
            'fecha_prestamo' => now()->toDateString(),
            'importe' => 10000, 'cuotas' => 12, 'tipo_planilla' => 3,
            'user_id' => $this->legal->id, 'headquarter_id' => $this->sede->id,
        ]);

        return Garantia::create([
            'credit_id' => $credit->id, 'client_id' => $client->id,
            'tipo' => 'mobiliaria_vehicular', 'tipo_persona' => 'natural',
            'monto_gravamen' => 10000,
            'estado' => 'vigente', 'vigencia_hasta' => $vigenciaHasta,
            'registrado_por' => $this->legal->id,
        ]);
    }

    /**
     * Trámite en notaría con el marcador tanto en el apellido del cliente
     * como en la descripción: la fila se detecta pinte lo que pinte la vista.
     */
    private function tramiteEnNotaria(string $marcador, string $estadoDesde): TramiteNotarial
    {
        return TramiteNotarial::create([
            'client_id' => $this->cliente($marcador)->id,
            'tipo' => 'carta_notarial',
            'descripcion' => "Carta notarial {$marcador}",
            'notaria' => 'NOTARÍA HINOJOSA',
            'estado' => 'en_notaria',
            'estado_desde' => $estadoDesde,
        ]);
    }

    public function test_muestra_vencida_proxima_y_varado_y_oculta_lejana_y_fresco(): void
    {
        $this->garantiaVigente('GARVENCIDA', now()->subDay()->toDateString());
        $this->garantiaVigente('GARTRESDIAS', now()->addDays(3)->toDateString());
        $this->garantiaVigente('GARTREINTA', now()->addDays(30)->toDateString());
        $this->tramiteEnNotaria('TRAVARADO', now()->subDays(20)->toDateString());
        $this->tramiteEnNotaria('TRAFRESCO', now()->subDays(2)->toDateString());

        $this->actingAs($this->legal);

        Livewire::test(LegalBell::class)
            ->assertSee('Renovar aviso SIGM')
            ->assertSee('GARVENCIDA')
            ->assertSee('GARTRESDIAS')
            ->assertDontSee('GARTREINTA')
            ->assertSee('Trámite notarial')
            ->assertSee('TRAVARADO')
            ->assertDontSee('TRAFRESCO');
    }

    public function test_registrar_la_renovacion_saca_a_la_garantia_de_la_campana(): void
    {
        $garantia = $this->garantiaVigente('GARVENCIDA', now()->subDay()->toDateString());

        $this->actingAs($this->legal);
        Livewire::test(LegalBell::class)->assertSee('GARVENCIDA');

        SigmAviso::create([
            'garantia_id' => $garantia->id, 'tipo' => 'renovacion',
            'nro_formulario' => '2026-999888',
            'fecha_presentacion' => now()->toDateString(),
            'vigencia_hasta' => now()->addYears(5)->toDateString(),
            'registrado_por' => $this->legal->id,
        ]);
        $garantia->sincronizarConAvisos();

        $this->assertSame('vigente', $garantia->fresh()->estado);
        Livewire::test(LegalBell::class)
            ->assertDontSee('GARVENCIDA')
            ->assertDontSee('Renovar aviso SIGM');
    }

    public function test_sin_permiso_legal_garantias_la_campana_va_vacia(): void
    {
        $this->garantiaVigente('GARVENCIDA', now()->subDay()->toDateString());
        $this->tramiteEnNotaria('TRAVARADO', now()->subDays(20)->toDateString());

        $analista = User::factory()->create(['username' => 'bell-analista']);
        $analista->assignRole('analista-creditos');
        $this->actingAs($analista);

        Livewire::test(LegalBell::class)
            ->assertDontSee('GARVENCIDA')
            ->assertDontSee('TRAVARADO')
            ->assertDontSee('Renovar aviso SIGM')
            ->assertDontSee('Trámite notarial');
    }
}
