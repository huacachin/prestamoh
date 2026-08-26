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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function test_destroy_de_credito_exige_permiso_y_dia(): void
    {
        // El administrador NO tiene creditos.eliminar: destroy debe rebotar
        $admin = User::factory()->create(['username' => 'adm-dc']);
        $admin->assignRole('administrador');
        $cliente = Client::create(['nombre' => 'Cliente DC', 'asesor_id' => $admin->id]);
        $credit = Credit::create([
            'client_id' => $cliente->id, 'fecha_prestamo' => now()->format('Y-m-d'), 'importe' => 1000,
            'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 100,
            'situacion' => 'Activo', 'estado' => 1,
        ]);

        $this->actingAs($admin);
        try {
            Livewire::test(CreditsEdit::class, ['id' => $credit->id])
                ->call('destroy', $credit->id);
        } catch (AuthorizationException|HttpException) {
            // 403 esperado
        }
        $this->assertSame('Activo', $credit->fresh()->situacion, 'sin creditos.eliminar no se elimina');
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
