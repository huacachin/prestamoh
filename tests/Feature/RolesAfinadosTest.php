<?php

namespace Tests\Feature;

use App\Livewire\Cash\CreateExpense;
use App\Livewire\Cash\CreateIncome;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Afinado de roles (21/08): director todo y de cualquier día; administrador
 * lo mismo pero SOLO del día; analista-creditos solo VE sus créditos y paga
 * (no crea clientes ni créditos, no refinancia, no ve créditos ajenos).
 */
class RolesAfinadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionCatalogSeeder::class);
        $this->seed(RoleSetupSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrador_pierde_los_poderes_historicos_y_director_los_conserva(): void
    {
        $admin = Role::findByName('administrador');
        $director = Role::findByName('director');

        foreach (['caja.bypass-fecha-anterior', 'caja.editar-historico', 'registro.eliminar-masivo.revertir'] as $p) {
            $this->assertFalse($admin->hasPermissionTo($p), "administrador NO debe tener $p");
            $this->assertTrue($director->hasPermissionTo($p), "director SÍ debe tener $p");
        }
        // Lo del día lo conserva
        foreach (['clientes.eliminar', 'creditos.eliminar', 'caja.eliminar', 'registro.eliminar-masivo'] as $p) {
            $this->assertTrue($admin->hasPermissionTo($p), "administrador debe conservar $p");
        }
    }

    private function analistaConCreditos(): array
    {
        $analista = User::factory()->create(['username' => 'analista']);
        $analista->assignRole('analista-creditos');

        $otroAsesor = User::factory()->create(['username' => 'otro-asesor']);
        $mio = Client::create(['nombre' => 'Cliente Propio', 'asesor_id' => $analista->id]);
        $ajeno = Client::create(['nombre' => 'Cliente Ajeno', 'asesor_id' => $otroAsesor->id]);

        $miCredito = Credit::create([
            'client_id' => $mio->id, 'fecha_prestamo' => now()->format('Y-m-d'), 'importe' => 1000,
            'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 100,
            'situacion' => 'Activo', 'estado' => 1,
        ]);
        $ajenoCredito = Credit::create([
            'client_id' => $ajeno->id, 'fecha_prestamo' => now()->format('Y-m-d'), 'importe' => 2000,
            'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 200,
            'situacion' => 'Activo', 'estado' => 1,
        ]);

        return [$analista, $miCredito, $ajenoCredito];
    }

    public function test_analista_no_crea_clientes_ni_creditos_ni_refinancia(): void
    {
        [$analista, $miCredito] = $this->analistaConCreditos();
        $this->actingAs($analista);

        $this->get('/clients/create')->assertForbidden();
        $this->get('/credits/create')->assertForbidden();
        $this->get("/credits/{$miCredito->id}/edit")->assertForbidden();
        $this->get("/payments/refinance/{$miCredito->id}")->assertForbidden();
    }

    public function test_analista_solo_ve_y_paga_sus_creditos(): void
    {
        [$analista, $miCredito, $ajenoCredito] = $this->analistaConCreditos();
        $this->actingAs($analista);

        // Listado: solo el propio
        $lista = $this->get('/credits');
        $lista->assertOk();
        $lista->assertSee('Cliente Propio');
        $lista->assertDontSee('Cliente Ajeno');

        // Ficha, cronograma y cobro: propio sí, ajeno 403
        $this->get("/credits/{$miCredito->id}")->assertOk();
        $this->get("/credits/{$ajenoCredito->id}")->assertForbidden();
        $this->get("/credits/{$miCredito->id}/schedule")->assertOk();
        $this->get("/credits/{$ajenoCredito->id}/schedule")->assertForbidden();
        $this->get("/payments/create/{$miCredito->id}")->assertOk();
        $this->get("/payments/create/{$ajenoCredito->id}")->assertForbidden();
    }

    /** El administrador VE todos los ingresos/egresos aunque no edite lo histórico. */
    public function test_administrador_ve_el_listado_completo_de_caja(): void
    {
        $sede = Headquarter::create(['name' => 'Principal']);
        $admin = User::factory()->create(['username' => 'adm-caja', 'headquarter_id' => $sede->id]);
        $admin->assignRole('administrador');
        $otro = User::factory()->create(['username' => 'otro-cajero', 'headquarter_id' => $sede->id]);

        DB::table('incomes')->insert([
            'reason' => 'Fijos', 'detail' => 'Ingreso De Otro Usuario Historico', 'total' => 100,
            'date' => now()->subDays(10)->format('Y-m-d'), 'user_id' => $otro->id,
            'headquarter_id' => $sede->id, 'caja' => 1, 'documento' => 'GUIA',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin);
        $this->assertTrue($admin->can('caja.ver-todo'));
        $desde = now()->subDays(15)->format('Y-m-d');
        $hasta = now()->format('Y-m-d');
        $this->get("/cash/incomes?desde={$desde}&hasta={$hasta}")
            ->assertOk()->assertSee('Ingreso De Otro Usuario Historico');
    }

    /** El administrador edita SOLO lo del día (guard de servidor); el director cualquier fecha. */
    public function test_administrador_edita_solo_lo_del_dia_en_caja(): void
    {
        $sede = Headquarter::create(['name' => 'Principal']);
        $admin = User::factory()->create(['username' => 'adm-caja2', 'headquarter_id' => $sede->id]);
        $admin->assignRole('administrador');

        $base = [
            'reason' => 'Fijos', 'detail' => 'x', 'total' => 50,
            'user_id' => $admin->id, 'headquarter_id' => $sede->id, 'caja' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $ingHoy = DB::table('incomes')->insertGetId($base + ['date' => now()->format('Y-m-d'), 'documento' => 'GUIA']);
        $ingAyer = DB::table('incomes')->insertGetId($base + ['date' => now()->subDays(5)->format('Y-m-d'), 'documento' => 'GUIA']);
        $egrHoy = DB::table('expenses')->insertGetId($base + ['date' => now()->format('Y-m-d')]);
        $egrAyer = DB::table('expenses')->insertGetId($base + ['date' => now()->subDays(5)->format('Y-m-d')]);

        $this->actingAs($admin);
        $this->get("/cash/incomes/{$ingHoy}/edit")->assertOk();
        $this->get("/cash/incomes/{$ingAyer}/edit")->assertForbidden();
        $this->get("/cash/expenses/{$egrHoy}/edit")->assertOk();
        $this->get("/cash/expenses/{$egrAyer}/edit")->assertForbidden();

        // Crear con el formulario completo (Fijos y Otros)
        Livewire::test(CreateIncome::class)->assertSet('canChooseOtros', true);
        Livewire::test(CreateExpense::class)->assertSet('canChooseOtros', true);

        // Director: lo histórico sigue abierto
        $director = User::factory()->create(['username' => 'dir-caja', 'headquarter_id' => $sede->id]);
        $director->assignRole('director');
        $this->actingAs($director);
        $this->get("/cash/incomes/{$ingAyer}/edit")->assertOk();
        $this->get("/cash/expenses/{$egrAyer}/edit")->assertOk();
    }

    public function test_director_y_administrador_siguen_creando(): void
    {
        $director = User::factory()->create(['username' => 'dir']);
        $director->assignRole('director');
        $this->actingAs($director);
        $this->get('/clients/create')->assertOk();
        $this->get('/credits/create')->assertOk();

        $admin = User::factory()->create(['username' => 'adm']);
        $admin->assignRole('administrador');
        $this->actingAs($admin);
        $this->get('/clients/create')->assertOk();
        $this->get('/credits/create')->assertOk();
    }
}
