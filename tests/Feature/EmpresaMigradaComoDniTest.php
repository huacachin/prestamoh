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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 02/10/2026 (URGENTE, caso Gestion Energetica JM S.A.C): la migración del
 * legacy puso tipo_documento = 'DNI' a TODOS los clientes, así que las diez
 * empresas migradas quedaron como personas naturales y el wizard de
 * contratos no activaba el modelo a.4 (deudor empresa) ni pedía los datos
 * del gerente general: emitía "a.1 Deudor" con el RUC como si fuera DNI.
 *
 * Ahora Client::esPersonaJuridica() reconoce el RUC por su forma (11 dígitos
 * que empiezan en 10 ó 20) aunque el tipo diga DNI, y el comando
 * clientes:normalizar-ruc corrige las fichas.
 */
class EmpresaMigradaComoDniTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    private Client $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Empresa', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'empresa-dni', 'headquarter_id' => $this->sede->id]));

        // Tal cual quedó migrada: razón social repartida en nombre/apellidos,
        // tipo DNI y el RUC en documento.
        $this->empresa = Client::create([
            'expediente' => '1309', 'nombre' => 'JM S.A.C', 'apellido_pat' => 'Gestion Energetica y', 'apellido_mat' => 'Operaciones Tecnologicas',
            'tipo_documento' => 'DNI', 'documento' => '20613514415', 'sexo' => 'M',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'transportista', 'estado_civil' => 'soltero',
            'direccion' => 'CALLE LOS FAISANES MZ Y LT 07', 'distrito' => 'CHORRILLOS', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);
        $credit = Credit::create([
            'client_id' => $this->empresa->id, 'fecha_prestamo' => '2026-09-25',
            'importe' => 11000, 'cuotas' => 2, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);
        foreach ([1, 2] as $i) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse('2026-10-02')->addWeeks($i - 1),
                'importe_cuota' => 5500, 'importe_interes' => 550, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }
        Vehiculo::create(['client_id' => $this->empresa->id, 'placa' => 'CDM703', 'marca' => 'KYC', 'modelo' => 'X5', 'valor' => 20000]);
    }

    public function test_el_ruc_se_reconoce_por_su_forma_aunque_la_ficha_diga_dni(): void
    {
        $this->assertTrue($this->empresa->esPersonaJuridica());
        $this->assertTrue((new Client(['tipo_documento' => 'RUC', 'documento' => 'x']))->esPersonaJuridica());
        $this->assertTrue((new Client(['tipo_documento' => 'DNI', 'documento' => ' 10412345678 ']))->esPersonaJuridica());

        $this->assertFalse((new Client(['tipo_documento' => 'DNI', 'documento' => '47200001']))->esPersonaJuridica());
        $this->assertFalse((new Client(['tipo_documento' => 'DNI', 'documento' => '30613514415']))->esPersonaJuridica()); // 11 dígitos pero no es RUC
        $this->assertFalse((new Client(['tipo_documento' => 'CE', 'documento' => '001234567890']))->esPersonaJuridica());
    }

    public function test_el_wizard_resuelve_el_modelo_de_empresa_y_pide_al_gerente_general(): void
    {
        $comp = Livewire::test(Documentos::class, ['id' => $this->empresa->id])
            ->call('abrirModalContrato');

        $this->assertSame('a4', $comp->get('modeloContrato'), 'deudor empresa, no a.1 Deudor');
        $comp->assertSee('a.4 GPS. Deudor Empresa')
            ->assertSee('Gerente general (firma en representación)')
            ->assertDontSee('a.1 GPS. Deudor - Contrato SIGM');

        // Empresa: solo GPS y destino propio o gerente (a.4.1).
        $comp->set('destinoContrato', 'gerente');
        $this->assertSame('a41', $comp->get('modeloContrato'));
        $comp->assertSee('consigna el desembolso al gerente general');

        $comp->set('destinoContrato', 'tercero');
        $this->assertSame('a4', $comp->get('modeloContrato'), 'el tercero no existe para empresas: vuelve a propio');
    }

    public function test_el_comando_marca_como_ruc_solo_a_los_que_tienen_numero_de_ruc(): void
    {
        $natural = Client::create([
            'expediente' => '9801', 'nombre' => 'JUAN', 'apellido_pat' => 'NATURAL', 'tipo_documento' => 'DNI', 'documento' => '47200001',
            'sexo' => 'M', 'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        $this->artisan('clientes:normalizar-ruc', ['--dry-run' => true])
            ->expectsOutputToContain('1 cliente(s) pasarían a RUC')
            ->assertSuccessful();
        $this->assertSame('DNI', $this->empresa->fresh()->tipo_documento);

        $this->artisan('clientes:normalizar-ruc')
            ->expectsOutputToContain('1 cliente(s) marcados como RUC')
            ->assertSuccessful();
        $this->assertSame('RUC', $this->empresa->fresh()->tipo_documento);
        $this->assertSame('DNI', $natural->fresh()->tipo_documento);

        // Idempotente y con rastro en la auditoría automática del modelo.
        $this->artisan('clientes:normalizar-ruc')
            ->expectsOutputToContain('No hay clientes')
            ->assertSuccessful();
        $this->assertDatabaseHas('activity_log', ['subject_id' => $this->empresa->id, 'event' => 'updated']);
    }
}
