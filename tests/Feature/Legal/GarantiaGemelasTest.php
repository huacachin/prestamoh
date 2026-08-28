<?php

namespace Tests\Feature\Legal;

use App\Livewire\Legal\Garantias\Show;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Garantia;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Aviso de garantías "gemelas" (25/08): el Excel histórico trajo deudores
 * duplicados entre hojas de tasa, así que un cliente puede tener una garantía
 * vigente y otra cancelada. Al abrir la NO vigente, el detalle debe avisar
 * dónde está la vigente (los contratos se emiten sobre ella); al abrir la
 * vigente, las demás se listan como historial discreto.
 */
class GarantiaGemelasTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(): array
    {
        $this->seed([
            PermissionCatalogSeeder::class,
            RoleSetupSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $user = User::factory()->create(['username' => 'gemelas-tester']);
        $user->assignRole('area-legal');
        $this->actingAs($user);

        $client = Client::create([
            'expediente' => '9100', 'nombre' => 'Doble', 'apellido_pat' => 'Garantia', 'apellido_mat' => 'Prueba',
            'tipo_documento' => 'DNI', 'documento' => '99887755', 'sexo' => 'M', 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subMonths(2),
            'importe' => 5000, 'cuotas' => 10, 'tipo_planilla' => 1, 'interes' => 4,
            'situacion' => 'Activo',
        ]);

        $vigente = Garantia::create(['credit_id' => $credit->id, 'client_id' => $client->id, 'estado' => 'vigente']);
        $cancelada = Garantia::create(['credit_id' => $credit->id, 'client_id' => $client->id, 'estado' => 'cancelada']);

        return [$vigente, $cancelada];
    }

    public function test_la_cancelada_avisa_donde_esta_la_vigente(): void
    {
        [$vigente, $cancelada] = $this->mundo();

        Livewire::test(Show::class, ['garantiaId' => $cancelada->id])
            ->assertSee('una garantía vigente')
            ->assertSee('garantía #'.$vigente->id)
            ->assertSee('queda como historial');
    }

    public function test_la_vigente_lista_las_demas_como_historial_discreto(): void
    {
        [$vigente, $cancelada] = $this->mundo();

        Livewire::test(Show::class, ['garantiaId' => $vigente->id])
            ->assertSee('Otras garantías del cliente')
            ->assertSee('#'.$cancelada->id)
            ->assertDontSee('queda como historial');
    }

    public function test_sin_gemelas_no_muestra_ningun_aviso(): void
    {
        [$vigente, $cancelada] = $this->mundo();
        $cancelada->delete();

        Livewire::test(Show::class, ['garantiaId' => $vigente->id])
            ->assertDontSee('Otras garantías del cliente')
            ->assertDontSee('garantía vigente');
    }
}
