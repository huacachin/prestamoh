<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\SigmAviso;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Services\Documentos\GeneradorSigm;
use App\Services\Documentos\Ocr\FormularioSigmIlegible;
use App\Services\Documentos\Ocr\LectorDeFormularioSigm;
use App\Services\Documentos\Ocr\LectorFormularioSigmClaude;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Trello #137, punto 4 (13/11/2026): la captura del "Formulario de constitución" del SIGM se pega en
 * Documentos → SIGM → Registro y se llenan formulario, folio y fecha para revisarlos antes de registrar.
 * El lector va por interfaz: estos tests no gastan llamadas a la API.
 */
class LecturaFormularioSigmTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-13 10:00:00');
        config(['services.anthropic.habilitado' => true]);

        $sede = Headquarter::create(['name' => 'Sede Lectura SIGM', 'status' => 'active']);
        $this->actingAs(User::factory()->create(['username' => 'lee-sigm', 'headquarter_id' => $sede->id]));
        $this->client = Client::create([
            'expediente' => '9930', 'nombre' => 'ROSA LINDA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781900', 'sexo' => 'F', 'email' => 'rosa.p@example.com',
            'nacionalidad' => 'PERUANO', 'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO',
            'direccion' => 'AV. AREQUIPA 3400', 'distrito' => 'LINCE', 'provincia' => 'LIMA', 'departamento' => 'LIMA',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-11-01', 'importe' => 5000, 'cuotas' => 4,
            'tipo_planilla' => 1, 'interes' => 10, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
        CreditInstallment::create([
            'credit_id' => $this->credit->id, 'num_cuota' => 1, 'fecha_vencimiento' => Carbon::parse('2026-11-08'),
            'importe_cuota' => 5000, 'importe_interes' => 500, 'importe_excedente' => 0, 'importe_aplicado' => 0,
            'interes_aplicado' => 0, 'excedente_aplicado' => 0, 'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => false,
        ]);
        $vehiculo = Vehiculo::create([
            'client_id' => $this->client->id, 'placa' => 'LEE111', 'marca' => 'TOYOTA',
            'modelo' => 'HIACE', 'nro_serie' => 'SER-LE', 'nro_motor' => 'MOT-LE', 'valor' => 15000,
        ]);
        Storage::fake('public');
        GeneradorContrato::generar($this->client, $this->credit, [$vehiculo->id], 'a2', [
            'fecha' => '10/11/2026', 'banco' => 'bcp', 'valor_bien' => 20000,
            'deudores' => [[
                'sexo' => 'F', 'nombre' => 'QUISPE MAMANI ROSA LINDA', 'dni' => '46781900', 'nacionalidad' => 'PERUANO',
                'ocupacion' => 'COMERCIANTE', 'estado_civil' => 'CASADO', 'correo' => 'ROSA.P@EXAMPLE.COM',
                'domicilio' => 'CALLE LAS BEGONIAS 450, DISTRITO DE SAN ISIDRO, PROVINCIA Y DEPARTAMENTO DE LIMA',
            ]],
        ]);
    }

    /** @param  array<string, mixed>|FormularioSigmIlegible  $resultado */
    private function lectorDevuelve(array|FormularioSigmIlegible $resultado): void
    {
        $this->app->bind(LectorDeFormularioSigm::class, fn () => new class($resultado) implements LectorDeFormularioSigm
        {
            public function __construct(private array|FormularioSigmIlegible $resultado) {}

            public function leer(string $rutaAbsoluta): array
            {
                if ($this->resultado instanceof FormularioSigmIlegible) {
                    throw $this->resultado;
                }

                return $this->resultado + ['formulario' => '', 'folio' => '', 'fecha' => '', 'documentos' => [],
                    'esConstitucion' => true, 'dudas' => '', 'modelo' => 'modelo-de-prueba'];
            }
        });
    }

    private function pestanaRegistro(): Testable
    {
        return Livewire::test(Documentos::class, ['id' => $this->client->id])->call('abrirModalSigm');
    }

    private function captura(): UploadedFile
    {
        return UploadedFile::fake()->image('formulario-sigm.png', 800, 600);
    }

    public function test_la_captura_pegada_llena_formulario_folio_y_fecha_para_revisarlos(): void
    {
        $this->lectorDevuelve(['formulario' => '2026-928139', 'folio' => '20261107002358210', 'fecha' => '2026-11-07', 'documentos' => ['46781900']]);

        $c = $this->pestanaRegistro()
            ->assertSee('Pega la captura del formulario de constitución (Ctrl+V)')
            ->set('sigmImagen', $this->captura())
            ->assertSet('sigmFormulario', '2026-928139')
            ->assertSet('sigmFolio', '20261107002358210')
            ->assertSet('sigmFecha', '2026-11-07')
            ->assertSet('sigmImagen', null)
            ->assertSet('sigmAvisoLectura', '')
            ->assertDispatched('successAlert', ['message' => 'Datos leídos del formulario. Revísalos y pulsa "Registrar constitución".']);

        $this->assertSame(0, SigmAviso::count(), 'leer no registra: la persona revisa y pulsa el botón');
        $this->assertDatabaseHas('activity_log', ['description' => "Leyó el formulario de constitución SIGM del crédito #{$this->credit->id} con modelo-de-prueba: formulario 2026-928139, folio 20261107002358210, fecha 2026-11-07"]);

        $c->call('guardarRegistroSigm')->assertHasNoErrors();
        $this->assertSame(['2026-928139', '20261107002358210', '2026-11-07'], [
            SigmAviso::sole()->nro_formulario, SigmAviso::sole()->folio, SigmAviso::sole()->fecha_presentacion->toDateString(),
        ]);
    }

    public function test_avisa_otro_deudor_datos_que_faltan_y_dudas(): void
    {
        $this->lectorDevuelve(['formulario' => '2026-928139', 'folio' => '', 'fecha' => '2026-11-07',
            'documentos' => ['20615553141'], 'dudas' => 'el 5to dígito del formulario podría ser 8 o 3']);

        $this->pestanaRegistro()
            ->set('sigmFolio', 'lo-escrito-antes')
            ->set('sigmImagen', $this->captura())
            ->assertSet('sigmFormulario', '2026-928139')
            ->assertSet('sigmFolio', 'lo-escrito-antes')
            ->assertSet('sigmAvisoLectura', 'No se leyó: folio. Escríbelo a mano. El documento del deudor en la imagen (20615553141) no coincide con el de este contrato (46781900): ¿es el formulario de otro cliente? Dudas de la lectura: el 5to dígito del formulario podría ser 8 o 3')
            ->assertSee('¿es el formulario de otro cliente?');
    }

    public function test_otro_formulario_o_lectura_fallida_no_tocan_los_campos(): void
    {
        $this->lectorDevuelve(['formulario' => '2026-111111', 'folio' => '123', 'fecha' => '2026-11-07', 'esConstitucion' => false]);
        $this->pestanaRegistro()
            ->set('sigmImagen', $this->captura())
            ->assertSet('sigmFormulario', '')
            ->assertSet('sigmFolio', '')
            ->assertDispatched('errorAlert');

        $this->lectorDevuelve(new FormularioSigmIlegible('La lectura automática está saturada ahora mismo: espera un momento y vuelve a intentar.'));
        $this->pestanaRegistro()
            ->set('sigmImagen', $this->captura())
            ->assertSet('sigmFormulario', '')
            ->assertDispatched('errorAlert', ['message' => 'La lectura automática está saturada ahora mismo: espera un momento y vuelve a intentar. Escribe los datos a mano.']);

        $this->pestanaRegistro()
            ->set('sigmImagen', UploadedFile::fake()->create('formulario.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['sigmImagen' => 'image'])
            ->assertSet('sigmImagen', null);
    }

    public function test_registrada_de_otro_dia_no_se_lee_ni_se_muestra_la_zona(): void
    {
        config(['auditoria.eliminar_mismo_dia.activo' => true]);
        GeneradorSigm::registrar($this->client, $this->credit, '2026-905021', '20261027002239830', '2026-11-12');
        $this->travelTo('2026-11-14 09:00:00');
        $this->lectorDevuelve(['formulario' => '2026-928139', 'folio' => '20261107002358210', 'fecha' => '2026-11-07']);

        $this->pestanaRegistro()
            ->assertDontSee('Pega la captura del formulario de constitución')
            ->set('sigmImagen', $this->captura())
            ->assertSet('sigmFormulario', '2026-905021')
            ->assertDispatched('errorAlert');
    }

    public function test_el_lector_normaliza_lo_que_devuelve_el_modelo(): void
    {
        $respuesta = (object) ['stopReason' => 'end_turn', 'content' => [(object) ['type' => 'text', 'text' => json_encode([
            'formulario' => ' 2026 - 928139 ', 'folio' => '2026 1107 0023 5821 0', 'fecha' => '07/11/2026 12:59:30',
            'documentos' => ['RUC 20615553141', ''], 'es_constitucion' => true, 'dudas' => ' ',
        ])]]];
        $interpretar = new ReflectionMethod(LectorFormularioSigmClaude::class, 'interpretar');

        $this->assertSame([
            'formulario' => '2026-928139', 'folio' => '20261107002358210', 'fecha' => '2026-11-07',
            'documentos' => ['20615553141'], 'esConstitucion' => true, 'dudas' => '', 'modelo' => 'm',
        ], $interpretar->invoke(new LectorFormularioSigmClaude, $respuesta, 'm'));

        $respuesta->content[0]->text = json_encode(['formulario' => '', 'folio' => '', 'fecha' => '31/02/2026', 'documentos' => [], 'es_constitucion' => true, 'dudas' => '']);
        $this->assertSame('', $interpretar->invoke(new LectorFormularioSigmClaude, $respuesta, 'm')['fecha'], 'fecha imposible: vacía');

        $this->expectException(FormularioSigmIlegible::class);
        $interpretar->invoke(new LectorFormularioSigmClaude, (object) ['stopReason' => 'refusal', 'content' => []], 'm');
    }
}
