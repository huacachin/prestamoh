<?php

namespace Tests\Feature;

use App\Livewire\Payments\Index;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 10/10/2026 (Antony): en /payments los botones "Masivo" y "Refinanciar" se
 * abren en otra pestaña, para no perder el listado con los filtros puestos.
 */
class PagosEnlacesNuevaPestanaTest extends TestCase
{
    use RefreshDatabase;

    public function test_masivo_y_refinanciar_abren_en_otra_pestana_en_escritorio_y_en_movil(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'pagos-pestana', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo('pagos');
        $client = Client::create([
            'expediente' => '1002', 'nombre' => 'NOMBREPAGO', 'apellido_pat' => 'PATERNO', 'apellido_mat' => 'MATERNO',
            'tipo_documento' => 'DNI', 'documento' => '40000002', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
        ]);
        // Mensual de una cuota: el único caso con botón Refinanciar.
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-09-01', 'importe' => 1500,
            'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
            'headquarter_id' => $sede->id,
        ]);

        $masivo = '<a href="'.route('payments.create', $credit->id).'" target="_blank" rel="noopener"';
        $refinanciar = '<a href="'.route('payments.refinance', $credit->id).'" target="_blank" rel="noopener"';

        $comp = Livewire::test(Index::class);
        $comp->assertSeeHtml($masivo)->assertSeeHtml($refinanciar);
        $comp->set('movil', true)->assertSeeHtml($masivo)->assertSeeHtml($refinanciar);

        // Ningún enlace a esas pantallas sin target: los cuatro (2 de escritorio + 2 de móvil) lo llevan.
        $vista = file_get_contents(resource_path('views/livewire/payments/index.blade.php'));
        $this->assertSame(2, substr_count($vista, "route('payments.create', \$credit->id) }}\" target=\"_blank\" rel=\"noopener\""));
        $this->assertSame(2, substr_count($vista, "route('payments.refinance', \$credit->id) }}\" target=\"_blank\" rel=\"noopener\""));
        $this->assertSame(4, substr_count($vista, 'target="_blank" rel="noopener"'));
    }
}
