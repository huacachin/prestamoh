<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Support\Documentos\BancosVoucher;
use App\Support\Documentos\Genero;
use App\Support\Documentos\ModelosContrato;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Alineación con la ESPEC FINAL del área ("a. Guia simple de Modelos
 * Contratos SIGM", Desktop/sistema-legal, 28/08) + las tres reglas
 * confirmadas por el negocio:
 *
 *   1. El plural es SIEMPRE "LOS DEUDORES", también con dos mujeres (no
 *      existe maestra de dos deudoras).
 *   2. La nacionalidad no flexiona (ya cubierto en ContratoTramoBTest).
 *   3. El copropietario se trata como codeudor, sin caso aparte (tramo D).
 *
 * Y lo que la guía volvió especificación:
 *   - custodia se muestra como c.1/c.2/c.3 (la clave a16/a26/a36 no cambia);
 *   - el tercero lleva banco y "cuenta o CCI";
 *   - el depósito al gerente (a.4.1) exige banco y cuenta personal;
 *   - el bien futuro suma el estado registral a fecha/kárdex/notario;
 *   - el catálogo de estado civil es cerrado de 4 (sin CONVIVIENTE);
 *   - el gerente puede identificarse con CE.
 */
class ContratoEspecFinalTest extends TestCase
{
    use RefreshDatabase;

    private Headquarter $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sede = Headquarter::create(['name' => 'Sede Espec', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'espec-final', 'headquarter_id' => $this->sede->id]));
    }

    // ── 1 · Plural siempre masculino ──────────────────────────────────────

    public function test_dos_mujeres_salen_los_deudores(): void
    {
        $g = Genero::conjunto(['F', 'F']);

        $this->assertSame('LOS DEUDORES', $g->deudor());
        $this->assertSame('DEUDORES', $g->deudorSolo());
        $this->assertSame('IDENTIFICADOS', mb_strtoupper($g->identificado()));

        // Y el singular femenino no se toca.
        $this->assertSame('LA DEUDORA', Genero::conjunto(['F'])->deudor());
    }

    // ── 2 · Custodia con los nombres de la guía ───────────────────────────

    public function test_custodia_se_muestra_como_c1_c2_c3(): void
    {
        $this->assertSame('c.1 Custodia. Deudor - Contrato SIGM', ModelosContrato::get('a16')['nombre']);
        $this->assertSame('c.2 Custodia. Deudora - Contrato SIGM', ModelosContrato::get('a26')['nombre']);
        $this->assertSame('c.3 Custodia. Deudores - Contrato SIGM', ModelosContrato::get('a36')['nombre']);
    }

    // ── 3 · El guard con los campos nuevos de la guía ─────────────────────

    /** @return array{0: Client, 1: Credit, 2: Vehiculo} */
    private function mundo(array $extra = []): array
    {
        static $n = 0;
        $n++;

        $client = Client::create(array_merge([
            'expediente' => (string) (9950 + $n), 'nombre' => 'ESPEC'.$n,
            'apellido_pat' => 'FINAL', 'apellido_mat' => 'TEST',
            'tipo_documento' => 'DNI', 'documento' => (string) (46900000 + $n), 'sexo' => 'M',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'email' => "espec{$n}@example.com",
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE',
            'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ], $extra));

        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-08-25',
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

        $v = Vehiculo::create([
            'client_id' => $client->id, 'placa' => 'ESP'.(100 + $n), 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-ESP'.$n, 'nro_motor' => 'MOT-ESP'.$n, 'valor' => 15000,
        ]);

        return [$client, $credit, $v];
    }

    public function test_el_tercero_exige_banco(): void
    {
        [$client, $credit, $v] = $this->mundo();

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a11', [
            'banco' => 'bcp',
            'tercero' => ['nombre' => 'CARLOS HUAMAN', 'dni' => '74218017', 'cuenta' => '123', 'motivo' => 'X'],
        ]));

        $this->assertStringContainsString('banco del tercero', $texto);
    }

    public function test_el_deposito_al_gerente_exige_su_banco_y_cuenta(): void
    {
        [$client, $credit, $v] = $this->mundo(['tipo_documento' => 'RUC', 'documento' => '20609999991']);

        $empresa = [
            'razon_social' => 'ESPEC S.A.C.', 'ruc' => '20609999991', 'partida' => '111',
            'oficina_registral' => 'LIMA', 'correo' => 'E@E.COM',
            'domicilio' => 'AV. X, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            'gerente' => [
                'nombre' => 'JACK BLAS', 'dni' => '46964491', 'sexo' => 'M', 'nacionalidad' => 'PERUANO',
                'ocupacion' => 'EMPRESARIO', 'estado_civil' => 'SOLTERO',
                'domicilio' => 'AV. X, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
            ],
        ];

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a41', [
            'banco' => 'bcp', 'empresa' => $empresa,
        ]));
        $this->assertStringContainsString('banco de su cuenta personal', $texto);
        $this->assertStringContainsString('cuenta personal', $texto);

        // Con banco y cuenta, pasa; y a.4 (depósito a la empresa) no los pide.
        $empresa['gerente'] += ['banco' => 'INTERBANK', 'cuenta' => '200-1'];
        $ok = GeneradorContrato::validar($client, $credit, [$v->id], 'a41', ['banco' => 'bcp', 'empresa' => $empresa]);
        $this->assertSame([], $ok, implode(' | ', $ok));
    }

    public function test_el_bien_futuro_exige_estado_registral(): void
    {
        [$client, $credit, $v] = $this->mundo();

        $texto = implode(' | ', GeneradorContrato::validar($client, $credit, [$v->id], 'a14', [
            'banco' => 'bcp',
            'bienes' => [$v->id => [
                'es_futuro' => true, 'fecha_acta' => '2026-05-04',
                'kardex' => '0373-2026', 'notario' => 'JULIO BLAS',
                // sin estado_registral
            ]],
        ]));

        $this->assertStringContainsString('estado registral', $texto);
    }

    public function test_un_gerente_con_ce_no_sale_como_dni(): void
    {
        [$client, $credit, $v] = $this->mundo(['tipo_documento' => 'RUC', 'documento' => '20609999992']);

        $html = GeneradorContrato::previsualizar($client, $credit, [$v->id], 'a4', [
            'banco' => 'bcp',
            'empresa' => [
                'razon_social' => 'ESPEC2 S.A.C.', 'ruc' => '20609999992', 'partida' => '111',
                'oficina_registral' => 'LIMA', 'correo' => 'E2@E.COM',
                'domicilio' => 'AV. X, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'gerente' => [
                    'nombre' => 'NICOLAS MADURO PEREZ', 'tipo_documento' => 'CE', 'dni' => '001234567',
                    'sexo' => 'M', 'nacionalidad' => 'VENEZOLANO', 'ocupacion' => 'EMPRESARIO',
                    'estado_civil' => 'SOLTERO',
                    'domicilio' => 'AV. X, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
                ],
            ],
        ]);

        $this->assertStringContainsString('IDENTIFICADO CON CARNÉ DE EXTRANJERÍA N° 001234567', $html);
        $this->assertStringNotContainsString('CON DNI N° 001234567', $html);
    }

    // ── 3b · Consulta de documento en el wizard (gerente y tercero) ───────

    public function test_el_gerente_hereda_de_la_ficha_si_esta_registrado(): void
    {
        [$client] = $this->mundo(['tipo_documento' => 'RUC', 'documento' => '20609999993']);
        // La gerenta ya es cliente registrada: ocupación, sexo, estado civil,
        // nacionalidad y domicilio se HEREDAN de su ficha.
        Client::create([
            'expediente' => '9970', 'nombre' => 'GERENTA', 'apellido_pat' => 'REGISTRADA', 'apellido_mat' => 'YA',
            'tipo_documento' => 'DNI', 'documento' => '41752169', 'sexo' => 'F',
            'nacionalidad' => 'VENEZOLANO', 'ocupacion' => 'independiente', 'estado_civil' => 'casado',
            'direccion' => 'JR. LOS ALPES 69', 'distrito' => 'BELLAVISTA',
            'provincia' => 'CALLAO', 'departamento' => 'CALLAO',
            'headquarter_id' => $this->sede->id, 'status' => 'active',
        ]);

        $comp = Livewire::test(Documentos::class, ['id' => $client->id])
            ->call('abrirModalContrato')
            ->set('gerente.dni', '41752169')
            ->call('consultarDocGerente');

        $g = $comp->get('gerente');
        // fullName() = apellidos + nombres (convención de la casa)
        $this->assertSame('REGISTRADA YA GERENTA', $g['nombre']);
        $this->assertSame('F', $g['sexo']);
        $this->assertSame('VENEZOLANO', $g['nacionalidad']);
        $this->assertSame('INDEPENDIENTE', $g['ocupacion']);
        $this->assertSame('CASADO', $g['estado_civil']);
        $this->assertStringContainsString('PROVINCIA CONSTITUCIONAL DEL CALLAO', $g['domicilio']);
        // Y todo lo heredado queda marcado en rojo.
        $this->assertEqualsCanonicalizing(
            ['nombre', 'sexo', 'nacionalidad', 'ocupacion', 'estado_civil', 'domicilio'],
            $comp->get('autoGerente')
        );
    }

    public function test_el_gerente_cae_a_la_api_si_no_esta_registrado(): void
    {
        config(['services.factiliza.token' => 'token-de-prueba']);
        Http::fake(['*/dni/info/*' => Http::response([
            'success' => true,
            'data' => [
                'nombres' => 'JACK YELTSIN', 'apellido_paterno' => 'BLAS', 'apellido_materno' => 'SULLCA',
                'direccion' => 'AV. SALAVERRY 2900', 'distrito' => 'MAGDALENA DEL MAR',
                'provincia' => 'LIMA', 'departamento' => 'LIMA', 'sexo' => 'M', 'estado_civil' => 'SOLTERO',
            ],
        ])]);

        [$client] = $this->mundo(['tipo_documento' => 'RUC', 'documento' => '20609999994']);

        $comp = Livewire::test(Documentos::class, ['id' => $client->id])
            ->call('abrirModalContrato')
            ->set('gerente.dni', '46964491')
            ->call('consultarDocGerente');

        $g = $comp->get('gerente');
        $this->assertSame('JACK YELTSIN BLAS SULLCA', $g['nombre']);
        $this->assertStringContainsString('PROVINCIA Y DEPARTAMENTO DE LIMA', $g['domicilio']);
        $this->assertSame('SOLTERO', $g['estado_civil'], 'el estado civil de la API fluye al gerente');
        $this->assertContains('nombre', $comp->get('autoGerente'));
        $this->assertContains('estado_civil', $comp->get('autoGerente'));
    }

    public function test_el_tercero_consulta_su_dni(): void
    {
        config(['services.factiliza.token' => 'token-de-prueba']);
        Http::fake(['*/dni/info/*' => Http::response([
            'success' => true,
            'data' => ['nombres' => 'CARLOS ALEXIS', 'apellido_paterno' => 'HUAMAN', 'apellido_materno' => 'FLORES'],
        ])]);

        [$client] = $this->mundo();

        $comp = Livewire::test(Documentos::class, ['id' => $client->id])
            ->call('abrirModalContrato')
            ->set('tercero.dni', '74218017')
            ->call('consultarDocTercero');

        $this->assertSame('CARLOS ALEXIS HUAMAN FLORES', $comp->get('tercero')['nombre']);
        $this->assertSame(['nombre'], $comp->get('autoTercero'));
    }

    // ── 4 · Catálogo de estado civil cerrado ──────────────────────────────

    public function test_el_wizard_ya_no_ofrece_conviviente(): void
    {
        $this->assertArrayNotHasKey('CONVIVIENTE', Documentos::ESTADOS_CIVILES);
        $this->assertSame(
            ['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO'],
            array_keys(Documentos::ESTADOS_CIVILES)
        );
    }

    // ── 5 · Los combos nuevos del set final de vouchers ───────────────────

    public function test_los_combos_del_set_final_existen(): void
    {
        $combos = BancosVoucher::combosDisponibles();

        $this->assertContains('transferencia_interbancaria', $combos['bcp']);
        $this->assertContains('transferencia_datos', $combos['bcp']);
        $this->assertContains('detalle_movimiento', $combos['bbva']);
        $this->assertContains('deposito_descripcion', $combos['caja_huancayo']);
        $this->assertContains('constancia', $combos['interbank']);
    }
}
