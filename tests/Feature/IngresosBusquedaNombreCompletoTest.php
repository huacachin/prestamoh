<?php

namespace Tests\Feature;

use App\Livewire\Cash\Incomes;
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
 * 10/10/2026 (Antony): "en ingresos también". El filtro "A/" del listado de
 * ingresos (y de su Excel) buscaba el texto entero contra nombre y apellidos
 * por separado: un nombre completo no encontraba. Ahora usa el mismo
 * Client::nombreContiene del reporte de pagos.
 */
class IngresosBusquedaNombreCompletoTest extends TestCase
{
    use RefreshDatabase;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'caja-busca', 'headquarter_id' => $sede->id]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['caja.ingresos', 'caja.editar-historico', 'caja.ver-todo']);

        $n = 0;
        foreach ([['Torres Tapia Vda de Puma', 'Julia Isabel', 'Torres'], ['Perez', 'Juan', 'Test'], ['Perez', 'Rosa', 'Test']] as [$pat, $nombre, $mat]) {
            $n++;
            $client = Client::create([
                'expediente' => (string) (9200 + $n), 'nombre' => $nombre, 'apellido_pat' => $pat, 'apellido_mat' => $mat,
                'tipo_documento' => 'DNI', 'documento' => (string) (47000000 + $n), 'sexo' => 'F',
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
                'asesor' => 'tester', 'usuario' => 'caja-busca', 'user_id' => $user->id, 'headquarter_id' => $sede->id,
            ]);
        }
    }

    public function test_el_listado_de_ingresos_encuentra_el_cobro_por_el_nombre_completo(): void
    {
        $this->mundo();
        $julia = 'Torres Tapia Vda de Puma Torres Julia Isabel';
        $busca = fn (string $texto) => Livewire::test(Incomes::class)->set('tipo', '1')->set('compra', $texto);

        Livewire::test(Incomes::class)->assertSee($julia)->assertSee('Perez Test Juan')->assertSee('Perez Test Rosa');
        foreach (['Torres Tapia Vda de Puma Julia Isabel', 'Julia Torres', 'vda de puma'] as $texto) {
            $busca($texto)->assertSee($julia)->assertDontSee('Perez Test Juan')->assertDontSee('Perez Test Rosa');
        }
        $busca('Perez Rosa')->assertSee('Perez Test Rosa')->assertDontSee('Perez Test Juan')->assertDontSee($julia);
        $busca('Perez')->assertSee('Perez Test Rosa')->assertSee('Perez Test Juan')->assertDontSee($julia);
    }

    public function test_el_excel_de_ingresos_filtra_igual_que_el_listado(): void
    {
        $this->mundo();
        // El export arma su propia consulta (CashController::exportIncomes): mismo criterio.
        $this->get(route('exports.incomes', ['tipo' => '1', 'buscar' => 'Torres Tapia Vda de Puma Julia Isabel']))->assertOk();

        foreach (['CashController', 'Cash/Incomes'] as $f) {
            $fuente = file_get_contents(base_path($f === 'CashController' ? 'app/Http/Controllers/CashController.php' : 'app/Livewire/Cash/Incomes.php'));
            $this->assertStringContainsString("whereHas('credit.client', fn (\$c) => \$c->nombreContiene(\$term))", $fuente, $f);
            $this->assertStringNotContainsString("orWhere('apellido_mat', 'like'", $fuente, "{$f}: ya no busca campo por campo");
        }
    }
}
