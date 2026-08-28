<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El wizard por DECISIONES (28/08): "Agregar segundo vehículo", el toggle de
 * bien futuro por vehículo, garantía y destino — y el modelo se resuelve
 * solo (Guía simple §5). El asesor ya no necesita saber que "dos vehículos"
 * se llamaba "a.1.2".
 *
 * Las combinaciones que NO existen en las maestras se corrigen a la más
 * cercana en vez de dejar al asesor trabado: custodia fuerza un bien
 * presente y depósito propio; sin GPS no tiene bienes futuros; el tercero
 * solo existe con UN bien presente; y en el mixto el vehículo 1 es siempre
 * el futuro.
 */
class ContratoDecisionesTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private Client $client;

    private Vehiculo $v1;

    private Vehiculo $v2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Dec', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'decisiones', 'headquarter_id' => $this->sede->id]));

        $this->client = Client::create([
            'expediente' => '9800', 'nombre' => 'JUAN', 'apellido_pat' => 'DECIDE', 'apellido_mat' => 'BIEN',
            'tipo_documento' => 'DNI', 'documento' => '47200001', 'sexo' => 'M',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'transportista', 'estado_civil' => 'soltero',
            'email' => 'juan.decide@example.com',
            'direccion' => 'AV. UNO', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        $credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);
        foreach ([1, 2] as $i) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($i - 1),
                'importe_cuota' => 2500, 'importe_interes' => 100, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }

        $this->v1 = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'DEC111', 'marca' => 'TOYOTA', 'modelo' => 'HIACE', 'valor' => 15000]);
        $this->v2 = Vehiculo::create(['client_id' => $this->client->id, 'placa' => 'DEC222', 'marca' => 'NISSAN', 'modelo' => 'URVAN', 'valor' => 12000]);
    }

    private function wizard(): Testable
    {
        return Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalContrato');
    }

    public function test_agregar_y_quitar_el_segundo_vehiculo_resuelve_el_modelo(): void
    {
        $comp = $this->wizard();
        $this->assertSame('a1', $comp->get('modeloContrato'));
        $this->assertCount(1, $comp->get('contratoVehiculos'));

        $comp->call('agregarVehiculoContrato');
        $this->assertSame('a12', $comp->get('modeloContrato'), 'dos presentes = a.1.2, sin elegir nada');
        $this->assertCount(2, $comp->get('contratoVehiculos'));

        $comp->call('quitarVehiculoContrato', 1);
        $this->assertSame('a1', $comp->get('modeloContrato'));
        $this->assertCount(1, $comp->get('contratoVehiculos'));
    }

    public function test_no_se_puede_pasar_de_dos_vehiculos(): void
    {
        $comp = $this->wizard()
            ->call('agregarVehiculoContrato')
            ->call('agregarVehiculoContrato');

        $this->assertCount(2, $comp->get('contratoVehiculos'), 'las maestras admiten máximo dos');
    }

    public function test_el_toggle_de_bien_futuro_resuelve_los_modelos_de_preconstitucion(): void
    {
        $comp = $this->wizard()
            ->set('contratoVehiculos.0.es_futuro', true);
        $this->assertSame('a14', $comp->get('modeloContrato'), 'un futuro = a.1.4');

        $comp->call('agregarVehiculoContrato')
            ->set('contratoVehiculos.1.es_futuro', true);
        $this->assertSame('a13', $comp->get('modeloContrato'), 'dos futuros = a.1.3');
    }

    public function test_en_el_mixto_el_vehiculo_futuro_pasa_a_ser_el_primero(): void
    {
        // El asesor marca como futuro el SEGUNDO: la maestra a.1.5 exige que
        // el vehículo 1 sea el futuro, así que los slots se reordenan.
        $comp = $this->wizard()
            ->set('contratoVehiculos.0.vehiculo_id', $this->v1->id)
            ->call('agregarVehiculoContrato')
            ->set('contratoVehiculos.1.vehiculo_id', $this->v2->id)
            ->set('contratoVehiculos.1.es_futuro', true);

        $this->assertSame('a15', $comp->get('modeloContrato'));
        $slots = $comp->get('contratoVehiculos');
        $this->assertSame($this->v2->id, (int) $slots[0]['vehiculo_id'], 'el futuro quedó como vehículo 1');
        $this->assertTrue((bool) $slots[0]['es_futuro']);
        $this->assertFalse((bool) $slots[1]['es_futuro']);
    }

    public function test_custodia_fuerza_un_bien_presente_y_deposito_propio(): void
    {
        $comp = $this->wizard()
            ->call('agregarVehiculoContrato')
            ->set('contratoVehiculos.0.es_futuro', true)
            ->set('destinoContrato', 'tercero')
            ->set('garantiaContrato', 'custodia');

        $this->assertSame('a16', $comp->get('modeloContrato'), 'c.1: custodia deudor');
        $this->assertCount(1, $comp->get('contratoVehiculos'));
        $this->assertSame('propio', $comp->get('destinoContrato'));
        $this->assertFalse((bool) $comp->get('contratoVehiculos')[0]['es_futuro']);
    }

    public function test_sin_gps_apaga_los_bienes_futuros(): void
    {
        $comp = $this->wizard()
            ->set('contratoVehiculos.0.es_futuro', true)
            ->set('garantiaContrato', 'sin_gps');

        $this->assertSame('b1', $comp->get('modeloContrato'), 'no existe bien futuro sin GPS');
        $this->assertFalse((bool) $comp->get('contratoVehiculos')[0]['es_futuro']);
    }

    public function test_agregar_vehiculo_con_destino_tercero_vuelve_a_propio(): void
    {
        $comp = $this->wizard()
            ->set('destinoContrato', 'tercero');
        $this->assertSame('a11', $comp->get('modeloContrato'));

        // El tercero solo existe con UN bien presente: al agregar el segundo
        // vehículo, el destino se corrige solo.
        $comp->call('agregarVehiculoContrato');
        $this->assertSame('propio', $comp->get('destinoContrato'));
        $this->assertSame('a12', $comp->get('modeloContrato'));
    }

    public function test_la_deudora_resuelve_la_serie_a2(): void
    {
        $this->client->update(['sexo' => 'F']);

        $comp = $this->wizard();
        $this->assertSame('a2', $comp->get('modeloContrato'));

        $comp->call('agregarVehiculoContrato');
        $this->assertSame('a22', $comp->get('modeloContrato'));
    }

    public function test_con_codeudor_resuelve_la_serie_a3_y_al_quitarlo_vuelve(): void
    {
        $codeudor = Client::create([
            'expediente' => '9801', 'nombre' => 'MARIA', 'apellido_pat' => 'CODEUDORA', 'apellido_mat' => 'X',
            'tipo_documento' => 'DNI', 'documento' => '47200002', 'sexo' => 'F',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        $comp = $this->wizard()
            ->call('seleccionarCodeudorContrato', $codeudor->id);
        $this->assertSame('a3', $comp->get('modeloContrato'));

        $comp->call('quitarCodeudorContrato');
        $this->assertSame('a1', $comp->get('modeloContrato'));
    }
}
