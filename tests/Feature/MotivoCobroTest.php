<?php

namespace Tests\Feature;

use App\Livewire\Reports\Payments;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use App\Models\User;
use App\Support\MotivoCobro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 23/10/2026 (Antony): el "Motivo" del reporte de pagos ya no es el detalle
 * legacy del primer movimiento; describe qué cuota(s) se pagaron y de
 * cuántas, el desglose capital/interés/mora, si fue abono parcial o canceló
 * el crédito, y el atraso o adelanto respecto al vencimiento.
 */
class MotivoCobroTest extends TestCase
{
    use RefreshDatabase;

    private function cuota(int $id, int $num, string $venc, bool $pagada = false, float $aplicado = 0): CreditInstallment
    {
        return (new CreditInstallment)->forceFill([
            'id' => $id, 'credit_id' => 1, 'num_cuota' => $num, 'fecha_vencimiento' => $venc,
            'importe_cuota' => 625.00, 'importe_interes' => 187.50, 'importe_excedente' => 0,
            'importe_aplicado' => $pagada ? 625.00 : $aplicado, 'interes_aplicado' => $pagada ? 187.50 : 0, 'excedente_aplicado' => 0, 'pagado' => $pagada,
        ]);
    }

    private function pago(string $tipo, float $monto, ?int $installmentId = null, string $detalle = '', string $fecha = '2026-10-22'): Payment
    {
        return (new Payment)->forceFill(['tipo' => $tipo, 'documento' => $tipo, 'monto' => $monto, 'installment_id' => $installmentId, 'detalle' => $detalle, 'fecha' => $fecha]);
    }

    private function credito(int $cuotas = 12, ?string $cancelacion = null): Credit
    {
        return (new Credit)->forceFill(['id' => 1, 'cuotas' => $cuotas, 'fecha_cancelacion' => $cancelacion, 'situacion' => $cancelacion ? 'Cancelado' : 'Activo']);
    }

    public function test_describe_la_cuota_el_desglose_y_el_atraso_desde_los_movimientos_con_cuota(): void
    {
        $c8 = $this->cuota(80, 8, '2026-10-07', pagada: true);
        $pagos = collect([$this->pago('INTERES', 187.50, 80), $this->pago('CAPITAL', 625.00, 80), $this->pago('MORA', 5.80, null, 'Mora de cuota')]);

        $m = MotivoCobro::de($pagos, $this->credito(), [80 => $c8], [8 => $c8]);

        $this->assertSame('Cuota 8 de 12', $m['resumen']);
        $this->assertSame('Capital 625.00 · Interés 187.50 · Mora 5.80', $m['desglose']);
        $this->assertSame('Atraso 15 días', $m['situacion']);
        $this->assertSame('Cuota 8 de 12 · Capital 625.00 · Interés 187.50 · Mora 5.80 · Atraso 15 días', $m['texto']);
    }

    public function test_reconoce_la_cuota_por_el_detalle_legacy_junta_varias_y_dice_si_fue_adelantado(): void
    {
        $c16 = $this->cuota(160, 16, '2026-10-24', pagada: true);
        $c17 = $this->cuota(170, 17, '2026-10-31', aplicado: 100);
        $pagos = collect([
            $this->pago('CAPITAL', 625.00, null, 'Pago : 28842 Cuota:  16/96'),
            $this->pago('INTERES', 187.50, null, 'Pago : 28842 Interes:  16/96'),
            $this->pago('CAPITAL', 100.00, null, 'Pago : 28842 Cuota:  17/96'),
        ]);

        $m = MotivoCobro::de($pagos, $this->credito(96), [160 => $c16, 170 => $c17], [16 => $c16, 17 => $c17]);

        $this->assertSame('Cuotas 16 y 17 de 96 · abono parcial, queda S/ 712.50', $m['resumen']);
        $this->assertSame('Capital 725.00 · Interés 187.50', $m['desglose']);
        $this->assertSame('Adelantado 2 días', $m['situacion']);
    }

    public function test_marca_solo_interes_y_la_cancelacion_del_credito_el_mismo_dia(): void
    {
        $c1 = $this->cuota(10, 1, '2026-10-22');
        $soloInteres = MotivoCobro::de(collect([$this->pago('INTERES', 840.00, 10)]), $this->credito(1), [10 => $c1], [1 => $c1]);
        $this->assertSame('Cuota 1 de 1 (solo interés)', $soloInteres['resumen']);
        $this->assertSame('Al día', $soloInteres['situacion']);

        $cancela = MotivoCobro::de(collect([$this->pago('CAPITAL', 625.00, 10), $this->pago('INTERES', 187.50, 10)]), $this->credito(1, '2026-10-22'), [10 => $c1], [1 => $c1]);
        $this->assertSame('Cuota 1 de 1 · cancela el crédito', $cancela['resumen']);
    }

