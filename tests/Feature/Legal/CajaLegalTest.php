<?php

namespace Tests\Feature\Legal;

use App\Livewire\Legal\Garantias\AvisoModal;
use App\Livewire\Reports\Cash as ReportsCash;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\Income;
use App\Models\SigmAviso;
use App\Models\User;
use App\Services\Legal\CajaLegal;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Caja Legal (fase 6): asientos de ingresos/egresos del Área Legal sobre las
 * tablas incomes/expenses con caja=4. Contratos que se blindan aquí:
 *
 *  - CajaLegal::egreso/ingreso SIEMPRE escriben caja=4 explícito (el default
 *    de la columna es 1: olvidarlo cae silenciosamente en la caja operativa),
 *    con la semántica de CreateExpense/CreateIncome (modo 'Otros', documento
 *    'GUIA', user y headquarter del autenticado) y SIN espejo caja=3 ni
 *    mass_deletion_id (la reversa de depósitos borra por ese campo sin
 *    filtrar caja).
 *  - Registrar un aviso SIGM genera el egreso legal de la tasa (S/ 4.00) y lo
 *    engancha en sigm_avisos.expense_id.
 *  - Los asientos caja=4 son INVISIBLES para la caja operativa: no salen en
 *    la pantalla de egresos, no suman en Reports\Cash y su edición desde
 *    cash/expenses responde 403 incluso para un director.
 */
class CajaLegalTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermisos(): void
    {
        $this->seed([
            PermissionCatalogSeeder::class,
            RoleSetupSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    /** Usuario autenticado con rol, username y sede propia. */
    private function usuario(string $rol, string $username, ?Headquarter $sede = null): User
    {
        $sede ??= Headquarter::create(['name' => 'Sede '.$username]);
        $user = User::factory()->create([
            'username' => $username,
            'headquarter_id' => $sede->id,
        ]);
        $user->assignRole($rol);
        $this->actingAs($user);

        return $user;
    }

    /** Garantía mínima en constitución (mundo de GarantiaSigmTest). */
    private function garantia(User $user): Garantia
    {
        $client = Client::create([
            'nombre' => 'JUAN', 'apellido_pat' => 'PEREZ',
            'documento' => '45678912', 'sexo' => 'M',
        ]);
        $credit = Credit::create([
            'client_id' => $client->id,
            'fecha_prestamo' => now()->toDateString(),
            'importe' => 10000, 'cuotas' => 12, 'tipo_planilla' => 3,
            'user_id' => $user->id, 'headquarter_id' => $user->headquarter_id,
        ]);

        return Garantia::create([
            'credit_id' => $credit->id,
            'client_id' => $client->id,
            'tipo' => 'mobiliaria_vehicular',
            'tipo_persona' => 'natural',
            'monto_gravamen' => 10000,
            'registrado_por' => $user->id,
        ]);
    }

    /** Egreso operativo (caja=1) de hoy, con la forma que escribe CreateExpense. */
    private function egresoOperativo(User $user, string $reason, float $total): Expense
    {
        return Expense::create([
            'date' => now()->toDateString(),
            'modo' => 'Otros',
            'documento' => 'GUIA',
            'caja' => 1,
            'reason' => $reason,
            'detail' => 'Egreso operativo de prueba',
            'total' => $total,
            'user_id' => $user->id,
            'headquarter_id' => $user->headquarter_id,
        ]);
    }

    /** Asiento legal (caja=4) de hoy, como los deja CajaLegal. */
    private function egresoLegal(User $user, string $reason, float $total): Expense
    {
        return Expense::create([
            'date' => now()->toDateString(),
            'modo' => 'Otros',
            'documento' => 'GUIA',
            'caja' => 4,
            'reason' => $reason,
            'detail' => 'Asiento del Área Legal',
            'total' => $total,
            'user_id' => $user->id,
            'headquarter_id' => $user->headquarter_id,
        ]);
    }

    public function test_el_servicio_registra_egreso_e_ingreso_en_caja_4(): void
    {
        $this->seedPermisos();
        $user = $this->usuario('area-legal', 'legal-caja');

        CajaLegal::egreso('Tasa SIGM', 4.0, 'x');

        $expense = Expense::sole();
        // caja=4 EXPLÍCITO: el default de la columna es 1 (caja operativa)
        $this->assertSame(4, (int) $expense->caja);
        $this->assertSame('4.00', (string) $expense->total);
        $this->assertSame('Tasa SIGM', $expense->reason);
        $this->assertSame('x', $expense->detail);
        $this->assertSame('Otros', $expense->modo);
        $this->assertSame('GUIA', $expense->documento);
        $this->assertSame(now()->toDateString(), $expense->date->toDateString());
        $this->assertSame($user->id, $expense->user_id);
        $this->assertSame($user->headquarter_id, $expense->headquarter_id);
        // Sin espejo caja=3 (eso es solo de los 'Fijos' operativos)...
        $this->assertSame(1, Expense::count());
        // ...y JAMÁS mass_deletion_id: la reversa de depósitos borra por ese
        // campo sin filtrar caja y arrasaría los asientos legales.
        $this->assertNull($expense->mass_deletion_id);

        CajaLegal::ingreso('Reembolso notarial', 12.5, 'y');

        $income = Income::sole();
        $this->assertSame(4, (int) $income->caja);
        $this->assertSame('12.50', (string) $income->total);
        $this->assertSame('Reembolso notarial', $income->reason);
        $this->assertSame('y', $income->detail);
        $this->assertSame('Otros', $income->modo);
        $this->assertSame('GUIA', $income->documento);
        $this->assertSame($user->id, $income->user_id);
        $this->assertSame($user->headquarter_id, $income->headquarter_id);
    }

    public function test_registrar_aviso_sigm_genera_el_egreso_legal_y_lo_enlaza(): void
    {
        $this->seedPermisos();
        $user = $this->usuario('area-legal', 'legal-avisos');
        $garantia = $this->garantia($user);

        Livewire::test(AvisoModal::class)
            ->call('abrir', $garantia->id)
            ->set('tipo', 'constitucion')
            ->set('nroFormulario', '2026-380805')
            ->set('fechaPresentacion', now()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('aviso-registrado');

        $expense = Expense::where('caja', 4)->first();
        $this->assertNotNull($expense, 'El aviso SIGM debe generar un egreso en la caja legal (caja=4).');
        $this->assertSame('4.00', (string) $expense->total); // SigmAviso::TASA_SIGM
        $this->assertSame(now()->toDateString(), $expense->date->toDateString());

        $aviso = SigmAviso::sole();
        $this->assertSame($expense->id, $aviso->expense_id);

        // Nada cayó en la caja operativa por el default de la columna
        $this->assertSame(0, Expense::whereIn('caja', [1, 3])->count());
    }

    public function test_los_asientos_legales_no_aparecen_en_la_caja_operativa(): void
    {
        $this->seedPermisos();
        $admin = $this->usuario('administrador', 'admin-caja');

        $this->egresoOperativo($admin, 'EGRESO-OPERATIVO-VISIBLE', 7.5);
        $this->egresoLegal($admin, 'ASIENTO-LEGAL-OCULTO', 4.0);

        // Pantalla de egresos operativos: solo el caja=1
        $this->get('/cash/expenses')
            ->assertOk()
            ->assertSee('EGRESO-OPERATIVO-VISIBLE')
            ->assertDontSee('ASIENTO-LEGAL-OCULTO');

        // Reporte de caja del día: suma SOLO la caja operativa
        Livewire::test(ReportsCash::class)
            ->set('fecha_desde', now()->toDateString())
            ->set('fecha_hasta', now()->toDateString())
            ->assertViewHas('summary', fn ($s) => abs((float) $s->total_egresos - 7.5) < 0.001)
            ->assertViewHas('expenses', fn ($expenses) => $expenses->count() === 1);
    }

    public function test_editar_un_asiento_legal_desde_cash_responde_403_incluso_para_director(): void
    {
        $this->seedPermisos();
        $director = $this->usuario('director', 'director-caja');

        $legal = $this->egresoLegal($director, 'Tasa SIGM', 4.0);

        // Con permisos plenos de caja igual se bloquea: los asientos legales
        // se gestionan desde su documento de origen en el Área Legal.
        $this->get("/cash/expenses/{$legal->id}/edit")->assertForbidden();

        // Sanidad: un egreso operativo del mismo día SÍ es editable
        $operativo = $this->egresoOperativo($director, 'Egreso operativo', 10.0);
        $this->get("/cash/expenses/{$operativo->id}/edit")->assertOk();
    }

    public function test_un_asiento_legal_de_hoy_no_altera_el_balance_de_reports_cash(): void
    {
        $this->seedPermisos();
        $admin = $this->usuario('administrador', 'admin-reporte');

        $this->egresoOperativo($admin, 'Egreso operativo', 150.0);

        $hoy = now()->toDateString();
        $sinLegal = fn ($s) => abs((float) $s->total_egresos - 150.0) < 0.001;

        Livewire::test(ReportsCash::class)
            ->set('fecha_desde', $hoy)
            ->set('fecha_hasta', $hoy)
            ->assertViewHas('summary', $sinLegal);

        $this->egresoLegal($admin, 'Tasa SIGM', 4.0);

        // total_egresos idéntico con y sin el asiento legal
        Livewire::test(ReportsCash::class)
            ->set('fecha_desde', $hoy)
            ->set('fecha_hasta', $hoy)
            ->assertViewHas('summary', $sinLegal);
    }
}
