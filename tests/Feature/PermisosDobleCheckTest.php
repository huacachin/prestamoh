<?php

namespace Tests\Feature;

use App\Livewire\Credits\Activate;
use App\Livewire\Credits\Edit as CreditsEdit;
use App\Livewire\Credits\MassDeleteEdit;
use App\Models\Client;
use App\Models\Credit;
use App\Models\MassDeletion;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Doble-check de permisos (21/08): exports gateados por módulo, detalle de
 * cliente escopado a la cartera del analista, y acciones Livewire re-validadas
 * (destroy/update de créditos, activate).
 */
class PermisosDobleCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function analistaConClienteAjeno(): array
    {
        $analista = User::factory()->create(['username' => 'ana-dc']);
        $analista->assignRole('analista-creditos');
        $otro = User::factory()->create(['username' => 'otro-dc']);
        $ajeno = Client::create(['nombre' => 'Cliente Ajeno DC', 'asesor_id' => $otro->id]);

        return [$analista, $ajeno];
    }

    public function test_exports_exigen_el_permiso_del_modulo(): void
    {
        [$analista] = $this->analistaConClienteAjeno();
        $this->actingAs($analista);

        // Sin permiso de caja ni morosidad: los Excel globales quedan cerrados
        $this->get('/exports/incomes')->assertForbidden();
        $this->get('/exports/expenses')->assertForbidden();
        $this->get('/exports/reports/delinquent')->assertForbidden();
        $this->get('/exports/reports/cash-general-1')->assertForbidden();
        $this->get('/exports/reports/payments')->assertForbidden();
        $this->get('/exports/concepts')->assertForbidden();
    }

    public function test_detalle_de_cliente_ajeno_cerrado_para_el_analista(): void
    {
        [$analista, $ajeno] = $this->analistaConClienteAjeno();
        $this->actingAs($analista);

        $this->get("/clients/{$ajeno->id}")->assertForbidden();
        $this->get("/clients/{$ajeno->id}/gallery")->assertForbidden();
        $this->get("/clients/{$ajeno->id}/aval")->assertForbidden();
        $this->get("/clients/{$ajeno->id}/edit")->assertForbidden();
        $this->get("/exports/clients/{$ajeno->id}/history")->assertForbidden();
    }

    /**
     * 05/09: el administrador SÍ elimina créditos, pero solo los que
     * registró ÉL MISMO y del día (sin pagos y no refinanciados). Lo ajeno
     * y lo histórico son del director.
     */
    public function test_destroy_de_credito_exige_permiso_y_dia(): void
    {
        $admin = User::factory()->create(['username' => 'adm-dc']);
        $admin->assignRole('administrador');
        $cliente = Client::create(['nombre' => 'Cliente DC', 'asesor_id' => $admin->id]);
        $credito = fn (string $fecha, ?int $dueno = null) => Credit::create([
            'client_id' => $cliente->id, 'fecha_prestamo' => $fecha, 'importe' => 1000,
            'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 100,
            'situacion' => 'Activo', 'estado' => 1, 'user_id' => $dueno ?? $admin->id,
        ]);

        $this->actingAs($admin);

        // El del DÍA sí se elimina.
        $hoy = $credito(now()->format('Y-m-d'));
        Livewire::test(CreditsEdit::class, ['id' => $hoy->id])->call('destroy', $hoy->id);
        // destroy es borrado LÓGICO: marca la situación, no borra la fila.
        $this->assertSame('Eliminado', $hoy->fresh()->situacion, 'el administrador elimina el crédito del día');

        // El de AYER rebota.
        $ayer = $credito(now()->subDay()->format('Y-m-d'));
        try {
            Livewire::test(CreditsEdit::class, ['id' => $ayer->id])->call('destroy', $ayer->id);
        } catch (\Throwable) {
            // 403 esperado
        }
        $this->assertSame('Activo', $ayer->fresh()->situacion, 'lo histórico es del director');

        // El de OTRO usuario, aunque sea de hoy, tampoco.
        $otroUser = User::factory()->create(['username' => 'otro-dc']);
        $ajeno = $credito(now()->format('Y-m-d'), $otroUser->id);
        try {
            Livewire::test(CreditsEdit::class, ['id' => $ajeno->id])->call('destroy', $ajeno->id);
        } catch (\Throwable) {
            // 403 esperado
        }
        $this->assertSame('Activo', $ajeno->fresh()->situacion, 'solo se elimina lo registrado por uno mismo');

        // Y sin el permiso, ni el del día.
        $cobra = User::factory()->create(['username' => 'cob-dc']);
        $cobra->assignRole('cobranzas');
        $this->actingAs($cobra);
        $otro = $credito(now()->format('Y-m-d'));
        try {
            Livewire::test(CreditsEdit::class, ['id' => $otro->id])->call('destroy', $otro->id);
        } catch (\Throwable) {
            // 403 esperado
        }
        $this->assertSame('Activo', $otro->fresh()->situacion, 'sin creditos.eliminar no se elimina');
    }

    /** La puerta de atrás: marcar "Eliminado" desde la edición sigue la misma regla. */
    public function test_marcar_eliminado_desde_la_edicion_tambien_exige_el_dia(): void
    {
        $admin = User::factory()->create(['username' => 'adm-dc3']);
        $admin->assignRole('administrador');
        $cliente = Client::create(['nombre' => 'Cliente DC3', 'asesor_id' => $admin->id]);
        $viejo = Credit::create([
            'client_id' => $cliente->id, 'fecha_prestamo' => now()->subDays(30)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ]);

        $this->actingAs($admin);
        try {
            Livewire::test(CreditsEdit::class, ['id' => $viejo->id])
                ->set('situacion', 'Eliminado')
                ->call('update');
        } catch (\Throwable) {
            // 403 esperado (tras el abort, Livewire deja el snapshot inservible)
        }
        $this->assertSame('Activo', $viejo->fresh()->situacion);
    }

    public function test_update_de_credito_no_backdatea_sin_bypass(): void
    {
        $admin = User::factory()->create(['username' => 'adm-dc2']);
        $admin->assignRole('administrador');
        $cliente = Client::create(['nombre' => 'Cliente DC2', 'asesor_id' => $admin->id]);
        $credit = Credit::create([
            'client_id' => $cliente->id, 'fecha_prestamo' => now()->format('Y-m-d'), 'importe' => 1000,
            'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 100,
            'situacion' => 'Activo', 'estado' => 1,
        ]);

        $this->actingAs($admin);
        Livewire::test(CreditsEdit::class, ['id' => $credit->id])
            ->set('fecha_prestamo', now()->subDays(20)->format('Y-m-d'))
            ->call('update');

        $this->assertSame(now()->format('Y-m-d'), $credit->fresh()->fecha_prestamo?->format('Y-m-d'),
            'sin bypass la fecha del crédito no se toca');
    }

    public function test_reverse_masivo_solo_del_dia_para_administrador(): void
    {
        $admin = User::factory()->create(['username' => 'adm-rev']);
        $admin->assignRole('administrador');
        $this->actingAs($admin);

        $hoy = MassDeletion::create([
            'credit_id' => null, 'amount' => 100,
            'date' => now()->format('Y-m-d'), 'time' => '10:00:00', 'user' => 'adm-rev',
        ]);
        $ayer = MassDeletion::create([
            'credit_id' => null, 'amount' => 100,
            'date' => now()->subDays(3)->format('Y-m-d'), 'time' => '10:00:00', 'user' => 'adm-rev',
        ]);

        // Histórico: bloqueado (el registro sobrevive)
        Livewire::test(MassDeleteEdit::class, ['id' => $ayer->id])
            ->call('reverse');
        $this->assertNotNull($ayer->fresh(), 'cobro histórico NO se revierte sin editar-historico');

        // Del día: procede (el registro desaparece)
        Livewire::test(MassDeleteEdit::class, ['id' => $hoy->id])
            ->call('reverse');
        $this->assertNull($hoy->fresh(), 'cobro del día SÍ se revierte');

        // Director: lo histórico sigue abierto
        $director = User::factory()->create(['username' => 'dir-rev']);
        $director->assignRole('director');
        $this->actingAs($director);
        Livewire::test(MassDeleteEdit::class, ['id' => $ayer->id])
            ->call('reverse');
        $this->assertNull($ayer->fresh(), 'el director revierte cualquier fecha');
    }

    public function test_activate_no_reactiva_cancelados_antiguos_sin_bypass(): void
    {
        $admin = User::factory()->create(['username' => 'adm-dc3']);
        $admin->assignRole('administrador');
        $cliente = Client::create(['nombre' => 'Cliente DC3', 'asesor_id' => $admin->id]);
        $credit = Credit::create([
            'client_id' => $cliente->id, 'fecha_prestamo' => now()->subMonths(3)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 10, 'interes_total' => 100,
            'situacion' => 'Cancelado', 'estado' => 1,
            'fecha_cancelacion' => now()->subMonths(2)->format('Y-m-d'),
        ]);

        $this->actingAs($admin);
        Livewire::test(Activate::class)
            ->set('selectedId', $credit->id)
            ->call('activate');

        $this->assertSame('Cancelado', $credit->fresh()->situacion,
            'cancelado hace 2 meses: el admin (sin bypass) no puede re-activarlo');
    }
}
