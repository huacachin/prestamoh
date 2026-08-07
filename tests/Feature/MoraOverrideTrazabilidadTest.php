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
 * Trazabilidad del ajuste manual de mora: ajustar el Total Mora a mano exige
 * motivo y deja registro en `mora_overrides` (calculada, cobrada, diferencia,
 * motivo, responsable).
 */
class MoraOverrideTrazabilidadTest extends TestCase
{
    use RefreshDatabase;

    private function actor(bool $conPermiso = true): User
    {
        // El componente cae a headquarter_id = 1 si el usuario no tiene sede.
        // insert directo: `id` no es fillable en el modelo.
        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::factory()->create(['username' => 'tester', 'headquarter_id' => 1]);
        if ($conPermiso) {
            $user->givePermissionTo(Permission::findOrCreate('pagos.mora-manual', 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /** Crédito mensual con una cuota vencida hace 40 días e impaga. */
    private function creditoVencido(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente Mora']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subMonths(3)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->subDays(40)->format('Y-m-d'),
            'importe_cuota' => 1000, 'importe_interes' => 100, 'pagado' => 0,
        ]);

        return $credit->refresh();
    }

    public function test_ajustar_la_mora_sin_motivo_no_deja_cobrar(): void
    {
        $this->actingAs($this->actor());
        $credit = $this->creditoVencido();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->set('moraManual', 5)      // muy por debajo de la calculada
            ->set('moraMotivo', '')     // sin motivo
            ->call('pagar');

        // No se registró nada.
        $this->assertDatabaseCount('mora_overrides', 0);
        $this->assertSame(0, DB::table('payments')->where('credit_id', $credit->id)->count());
        $this->assertStringContainsString('motivo', strtolower(json_encode($comp->effects) ?: ''));
    }

    public function test_ajuste_con_motivo_queda_registrado(): void
    {
        $user = $this->actor();
        $this->actingAs($user);
        $credit = $this->creditoVencido();

        // Mora calculada esperada: cuota (1000+100) * 5% / 30 = 1.83/día × 40 = 73.20
        $calc = round(round(1100 * 5 / 100 / 30, 2) * 40, 2);
        $this->assertEqualsWithDelta(73.20, $calc, 0.01);

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->set('moraManual', 20)
            ->set('moraMotivo', 'Acuerdo con el cliente')
            ->call('pagar');

        $ov = DB::table('mora_overrides')->where('credit_id', $credit->id)->first();
        $this->assertNotNull($ov, 'debe existir el registro del ajuste');
        $this->assertEqualsWithDelta($calc, (float) $ov->mora_calculada, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $ov->mora_cobrada, 0.01);
        $this->assertEqualsWithDelta(20.0 - $calc, (float) $ov->diferencia, 0.01);
        $this->assertSame('Acuerdo con el cliente', $ov->motivo);
        $this->assertSame($user->id, (int) $ov->user_id);
        $this->assertSame(40, (int) $ov->dias_atraso);
        // Queda enlazado al pago de mora que se generó.
        $this->assertNotNull($ov->payment_id);

        // Y deja rastro en la auditoría.
        $this->assertDatabaseHas('activity_log', ['log_name' => 'auditoria']);
    }

    public function test_mora_perdonada_entera_tambien_queda_registrada(): void
    {
        // El caso que más importa auditar: mora a 0 → no hay pago MORA que
        // mirar, así que sin bitácora el ajuste sería invisible.
        $this->actingAs($this->actor());
        $credit = $this->creditoVencido();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->set('moraManual', 0)
            ->set('moraMotivo', 'Condonada por gerencia')
            ->call('pagar');

        $ov = DB::table('mora_overrides')->where('credit_id', $credit->id)->first();
        $this->assertNotNull($ov, 'perdonar toda la mora también debe registrarse');
        $this->assertEqualsWithDelta(0.0, (float) $ov->mora_cobrada, 0.01);
        $this->assertNull($ov->payment_id, 'no hay pago MORA cuando se perdona entera');
        $this->assertSame('Condonada por gerencia', $ov->motivo);
        $this->assertSame(0, DB::table('payments')->where('credit_id', $credit->id)->where('tipo', 'MORA')->count());
    }

    public function test_sin_ajuste_no_se_registra_nada(): void
    {
        // Dejar el campo con la mora calculada (o no tocarlo) NO es un ajuste.
        $this->actingAs($this->actor());
        $credit = $this->creditoVencido();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->call('pagar');

        $this->assertDatabaseCount('mora_overrides', 0);
        // El cobro sí se registró.
        $this->assertGreaterThan(0, DB::table('payments')->where('credit_id', $credit->id)->count());
    }

    public function test_el_modal_de_confirmacion_muestra_el_ajuste(): void
    {
        // El modal es el camino real del cajero: si le faltan las claves del
        // ajuste, revienta al confirmar.
        $this->actingAs($this->actor());
        $credit = $this->creditoVencido();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->set('moraManual', 20)
            ->set('moraMotivo', 'Acuerdo con el cliente')
            ->call('confirmarPago');

        $preview = $comp->get('preview');
        $this->assertTrue($preview['mora_ajustada']);
        $this->assertEqualsWithDelta(20.0, (float) $preview['mora_ajuste_final'], 0.01);
        $this->assertEqualsWithDelta(73.20, (float) $preview['mora_calculada'], 0.01);
        $this->assertSame('Acuerdo con el cliente', $preview['mora_motivo']);

        // Y la vista con el modal abierto se renderiza sin romper.
        $comp->assertSee('Mora ajustada a mano');
    }

    public function test_usuario_sin_permiso_no_puede_ajustar(): void
    {
        $this->actingAs($this->actor(conPermiso: false));
        $credit = $this->creditoVencido();

        Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 100)
            ->set('moraManual', 1)   // intento de inyección desde el front
            ->call('pagar');

        $this->assertDatabaseCount('mora_overrides', 0);
        // La mora cobrada debe ser la calculada, no el 1 inyectado.
        $mora = (float) DB::table('payments')->where('credit_id', $credit->id)
            ->where('tipo', 'MORA')->sum('monto');
        $this->assertGreaterThan(1.0, $mora);
    }
}
