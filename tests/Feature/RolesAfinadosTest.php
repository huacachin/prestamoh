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

        foreach (['caja.bypass-fecha-anterior', 'caja.editar-historico'] as $p) {
            $this->assertFalse($admin->hasPermissionTo($p), "administrador NO debe tener $p");
            $this->assertTrue($director->hasPermissionTo($p), "director SÍ debe tener $p");
        }
        // revertir (anular cobros) lo tiene, pero el código lo limita al día (26/08)
        $this->assertTrue($admin->hasPermissionTo('registro.eliminar-masivo.revertir'));
        // Exclusivos del director (permisos-nuevos 21/08): eliminar registros,
        // identidad, usuarios/permisos y sucursales
        foreach ([
            'clientes.eliminar', 'creditos.eliminar', 'clientes.editar-identidad',
            'configuracion.usuarios', 'usuarios.gestionar-permisos', 'configuracion.sucursales',
        ] as $p) {
            $this->assertFalse($admin->hasPermissionTo($p), "administrador NO debe tener $p");
            $this->assertTrue($director->hasPermissionTo($p), "director SÍ debe tener $p");
        }
        // Lo operativo del día lo conserva
        foreach (['caja.eliminar', 'registro.eliminar-masivo', 'configuracion.conceptos'] as $p) {
            $this->assertTrue($admin->hasPermissionTo($p), "administrador debe conservar $p");
        }
        // Analista sin dashboard ni cesados
        $ana = Role::findByName('analista-creditos');
        $this->assertFalse($ana->hasPermissionTo('dashboard'));
        $this->assertFalse($ana->hasPermissionTo('registro.cesados'));
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

    /**
     * El administrador edita SOLO lo del día Y SOLO lo propio (regla 04/09:
     * el director es el único que edita registros de todos — revierte la
     * apertura del 21/08 que le dejaba corregir lo de los cobradores).
     */
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

        // Movimientos de HOY de OTRO usuario: desde el 04/09 NO los edita
        // (solo el director toca registros ajenos)
        $otro = User::factory()->create(['username' => 'cobrador-caja', 'headquarter_id' => $sede->id]);
        $ingHoyAjeno = DB::table('incomes')->insertGetId(
            array_merge($base, ['user_id' => $otro->id, 'date' => now()->format('Y-m-d'), 'documento' => 'GUIA'])
        );

        $this->actingAs($admin);
        $this->get("/cash/incomes/{$ingHoy}/edit")->assertOk();
        $this->get("/cash/incomes/{$ingHoyAjeno}/edit")->assertForbidden();
        $this->get('/cash/incomes?desde=&hasta=')->assertDontSee("/cash/incomes/{$ingHoyAjeno}/edit");
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

        // Operador de caja (sin ver-todo): lo ajeno sigue cerrado aunque sea de hoy
        $operador = User::factory()->create(['username' => 'operador-caja', 'headquarter_id' => $sede->id]);
        $operador->assignRole('caja');
        $this->actingAs($operador);
        $this->get("/cash/incomes/{$ingHoyAjeno}/edit")->assertForbidden();
    }

    /** Sin permiso dashboard se aterriza en /credits; el drill-down queda cerrado. */
    public function test_analista_sin_dashboard_aterriza_en_creditos(): void
    {
        [$analista] = $this->analistaConCreditos();
        $this->actingAs($analista);

        $this->get('/dashboard')->assertRedirect(route('credits.index'));
        $this->get('/reports/desembolsos')->assertForbidden();
    }

    /** Los reportes del analista solo muestran SU cartera. */
    public function test_reportes_del_analista_solo_su_cartera(): void
    {
        [$analista, $miCredito, $ajenoCredito] = $this->analistaConCreditos();
        // El reporte diario filtra tipo_planilla=4
        Credit::whereKey([$miCredito->id, $ajenoCredito->id])->update(['tipo_planilla' => 4]);

        $this->actingAs($analista);
        $cartera = $this->get('/reports/portfolio');
        $cartera->assertOk()->assertSee('Cliente Propio')->assertDontSee('Cliente Ajeno');

        $diario = $this->get('/payments/daily');
        $diario->assertOk()->assertSee('Cliente Propio')->assertDontSee('Cliente Ajeno');
    }

    /** El administrador edita solo créditos registrados HOY; el director cualquiera. */
    public function test_administrador_edita_solo_creditos_de_hoy(): void
    {
        $admin = User::factory()->create(['username' => 'adm-cred']);
        $admin->assignRole('administrador');
        $cliente = Client::create(['nombre' => 'Cliente Editable', 'asesor_id' => $admin->id]);

        $base = [
            'client_id' => $cliente->id, 'importe' => 1000, 'cuotas' => 4, 'tipo_planilla' => 1,
            'interes' => 10, 'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1,
        ];
        $hoy = Credit::create($base + ['fecha_prestamo' => now()->format('Y-m-d')]);
        $viejo = Credit::create($base + ['fecha_prestamo' => now()->subDays(5)->format('Y-m-d')]);

        $this->actingAs($admin);
        $this->get("/credits/{$hoy->id}/edit")->assertOk();
        $this->get("/credits/{$viejo->id}/edit")->assertForbidden();

        $director = User::factory()->create(['username' => 'dir-cred']);
        $director->assignRole('director');
        $this->actingAs($director);
        $this->get("/credits/{$viejo->id}/edit")->assertOk();
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
