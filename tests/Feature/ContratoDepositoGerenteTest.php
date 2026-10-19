<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\RenderDocumento;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 19/10/2026 (Antony, caso Justo H. Contratistas Generales S.A.C): el a.4.1
 * (deudor empresa, depósito al gerente) nunca se pudo emitir desde el wizard:
 * el guard exigía banco y cuenta personal del gerente y el formulario no los
 * pedía. Decisión de Antony: el desembolso va SIEMPRE a la cuenta personal del
 * gerente o representante, así que su banco es el "Banco del desembolso" que
 * ya se elige (sin segundo campo de banco), y la plantilla no imprime ningún
 * número de cuenta, así que no se pide ni se exige.
 */
class ContratoDepositoGerenteTest extends TestCase
{
    use RefreshDatabase;

    protected Client $empresa;

    protected Credit $credit;

    protected Vehiculo $vehiculo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $sede = Headquarter::create(['name' => 'Sede Gerente', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'dep-gerente', 'headquarter_id' => $sede->id]));

        $this->empresa = Client::create([
            'expediente' => '9910', 'nombre' => 'Generales S.A.C', 'apellido_pat' => 'Justo H.', 'apellido_mat' => 'Contratistas',
            'tipo_documento' => 'RUC', 'documento' => '20602131778', 'sexo' => 'M',
            'email' => 'contratistas@example.com',
            'direccion' => 'AV. UNO 100', 'distrito' => 'ATE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->empresa->id, 'fecha_prestamo' => '2026-10-19',
            'importe' => 10000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 30,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        foreach ([1, 2, 3, 4] as $i) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse('2026-10-26')->addWeeks($i - 1),
                'importe_cuota' => 2500, 'importe_interes' => 750, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }
        $this->vehiculo = Vehiculo::create([
            'client_id' => $this->empresa->id, 'placa' => 'BPS576', 'marca' => 'HYUNDAI',
            'modelo' => 'H1', 'nro_serie' => 'SER-BPS576', 'nro_motor' => 'MOT-BPS576', 'valor' => 25000,
        ]);
    }

    /** El wizard con lo que la empresa y el gerente exigen, igual que lo llenó Licet el 19/10. */
    protected function wizard(string $modelo = 'a41')
    {
        return Livewire::test(Documentos::class, ['id' => $this->empresa->id])
            ->call('abrirModalContrato')
            ->set('contratoCreditoId', $this->credit->id)
            ->set('modeloContrato', $modelo)
            ->set('contratoVehiculos.0.vehiculo_id', $this->vehiculo->id)
            ->set('bancoDesembolso', 'interbank')
            ->set('empresa.partida', '13103733')
            ->set('empresa.oficina_registral', 'LIMA')
            ->set('gerente.nombre', 'JUSTO HUAMAN ROJAS')
            ->set('gerente.dni', '40463004')
            ->set('gerente.sexo', 'M')
            ->set('gerente.nacionalidad', 'PERUANO')
            ->set('gerente.ocupacion', 'EMPRESARIO')
            ->set('gerente.estado_civil', 'CASADO')
            ->set('gerente.domicilio', 'AV. UNO 100, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA');
    }

    public function test_el_a41_se_emite_sin_pedir_cuenta_y_con_el_banco_del_desembolso_como_banco_del_gerente(): void
    {
        $comp = $this->wizard();

        // Un solo campo de banco en el formulario (el del desembolso) y ningún campo de cuenta.
        $html = $comp->html();
        $this->assertSame(1, substr_count($html, 'Banco del desembolso'));
        $this->assertStringNotContainsString('wire:model.blur="gerente.banco"', $html);
        $this->assertStringNotContainsString('wire:model.blur="gerente.cuenta"', $html);
        $this->assertStringContainsString('a su cuenta personal en este banco', $html);

        $comp->call('generarContrato')->assertDispatched('successAlert')->assertNotDispatched('errorAlert');

        $doc = DocumentoCliente::where('client_id', $this->empresa->id)->where('tipo', 'contrato')->first();
        $this->assertNotNull($doc);
        $this->assertSame('a41', $doc->modelo);
        $g = $doc->snapshot['deudores'][0]['gerente'];
        $this->assertSame('INTERBANK', $g['banco'], 'el banco del gerente es el del desembolso, como dato del snapshot');
        $this->assertSame('', $g['cuenta'], 'la plantilla no imprime la cuenta: no se pide');
        $this->assertSame('interbank', $doc->snapshot['banco']);
    }

    public function test_el_a4_cuenta_de_la_empresa_no_rellena_el_banco_del_gerente(): void
    {
        $this->wizard('a4')->call('generarContrato')->assertDispatched('successAlert');

        $doc = DocumentoCliente::where('client_id', $this->empresa->id)->where('tipo', 'contrato')->first();
        $this->assertSame('a4', $doc->modelo);
        $this->assertSame('', $doc->snapshot['deudores'][0]['gerente']['banco']);
    }

    public function test_el_contrato_a41_emitido_no_imprime_numero_de_cuenta(): void
    {
        $this->wizard()->call('generarContrato')->assertDispatched('successAlert');
        $doc = DocumentoCliente::where('client_id', $this->empresa->id)->where('tipo', 'contrato')->first();
        $html = RenderDocumento::html($doc->snapshot, 'contrato', 'pdf');

        $this->assertStringContainsString('A LA CUENTA DE TITULARIDAD DEL GERENTE GENERAL', $html);
        $this->assertStringContainsString('INTERBANK', $html, 'la constancia nombra el banco del desembolso');
        $this->assertStringNotContainsString('CUENTA BANCARIA N°', $html, 'ese número solo existe en el depósito a un tercero');
    }
}
