<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Livewire\Clients\Vehiculos;
use App\Models\Client;
use App\Models\ClientEmpresa;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tramo D — persistencia: copropietario de vehículo, empresa + representante
 * legal, y el tipo de documento del deudor.
 *
 *   1. Pivote cliente_vehiculo: el copropietario habilita los contratos de
 *      dos deudores con el MISMO vehículo, que antes se rechazaban.
 *   2. El wizard precarga al copropietario como codeudor y acepta vehículos
 *      de cualquiera de los deudores.
 *   3. client_empresas + empresa_representantes: lo tipeado al emitir queda
 *      en la ficha y el siguiente contrato precarga. El cambio de gerente
 *      no pisa el historial: el anterior queda vigente=false.
 *   4. "CON DNI N°" estaba hardcodeado: un deudor con CE ahora sale
 *      "IDENTIFICADO CON CARNÉ DE EXTRANJERÍA N° ...".
 */
class ContratoTramoDTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede TramoD', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'tramo-d', 'headquarter_id' => $this->sede->id]));
    }

    private function cliente(array $extra = []): Client
    {
        static $n = 0;
        $n++;

        return Client::create(array_merge([
            'expediente' => (string) (9800 + $n),
            'nombre' => 'CLIENTE'.$n, 'apellido_pat' => 'TRAMO', 'apellido_mat' => 'DE',
            'tipo_documento' => 'DNI', 'documento' => (string) (46800000 + $n), 'sexo' => 'M',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'email' => "cliente{$n}@tramod.example.com",
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE',
            'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ], $extra));
    }

    private function credito(Client $c): Credit
    {
        $credit = Credit::create([
            'client_id' => $c->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id,
        ]);

        foreach ([1, 2, 3, 4] as $i) {
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $i,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($i - 1),
                'importe_cuota' => 1250, 'importe_interes' => 50, 'importe_excedente' => 0,
                'importe_aplicado' => 0, 'interes_aplicado' => 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
            ]);
        }

        return $credit;
    }

    private function vehiculo(Client $c, string $placa = 'TRD111'): Vehiculo
    {
        return Vehiculo::create([
            'client_id' => $c->id, 'placa' => $placa, 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-'.$placa, 'nro_motor' => 'MOT-'.$placa, 'valor' => 15000,
        ]);
    }

    // ── 1 · El pivote de copropiedad ──────────────────────────────────────

    public function test_la_pestana_vehiculos_vincula_y_quita_copropietarios(): void
    {
        $titular = $this->cliente();
        $copro = $this->cliente();
        $v = $this->vehiculo($titular);

        Livewire::test(Vehiculos::class, ['id' => $titular->id])
            ->call('abrirCopro', $v->id)
            ->call('vincularCopro', $v->id, $copro->id);

        $this->assertTrue($v->fresh()->copropietarios->contains($copro->id));
        $this->assertTrue($v->fresh()->perteneceA($copro->id));
        $this->assertTrue($v->fresh()->perteneceA($titular->id), 'el titular sigue siendo dueño');

        Livewire::test(Vehiculos::class, ['id' => $titular->id])
            ->call('quitarCopro', $v->id, $copro->id);

        $this->assertFalse($v->fresh()->copropietarios->contains($copro->id));
    }

    public function test_el_titular_no_puede_ser_su_propio_copropietario(): void
    {
        $titular = $this->cliente();
        $v = $this->vehiculo($titular);

        Livewire::test(Vehiculos::class, ['id' => $titular->id])
            ->call('vincularCopro', $v->id, $titular->id);

        $this->assertCount(0, $v->fresh()->copropietarios);
    }

    public function test_sin_pivote_el_unico_dueno_es_el_titular(): void
    {
        $titular = $this->cliente();
        $otro = $this->cliente();
        $v = $this->vehiculo($titular);

        $this->assertTrue($v->perteneceA($titular->id));
        $this->assertFalse($v->perteneceA($otro->id));
    }

    // ── 2 · El wizard con copropietario ───────────────────────────────────

    public function test_el_wizard_precarga_al_copropietario_como_codeudor(): void
    {
        $titular = $this->cliente();
        $copro = $this->cliente();
        $this->credito($titular);
        $v = $this->vehiculo($titular);
        $v->copropietarios()->attach($copro->id, ['rol' => 'copropietario']);

        $comp = Livewire::test(Documentos::class, ['id' => $titular->id])
            ->call('abrirModalContrato');

        $this->assertSame($copro->id, $comp->get('codeudorClientId'));
        $this->assertSame(mb_strtoupper($copro->fullName()), $comp->get('deudores')[1]['nombre']);

        // Con codeudor precargado, el selector ofrece los modelos de DOS deudores.
        $this->assertStringContainsString('a.3 GPS. Deudores', $comp->html());
        $this->assertStringNotContainsString('a.1 GPS. Deudor -', $comp->html());
    }

    public function test_se_emite_a3_con_el_vehiculo_compartido(): void
    {
        $titular = $this->cliente();
        $copro = $this->cliente(['sexo' => 'F']);
        $credit = $this->credito($titular);
        $v = $this->vehiculo($titular);
        $v->copropietarios()->attach($copro->id, ['rol' => 'copropietario']);

        Livewire::test(Documentos::class, ['id' => $titular->id])
            ->call('abrirModalContrato')
            ->set('contratoCreditoId', $credit->id)
            ->set('modeloContrato', 'a3')
            ->set('contratoVehiculos.0.vehiculo_id', $v->id)
            ->set('bancoDesembolso', 'bcp')
            ->call('generarContrato')
            ->assertDispatched('successAlert');

        $doc = DocumentoCliente::where('client_id', $titular->id)->where('tipo', 'contrato')->first();
        $this->assertNotNull($doc);
        $this->assertSame('a3', $doc->modelo);
    }

    public function test_el_vehiculo_del_codeudor_tambien_vale(): void
    {
        // El vehículo está a nombre del CODEUDOR (no del titular del crédito):
        // el caso que antes se rechazaba con "no pertenece al cliente".
        $titular = $this->cliente();
        $codeudor = $this->cliente();
        $credit = $this->credito($titular);
        $vAjeno = $this->vehiculo($codeudor, 'TRD222');

        $comp = Livewire::test(Documentos::class, ['id' => $titular->id])
            ->call('abrirModalContrato')
            ->call('seleccionarCodeudorContrato', $codeudor->id)
            ->set('contratoCreditoId', $credit->id)
            ->set('modeloContrato', 'a3')
            ->set('contratoVehiculos.0.vehiculo_id', $vAjeno->id)
            ->set('bancoDesembolso', 'bcp')
            ->call('generarContrato')
            ->assertDispatched('successAlert');
    }

    public function test_un_vehiculo_de_un_extrano_sigue_rechazandose(): void
    {
        $titular = $this->cliente();
        $extrano = $this->cliente();
        $credit = $this->credito($titular);
        $vAjeno = $this->vehiculo($extrano, 'TRD333');

        Livewire::test(Documentos::class, ['id' => $titular->id])
            ->call('abrirModalContrato')
            ->set('contratoCreditoId', $credit->id)
            ->set('modeloContrato', 'a1')
            ->set('contratoVehiculos.0.vehiculo_id', $vAjeno->id)
            ->set('bancoDesembolso', 'bcp')
            ->call('generarContrato')
            ->assertDispatched('errorAlert');

        $this->assertNull(DocumentoCliente::where('client_id', $titular->id)->where('tipo', 'contrato')->first());
    }

    // ── 3 · Empresa y representante persistidos ───────────────────────────

    private function emitirContratoEmpresa(Client $empresa, Credit $credit, Vehiculo $v, array $gerente): void
    {
        Livewire::test(Documentos::class, ['id' => $empresa->id])
            ->call('abrirModalContrato')
            ->set('contratoCreditoId', $credit->id)
            ->set('modeloContrato', 'a4')
            ->set('contratoVehiculos.0.vehiculo_id', $v->id)
            ->set('bancoDesembolso', 'bcp')
            ->set('empresa.partida', '15626194')
            ->set('empresa.oficina_registral', 'LIMA')
            ->set('gerente.nombre', $gerente['nombre'])
            ->set('gerente.dni', $gerente['dni'])
            ->set('gerente.sexo', $gerente['sexo'])
            ->set('gerente.nacionalidad', 'PERUANO')
            ->set('gerente.ocupacion', 'EMPRESARIO')
            ->set('gerente.estado_civil', 'CASADO')
            ->set('gerente.domicilio', 'AV. SALAVERRY 2900, DISTRITO DE MAGDALENA DEL MAR, PROVINCIA Y DEPARTAMENTO DE LIMA')
            ->call('generarContrato')
            ->assertDispatched('successAlert');
    }

    public function test_emitir_a4_persiste_la_empresa_y_su_gerente(): void
    {
        $empresa = $this->cliente(['tipo_documento' => 'RUC', 'documento' => '20601234567', 'nombre' => 'TRANSPORTES TD S.A.C.']);
        $credit = $this->credito($empresa);
        $v = $this->vehiculo($empresa, 'TRD444');

        $this->emitirContratoEmpresa($empresa, $credit, $v, [
            'nombre' => 'JACK BLAS SULLCA', 'dni' => '46964491', 'sexo' => 'M',
        ]);

        $ficha = ClientEmpresa::where('client_id', $empresa->id)->first();
        $this->assertNotNull($ficha, 'la ficha de empresa debe crearse al emitir');
        $this->assertSame('15626194', $ficha->partida_registral);

        $vigente = $ficha->representanteVigente;
        $this->assertNotNull($vigente);
        $this->assertSame('JACK BLAS SULLCA', $vigente->nombre);
        $this->assertSame('GERENTE GENERAL', $vigente->cargo);
    }

    public function test_el_segundo_contrato_precarga_la_empresa_sin_retipear(): void
    {
        $empresa = $this->cliente(['tipo_documento' => 'RUC', 'documento' => '20601234568', 'nombre' => 'TRANSPORTES TD2 S.A.C.']);
        $credit = $this->credito($empresa);
        $v = $this->vehiculo($empresa, 'TRD555');

        $this->emitirContratoEmpresa($empresa, $credit, $v, [
            'nombre' => 'JACK BLAS SULLCA', 'dni' => '46964491', 'sexo' => 'M',
        ]);

        // Reabrir el wizard: partida, oficina y gerente vienen de la ficha.
        $comp = Livewire::test(Documentos::class, ['id' => $empresa->id])
            ->call('abrirModalContrato');

        $this->assertSame('15626194', $comp->get('empresa')['partida']);
        $this->assertSame('LIMA', $comp->get('empresa')['oficina_registral']);
        $this->assertSame('JACK BLAS SULLCA', $comp->get('gerente')['nombre']);
        $this->assertSame('46964491', $comp->get('gerente')['dni']);
    }

    public function test_el_cambio_de_gerente_no_pisa_el_historial(): void
    {
        $empresa = $this->cliente(['tipo_documento' => 'RUC', 'documento' => '20601234569', 'nombre' => 'TRANSPORTES TD3 S.A.C.']);
        $credit = $this->credito($empresa);
        $v = $this->vehiculo($empresa, 'TRD666');

        $this->emitirContratoEmpresa($empresa, $credit, $v, [
            'nombre' => 'JACK BLAS SULLCA', 'dni' => '46964491', 'sexo' => 'M',
        ]);
        // El poder cambia de titular: gerenta nueva.
        $this->emitirContratoEmpresa($empresa, $credit, $v, [
            'nombre' => 'ROSA QUISPE MAMANI', 'dni' => '41752169', 'sexo' => 'F',
        ]);

        $ficha = ClientEmpresa::where('client_id', $empresa->id)->first();

        $this->assertSame(2, $ficha->representantes()->count(), 'el anterior queda como historial');
        $this->assertSame('ROSA QUISPE MAMANI', $ficha->representanteVigente->nombre);
        $this->assertSame('F', $ficha->representanteVigente->sexo);
        $this->assertFalse((bool) $ficha->representantes()->where('documento', '46964491')->first()->vigente);
    }

    public function test_reemitir_con_el_mismo_gerente_actualiza_sin_duplicar(): void
    {
        $empresa = $this->cliente(['tipo_documento' => 'RUC', 'documento' => '20601234570', 'nombre' => 'TRANSPORTES TD4 S.A.C.']);
        $credit = $this->credito($empresa);
        $v = $this->vehiculo($empresa, 'TRD777');

        $this->emitirContratoEmpresa($empresa, $credit, $v, [
            'nombre' => 'JACK BLAS SULLCA', 'dni' => '46964491', 'sexo' => 'M',
        ]);
        $this->emitirContratoEmpresa($empresa, $credit, $v, [
            'nombre' => 'JACK YELTSIN BLAS SULLCA', 'dni' => '46964491', 'sexo' => 'M',
        ]);

        $ficha = ClientEmpresa::where('client_id', $empresa->id)->first();

        $this->assertSame(1, $ficha->representantes()->count(), 'misma persona: se actualiza, no se duplica');
        $this->assertSame('JACK YELTSIN BLAS SULLCA', $ficha->representanteVigente->nombre);
    }

    // ── 4 · Tipo de documento del deudor ──────────────────────────────────

    public function test_un_deudor_con_ce_no_sale_como_dni(): void
    {
        $client = $this->cliente(['tipo_documento' => 'CE', 'documento' => '001234567', 'nacionalidad' => 'VENEZOLANO']);
        $credit = $this->credito($client);
        $v = $this->vehiculo($client, 'TRD888');

        $html = GeneradorContrato::previsualizar($client, $credit, [$v->id], 'a1', ['banco' => 'bcp']);

        $this->assertStringContainsString('IDENTIFICADO CON CARNÉ DE EXTRANJERÍA N° 001234567', $html);
        $this->assertStringContainsString('DE NACIONALIDAD VENEZOLANO', $html);
        $this->assertStringNotContainsString('CON DNI N° 001234567', $html);
        // En las firmas también.
        $this->assertStringContainsString('CARNÉ DE EXTRANJERÍA N° 001234567', $html);
    }

    public function test_un_deudor_con_dni_sigue_saliendo_dni(): void
    {
        $client = $this->cliente();
        $credit = $this->credito($client);
        $v = $this->vehiculo($client, 'TRD999');

        $html = GeneradorContrato::previsualizar($client, $credit, [$v->id], 'a1', ['banco' => 'bcp']);

        $this->assertStringContainsString('IDENTIFICADO CON DNI N° '.$client->documento, $html);
    }
}
