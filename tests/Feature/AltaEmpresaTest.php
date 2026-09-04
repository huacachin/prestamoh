<?php

namespace Tests\Feature;

use App\Livewire\Clients\Create;
use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\ClientEmpresa;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alta de EMPRESA (RUC) con representante legal (28/08).
 *
 * La empresa no tiene sexo, ocupación, estado civil ni nacionalidad: esos
 * datos son del GERENTE, que es exactamente lo que el contrato a.4 exige.
 * El alta con RUC los pide en la sección "Representante legal", los guarda
 * en client_empresas + empresa_representantes (vigente) y el wizard de
 * contrato los precarga solo.
 */
class AltaEmpresaTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Empresa', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'alta-empresa', 'headquarter_id' => $this->sede->id]));
        DB::table('correlativos')->insertOrIgnore(['tipo' => 'Cliente', 'correl' => 9600]);
    }

    private function altaRuc(): Testable
    {
        return Livewire::test(Create::class)
            ->set('tipo_documento', 'RUC')
            ->set('documento', '20601234599')
            ->set('nombre', 'TRANSPORTES ALTA S.A.C.')
            ->set('correos.0.email', 'contacto@alta.com')
            ->set('expediente', 9601)
            ->set('direccion', 'AV. SALAVERRY 2900')
            ->set('distrito', 'MAGDALENA DEL MAR')
            ->set('provincia', 'LIMA')
            ->set('departamento', 'LIMA');
    }

    public function test_el_alta_ruc_exige_al_representante_y_no_los_personales_del_cliente(): void
    {
        // Sin representante: bloquea por sus campos, no por sexo/ocupación
        // del "cliente" (que una empresa no tiene).
        $comp = $this->altaRuc()
            ->set('representante.nombre', '')
            ->set('representante.dni', '')
            ->call('siguientePaso')
            ->assertHasErrors(['representante.nombre', 'representante.dni'])
            ->assertHasNoErrors(['sexo', 'ocupacion', 'estado_civil', 'nacionalidad']);
    }

    public function test_el_alta_ruc_guarda_empresa_y_representante_vigente(): void
    {
        $this->altaRuc()
            ->set('representante.nombre', 'JACK YELTSIN BLAS SULLCA')
            ->set('representante.tipo_documento', 'DNI')
            ->set('representante.dni', '46964491')
            ->set('representante.sexo', 'M')
            ->set('representante.nacionalidad', 'PERUANO')
            ->set('representante.ocupacion', 'independiente')
            ->set('representante.estado_civil', 'soltero')
            ->call('siguientePaso')
            ->call('save')
            ->assertHasNoErrors();

        $empresa = Client::where('documento', '20601234599')->firstOrFail();

        // La empresa NO tiene datos personales.
        $this->assertNull($empresa->sexo);
        $this->assertNull($empresa->ocupacion);
        $this->assertNull($empresa->estado_civil);
        $this->assertNull($empresa->nacionalidad);

        // La ficha registral existe y el representante quedó VIGENTE.
        $ficha = ClientEmpresa::where('client_id', $empresa->id)->firstOrFail();
        $vigente = $ficha->representanteVigente;
        $this->assertNotNull($vigente);
        $this->assertSame('JACK YELTSIN BLAS SULLCA', $vigente->nombre);
        $this->assertSame('46964491', $vigente->documento);
        $this->assertSame('independiente', $vigente->ocupacion);
        // Sin domicilio propio: hereda el de la empresa (domicilio legal armado).
        $this->assertStringContainsString('PROVINCIA Y DEPARTAMENTO DE LIMA', $vigente->domicilio);
    }

    public function test_el_wizard_de_contrato_precarga_al_gerente_del_alta(): void
    {
        $this->altaRuc()
            ->set('representante.nombre', 'ROSA QUISPE MAMANI')
            ->set('representante.dni', '41752169')
            ->set('representante.sexo', 'F')
            ->set('representante.nacionalidad', 'VENEZOLANO')
            ->set('representante.ocupacion', 'independiente')
            ->set('representante.estado_civil', 'casado')
            ->call('siguientePaso')
            ->call('save')
            ->assertHasNoErrors();

        $empresa = Client::where('documento', '20601234599')->firstOrFail();

        // Crédito activo para que el modal del contrato se arme.
        $credit = Credit::create([
            'client_id' => $empresa->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
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
        Vehiculo::create([
            'client_id' => $empresa->id, 'placa' => 'EMP111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'valor' => 15000,
        ]);

        $comp = Livewire::test(Documentos::class, ['id' => $empresa->id])
            ->call('abrirModalContrato');

        // El gerente viene precargado del alta: NO se retipea nada.
        $g = $comp->get('gerente');
        $this->assertSame('ROSA QUISPE MAMANI', $g['nombre']);
        $this->assertSame('41752169', $g['dni']);
        $this->assertSame('F', $g['sexo']);
        $this->assertSame('VENEZOLANO', $g['nacionalidad']);
        $this->assertSame('independiente', $g['ocupacion']);
        $this->assertSame('casado', $g['estado_civil']);
    }

    public function test_el_alta_con_dni_no_pide_representante(): void
    {
        Livewire::test(Create::class)
            ->set('tipo_documento', 'DNI')
            ->set('documento', '47100001')
            ->set('nombre', 'JUAN')
            ->set('apellido_pat', 'NORMAL')
            ->set('sexo', 'M')
            ->set('correos.0.email', 'juan.normal@example.com')
            ->set('ocupacion', 'transportista')
            ->set('estado_civil', 'soltero')
            ->set('expediente', 9602)
            ->set('direccion', 'AV. UNO')
            ->set('distrito', 'ATE')
            ->set('provincia', 'LIMA')
            ->set('departamento', 'LIMA')
            ->call('siguientePaso')
            ->assertHasNoErrors();

        $this->assertSame(0, ClientEmpresa::count());
    }
}
