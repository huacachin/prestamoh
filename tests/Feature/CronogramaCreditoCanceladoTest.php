<?php

namespace Tests\Feature;

use App\Livewire\Credits\Schedule;
use App\Livewire\Credits\Show;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 30/10/2026 (Antony, crédito 29013): en un crédito cancelado, la cuota que el legacy dejó sin marcar
 * como pagada salía "Vencida" con su saldo y como capital e interés pendientes. Se muestra "Cancelado",
 * con lo que cobró caja para esa cuota y sin saldo, en el cronograma y en la ficha del crédito; los
 * datos no cambian.
 */
class CronogramaCreditoCanceladoTest extends TestCase
{
    use RefreshDatabase;

    private Credit $credit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-30 10:00:00');
        DB::table('headquarters')->insertOrIgnore(['id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin', 'status' => 'active', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::factory()->create(['username' => 'crono-cancelado', 'headquarter_id' => 1]));

        $client = Client::create(['expediente' => '1289', 'nombre' => 'Cristhian', 'apellido_pat' => 'Quispe', 'headquarter_id' => 1, 'status' => 'active']);
        $this->credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-07-07', 'importe' => 750, 'cuotas' => 3, 'tipo_planilla' => 1,
            'interes' => 24, 'interes_total' => 180, 'situacion' => 'Cancelado', 'estado' => 0, 'fecha_cancelacion' => '2026-08-13', 'headquarter_id' => 1,
        ]);
        // Como el 29013: las tres cuotas cobradas el 13/08, la última sin marcar como pagada.
        foreach ([1, 2, 3] as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => '2026-08-0'.(3 + $n),
                'importe_cuota' => 250, 'importe_interes' => 60,
                'importe_aplicado' => $n < 3 ? 250 : 0, 'interes_aplicado' => $n < 3 ? 60 : 0, 'pagado' => $n < 3,
            ]);
            $this->pago($n, 250, 60);
        }
    }

    private function pago(int $cuota, float $capital, float $interes): void
    {
        foreach ([['CAPITAL', $capital, 'Cuota'], ['INTERES', $interes, 'Interes']] as [$tipo, $monto, $rotulo]) {
            DB::table('payments')->insert([
                'credit_id' => $this->credit->id, 'modo' => 'CREDITO', 'tipo' => $tipo, 'documento' => $tipo,
                'fecha' => '2026-08-13 00:00:00', 'hora' => '11:01:10', 'monto' => $monto,
                'detalle' => "Pago : {$this->credit->id} {$rotulo}:  {$cuota}/3", 'usuario' => 'Licet', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_la_cuota_sin_marcar_de_un_credito_cancelado_no_sale_vencida_ni_como_deuda(): void
    {
        $c = Livewire::test(Schedule::class, ['id' => $this->credit->id]);

        $html = $c->html();
        $this->assertStringNotContainsString('>Vencida</span>', $html);
        $this->assertStringNotContainsString('>Pendiente</span>', $html);
        $this->assertSame(2, substr_count($html, '>Pagado</span>'));
        $this->assertStringContainsString('Crédito cancelado el 13/08/2026: la cuota quedó sin marcar como pagada en el sistema anterior.', $html);
        $this->assertMatchesRegularExpression('/<span class="badge bg-secondary"[^>]*>Cancelado<\/span>/', $html);
        $this->assertStringNotContainsString('no se cobraron', $html, 'se cobró todo');

        $cuota3 = $c->viewData('rows')[2];
        $this->assertSame([250.0, 60.0, 0.0, true], [$cuota3['pagado_cap'], $cuota3['pagado_int'], $cuota3['saldo'], $cuota3['cerrada']]);
        $this->assertSame(0.0, $c->viewData('capPendienteTotal'));
        // Pie: Retraso, Saldo e Interés pendiente en 0 (antes 310, 0 y 60).
        $this->assertMatchesRegularExpression('/<b>Retraso<\/b><\/td>\s*<td[^>]*><b>0\.00<\/b>/', $html);
        $this->assertMatchesRegularExpression('/Interés pendiente total<\/b><\/td>\s*<td[^>]*>\s*<b[^>]*>0\.00<\/b>/', $html);

        // Los datos no se tocan.
        $this->assertDatabaseHas('credit_installments', ['credit_id' => $this->credit->id, 'num_cuota' => 3, 'pagado' => 0, 'importe_aplicado' => 0]);
    }

    public function test_la_ficha_del_credito_muestra_lo_mismo(): void
    {
        $html = Livewire::test(Show::class, ['id' => $this->credit->id])->html();

        $this->assertStringNotContainsString('>Vencida</span>', $html);
        $this->assertStringNotContainsString('>Pendiente</span>', $html);
        $this->assertStringContainsString('title="Crédito cancelado el 13/08/2026: la cuota quedó sin marcar como pagada en el sistema anterior." style="cursor:help;">Cancelado</span>', $html);
        $this->assertStringContainsString('title="Crédito cancelado el 13/08/2026."', $html, 'saldo pendiente en 0, se cobró todo');
        // Cuota 3: pagado capital 250 e interés 60 (lo que cobró caja), saldo 0; totales 750 / 180 / 0.
        $texto = preg_replace('/\s+/', ' ', strip_tags($html));
        $this->assertStringContainsString('3 06/08/2026 250.00 60.00 250.00 60.00 0.00 Cancelado', $texto);
        $this->assertStringContainsString('Totales 750.00 180.00 750.00 180.00 0.00', $texto);

        // Un crédito activo sigue igual.
        $this->credit->update(['situacion' => 'Activo', 'estado' => 1, 'fecha_cancelacion' => null]);
        $this->assertStringContainsString('>Vencida</span>', Livewire::test(Show::class, ['id' => $this->credit->id])->html());
    }

    public function test_lo_no_cobrado_se_mira_por_credito_y_no_por_cuota(): void
    {
        // Como el 28155: el legacy anotó en la cuota 2 S/ 40 de interés de la 3. Se cobró todo: nada condonado.
        DB::table('payments')->where('detalle', 'like', '%/3')->where('detalle', 'not like', '%1/3')->delete();
        $this->pago(2, 250, 100);
        $this->pago(3, 250, 20);
        CreditInstallment::where('credit_id', $this->credit->id)->where('num_cuota', 2)->update(['interes_aplicado' => 100]);
        CreditInstallment::where('credit_id', $this->credit->id)->where('num_cuota', 3)->update(['importe_aplicado' => 250, 'interes_aplicado' => 20]);

        $c = Livewire::test(Schedule::class, ['id' => $this->credit->id]);
        $this->assertSame([250.0, 20.0, 0.0], [$c->viewData('rows')[2]['pagado_cap'], $c->viewData('rows')[2]['pagado_int'], $c->viewData('rows')[2]['saldo']]);
        $c->assertDontSee('no se cobraron')->assertDontSee('Amortizada</span>', false)
            ->assertSee('Crédito cancelado el 13/08/2026.');

        // Condonado de verdad: la cuota 2 vuelve a lo normal y faltan S/ 40 del cronograma.
        DB::table('payments')->where('detalle', 'like', '%Interes:  2/3')->update(['monto' => 60]);
        CreditInstallment::where('credit_id', $this->credit->id)->where('num_cuota', 2)->update(['interes_aplicado' => 60]);
        Livewire::test(Schedule::class, ['id' => $this->credit->id])
            ->assertSee('Crédito cancelado el 13/08/2026: S/ 40.00 del cronograma no se cobraron.');

        // Refinanciado: el saldo pasó al crédito nuevo.
        $this->credit->update(['cancelado_por_refi' => true]);
        Livewire::test(Schedule::class, ['id' => $this->credit->id])
            ->assertSee('Crédito cancelado por refinanciación el 13/08/2026: el saldo de esta cuota pasó al crédito nuevo.')
            ->assertSee('S/ 40.00 pasaron al crédito nuevo (refinanciación).')
            ->assertDontSee('no se cobraron');
    }

    public function test_un_credito_activo_sigue_mostrando_su_cuota_vencida(): void
    {
        $this->credit->update(['situacion' => 'Activo', 'estado' => 1, 'fecha_cancelacion' => null]);

        $c = Livewire::test(Schedule::class, ['id' => $this->credit->id]);
        $this->assertStringContainsString('>Vencida</span>', $c->html());
        $this->assertSame([0.0, 0.0, 310.0, false], [$c->viewData('rows')[2]['pagado_cap'], $c->viewData('rows')[2]['pagado_int'], $c->viewData('rows')[2]['saldo'], $c->viewData('rows')[2]['cerrada']]);
        $this->assertSame(250.0, $c->viewData('capPendienteTotal'));
    }
}
