<?php

namespace Tests\Feature;

use App\Livewire\Payments\Create;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 22/10/2026 (Antony): cada pago guarda la ubicación de quien lo registra
 * (payments.latitud/longitud, la del navegador) y sin ubicación no se cobra.
 * phpunit.xml apaga la exigencia para el resto de la suite; aquí se enciende
 * a propósito.
 */
class CobroUbicacionObligatoriaTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE = 'Activa la ubicación del navegador para registrar el pago';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('headquarters')->insertOrIgnore([
            'id' => 1, 'name' => 'Principal', 'empresa' => 'Huacachin',
            'status' => 'active', 'sort_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['username' => 'cajero-gps', 'headquarter_id' => 1]));
        config(['prestamos.ubicacion_obligatoria' => true]);
    }

    /** Crédito mensual de una cuota vigente (vence en 10 días): cualquier abono es válido. */
    private function credito(): Credit
    {
        $client = Client::create(['nombre' => 'Cliente GPS', 'headquarter_id' => 1, 'status' => 'active']);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => now()->subDays(20)->format('Y-m-d'),
            'importe' => 1000, 'cuotas' => 1, 'tipo_planilla' => 3, 'interes' => 10,
            'interes_total' => 100, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => 1,
        ]);
        CreditInstallment::create([
            'credit_id' => $credit->id, 'num_cuota' => 1,
            'fecha_vencimiento' => now()->addDays(10)->format('Y-m-d'),
            'importe_cuota' => 1000, 'importe_interes' => 100, 'pagado' => 0,
        ]);

        return $credit->refresh();
    }

    public function test_sin_ubicacion_no_se_cobra(): void
    {
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 300)
            ->call('confirmarPago');

        $comp->assertDispatched('errorAlert', fn ($name, $params) => str_contains($params['message'] ?? $params[0]['message'] ?? '', self::MENSAJE));
        $this->assertEmpty($comp->get('preview'), 'sin ubicación no se arma el ticket');

        // Tampoco por la puerta de atrás: pagar() directo con el modal "abierto".
        $comp->call('pagar');
        $this->assertSame(0, DB::table('payments')->where('credit_id', $credit->id)->count(), 'no se registró nada');
    }

    public function test_con_ubicacion_el_pago_se_registra_con_sus_coordenadas(): void
    {
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('latitud', '-12.0463731')
            ->set('longitud', '-77.0427934')
            ->set('monto', 300)
            ->call('confirmarPago')
            ->assertNotDispatched('errorAlert');
        $this->assertNotEmpty($comp->get('preview'));

        $comp->set('decisionTotal', 'no')->call('pagar')->assertNotDispatched('errorAlert');

        $pagos = DB::table('payments')->where('credit_id', $credit->id)->get(['tipo', 'monto', 'latitud', 'longitud']);
        $this->assertGreaterThan(0, $pagos->count());
        foreach ($pagos as $p) {
            $this->assertSame('-12.0463731', (string) $p->latitud, "pago {$p->tipo}: guarda la latitud");
            $this->assertSame('-77.0427934', (string) $p->longitud, "pago {$p->tipo}: guarda la longitud");
        }
    }

    public function test_coordenadas_invalidas_no_valen_como_ubicacion(): void
    {
        $credit = $this->credito();

        foreach ([['abc', '-77.04'], ['0', '0'], ['95', '-77.04'], ['-12.04', '200']] as [$lat, $lng]) {
            Livewire::test(Create::class, ['creditId' => $credit->id])
                ->set('latitud', $lat)->set('longitud', $lng)
                ->set('monto', 300)
                ->call('confirmarPago')
                ->assertDispatched('errorAlert');
        }
    }

    public function test_con_la_exigencia_apagada_se_cobra_sin_ubicacion(): void
    {
        config(['prestamos.ubicacion_obligatoria' => false]);
        $credit = $this->credito();

        $comp = Livewire::test(Create::class, ['creditId' => $credit->id])
            ->set('monto', 300)
            ->call('confirmarPago')
            ->assertNotDispatched('errorAlert');
        $this->assertNotEmpty($comp->get('preview'));
    }
}
