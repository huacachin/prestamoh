<?php

namespace Tests\Feature;

use App\Livewire\Reports\Payments;
use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 23/10 (Antony): el reporte de pagos se colgaba al cambiar la fecha: los
 * filtros eran live (cada tecla consultaba y redibujaba) y un "desde" vacío
 * o a medias abría la tabla a toda la historia. Ahora los filtros se aplican
 * con Buscar/Enter, una fecha inválida nunca deja el rango abierto y sin
 * filtro por crédito/cliente el rango se limita a 3 meses.
 */
class ReportePagosRangoSeguroTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-10-23';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->travelTo(self::HOY.' 10:00:00');
        $this->actingAs(User::factory()->create(['username' => 'rep-rango', 'headquarter_id' => 1]));
    }

    /** Un pago por cada fecha dada, todos del mismo crédito. */
    private function pagos(array $fechas): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Rango', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2025-01-01', 'importe' => 1000, 'cuotas' => 48,
            'tipo_planilla' => 1, 'interes' => 60, 'interes_total' => 600, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        foreach ($fechas as $f) {
            DB::table('payments')->insert([
                'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => 'CAPITAL', 'documento' => 'CAPITAL',
                'fecha' => $f.' 00:00:00', 'hora' => '09:00:00', 'monto' => 10, 'detalle' => "Pago $f",
                'usuario' => 'Licet', 'asesor' => 'Guilmer', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $credit;
    }

    public function test_un_desde_vacio_o_a_medias_no_abre_toda_la_historia(): void
    {
        $this->pagos(['2020-01-15', '2026-10-01', self::HOY]);

        foreach (['', '2026-1', 'abc', null] as $desde) {
            $c = Livewire::test(Payments::class)->set('fei', $desde)->set('fef', self::HOY)->call('search');

            $this->assertSame(self::HOY, $c->viewData('desdeEfectivo'), "desde inválido ('{$desde}') cae al mismo día que hasta");
            $this->assertCount(1, $c->viewData('rows'), 'solo el pago de hoy, no los de 2020');
        }
    }

    public function test_un_hasta_invalido_cae_a_hoy_y_las_fechas_cruzadas_se_ordenan(): void
    {
        $this->pagos(['2026-10-01', '2026-10-10', self::HOY]);

        $c = Livewire::test(Payments::class)->set('fei', '2026-10-01')->set('fef', '')->call('search');
        $this->assertSame(self::HOY, $c->viewData('hastaEfectivo'));
        $this->assertCount(3, $c->viewData('rows'));

        $c = Livewire::test(Payments::class)->set('fei', self::HOY)->set('fef', '2026-10-10')->call('search');
        $this->assertSame(['2026-10-10', self::HOY], [$c->viewData('desdeEfectivo'), $c->viewData('hastaEfectivo')], 'cruzadas: se intercambian');
    }

    public function test_sin_filtro_el_rango_se_limita_a_tres_meses(): void
    {
        $credit = $this->pagos(['2026-01-10', '2026-07-01', self::HOY]);

        $c = Livewire::test(Payments::class)->set('fei', '2025-01-01')->set('fef', self::HOY)->call('search');
        $this->assertSame('2026-07-23', $c->viewData('desdeEfectivo'), '92 días antes del hasta');
        $this->assertSame('2025-01-01', $c->get('fei'), 'lo escrito por el usuario no se pisa');
        $this->assertTrue($c->viewData('rangoRecortado'));
        $this->assertCount(1, $c->viewData('rows'), 'solo el de hoy cae dentro');
        $c->assertSee('Rango limitado a 3 meses');

        // Con filtro por crédito (el enlace del cronograma trae "desde la fecha del préstamo") no se recorta.
        $c = Livewire::test(Payments::class)->set('creditoId', (string) $credit->id)->set('fei', '2025-01-01')->set('fef', self::HOY)->call('search');
        $this->assertSame('2025-01-01', $c->viewData('desdeEfectivo'));
        $this->assertFalse($c->viewData('rangoRecortado'));
        $this->assertCount(3, $c->viewData('rows'));
        $c->assertDontSee('Rango limitado');
    }

    public function test_los_filtros_no_son_live_y_buscar_los_aplica(): void
    {
        $this->pagos(['2026-10-01', self::HOY]);

        $c = Livewire::test(Payments::class);
        $c->assertDontSeeHtml('wire:model.live')
            ->assertSeeHtml('wire:model="fei" wire:keydown.enter="search"')
            ->assertSeeHtml('wire:model="compra" wire:keydown.enter="search"')
            ->assertSeeHtml('wire:click="search"');

        $this->assertCount(1, $c->viewData('rows'), 'al abrir: hoy');
        $c->set('fei', '2026-10-01')->call('search');
        $this->assertCount(2, $c->viewData('rows'), 'tras Buscar: el rango nuevo');
    }
}
