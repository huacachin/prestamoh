<?php

namespace Tests\Feature;

use App\Livewire\Clients\Index as ClientsIndex;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\MorosidadClientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El filtro de morosidad de /clients (al día / 2 / 3 / 4+ / ejecución) ahora
 * también llega al Excel: pantalla y export comparten el cálculo
 * (App\Support\MorosidadClientes), así que cuadran por construcción.
 */
class ClientesMorosidadExcelTest extends TestCase
{
    use RefreshDatabase;

    private Client $alDia;

    private Client $rojo;

    private Client $ejecucion;

    protected function setUp(): void
    {
        parent::setUp();

        // La vista arma el select de asesores con este permiso.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tester = User::factory()->create(['username' => 'tester']);
        $tester->givePermissionTo(Permission::findOrCreate('clientes', 'web'));
        $this->actingAs($tester);

        $this->alDia = $this->cliente('Aldia Perez', vencidas: 0);
        $this->rojo = $this->cliente('Rojo Gomez', vencidas: 3);
        $this->ejecucion = $this->cliente('Ejecucion Diaz', vencidas: 5, zona: 'SIGM.S-Ejecucion 08/04');
    }

    private function cliente(string $nombre, int $vencidas, ?string $zona = null): Client
    {
        $client = Client::create(['nombre' => $nombre, 'zona' => $zona, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-01-01',
            'importe' => 1000, 'cuotas' => 8, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach (range(1, 8) as $n) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                // Las primeras $vencidas cuotas quedan vencidas e impagas.
                'fecha_vencimiento' => $n <= $vencidas
                    ? now()->subWeeks($vencidas - $n + 1)->format('Y-m-d')
                    : now()->addWeeks($n)->format('Y-m-d'),
                'importe_cuota' => 125, 'importe_interes' => 12.50, 'pagado' => 0,
            ]);
        }

        return $client;
    }

    public function test_el_helper_clasifica_los_tres_niveles(): void
    {
        $ids = [$this->alDia->id, $this->rojo->id, $this->ejecucion->id];
        ['morosidad' => $m, 'enEjecucion' => $e] = MorosidadClientes::calcular($ids);

        $this->assertSame([$this->alDia->id], MorosidadClientes::idsDelNivel($ids, 'aldia', $m, $e));
        $this->assertSame([$this->rojo->id], MorosidadClientes::idsDelNivel($ids, 'rojo', $m, $e));
        // En ejecución NO cuenta como crítico aunque tenga 5 vencidas.
        $this->assertSame([], MorosidadClientes::idsDelNivel($ids, 'critico', $m, $e));
        $this->assertSame([$this->ejecucion->id], MorosidadClientes::idsDelNivel($ids, 'ejecucion', $m, $e));
    }

    public function test_el_excel_respeta_el_filtro_de_morosidad(): void
    {
        $respuesta = $this->get(route('exports.clients', ['estado' => 'rojo']));

        $respuesta->assertOk();
        $respuesta->assertSee('Rojo Gomez');
        $respuesta->assertDontSee('Aldia Perez');
        $respuesta->assertDontSee('Ejecucion Diaz');
    }

    public function test_el_excel_sin_estado_trae_todos(): void
    {
        $respuesta = $this->get(route('exports.clients'));

        $respuesta->assertOk();
        $respuesta->assertSee('Rojo Gomez');
        $respuesta->assertSee('Aldia Perez');
        $respuesta->assertSee('Ejecucion Diaz');
    }

    public function test_la_pantalla_sigue_filtrando_igual_tras_el_refactor(): void
    {
        $nombres = fn (string $estado) => Livewire::withQueryParams(['estado' => $estado])
            ->test(ClientsIndex::class)->viewData('clients')->pluck('nombre')->all();

        $this->assertSame(['Rojo Gomez'], $nombres('rojo'));
        $this->assertSame(['Ejecucion Diaz'], $nombres('ejecucion'));
        $this->assertSame(['Aldia Perez'], $nombres('aldia'));
    }
}
