<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Chat\Herramientas\Catalogo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * 23/10/2026 (Antony): las consultas de solo lectura que el chat ofrece al
 * modelo devuelven lo mismo que cuentan las pantallas (saldo por cronograma,
 * cuotas vencidas, días de atraso, mora de hoy, cobros por lote) y corren por
 * la conexión configurada.
 */
class ChatHerramientasTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private User $user;

    private Catalogo $catalogo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['chat.conexion' => 'mysql']);
        $this->travelTo(Carbon::parse('2026-10-23 10:00:00'));
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'guilmer-test', 'headquarter_id' => $this->sede->id]);
        $this->catalogo = new Catalogo;
    }

    /**
     * Crédito semanal de 4 cuotas de S/ 333.33: dos pagadas, la 3 vencida hace
     * 10 días (13/10) y la 4 por vencer el 30/10.
     *
     * @return array{0: Client, 1: Credit, 2: array<int, int>}
     */
    private function clienteConCredito(): array
    {
        $client = Client::create([
            'nombre' => 'Mariela Ida', 'apellido_pat' => 'Chagua', 'apellido_mat' => 'Chavez', 'tipo_documento' => 'DNI', 'documento' => '70887721',
            'expediente' => '48', 'celular1' => '918512055', 'zona' => 'Alq.Ven.D.', 'headquarter_id' => $this->sede->id, 'status' => 'active', 'asesor_id' => $this->user->id,
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-09-18', 'importe' => 833.32, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 60,
            'interes_total' => 499.99, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id, 'user_id' => $this->user->id,
        ]);
        $ids = [];
        foreach (['2026-09-25', '2026-10-02', '2026-10-13', '2026-10-30'] as $i => $venc) {
            $n = $i + 1;
            $ids[$n] = CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => $venc,
                'importe_cuota' => 208.33, 'importe_interes' => 125.00, 'importe_excedente' => 0,
                'importe_aplicado' => $n <= 2 ? 208.33 : 0, 'interes_aplicado' => $n <= 2 ? 125.00 : 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => $n <= 2, 'fecha_pago' => $n <= 2 ? $venc : null,
            ])->id;
        }

        return [$client, $credit, $ids];
    }

    private function ejecutar(string $herramienta, array $argumentos): array
    {
        $r = $this->catalogo->ejecutar($herramienta, $argumentos);
        $this->assertTrue($r['ok'], $r['error'] ?? '');

        return $r['datos'];
    }

    public function test_buscar_clientes_encuentra_por_nombre_en_cualquier_orden_documento_expediente_o_codigo_de_credito(): void
    {
        [$client, $credit] = $this->clienteConCredito();
        Client::create(['nombre' => 'Otro', 'apellido_pat' => 'Perez', 'headquarter_id' => $this->sede->id, 'status' => 'active']);

        $porNombre = $this->ejecutar('buscar_clientes', ['texto' => 'Chavez Mariela']);
        $this->assertCount(1, $porNombre['clientes']);
        $this->assertSame([
            'client_id' => $client->id, 'expediente' => '48', 'nombre' => 'Chagua Chavez Mariela Ida', 'documento' => 'DNI 70887721',
            'celular' => '918512055', 'zona' => 'Alq.Ven.D.', 'estado' => 'active', 'asesor' => 'guilmer-test',
            'creditos_activos' => 1, 'cuotas_vencidas' => 1, 'en_ejecucion' => false, 'ficha' => route('clients.show', $client->id),
        ], $porNombre['clientes'][0]);

        $this->assertSame($client->id, $this->ejecutar('buscar_clientes', ['texto' => '70887721'])['clientes'][0]['client_id']);
        $this->assertSame($client->id, $this->ejecutar('buscar_clientes', ['texto' => '48'])['clientes'][0]['client_id']);

        $porCredito = $this->ejecutar('buscar_clientes', ['texto' => (string) $credit->id]);
        $this->assertSame($client->id, $porCredito['clientes'][0]['client_id']);
        $this->assertStringContainsString('código del crédito', $porCredito['nota']);

        $nadie = $this->ejecutar('buscar_clientes', ['texto' => 'Zutano']);
        $this->assertSame([], $nadie['clientes']);
        $this->assertSame('Ningún cliente coincide.', $nadie['nota']);
    }

    public function test_ficha_cliente_resume_el_credito_activo_con_saldo_cuotas_vencidas_dias_de_atraso_y_mora_de_hoy(): void
    {
        [$client, $credit] = $this->clienteConCredito();

        $ficha = $this->ejecutar('ficha_cliente', ['client_id' => $client->id]);

        $this->assertSame('Chagua Chavez Mariela Ida', $ficha['nombre']);
        $this->assertSame('DNI 70887721', $ficha['documento']);
        $this->assertSame(['918512055'], $ficha['celulares']);
        $this->assertSame(['cantidad' => 0, 'nota' => 'Pide incluir_cancelados=true para verlos.'], $ficha['creditos_cancelados']);
        $this->assertCount(1, $ficha['creditos_activos']);

        $resumen = $ficha['creditos_activos'][0];
        $this->assertSame($credit->id, $resumen['credito']);
        $this->assertSame('Semanal', $resumen['tipo']);
        $this->assertSame([2, 2, 1, 0], [$resumen['cuotas_pagadas'], $resumen['cuotas_pendientes'], $resumen['cuotas_vencidas'], $resumen['cuotas_amortizadas']]);
        $this->assertSame(666.66, $resumen['saldo']);
        $this->assertSame(666.66, $resumen['pagado_total']);
        $this->assertSame(10, $resumen['dias_atraso']);
        $this->assertSame(['dias' => 10, 'por_dia' => 2.38, 'monto' => 23.8], $resumen['mora_hoy']);
        $this->assertSame(3, $resumen['primera_cuota_vencida']['numero']);
        $this->assertSame('13/10/2026', $resumen['primera_cuota_vencida']['vencimiento']);
        $this->assertSame(['numero' => 4, 'vencimiento' => '30/10/2026', 'total' => 333.33, 'saldo' => 333.33, 'dias_atraso' => 0], $resumen['proxima_cuota']);
        $this->assertNull($resumen['ultimo_pago']);
        $this->assertSame(route('credits.schedule', $credit->id), $resumen['enlaces']['cronograma']);
    }

    public function test_cronograma_credito_marca_pagada_vencida_pendiente_y_amortizada(): void
    {
        [, $credit, $ids] = $this->clienteConCredito();
        CreditInstallment::whereKey($ids[4])->update(['importe_aplicado' => 50]);

        $pendientes = $this->ejecutar('cronograma_credito', ['credit_id' => $credit->id]);
        $this->assertSame([3, 4], array_column($pendientes['cuotas'], 'numero'));
        $this->assertSame(['Vencida', 'Amortizada'], array_column($pendientes['cuotas'], 'estado'));
        $this->assertSame([10, 0], array_column($pendientes['cuotas'], 'dias_atraso'));
        $this->assertSame(283.33, $pendientes['cuotas'][1]['saldo']);
        $this->assertSame(['pagadas' => 2, 'vencidas' => 1, 'saldo' => 616.66, 'saldo_vencido' => 333.33], $pendientes['totales']);

        $todas = $this->ejecutar('cronograma_credito', ['credit_id' => $credit->id, 'solo_pendientes' => false]);
        $this->assertSame(['Pagada', 'Pagada', 'Vencida', 'Amortizada'], array_column($todas['cuotas'], 'estado'));
    }

    public function test_pagos_credito_agrupa_cada_cobro_con_su_desglose_cuotas_cobrador_y_recibo_y_detalle_credito_suma_por_tipo(): void
    {
        [, $credit, $ids] = $this->clienteConCredito();
        $pago = fn (string $tipo, float $monto, ?int $insId) => DB::table('payments')->insertGetId([
            'credit_id' => $credit->id, 'installment_id' => $insId, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
            'fecha' => '2026-10-02 00:00:00', 'hora' => '09:15:00', 'monto' => $monto, 'detalle' => 'Cuota: 2/4', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $lote = DB::table('mass_deletions')->insertGetId([
            'credit_id' => $credit->id, 'amount' => 339.13, 'date' => '2026-10-02', 'time' => '09:15:00', 'performed_by' => 'rosa', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['C', 208.33, 'CAPITAL'], ['I', 125.00, 'INTERES'], ['M', 5.80, 'MORA']] as [$tipo, $monto, $tipoPago]) {
            DB::table('mass_deletion_details')->insert([
                'mass_deletion_id' => $lote, 'installment_id' => $ids[2], 'payment_id' => $pago($tipoPago, $monto, $tipo === 'M' ? null : $ids[2]),
                'amount' => $monto, 'fecha' => now(), 'tipo' => $tipo, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $pagos = $this->ejecutar('pagos_credito', ['credit_id' => $credit->id]);
        $this->assertSame([1, 1, 339.13], [$pagos['cobros_en_total'], $pagos['mostrados'], $pagos['suma_mostrada']]);
        $cobro = $pagos['cobros'][0];
        $this->assertSame($lote, $cobro['recibo']);
        $this->assertSame(['02/10/2026', '09:15', 339.13, [2], 'rosa'], [$cobro['fecha'], $cobro['hora'], $cobro['total'], $cobro['cuotas'], $cobro['cobrador']]);
        $this->assertSame(['capital' => 208.33, 'interes' => 125.0, 'mora' => 5.8, 'excedente' => 0.0], $cobro['desglose']);
        $this->assertSame(route('payments.ticket', $lote), $cobro['recibo_url'], 'recibo interno con sesión, no el enlace público firmado');

        $fuera = $this->ejecutar('pagos_credito', ['credit_id' => $credit->id, 'desde' => '2026-10-03']);
        $this->assertSame([0, []], [$fuera['cobros_en_total'], $fuera['cobros']]);

        $detalle = $this->ejecutar('detalle_credito', ['credit_id' => $credit->id]);
        $this->assertSame('Chagua Chavez Mariela Ida', $detalle['cliente']['nombre']);
        $this->assertSame(['total' => 5.8, 'movimientos' => 1], $detalle['pagado_por_tipo']['mora']);
        $this->assertSame(5.8, $detalle['mora_pagada']);
        $this->assertSame(['fecha' => '02/10/2026', 'hora' => '09:15', 'monto' => 339.13, 'recibo' => $lote], $detalle['ultimo_pago']);
        $this->assertSame(1, $detalle['cobros']);
    }

    public function test_documentos_cliente_lista_tipo_credito_version_estado_y_pdf(): void
    {
        [$client, $credit] = $this->clienteConCredito();
        $doc = DocumentoCliente::create([
            'client_id' => $client->id, 'credit_id' => $credit->id, 'tipo' => 'contrato', 'modelo' => 'a1', 'version' => 2,
            'estado' => 'emitido', 'generado_por' => $this->user->id, 'snapshot' => [], 'pdf_path' => 'x.pdf', 'sha256' => str_repeat('a', 64),
        ]);

        $lista = $this->ejecutar('documentos_cliente', ['client_id' => $client->id]);

        $this->assertCount(1, $lista['documentos']);
        $fila = $lista['documentos'][0];
        $this->assertSame([$doc->id, 'Contrato', $credit->id, 'a1', 2, 'emitido', 'guilmer-test'], [
            $fila['id'], $fila['tipo'], $fila['credito'], $fila['modelo'], $fila['version'], $fila['estado'], $fila['generado_por'],
        ]);
        $this->assertSame(route('clients.documentos.pdf', $doc->id), $fila['pdf']);
    }

    public function test_un_credito_cancelado_no_acumula_atraso_ni_mora_y_los_cancelados_van_aparte_con_su_total(): void
    {
        [$client, $credit] = $this->clienteConCredito();
        $credit->update(['situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-10-20', 'cancelado_por_refi' => 1, 'refinanciado' => 1, 'cod_rem' => 'REF']);
        $nuevo = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-10-20', 'importe' => 500, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 5,
            'interes_total' => 25, 'situacion' => 'Activo', 'estado' => 1, 'refinanciado' => 1, 'cod_rem' => 'REF', 'idcan' => $credit->id,
            'headquarter_id' => $this->sede->id, 'user_id' => $this->user->id,
        ]);

        $detalle = $this->ejecutar('detalle_credito', ['credit_id' => $credit->id]);
        $this->assertSame([0, 0, null, null], [$detalle['cuotas_vencidas'], $detalle['dias_atraso'], $detalle['mora_hoy'], $detalle['primera_cuota_vencida']]);
        $this->assertSame([true, null, $nuevo->id], [$detalle['cerrado_por_refinanciamiento'], $detalle['credito_anterior_que_refinancio'], $detalle['credito_que_lo_reemplaza']]);
        $this->assertStringContainsString('no acumula atraso ni mora', $detalle['nota']);

        $ficha = $this->ejecutar('ficha_cliente', ['client_id' => $client->id, 'incluir_cancelados' => true]);
        $this->assertSame([$nuevo->id], array_column($ficha['creditos_activos'], 'credito'));
        $this->assertSame([false, $credit->id, null], [
            $ficha['creditos_activos'][0]['cerrado_por_refinanciamiento'], $ficha['creditos_activos'][0]['credito_anterior_que_refinancio'], $ficha['creditos_activos'][0]['credito_que_lo_reemplaza'],
        ]);
        $this->assertSame([1, 1, null], [$ficha['creditos_cancelados']['cantidad'], $ficha['creditos_cancelados']['mostrados'], $ficha['creditos_cancelados']['nota']]);
        $this->assertSame($credit->id, $ficha['creditos_cancelados']['lista'][0]['credito']);

        $cronograma = $this->ejecutar('cronograma_credito', ['credit_id' => $credit->id]);
        $this->assertSame(['Pendiente', 'Pendiente'], array_column($cronograma['cuotas'], 'estado'), 'sin crédito activo no hay cuotas vencidas');
        $this->assertSame(0, $cronograma['totales']['vencidas']);
    }

    public function test_un_cliente_o_credito_inexistente_devuelve_un_error_legible_y_no_una_excepcion(): void
    {
        $this->assertSame(['error' => 'No existe el cliente 999.'], $this->ejecutar('ficha_cliente', ['client_id' => 999]));
        $this->assertSame(['error' => 'No existe el crédito 999.'], $this->ejecutar('cronograma_credito', ['credit_id' => 999]));
    }

    public function test_las_herramientas_corren_por_la_conexion_configurada_y_un_fallo_vuelve_como_resultado(): void
    {
        config(['chat.conexion' => 'no-existe']);
        Log::spy();

        $r = $this->catalogo->ejecutar('buscar_clientes', ['texto' => 'Chagua']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('error interno', $r['error']);
        $this->assertStringNotContainsString('no-existe', $r['error'], 'el detalle técnico no viaja al modelo');
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($mensaje, $contexto) => str_contains($contexto['error'] ?? '', 'no-existe'));
        $this->assertSame(['ok' => false, 'error' => "No existe la consulta 'nada'. Disponibles: buscar_clientes, ficha_cliente, detalle_credito, cronograma_credito, pagos_credito, documentos_cliente."], $this->catalogo->ejecutar('nada', []));
    }
}