    public function test_sin_cuota_reconocible_queda_el_detalle_legacy_limpio_o_solo_mora(): void
    {
        $legacy = MotivoCobro::de(collect([$this->pago('CAPITAL', 500.00, null, 'Pago : 28423 Abono')]), $this->credito(), [], []);
        $this->assertSame('Abono', $legacy['resumen']);
        $this->assertSame('Capital 500.00', $legacy['desglose']);
        $this->assertSame('', $legacy['situacion']);

        $mora = MotivoCobro::de(collect([$this->pago('MORA', 35.70, null, 'Mora de cuota')]), $this->credito(), [], []);
        $this->assertSame('Solo mora', $mora['resumen']);
        $this->assertSame('Mora 35.70', $mora['desglose']);
    }

    public function test_cuotas_tocadas_trae_solo_las_cuotas_de_los_pagos_por_id_o_por_el_n_de_n_legacy(): void
    {
        DB::table('headquarters')->insertOrIgnore(['id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $client = Client::create(['nombre' => 'Edwin', 'apellido_pat' => 'Leoncio', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create(['client_id' => $client->id, 'fecha_prestamo' => '2026-09-01', 'importe' => 300, 'cuotas' => 3, 'tipo_planilla' => 1, 'interes' => 10, 'interes_total' => 30, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1]);
        $ids = [];
        foreach ([1, 2, 3] as $n) {
            $ids[$n] = CreditInstallment::create(['credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => '2026-09-0'.(1 + $n * 2), 'importe_cuota' => 100, 'importe_interes' => 10, 'importe_excedente' => 0, 'importe_aplicado' => $n === 1 ? 100 : 0, 'interes_aplicado' => $n === 1 ? 10 : 0, 'excedente_aplicado' => 0, 'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => $n === 1])->id;
        }
        $pagos = collect([
            $this->pago('CAPITAL', 100.00, $ids[1], 'Pago : X Cuota: 1/3')->forceFill(['credit_id' => $credit->id]),
            $this->pago('INTERES', 10.00, null, 'Pago : X Interes: 2/3')->forceFill(['credit_id' => $credit->id]),
            $this->pago('MORA', 5.00, null, 'Mora de cuota')->forceFill(['credit_id' => $credit->id]),
        ]);

        $indices = MotivoCobro::cuotasTocadas($pagos);

        $porId = array_keys($indices[$credit->id][0]);
        sort($porId);
        $this->assertSame([$ids[1], $ids[2]], $porId, 'la 1 por su installment_id y la 2 por el "2/3" del detalle; la 3 no se toca');
        $porNum = array_keys($indices[$credit->id][1]);
        sort($porNum);
        $this->assertSame([1, 2], $porNum);
        $this->assertSame('Cuotas 1 y 2 de 3', MotivoCobro::de($pagos, $credit, $indices[$credit->id][0], $indices[$credit->id][1])['resumen']);
    }

    public function test_el_reporte_pinta_el_motivo_detallado_y_lo_manda_tal_cual_al_excel(): void
    {
        DB::table('headquarters')->insertOrIgnore(['id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->travelTo('2026-10-22 10:00:00');
        $this->actingAs(User::factory()->create(['username' => 'rep-motivo', 'headquarter_id' => 1]));
        $client = Client::create(['expediente' => '1288', 'nombre' => 'Edwin', 'apellido_pat' => 'Leoncio', 'apellido_mat' => 'Escalera', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create(['client_id' => $client->id, 'fecha_prestamo' => '2026-08-25', 'importe' => 10000, 'cuotas' => 48, 'tipo_planilla' => 1, 'interes' => 60, 'interes_total' => 6000, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1]);
        $cuota = CreditInstallment::create(['credit_id' => $credit->id, 'num_cuota' => 6, 'fecha_vencimiento' => '2026-10-06', 'importe_cuota' => 208.33, 'importe_interes' => 125.00, 'importe_excedente' => 0, 'importe_aplicado' => 208.33, 'interes_aplicado' => 125.00, 'excedente_aplicado' => 0, 'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => true, 'fecha_pago' => '2026-10-22']);
        foreach ([['INTERES', 125.00], ['CAPITAL', 208.33]] as [$tipo, $monto]) {
            DB::table('payments')->insert(['credit_id' => $credit->id, 'installment_id' => $cuota->id, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo, 'fecha' => '2026-10-22 00:00:00', 'hora' => '08:55:42', 'monto' => $monto, 'detalle' => "Pago : {$credit->id} {$tipo}", 'usuario' => 'Licet', 'created_at' => now(), 'updated_at' => now()]);
        }

        $comp = Livewire::test(Payments::class)->set('fei', '2026-10-22')->set('fef', '2026-10-22')->call('search');

        $comp->assertSee('Cuota 6 de 48')->assertSee('Capital 208.33 · Interés 125.00')
            ->assertSeeHtml('<span class="text-danger fw-bold">Atraso 16 días</span>') // el atraso en rojo y negrita (23/10)
            ->assertSeeHtml('title="N° de expediente del cliente">Exp. 1288</span>'); // 30/10: el expediente encabeza el motivo
        $fila = $comp->viewData('rows')[0];
        $this->assertSame('Exp. 1288 · Cuota 6 de 48 · Capital 208.33 · Interés 125.00 · Atraso 16 días', $fila['detalle'], 'el Excel recibe la misma descripción en una línea');
    }
}
