<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
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
 * Días de mora = calendario corrido para TODOS los tipos (regla única,
 * 02/09). Antes semanal/diario saltaban sábados y domingos (lun-vie, como
 * el legacy); mensual siempre contó corrido. Decisión de Antony: unificar.
 */
class MoraDiasCalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::factory()->create(['username' => 'mora-cal-tester', 'headquarter_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** Crédito SEMANAL con una cuota vencida hace exactamente 14 días calendario. */
    private function semanalVencido(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Mora Semanal']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(21)->format('Y-m-d'),
            'importe' => 700, 'cuotas' => 1, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 70, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subDays(14)->format('Y-m-d'),
            'importe_cuota' => 700, 'importe_interes' => 70, 'pagado' => 0,
        ]);

        return $credit->refresh();
    }

    public function test_semanal_cuenta_dias_calendario_incluyendo_fines_de_semana(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->semanalVencido();

        // 14 días calendario SIEMPRE incluyen 4 días de fin de semana: la
        // regla vieja (lun-vie) habría contado 10. Tarifa semanal: 5% de la
        // cuota (770) ÷ 7 = 5.50/día → mora calculada 77.00.
        $calc = round(round(770 * 5 / 100 / 7, 2) * 14, 2);
        $this->assertEqualsWithDelta(77.00, $calc, 0.01);

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 50)
            ->set('moraManual', 10)
            ->set('moraMotivo', 'Test regla calendario')
            ->call('pagar');

        $ov = DB::table('mora_overrides')->where('credit_id', $credit->id)->first();
        $this->assertNotNull($ov, 'debe existir el registro del ajuste');
        $this->assertSame(14, (int) $ov->dias_atraso,
            'semanal cuenta calendario corrido (la regla lun-vie daría 10)');
        $this->assertEqualsWithDelta($calc, (float) $ov->mora_calculada, 0.01);
    }
}
