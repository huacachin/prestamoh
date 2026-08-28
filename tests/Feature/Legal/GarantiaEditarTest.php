<?php

namespace Tests\Feature\Legal;

use App\Livewire\Legal\Garantias\EditarModal;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Garantia;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Modal de edición de la garantía (25/08): las garantías importadas del Excel
 * llegan sin monto_gravamen ni valor de vehículo, y el validador de contratos
 * las bloquea. El modal permite completarlos, con la sugerencia del total
 * real del cronograma (que es lo que el validador exige que cuadre).
 */
class GarantiaEditarTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(): array
    {
        $this->seed([
            PermissionCatalogSeeder::class,
            RoleSetupSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $user = User::factory()->create(['username' => 'editor-garantias']);
        $user->assignRole('area-legal');
        $this->actingAs($user);

        $client = Client::create([
            'expediente' => '9001', 'nombre' => 'Prueba', 'apellido_pat' => 'Editar', 'apellido_mat' => 'Garantia',
            'tipo_documento' => 'DNI', 'documento' => '99887761', 'sexo' => 'F', 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subMonth(),
            'importe' => 7000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 4,
            'situacion' => 'Activo',
        ]);
        foreach (range(1, 4) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addWeeks($n),
                'importe_cuota' => 1750, 'importe_interes' => 70, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => 0,
            ]);
        }
        $vehiculo = Vehiculo::create(['client_id' => $client->id, 'placa' => 'F9X617', 'marca' => 'KIA', 'modelo' => 'RIO']);
        $garantia = Garantia::create([
            'credit_id' => $credit->id, 'client_id' => $client->id,
            'requiere_revision' => true, // como llegan las importadas
        ]);
        $garantia->vehiculos()->attach($vehiculo->id, ['orden' => 1]);

        return [$garantia, $vehiculo];
    }

    public function test_completa_monto_gravamen_y_valor_del_vehiculo(): void
    {
        [$garantia, $vehiculo] = $this->mundo();

        Livewire::test(EditarModal::class)
            ->call('abrir', $garantia->id)
            ->assertSet('totalCronograma', 7280.0) // 4 × (1750 + 70)
            ->call('usarTotalCronograma')
            ->set('valoresVehiculos.'.$vehiculo->id, 9000)
            ->set('requiereRevision', false)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('garantia-editada');

        $garantia->refresh();
        $this->assertSame('7280.00', (string) $garantia->monto_gravamen);
        $this->assertFalse($garantia->requiere_revision);
        $this->assertSame('9000.00', (string) $vehiculo->fresh()->valor);
    }

    public function test_monto_gravamen_es_obligatorio(): void
    {
        [$garantia] = $this->mundo();

        Livewire::test(EditarModal::class)
            ->call('abrir', $garantia->id)
            ->set('montoGravamen', null)
            ->call('guardar')
            ->assertHasErrors(['montoGravamen' => 'required']);
    }

    public function test_sin_permiso_no_abre(): void
    {
        [$garantia] = $this->mundo();

        $sinPermiso = User::factory()->create(['username' => 'sin-permiso-legal']);
        $sinPermiso->assignRole('analista-creditos');
        $this->actingAs($sinPermiso);

        Livewire::test(EditarModal::class)
            ->call('abrir', $garantia->id)
            ->assertStatus(403);
    }
}
