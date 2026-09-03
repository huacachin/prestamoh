<?php

namespace Tests\Feature;

use App\Livewire\Reports\Advisor;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El reporte de asesor renderiza sin errores de SQL (01/09).
 *
 * El fix del cronograma (52bf66a) pasó "IMP. A COBRAR" de fecha_pago a
 * fecha_vencimiento pero dejó el GROUP BY con la columna vieja: MySQL en
 * modo only_full_group_by tiraba 1055 y la página moría con 500. Nadie lo
 * notó en 6 días porque ningún test rendía el componente.
 */
class ReporteAsesorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Headquarter::create(['name' => 'Sede Asesor', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'asesor-tester', 'headquarter_id' => $sede->id]));

        $client = Client::create([
            'expediente' => '9996', 'nombre' => 'ASESOR', 'apellido_pat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => '49000002', 'sexo' => 'M',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-01',
            'importe' => 1000, 'cuotas' => 2, 'tipo_planilla' => 3, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        // Una cuota que vence en agosto pero se pagó en septiembre: si el
        // GROUP BY vuelve a desalinearse de fecha_vencimiento, este dato
        // dispara el 1055 (o agruparía por el mes equivocado).
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => '2026-08-15', 'fecha_pago' => '2026-09-03',
            'importe_cuota' => 500, 'importe_interes' => 100, 'pagado' => true,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 2,
            'fecha_vencimiento' => '2026-08-30', 'fecha_pago' => null,
            'importe_cuota' => 500, 'importe_interes' => 100, 'pagado' => false,
        ]);
    }

    public function test_renderiza_y_el_imp_a_cobrar_agrupa_por_vencimiento(): void
    {
        Livewire::test(Advisor::class)
            ->set('selemes', '08')->set('selecano', '2026')
            ->assertOk()
            // 500 + 100 de la cuota del día 15: contada en agosto (su
            // vencimiento) aunque el pago real cayó en septiembre.
            ->assertSeeHtml('600');
    }

    public function test_renderiza_el_mes_actual_sin_datos(): void
    {
        Livewire::test(Advisor::class)->assertOk();
    }
}
