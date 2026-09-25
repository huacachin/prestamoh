<?php

namespace Tests\Feature;

use App\Livewire\Credits\Schedule;
use App\Livewire\Reports\Payments as ReportePagos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 26/09/2026: botón "Reporte de pagos" en el cronograma → reporte de pagos
 * filtrado EXACTAMENTE a ese cliente (parámetro cliente=id), desde su primer
 * crédito hasta hoy. El texto de "A/" busca por LIKE y no aísla a una persona.
 */
class ReportePagosPorClienteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private function mundo(): array
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'reporte-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo(['creditos', 'reportes.pagos']);

        $creditos = [];
        foreach ([['PEREZ', 'JUAN'], ['PEREZ', 'ROSA'], ['QUISPE', 'ROSA']] as [$apellido, $nombre]) {
            $client = Client::create([
                'expediente' => (string) (9050 + count($creditos)), 'nombre' => $nombre, 'apellido_pat' => $apellido, 'apellido_mat' => 'TEST',
                'tipo_documento' => 'DNI', 'documento' => (string) (45000000 + count($creditos)), 'sexo' => 'F',
                'headquarter_id' => $sede->id, 'asesor_id' => $this->user->id, 'status' => 'active',
            ]);
            $credit = Credit::create([
                'client_id' => $client->id, 'fecha_prestamo' => now()->subMonths(2)->toDateString(), 'importe' => 1000,
                'cuotas' => 1, 'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
                'headquarter_id' => $sede->id, 'user_id' => $this->user->id,
            ]);
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => now()->toDateString(),
                'importe_cuota' => 1000, 'importe_interes' => 100, 'pagado' => 0,
            ]);
            Payment::create([
                'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => 'CAPITAL', 'fecha' => now()->toDateString(),
                'hora' => '10:00:00', 'monto' => 100 + count($creditos), 'moneda' => 'S/', 'detalle' => 'Cuota 1',
                'asesor' => 'tester', 'usuario' => 'reporte-tester', 'user_id' => $this->user->id, 'headquarter_id' => $sede->id,
            ]);
            $creditos[] = $credit;
        }

        return $creditos;
    }

    public function test_el_reporte_filtra_exactamente_por_el_cliente_del_parametro(): void
    {
        [$juan, $rosaPerez] = $this->mundo();

        // Sin filtro: los dos pagos de hoy.
        Livewire::test(ReportePagos::class)
            ->assertSee('PEREZ TEST JUAN')
            ->assertSee('PEREZ TEST ROSA');

        // Con cliente=id: solo el suyo, y el chip con su nombre.
        Livewire::withQueryParams(['cliente' => $rosaPerez->client_id])
            ->test(ReportePagos::class)
            ->assertSee('PEREZ TEST ROSA')
            ->assertDontSee('PEREZ TEST JUAN')
            ->assertSee('Quitar el filtro de cliente')
            ->call('quitarCliente')
            ->assertSee('PEREZ TEST JUAN')
            // Al quitarlo vuelve al rango de hoy: sin cliente, el rango "desde el primer
            // crédito" cargaba todos los pagos de la empresa y colgaba el navegador.
            ->assertSet('fei', now()->format('Y-m-d'))
            ->assertSet('fef', now()->format('Y-m-d'));
    }

    public function test_el_cronograma_enlaza_al_reporte_filtrado_por_el_cliente(): void
    {
        [$juan] = $this->mundo();

        $comp = Livewire::test(Schedule::class, ['id' => $juan->id]);
        $comp->assertSee('Reporte de pagos')
            ->assertSeeHtml('cliente='.$juan->client_id)
            ->assertSeeHtml('desde='.now()->subMonths(2)->toDateString())
            ->assertSeeHtml('hasta='.now()->toDateString());
    }

    public function test_sin_permiso_de_reportes_no_sale_el_boton(): void
    {
        [$juan] = $this->mundo();
        $this->user->revokePermissionTo('reportes.pagos');

        Livewire::test(Schedule::class, ['id' => $juan->id])
            ->assertDontSee('Reporte de pagos');
    }
}
