<?php

namespace Tests\Feature;

use App\Livewire\Credits\Show;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 26/09/2026: la ficha del crédito abre el recibo de cada pago en el mismo
 * visor que la pantalla de cobro (modal con "Copiar imagen" e "Imprimir"),
 * además de la descarga en PDF.
 */
class FichaCreditoVisorReciboTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ficha_lleva_el_visor_del_recibo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'ficha-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['creditos', 'reportes.pagos']);

        $client = Client::create([
            'expediente' => '9040', 'nombre' => 'LUIS', 'apellido_pat' => 'PEREZ', 'apellido_mat' => 'RAMOS',
            'tipo_documento' => 'DNI', 'documento' => '45678912', 'sexo' => 'M', 'celular1' => '987654321',
            'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->toDateString(), 'importe' => 1000,
            'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
            'headquarter_id' => $sede->id, 'user_id' => $user->id,
        ]);
        foreach ([1, 2] as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => now()->addWeeks($n)->toDateString(),
                'importe_cuota' => 500, 'importe_interes' => 50, 'pagado' => 0,
            ]);
        }

        Livewire::test(Show::class, ['id' => $credit->id])
            ->assertSeeHtml('id="modal-recibo"')
            ->assertSeeHtml('allow="clipboard-write"')
            ->assertSeeHtml('function abrirRecibo(url)')
            // 26/09: botón al reporte de pagos filtrado a este cliente (igual que el cronograma).
            ->assertSee('Reporte de pagos')
            ->assertSeeHtml('cliente='.$client->id);
    }
}
