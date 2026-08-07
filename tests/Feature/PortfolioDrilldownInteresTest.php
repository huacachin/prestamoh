<?php

namespace Tests\Feature;

use App\Livewire\Reports\Portfolio;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Drill-down de "CRÉDITO · por % de interés": la columna Cnt. enlaza al propio
 * reporte filtrado por esa tasa. Lo que se prueba es el contrato del enlace —
 * que el número de la celda sea EXACTAMENTE el de filas que se ven al entrar—
 * más el filtro "Tipo", que estaba declarado pero nunca se aplicaba.
 */
class PortfolioDrilldownInteresTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::factory()->create(['username' => 'tester', 'headquarter_id' => 1]);
    }

    private function credito(float $interes, int $tipoPlanilla = 3, string $situacion = 'Activo'): Credit
    {
        $client = Client::create(['nombre' => 'Cliente '.uniqid()]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-07-01',
            'fecha_actualizacion' => '2026-07-01',
            'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => $tipoPlanilla,
            'interes' => $interes, 'interes_total' => 1000 * $interes / 100,
            'situacion' => $situacion, 'estado' => 1,
            'fecha_vencimiento' => '2026-08-01',
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2026-08-01',
            'importe_cuota' => 1000, 'importe_interes' => 1000 * $interes / 100, 'pagado' => 0,
        ]);

        return $credit;
    }

    public function test_el_conteo_de_la_celda_coincide_con_el_detalle_filtrado(): void
    {
        $this->actingAs($this->actor());

        // 3 al 10%, 2 al 5%.
        foreach ([10, 10, 10, 5, 5] as $t) {
            $this->credito($t);
        }

        // Sin filtro: la tabla resumen reporta 3 y 2.
        $sinFiltro = Livewire::test(Portfolio::class);
        $byInteres = collect($sinFiltro->viewData('byInteres'))->keyBy('porce');
        $this->assertSame(3, $byInteres['10']['ncount']);
        $this->assertSame(2, $byInteres['5']['ncount']);
        $this->assertCount(5, $sinFiltro->viewData('rows'));

        // Con el drill-down: el detalle trae exactamente lo que decía la celda.
        $filtrado = Livewire::withQueryParams(['interes' => '10'])->test(Portfolio::class);
        $this->assertCount(3, $filtrado->viewData('rows'), 'el detalle debe traer los 3 créditos al 10%');

        $filtrado5 = Livewire::withQueryParams(['interes' => '5'])->test(Portfolio::class);
        $this->assertCount(2, $filtrado5->viewData('rows'));
    }

    public function test_la_tasa_casa_aunque_se_guarde_con_decimales(): void
    {
        // credits.interes es decimal(8,4): 5 debe casar con 5.0000, que es la
        // misma equivalencia con la que la tabla agrupa ((string)(float)).
        $this->actingAs($this->actor());
        $this->credito(5);
        $this->credito(8.5);

        $this->assertCount(1, Livewire::withQueryParams(['interes' => '5'])->test(Portfolio::class)->viewData('rows'));
        $this->assertCount(1, Livewire::withQueryParams(['interes' => '8.5'])->test(Portfolio::class)->viewData('rows'));
    }

    public function test_el_drilldown_respeta_los_demas_filtros(): void
    {
        // Si el reporte venía acotado por periodo, el enlace lo arrastra y el
        // detalle no puede traer créditos de fuera de ese periodo.
        $this->actingAs($this->actor());

        $dentro = $this->credito(10);
        $fuera = $this->credito(10);
        DB::table('credits')->where('id', $fuera->id)->update(['fecha_actualizacion' => '2026-01-15']);

        $comp = Livewire::withQueryParams(['interes' => '10', 'mes' => '07', 'anio' => '2026'])->test(Portfolio::class);
        $rows = collect($comp->viewData('rows'));

        $this->assertCount(1, $rows, 'solo el crédito del periodo filtrado');
        $this->assertSame($dentro->id, (int) $rows->first()['codigo']);
    }

    public function test_el_filtro_tipo_ahora_si_aplica(): void
    {
        // Estaba declarado (#[Url as:'tipo']) y pintado, pero nunca llegaba a
        // la query: elegirlo no cambiaba nada.
        $this->actingAs($this->actor());
        $this->credito(10, tipoPlanilla: 3);   // Mensual
        $this->credito(10, tipoPlanilla: 1);   // Semanal
        $this->credito(10, tipoPlanilla: 1);

        $this->assertCount(3, Livewire::test(Portfolio::class)->viewData('rows'));
        $this->assertCount(1, Livewire::withQueryParams(['tipo' => '3'])->test(Portfolio::class)->viewData('rows'));
        $this->assertCount(2, Livewire::withQueryParams(['tipo' => '1'])->test(Portfolio::class)->viewData('rows'));
        // '0000' = Todos (igual que /credits), no debe filtrar.
        $this->assertCount(3, Livewire::withQueryParams(['tipo' => '0000'])->test(Portfolio::class)->viewData('rows'));
    }

    public function test_los_cancelados_siguen_fuera(): void
    {
        $this->actingAs($this->actor());
        $this->credito(10);
        $this->credito(10, situacion: 'Cancelado');

        $this->assertCount(1, Livewire::withQueryParams(['interes' => '10'])->test(Portfolio::class)->viewData('rows'));
    }
}
