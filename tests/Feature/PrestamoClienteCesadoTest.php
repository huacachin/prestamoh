<?php

namespace Tests\Feature;

use App\Livewire\Clients\Prestamos;
use App\Livewire\Credits\Create;
use App\Livewire\Payments\Refinance;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 06/11/2026 (Antony): el 05/11 se le hizo el préstamo #29531 a una cliente cesada. "Crear una
 * validación para que no se le pueda hacer préstamos a un cliente cesado, que lo tenga que dar de
 * alta antes". El legacy lo impedía al buscar el DNI (solo encontraba personas activas).
 */
class PrestamoClienteCesadoTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO = 'está cesado: dalo de alta en Cliente Cesados antes de hacerle un préstamo.';

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Headquarter::forceCreate(['id' => 1, 'name' => 'Principal', 'status' => 'active']);
        $user = User::factory()->create(['username' => 'cesado-tester', 'headquarter_id' => 1]);
        $this->actingAs($user);
        $this->seed(PermissionCatalogSeeder::class);
        $user->givePermissionTo(['creditos', 'registro.cesados']);
        $this->client = Client::create([
            'expediente' => '1270', 'nombre' => 'FLORINDA FLOR', 'apellido_pat' => 'SULLCARAY', 'apellido_mat' => 'BENITO',
            'tipo_documento' => 'DNI', 'documento' => '15217459', 'sexo' => 'F', 'status' => 'inactive',
            'headquarter_id' => 1, 'asesor_id' => $user->id,
        ]);
    }

    private function llenar(Testable $comp): Testable
    {
        return $comp->set('seletipl', '1')->set('impopres', 1000)->set('cuot', 4)->set('inte', 10)->set('nomasesores', 'cesado-tester');
    }

    public function test_al_buscar_el_dni_de_un_cesado_avisa_y_no_deja_guardar_hasta_darlo_de_alta(): void
    {
        $comp = $this->llenar(Livewire::test(Create::class)->set('codigoc', '15217459'))
            ->assertSet('dniMsgType', 'err')
            ->assertSee(self::AVISO)
            ->call('save')
            ->assertHasErrors('codigoc');
        $this->assertSame(0, Credit::count());

        $this->client->update(['status' => 'active']);
        $comp->call('save')->assertHasNoErrors();
        $this->assertSame(1, Credit::where('client_id', $this->client->id)->count());
    }

    public function test_la_pestana_prestamos_de_un_cesado_no_ofrece_nuevo_prestamo_y_manda_a_darlo_de_alta(): void
    {
        $html = Livewire::test(Prestamos::class, ['id' => $this->client->id])->html();
        $this->assertStringContainsString(self::AVISO, $html);
        $this->assertStringContainsString('Ir a Cliente Cesados', $html);
        $this->assertStringNotContainsString('Nuevo préstamo', $html);

        // El formulario embebido tampoco guarda aunque se llame directo.
        $this->llenar(Livewire::test(Create::class, ['clientId' => $this->client->id, 'embebido' => true]))
            ->assertSee(self::AVISO)
            ->call('save')
            ->assertHasErrors('codigoc');
        $this->assertSame(0, Credit::count());

        $this->client->update(['status' => 'active']);
        $this->assertStringContainsString('Nuevo préstamo', Livewire::test(Prestamos::class, ['id' => $this->client->id])->html());
    }

    public function test_no_se_refinancia_el_credito_de_un_cliente_cesado(): void
    {
        $this->client->update(['status' => 'active']);
        $credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-10-01', 'importe' => 1000, 'cuotas' => 1,
            'tipo_planilla' => 3, 'interes' => 5, 'interes_total' => 50, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2026-11-01',
            'importe_cuota' => 1000, 'importe_interes' => 50, 'importe_aplicado' => 0, 'interes_aplicado' => 50, 'pagado' => 0,
        ]);
        $this->client->update(['status' => 'inactive']);

        Livewire::test(Refinance::class, ['creditId' => $credit->id])
            ->assertSee(self::AVISO)
            ->assertDontSeeHtml('<i class="ti ti-coin"></i> Refinanciar')
            ->set('nomasesores', 'Licet')
            ->call('refinance')
            ->assertHasErrors('refinance');
        $this->assertNull(Credit::where('idcan', $credit->id)->first());
    }
}
