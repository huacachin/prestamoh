<?php

namespace Tests\Feature;

use App\Livewire\Payments\Refinance;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El interés MOSTRADO en /payments/refinance debe salir del capital del crédito
 * nuevo (el saldo), igual que el que se graba. Antes se copiaba el
 * importe_interes de la última cuota del crédito viejo (= tasa sobre el capital
 * ANTERIOR), así que si el cliente había abonado a capital la pantalla anunciaba
 * un interés mayor que el del crédito que se creaba.
 */
class RefinanceInteresMostradoTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // La vista arma el select de asesores con este permiso.
        Permission::findOrCreate('creditos.ser-asesor-responsable', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return User::factory()->create(['username' => 'tester', 'headquarter_id' => 1]);
    }

    /**
     * Crédito mensual de 1 cuota, tal como el 29276: capital 1000 al 5%
     * (interés 50), con $capitalPagado abonado a capital y el interés pagado.
     */
    private function credito(float $capitalPagado): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Refi']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-07-20',
            'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5,
            'interes_total' => 50, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => '2026-08-20',
            'importe_cuota' => 1000, 'importe_interes' => 50,
            'importe_aplicado' => $capitalPagado, 'interes_aplicado' => 50, 'pagado' => 0,
        ]);

        return $credit->refresh();
    }

    public function test_con_abono_a_capital_el_interes_mostrado_sale_del_saldo(): void
    {
        // Escenario real del crédito 29276: se abonaron 150 a capital.
        $this->actingAs($this->actor());
        $credit = $this->credito(capitalPagado: 150);

        $comp = Livewire::test(Refinance::class, ['creditId' => $credit->id]);

        // Capital nuevo = saldo = 1000 + 50 - 150 - 50 = 850
        $this->assertEqualsWithDelta(850.00, (float) $comp->get('impopres'), 0.01);
        // Interés mostrado = 5% de 850 = 42.50 (antes mostraba 50.00)
        $this->assertEqualsWithDelta(42.50, (float) $comp->get('intmont'), 0.01);
        $comp->assertSee('42.50');
        // Nota: en la página aparece 900.00, pero es la columna "Saldo" del
        // cronograma viejo —que por homologación del legacy no resta el interés
        // aplicado ((1000+50)−150)—, no el interés del refi.
    }

    public function test_el_interes_mostrado_coincide_con_el_que_se_graba(): void
    {
        // La garantía de fondo: pantalla y persistencia usan la misma fórmula.
        $this->actingAs($this->actor());
        $credit = $this->credito(capitalPagado: 150);

        $comp = Livewire::test(Refinance::class, ['creditId' => $credit->id]);
        $mostrado = round((float) $comp->get('intmont'), 2);

        $comp->set('nomasesores', 'Licet')->call('refinance');

        $nuevo = Credit::where('idcan', $credit->id)->first();
        $this->assertNotNull($nuevo, 'debe haberse creado el crédito refinanciado');
        $this->assertEqualsWithDelta(850.00, (float) $nuevo->importe, 0.01);
        // interes_total = interés por cuota × cuotas (1) = lo mostrado.
        $this->assertEqualsWithDelta($mostrado, (float) $nuevo->interes_total, 0.01);

        $cuota = CreditInstallment::where('credit_id', $nuevo->id)->first();
        $this->assertEqualsWithDelta($mostrado, (float) $cuota->importe_interes, 0.01);
    }

    public function test_sin_abono_a_capital_el_valor_no_cambia(): void
    {
        // Caso mayoritario (solo se pagó el interés): el saldo sigue siendo el
        // capital, así que el número mostrado es el mismo de antes. El fix no
        // altera lo que el equipo ya venía viendo en la mayoría de refis.
        $this->actingAs($this->actor());
        $credit = $this->credito(capitalPagado: 0);

        $comp = Livewire::test(Refinance::class, ['creditId' => $credit->id]);

        $this->assertEqualsWithDelta(1000.00, (float) $comp->get('impopres'), 0.01);
        $this->assertEqualsWithDelta(50.00, (float) $comp->get('intmont'), 0.01);
    }
}
