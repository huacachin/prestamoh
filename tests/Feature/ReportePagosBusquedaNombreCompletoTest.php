<?php

namespace Tests\Feature;

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
 * 10/10/2026 (Antony): en /reports/payments?buscar=Torres+Tapia+Vda+de+Puma+Julia+Isabel
 * no salía nada: el texto entero se buscaba contra nombre, apellido paterno y
 * materno por separado. Ahora busca palabra por palabra sobre el nombre completo
 * (scope Client::nombreContiene), en cualquier orden.
 */
class ReportePagosBusquedaNombreCompletoTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'busca-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['creditos', 'reportes.pagos']);

        $n = 0;
        foreach ([['Torres Tapia Vda de Puma', 'Julia Isabel', 'Torres'], ['Perez', 'Juan', 'Test'], ['Perez', 'Rosa', 'Test'], ['Quispe', 'Rosa', 'Test']] as [$pat, $nombre, $mat]) {
            $n++;
            $client = Client::create([
                'expediente' => (string) (9100 + $n), 'nombre' => $nombre, 'apellido_pat' => $pat, 'apellido_mat' => $mat,
                'tipo_documento' => 'DNI', 'documento' => (string) (46000000 + $n), 'sexo' => 'F',
                'headquarter_id' => $sede->id, 'asesor_id' => $user->id, 'status' => 'active',
            ]);
            $credit = Credit::create([
                'client_id' => $client->id, 'fecha_prestamo' => now()->subMonths(2)->toDateString(), 'importe' => 1000,
                'cuotas' => 1, 'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1,
                'headquarter_id' => $sede->id, 'user_id' => $user->id,
            ]);
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => now()->toDateString(),
                'importe_cuota' => 1000, 'importe_interes' => 100, 'pagado' => 0,
            ]);
            Payment::create([
                'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => 'CAPITAL', 'fecha' => now()->toDateString(),
                'hora' => '10:00:00', 'monto' => 100 + $n, 'moneda' => 'S/', 'detalle' => 'Cuota 1',
                'asesor' => 'tester', 'usuario' => 'busca-tester', 'user_id' => $user->id, 'headquarter_id' => $sede->id,
            ]);
        }
    }

    public function test_el_nombre_completo_y_cualquier_combinacion_de_palabras_encuentran_a_la_persona(): void
    {
        $this->mundo();
        $julia = 'Torres Tapia Vda de Puma Torres Julia Isabel';

        $busca = fn (string $texto) => Livewire::test(ReportePagos::class)->set('tipo', '1')->set('compra', $texto);

        // Lo que Antony tecleó (el caso que no salía) y variantes con menos palabras u otro orden.
        foreach (['Torres Tapia Vda de Puma Julia Isabel', 'Julia Torres', 'torres isabel', 'Vda de Puma', '  Julia   Isabel  '] as $texto) {
            $busca($texto)->assertSee($julia)->assertDontSee('Perez Test Juan')->assertDontSee('Quispe Test Rosa');
        }

        // Dos personas con el mismo apellido: la segunda palabra decide.
        $busca('Perez Rosa')->assertSee('Perez Test Rosa')->assertDontSee('Perez Test Juan')->assertDontSee('Quispe Test Rosa');
        $busca('Rosa Perez')->assertSee('Perez Test Rosa')->assertDontSee('Perez Test Juan');
        $busca('Perez')->assertSee('Perez Test Rosa')->assertSee('Perez Test Juan')->assertDontSee('Quispe Test Rosa');
        $busca('Rosa')->assertSee('Perez Test Rosa')->assertSee('Quispe Test Rosa')->assertDontSee('Perez Test Juan');

        // Nadie se llama así: vacío (y el % no es comodín).
        $busca('Perez Quispe')->assertDontSee('Perez Test')->assertDontSee('Quispe Test');
        $busca('%')->assertDontSee('Perez Test')->assertDontSee('Quispe Test')->assertDontSee($julia);

        // Los otros tipos de búsqueda siguen igual (motivo por LIKE directo).
        Livewire::test(ReportePagos::class)->set('tipo', '2')->set('compra', 'Cuota 1')->assertSee('Perez Test Juan');
    }
}
