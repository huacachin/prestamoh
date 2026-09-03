<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\Payment;
use App\Models\User;
use App\Services\CajaDailyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Capital de caja 1 HOMOLOGADO al legacy (01/09, decisión del negocio).
 *
 * El legacy distingue `refi=1` (cancelado POR refinanciación → fórmula de
 * settlement) de `cod_rem='REF'` (NACIÓ de una refinanciación → nada
 * especial). Nuestro `refinanciado` fusiona ambos, y la rama de settlement
 * disparaba también en RENOVACIONES pagadas de verdad: en agosto/2026 le
 * restó S/ 178,102.40 al reporte (créditos 29275/29279 — cancelados de un
 * pago y renovados el mismo día) frente al "Capital T." del legacy.
 *
 * La rama ahora dispara con `cancelado_por_refi` (el refi del legacy, 1:1).
 * Backtest 2020–2026 contra huaca_totcaj1a: delta 0.00 en todos los días
 * (agosto/2026 = 841,397.63 exacto); los deltas remanentes son snapshots
 * históricos 2016–2019 escritos por código legacy de esa época.
 */
class CajaCapitalComoLegacyTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Caja', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'caja-tester', 'headquarter_id' => $this->sede->id]));
    }

    private function credito(array $extra = []): Credit
    {
        static $n = 0;
        $n++;
        $client = Client::create([
            'expediente' => (string) (9990 + $n), 'nombre' => 'CAJA'.$n, 'apellido_pat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => (string) (48000000 + $n), 'sexo' => 'M',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        return Credit::create(array_merge([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-07-20',
            'importe' => 51800, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 4,
            'situacion' => 'Cancelado', 'estado' => 1, 'headquarter_id' => $this->sede->id,
            'fecha_cancelacion' => '2026-08-24',
        ], $extra));
    }

    private function capitalDelDia(string $fecha): float
    {
        $dias = app(CajaDailyService::class)->ingresosPorDia(2026, 8, null, '2026-08-31');
        $cap = 0.0;
        foreach ($dias[$fecha] ?? [] as $ing) {
            $cap += $ing['capital'];
        }

        return round($cap, 2);
    }

    /**
     * El caso 29275: un crédito que NACIÓ de una refi (refinanciado=1 por
     * cod_rem='REF') pero fue cancelado con un pago REAL de capital. El
     * legacy lo cuenta completo; nosotros lo borrábamos del reporte.
     */
    public function test_renovacion_pagada_cuenta_el_capital_completo_como_legacy(): void
    {
        $credit = $this->credito([
            'refinanciado' => 1,          // nació de refi (cod_rem REF)
            'cancelado_por_refi' => 0,    // pero NO fue cancelado por refi
            'cod_rem' => 'REF',
        ]);

        // Pago real del capital completo el día de la cancelación.
        Payment::create([
            'credit_id' => $credit->id, 'fecha' => '2026-08-24', 'tipo' => 'CAPITAL',
            'documento' => 'CAPITAL', 'monto' => 51800, 'detalle' => 'Pago : X Cuota: 1/1',
            'user_id' => auth()->id(), 'headquarter_id' => $this->sede->id,
        ]);

        $this->assertSame(51800.00, $this->capitalDelDia('2026-08-24'),
            'el capital pagado de verdad cuenta completo, como en el legacy');
    }

    /** Un refi VERDADERO (cancelado_por_refi=1) sigue con la fórmula de settlement. */
    public function test_refi_verdadero_sigue_neteando(): void
    {
        $credit = $this->credito([
            'refinanciado' => 1,
            'cancelado_por_refi' => 1,    // el refi del legacy
            'importe' => 10000, 'interes' => 10,
        ]);

        // Pagos previos + el settlement del día de la cancelación.
        Payment::create([
            'credit_id' => $credit->id, 'fecha' => '2026-08-10', 'tipo' => 'INTERES',
            'documento' => 'INTERES', 'monto' => 500, 'detalle' => 'Pago : X Interes: 1/1',
            'user_id' => auth()->id(), 'headquarter_id' => $this->sede->id,
        ]);
        Payment::create([
            'credit_id' => $credit->id, 'fecha' => '2026-08-24', 'tipo' => 'CAPITAL',
            'documento' => 'CAPITAL', 'monto' => 10500, 'detalle' => 'Pago : X Cuota: 1/1',
            'user_id' => auth()->id(), 'headquarter_id' => $this->sede->id,
        ]);

        // Fórmula del legacy con pagos previos:
        // capital = importe + interésTotal − previos − pagosHoy
        //         = 10000 + 1000 − 500 − 10500 = 0
        $this->assertSame(0.00, $this->capitalDelDia('2026-08-24'),
            'el settlement de una refi verdadera se netea, como en el legacy');
    }

    /** Un crédito normal (sin marcas) suma sus filas CAPITAL tal cual. */
    public function test_credito_normal_no_cambia(): void
    {
        $credit = $this->credito([
            'refinanciado' => 0, 'cancelado_por_refi' => 0,
            'situacion' => 'Activo', 'fecha_cancelacion' => null,
        ]);

        Payment::create([
            'credit_id' => $credit->id, 'fecha' => '2026-08-24', 'tipo' => 'CAPITAL',
            'documento' => 'CAPITAL', 'monto' => 1200, 'detalle' => 'Pago : X Cuota: 1/1',
            'user_id' => auth()->id(), 'headquarter_id' => $this->sede->id,
        ]);
        Payment::create([
            'credit_id' => $credit->id, 'fecha' => '2026-08-24', 'tipo' => 'INTERES',
            'documento' => 'INTERES', 'monto' => 300, 'detalle' => 'Pago : X Interes: 1/1',
            'user_id' => auth()->id(), 'headquarter_id' => $this->sede->id,
        ]);

        $this->assertSame(1200.00, $this->capitalDelDia('2026-08-24'));
    }
}
