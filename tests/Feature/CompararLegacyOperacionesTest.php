<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * reports:comparar-legacy — anotación de CAUSA en los desgloses distintos.
 *
 * Cuando el desglose capital/interés difiere entre sistemas, el comando compara
 * las OPERACIONES del día (huaca_cab_masivo vs mass_deletions) y dice si la
 * causa es la digitación (operaciones distintas / distinto orden — regla del
 * sobrante) o si apunta al motor (operaciones idénticas). Caso real: créditos
 * 29282 y 29163 del 10/08/2026.
 */
class CompararLegacyOperacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La conexión legacy apunta a la MISMA BD de test (prefijo huaca_ intacto):
        // así el comando corre completo sin tocar la BD legacy real.
        config(['database.connections.legacy.database' => config('database.connections.mysql.database')]);
        DB::purge('legacy');

        // Estos tests prueban la anotación de CAUSA del modo legacy (cuando el
        // desglose C/I aún era issue). Con la regla interés-primero vigente por
        // defecto, ese desglose pasa a ser informativo — se prueba aparte en
        // ImputacionInteresPrimeroTest.
        config(['prestamos.imputacion' => 'legacy']);

        $this->crearTablasLegacy();
    }

    /** Tablas legacy mínimas que compararDia() consulta (solo columnas usadas). */
    private function crearTablasLegacy(): void
    {
        $schema = Schema::connection('legacy');

        if (! $schema->hasTable('ingreso')) {
            $schema->create('ingreso', function ($t) {
                $t->id('identrada');
                $t->string('modo', 30)->nullable();
                $t->string('documento', 50)->nullable();
                $t->string('nroentrada', 50)->nullable();
                $t->date('fechaentrada')->nullable();
                $t->decimal('totalgeneral', 12, 2)->default(0);
                $t->text('detalle')->nullable();
            });
        }
        foreach (['entrada', 'entrada3', 'ingreso3'] as $tabla) {
            if (! $schema->hasTable($tabla)) {
                $schema->create($tabla, function ($t) {
                    $t->id();
                    $t->string('modo', 30)->nullable();
                    $t->date('fechaentrada')->nullable();
                    $t->decimal('totalgeneral', 12, 2)->default(0);
                    $t->text('detalle')->nullable();
                });
            }
        }
        if (! $schema->hasTable('cab_cuentacorriente')) {
            $schema->create('cab_cuentacorriente', function ($t) {
                $t->id();
                $t->decimal('importe', 12, 2)->default(0);
                $t->decimal('interes', 8, 2)->default(0);
                $t->integer('cuotas')->default(1);
                $t->integer('tipoplani')->default(1);
                $t->date('fechaactua')->nullable();
            });
        }
        if (! $schema->hasTable('det_cuentacorriente')) {
            $schema->create('det_cuentacorriente', function ($t) {
                $t->id();
                $t->unsignedBigInteger('idcab');
                $t->decimal('importecuota', 12, 2)->default(0);
                $t->decimal('importeinteres', 12, 2)->default(0);
                $t->decimal('importeapli', 12, 2)->default(0);
                $t->decimal('aplicado', 12, 2)->default(0);
            });
        }
        if (! $schema->hasTable('cab_masivo')) {
            $schema->create('cab_masivo', function ($t) {
                $t->id();
                $t->unsignedBigInteger('codpres')->nullable();
                $t->decimal('monto', 12, 2)->default(0);
                $t->date('fecha')->nullable();
                $t->string('hora', 10)->nullable();
            });
        }
    }

    /** Crédito nuevo + pagos del día con el desglose dado, y sus operaciones. */
    private function escenarioNuevo(string $fecha, float $cap, float $int, array $ops): int
    {
        $client = Client::create(['nombre' => 'Cliente '.uniqid()]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2029-01-01',
            'importe' => 10000, 'cuotas' => 24, 'tipo_planilla' => 1, 'interes' => 24,
            'situacion' => 'Activo', 'estado' => 1,
        ]);

        foreach ([['CAPITAL', $cap], ['INTERES', $int]] as [$tipo, $monto]) {
            DB::table('payments')->insert([
                'credit_id' => $credit->id, 'modo' => 'CREDITO', 'tipo' => $tipo,
                'documento' => $tipo, 'fecha' => $fecha, 'monto' => $monto,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($ops as $i => $monto) {
            DB::table('mass_deletions')->insert([
                'credit_id' => $credit->id, 'amount' => $monto, 'date' => $fecha,
                'time' => sprintf('10:0%d:00', $i), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $credit->id;
    }

    /** Lado legacy del mismo crédito: caja del día + sus operaciones. */
    private function escenarioLegacy(int $creditId, string $fecha, float $cap, float $int, array $ops): void
    {
        foreach ([['CAPITAL', $cap], ['INTERES', $int]] as [$tipo, $monto]) {
            DB::connection('legacy')->table('ingreso')->insert([
                'modo' => 'CREDITO', 'documento' => $tipo, 'nroentrada' => (string) $creditId,
                'fechaentrada' => $fecha, 'totalgeneral' => $monto,
                'detalle' => "Pago : {$creditId}",
            ]);
        }
        foreach ($ops as $i => $monto) {
            DB::connection('legacy')->table('cab_masivo')->insert([
                'codpres' => $creditId, 'monto' => $monto, 'fecha' => $fecha,
                'hora' => sprintf('10:0%d:00', $i),
            ]);
        }
    }

    public function test_desglose_distinto_por_operaciones_distintas_se_explica(): void
    {
        // El caso 29282: legacy digitó 1 op de 520; el nuevo, 500 + 20.
        $fecha = '2030-01-10';
        $cid = $this->escenarioNuevo($fecha, cap: 413.34, int: 106.66, ops: [500, 20]);
        $this->escenarioLegacy($cid, $fecha, cap: 420.00, int: 100.00, ops: [520]);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('DESGLOSE DISTINTO')
            ->expectsOutputToContain('CAUSA: OPERACIONES DISTINTAS — legacy 1 op(s): 520.00 · nuevo 2 op(s): 500.00 + 20.00')
            ->expectsOutputToContain('regla del sobrante')
            ->assertExitCode(1);
    }

    public function test_mismos_montos_en_distinto_orden_se_señala(): void
    {
        // El caso 29163: mismos dos montos, orden invertido.
        $fecha = '2030-01-11';
        $cid = $this->escenarioNuevo($fecha, cap: 1670.00, int: 455.00, ops: [1705, 420]);
        $this->escenarioLegacy($cid, $fecha, cap: 1675.00, int: 450.00, ops: [420, 1705]);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('(mismos montos, distinto ORDEN)')
            ->assertExitCode(1);
    }

    public function test_operaciones_identicas_apuntan_al_motor(): void
    {
        // Desglose distinto PERO mismas operaciones: eso sí sería del motor.
        $fecha = '2030-01-12';
        $cid = $this->escenarioNuevo($fecha, cap: 413.34, int: 106.66, ops: [500, 20]);
        $this->escenarioLegacy($cid, $fecha, cap: 420.00, int: 100.00, ops: [500, 20]);

        // OJO: una sola expectativa por línea de salida — dos
        // expectsOutputToContain sobre la MISMA línea no se satisfacen ambas
        // (Mockery enruta cada doWrite a la primera expectativa que matchee).
        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain("operaciones IDÉNTICAS en ambos sistemas (500.00 + 20.00) — la causa NO es la digitación: revisar el motor con payments:backtest-legacy --credit={$cid}")
            ->assertExitCode(1);
    }

    public function test_con_interes_primero_el_desglose_es_informativo(): void
    {
        // Regla vigente (12/08/2026): el desglose C/I difiere del legacy POR
        // DISEÑO — se informa sin contar como issue y el comando sale en 0.
        config(['prestamos.imputacion' => 'interes']);

        $fecha = '2030-01-14';
        $cid = $this->escenarioNuevo($fecha, cap: 413.34, int: 106.66, ops: [520]);
        $this->escenarioLegacy($cid, $fecha, cap: 420.00, int: 100.00, ops: [520]);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('esperado: regla interés-primero activa en el nuevo')
            ->assertExitCode(0);
    }

    public function test_con_interes_primero_la_mora_distinta_sigue_siendo_issue(): void
    {
        // La mora no depende de la regla de imputación: si difiere, es un
        // problema real y debe tumbar la conciliación.
        config(['prestamos.imputacion' => 'interes']);

        $fecha = '2030-01-15';
        // Mismo total (540) en ambos, pero el legacy trae 40 de mora que el
        // nuevo no tiene: eso NO es la regla de imputación, es un issue real.
        $cid = $this->escenarioNuevo($fecha, cap: 440.00, int: 100.00, ops: [540]);
        // Lado legacy con 20 de MORA (mismo total 540): desglose con mora distinta.
        $this->escenarioLegacy($cid, $fecha, cap: 400.00, int: 100.00, ops: [540]);
        DB::connection('legacy')->table('ingreso')->insert([
            'modo' => 'CREDITO', 'documento' => 'MORA', 'nroentrada' => (string) $cid,
            'fechaentrada' => $fecha, 'totalgeneral' => 40.00,
            'detalle' => "Pago : {$cid}",
        ]);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('DESGLOSE DISTINTO')
            ->assertExitCode(1);
    }

    /** Cronogramas con el saldo indicado en cada sistema para el crédito dado. */
    private function conCronogramas(int $creditId, float $saldoNuevo, float $saldoLegacy): void
    {
        DB::table('credit_installments')->insert([
            'credit_id' => $creditId, 'num_cuota' => 1, 'fecha_vencimiento' => '2030-06-01',
            'importe_cuota' => $saldoNuevo, 'importe_interes' => 0, 'pagado' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('legacy')->table('det_cuentacorriente')->insert([
            'idcab' => $creditId, 'importecuota' => $saldoLegacy, 'importeinteres' => 0,
            'importeapli' => 0, 'aplicado' => 0,
        ]);
    }

    public function test_deuda_identica_verifica_y_pasa(): void
    {
        $fecha = '2030-02-10';
        $cid = $this->escenarioNuevo($fecha, cap: 420.00, int: 100.00, ops: [520]);
        $this->escenarioLegacy($cid, $fecha, cap: 420.00, int: 100.00, ops: [520]);
        $this->conCronogramas($cid, saldoNuevo: 480.00, saldoLegacy: 480.00);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('1/1 con saldo idéntico en ambos sistemas')
            ->assertExitCode(0);
    }

    public function test_deuda_distinta_es_issue_aunque_la_caja_cuadre(): void
    {
        // El invariante del mundo interés-primero: las etiquetas pueden diferir,
        // la DEUDA jamás. Caja idéntica pero saldos 480 vs 500 → issue.
        $fecha = '2030-02-11';
        $cid = $this->escenarioNuevo($fecha, cap: 420.00, int: 100.00, ops: [520]);
        $this->escenarioLegacy($cid, $fecha, cap: 420.00, int: 100.00, ops: [520]);
        $this->conCronogramas($cid, saldoNuevo: 480.00, saldoLegacy: 500.00);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('DEUDA DISTINTA — saldo legacy S/ 500.00 vs nuevo S/ 480.00')
            ->assertExitCode(1);
    }

    public function test_condiciones_del_credito_distintas_es_issue(): void
    {
        // Mismo importe pero tasa digitada distinta: sin este check, el error
        // pasaba invisible y los cronogramas divergían para siempre.
        $fecha = '2030-02-12';
        $cid = $this->escenarioNuevo($fecha, cap: 100.00, int: 0.00, ops: [100]);
        $this->escenarioLegacy($cid, $fecha, cap: 100.00, int: 0.00, ops: [100]);
        $this->conCronogramas($cid, 0, 0);

        DB::table('credits')->where('id', $cid)->update([
            'fecha_actualizacion' => $fecha, 'importe' => 5000, 'interes' => 12, 'cuotas' => 24, 'tipo_planilla' => 1,
        ]);
        DB::connection('legacy')->table('cab_cuentacorriente')->insert([
            'id' => $cid, 'importe' => 5000, 'interes' => 10, 'cuotas' => 24, 'tipoplani' => 1,
            'fechaactua' => $fecha,
        ]);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('CONDICIONES DISTINTAS — tasa L/N 10%/12%')
            ->assertExitCode(1);
    }

    public function test_sin_diferencias_no_anota_nada(): void
    {
        // Digitación idéntica y desglose idéntico → TODO CUADRA, sin anotaciones.
        $fecha = '2030-01-13';
        $cid = $this->escenarioNuevo($fecha, cap: 420.00, int: 100.00, ops: [520]);
        $this->escenarioLegacy($cid, $fecha, cap: 420.00, int: 100.00, ops: [520]);

        $this->artisan('reports:comparar-legacy', ['--fecha' => $fecha])
            ->expectsOutputToContain('TODO CUADRA')
            ->assertExitCode(0);
    }
}
