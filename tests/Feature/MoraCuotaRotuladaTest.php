<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use App\Support\RotuloCuotas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pantalla de cobro (17/10/2026, pedido de Antony): el "Total Mora" es la
 * mora de UNA cuota —la impaga más antigua—, no la suma de todas las
 * vencidas, y el usuario podía creer lo contrario. La banda de Atraso dice
 * ahora de qué cuota es ("calculada · cuota 1", "días de atraso · cuota 1")
 * y los chips de mora acumulada aclaran que son "de cuotas ya pagadas".
 */
class MoraCuotaRotuladaTest extends TestCase
{
    use RefreshDatabase;

    private function actor(bool $conPermiso): User
    {
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['username' => 'rotula-mora', 'headquarter_id' => 1]);
        if ($conPermiso) {
            $user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** Mensual: cuota 1 vencida hace 40 días, cuota 2 hace 10, cuota 3 por vencer. */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Mora']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subMonths(3)->format('Y-m-d'),
            'importe' => 3000, 'cuotas' => 3, 'tipo_planilla' => 3, 'interes' => 10,
            'interes_total' => 300, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        foreach ([1 => -40, 2 => -10, 3 => 20] as $n => $dias) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n,
                'fecha_vencimiento' => now()->addDays($dias)->format('Y-m-d'),
                'importe_cuota' => 1000, 'importe_interes' => 100, 'pagado' => 0,
            ]);
        }

        return $credit->refresh();
    }

    public function test_la_banda_de_atraso_dice_de_que_cuota_es_la_mora(): void
    {
        $this->actingAs($this->actor(conPermiso: false));
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id]);
        $html = $comp->html();

        // Mora de la cuota 1 solamente: 40 días × 1.83 (1100 × 5 % ÷ 30) = 73.20,
        // no la suma con los 10 días de la cuota 2.
        $comp->assertSee('73.20')
            ->assertDontSee('91.50')
            ->assertSee('días de atraso · cuota 1')
            ->assertSee('calculada · cuota 1')
            ->assertSee('de cuotas ya pagadas');
        $this->assertStringContainsString('Mora de la cuota 1 (la impaga más antigua, vencida el '.now()->subDays(40)->format('d/m/Y').')', $html);
        $this->assertStringContainsString('No es la suma de la mora de todas las cuotas vencidas', $html);
    }

    public function test_con_permiso_de_mora_manual_el_rotulo_tambien_lleva_la_cuota(): void
    {
        $this->actingAs($this->actor(conPermiso: true));
        $credit = $this->credito();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->assertSee('calculada · cuota 1')
            ->set('monto', 100)
            ->assertSee('editable · cuota 1');
    }

    public function test_al_dia_tambien_se_nombra_la_cuota_que_sigue(): void
    {
        $this->actingAs($this->actor(conPermiso: false));
        $credit = $this->credito();
        CreditInstallment::where('credit_id', $credit->id)->whereIn('num_cuota', [1, 2])->update(['pagado' => 1]);

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->assertSee('vence en 20 días · cuota 3')
            ->assertSee('calculada · cuota 3');
    }

    public function test_con_elegir_cuotas_el_rotulo_nombra_las_cuotas_cuya_mora_se_suma(): void
    {
        $this->actingAs($this->actor(conPermiso: true));
        $credit = $this->credito();

        // El modal preselecciona lo vencido (cuotas 1 y 2); aplicar suma sus moras:
        // 40 × 1.83 + 10 × 1.83 = 73.20 + 18.30 = 91.50.
        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->call('aplicarCuotas')
            ->assertSet('moraManual', '91.50')
            ->assertSee('editable · cuotas 1 y 2')
            ->assertDontSee('editable · cuota 1');
        $this->assertStringContainsString('Mora de las cuotas 1 y 2: suma de la mora de cada cuota marcada', $comp->html());
        // Los días transcurridos siguen siendo los de la cuota 1.
        $comp->assertSee('días de atraso · cuota 1');

        // Solo la cuota 1 marcada → vuelve a nombrar una sola.
        $comp->set('moraCuotas.1.sel', false)
            ->call('aplicarCuotas')
            ->assertSet('moraManual', '73.20')
            ->assertSee('editable · cuota 1');

        // Marca de nuevo las dos y luego teclea otro monto: la selección caduca.
        $comp->call('abrirMoraCuotas')->call('aplicarCuotas')
            ->assertSee('editable · cuotas 1 y 2')
            ->set('monto', 50)
            ->assertSee('editable · cuota 1')
            ->assertDontSee('cuotas 1 y 2');
    }

    public function test_sin_permiso_de_mora_manual_elegir_cuotas_no_cambia_el_rotulo(): void
    {
        $this->actingAs($this->actor(conPermiso: false));
        $credit = $this->credito();

        // Sin permiso la mora del cobro sigue siendo la calculada global (cuota 1).
        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->call('abrirMoraCuotas')
            ->call('aplicarCuotas')
            ->assertSee('73.20')
            ->assertSee('calculada · cuota 1')
            ->assertDontSee('cuotas 1 y 2');
    }

    public function test_el_rotulo_de_cuotas(): void
    {
        $this->assertSame('', RotuloCuotas::de([]));
        $this->assertSame('cuota 1', RotuloCuotas::de([1]));
        $this->assertSame('cuotas 1 y 2', RotuloCuotas::de([2, 1]));
        $this->assertSame('cuotas 1 a 4', RotuloCuotas::de([1, 2, 3, 4]));
        $this->assertSame('cuotas 1, 2 y 5', RotuloCuotas::de([1, 2, 5]));
    }
}
