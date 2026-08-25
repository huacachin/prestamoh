<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorContrato;
use App\Support\Documentos\ModelosContrato;
use App\Support\Documentos\Ordinales;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Throwable;

/**
 * F2 — Contrato de garantía mobiliaria SUELTO (sin Anexos 1 y 2): catálogo de
 * los 32 modelos del área (presets), previsualización con flexión de género y
 * renumeración de cláusulas, emisión versionada (snapshot + PDF + sha256) y
 * descarga Word desde la ficha del cliente.
 *
 * Contrato de firmas de la spec F2 (los servicios se escriben en paralelo):
 *   ModelosContrato::todos()                      → los 32 presets
 *   ModelosContrato::get('a1')                    → preset {gps, custodia, destino, personas, bienes, ...}
 *   GeneradorContrato::construirSnapshot($client, $credit, $datos) → array
 *   GeneradorContrato::previsualizar($client, $credit, $datos)     → HTML (medio 'previa')
 *   GeneradorContrato::generar($client, $credit, $datos)           → DocumentoCliente
 *   GeneradorContrato::vmDesdeSnapshot($snapshot)                  → vm (API del ContratoViewModel)
 *
 * $datos = lo que deja el wizard: 'modelo' (clave del preset), 'deudores' []
 * (campos EDITABLES prellenados con la ficha), 'vehiculoIds' [], y 'montos'
 * (si se omiten, rigen los defaults del sistema: valor del bien = valor del
 * vehículo, obligación = importe del crédito). Validación SUAVE: solo bloquea
 * lo imposible (deudor sin nombre, sin vehículo con placa, sin cronograma).
 */
class DocumentoContratoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    /**
     * Mundo mínimo (mismo criterio que DocumentoAnexo1Test): sede y usuario
     * explícitos — el autoincrement de MySQL no se resetea entre tests, jamás
     * asumir id=1. Cliente MUJER (sexo F) para probar la flexión LA DEUDORA /
     * IDENTIFICADA; crédito activo de 4 cuotas de 1300; vehículo con placa y
     * valor 15000 (default del monto del bien en el contrato).
     */
    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);

        $this->user = User::factory()->create(['username' => 'contrato-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);

        $this->client = Client::create([
            'expediente' => '9002',
            'nombre' => 'ROSA LINDA',
            'apellido_pat' => 'QUISPE',
            'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI',
            'documento' => '46781234',
            'sexo' => 'F',
            'direccion' => 'MZ. B LOTE 12 AAHH LOS JARDINES',
            'distrito' => 'ATE',
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
            'celular1' => '987654321',
            'email' => 'rosa.quispe@example.com',
            'headquarter_id' => $sede->id,
            'status' => 'active',
        ]);

        $this->credit = Credit::create([
            'client_id' => $this->client->id,
            'fecha_prestamo' => '2026-08-25',
            'importe' => 5000,
            'cuotas' => 4,
            'tipo_planilla' => 1, // Semanal
            'interes' => 10,
            'situacion' => 'Activo',
            'estado' => 1,
            'headquarter_id' => $sede->id,
        ]);

        foreach ([1, 2, 3, 4] as $n) {
            CreditInstallment::create([
                'credit_id' => $this->credit->id,
                'num_cuota' => $n,
                'fecha_vencimiento' => Carbon::parse('2026-09-01')->addWeeks($n - 1),
                'importe_cuota' => 1250,
                'importe_interes' => 50, // cuota total = 1300
                'importe_excedente' => 0,
                'importe_aplicado' => 0,
                'interes_aplicado' => 0,
                'excedente_aplicado' => 0,
                'importe_mora' => 0,
                'mora_interes' => 0,
                'pagado' => false,
            ]);
        }

        $this->vehiculo = Vehiculo::create([
            'client_id' => $this->client->id,
            'placa' => 'ABC123',
            'marca' => 'TOYOTA',
            'modelo' => 'HILUX',
            'nro_serie' => 'SER-778899',
            'valor' => 15000,
        ]);
    }

    private function generador(): GeneradorContrato
    {
        return app(GeneradorContrato::class);
    }

    /** Permiso de página (las rutas de descarga van bajo permission:clientes). */
    private function darPermisoClientes(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');
    }

    /**
     * $datos tal como los dejaría el wizard: modelo elegido, deudor prellenado
     * con la ficha (todos EDITABLES — van al snapshot como queden) y el
     * vehículo seleccionado. Los 'montos' se omiten a propósito para ejercitar
     * los defaults del sistema.
     */
    private function datosWizard(string $modelo): array
    {
        return [
            'deudores' => [[
                'sexo' => 'F',
                'nombre' => mb_strtoupper($this->client->fullName()),
                'dni' => $this->client->documento,
                'nacionalidad' => 'PERUANO', // la vista flexiona: PERUANA
                'ocupacion' => 'COMERCIANTE',
                'estado_civil' => 'SOLTERA',
                'domicilio' => 'MZ. B LOTE 12 AAHH LOS JARDINES, DISTRITO DE ATE, PROVINCIA Y DEPARTAMENTO DE LIMA',
                'correo' => mb_strtoupper((string) $this->client->email),
            ]],
        ];
    }

    /**
     * Ordinal esperado de la cláusula de REPRESENTANTES según el preset: las 8
     * cláusulas base fijas (datos, objeto, bien, declaración, valor,
     * obligación, vigencia, ejecución) + gps y/o custodia SOLO si el preset
     * las activa. El orden lo fija el texto legal de los modelos del área;
     * el nombre del ordinal sale de Ordinales, nunca de un literal a ciegas.
     */
    private function ordinalRepresentantes(mixed $preset): string
    {
        $posicion = 8
            + (data_get($preset, 'gps') ? 1 : 0)
            + (data_get($preset, 'custodia') ? 1 : 0)
            + 1;

        return Ordinales::ordinal($posicion);
    }

    /** Ejecuta $fn esperando una excepción; la devuelve para inspeccionar el mensaje. */
    private function capturarExcepcion(callable $fn): Throwable
    {
        try {
            $fn();
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('Se esperaba una excepción de validación y no se lanzó ninguna.');
    }

    public function test_catalogo_tiene_32_modelos(): void
    {
        $this->assertCount(32, ModelosContrato::todos());

        // a1 — contrato base: CON GPS, sin custodia
        $a1 = ModelosContrato::get('a1');
        $this->assertTrue((bool) data_get($a1, 'gps'), 'a1 debe traer GPS.');
        $this->assertFalse((bool) data_get($a1, 'custodia'), 'a1 no lleva custodia.');

        // a16 — con posesión (custodia), sin GPS
        $a16 = ModelosContrato::get('a16');
        $this->assertTrue((bool) data_get($a16, 'custodia'), 'a16 debe traer custodia.');
        $this->assertFalse((bool) data_get($a16, 'gps'), 'a16 no lleva GPS.');

        // b1 — serie B: sin GPS
        $this->assertFalse((bool) data_get(ModelosContrato::get('b1'), 'gps'), 'b1 no lleva GPS.');

        // a41 — persona jurídica con desembolso al gerente
        $a41 = ModelosContrato::get('a41');
        $this->assertEquals('gerente', data_get($a41, 'destino'));
        $this->assertEquals('empresa', data_get($a41, 'personas'));

        // a32 — dos deudores, dos bienes presentes
        $a32 = ModelosContrato::get('a32');
        $this->assertEquals(2, data_get($a32, 'personas'));
        $this->assertEquals('2presentes', data_get($a32, 'bienes'));

        // Clave inexistente: excepción (el selector jamás manda claves libres)
        $lanzo = null;
        try {
            ModelosContrato::get('modelo-inexistente');
        } catch (Throwable $e) {
            $lanzo = $e;
        }
        $this->assertNotNull($lanzo, 'ModelosContrato::get() con clave inexistente debe lanzar excepción.');
    }

    public function test_previsualizar_contrato_base_flexiona_genero(): void
    {
        $this->mundo();

        $html = $this->generador()->previsualizar($this->client, $this->credit, [$this->vehiculo->id], 'a1', $this->datosWizard('a1'));

        // Flexión F de la deudora (los Word del área traían "GINA ... IDENTIFICADO")
        $this->assertStringContainsString('LA DEUDORA', $html);
        $this->assertStringContainsString('IDENTIFICADA', $html);

        // a1 trae GPS: la cláusula está presente
        $this->assertStringContainsString('DISPOSITIVO GPS', $html);

        // Nombre de la ficha en mayúsculas y valor del bien por defecto (vehículo 15000)
        $this->assertStringContainsString(mb_strtoupper($this->client->fullName()), $html);
        $this->assertStringContainsString('S/ 15,000.00', $html);
    }

    public function test_sin_gps_renumera(): void
    {
        $this->mundo();

        $preset = ModelosContrato::get('b1');

        $html = $this->generador()->previsualizar($this->client, $this->credit, [$this->vehiculo->id], 'b1', $this->datosWizard('b1'));

        // Sin GPS: la cláusula desaparece por completo
        $this->assertStringNotContainsString('DISPOSITIVO GPS', $html);

        // ... y las siguientes se renumeran solas (en Word esto se hacía a mano)
        $esperado = $this->ordinalRepresentantes($preset);
        $this->assertSame('NOVENO', $esperado, 'b1 sin gps ni custodia: representantes cae en la posición 9.');
        $this->assertStringContainsString($esperado.': REPRESENTANTES PARA LA EJECUCIÓN', $html);
        $this->assertStringNotContainsString('DÉCIMO: REPRESENTANTES PARA LA EJECUCIÓN', $html);
    }

    public function test_generar_versiona_y_word_descarga(): void
    {
        $this->mundo();
        $this->darPermisoClientes();
        Storage::fake('public');

        $doc = $this->generador()->generar($this->client, $this->credit, [$this->vehiculo->id], 'a1', $this->datosWizard('a1'));

        $this->assertInstanceOf(DocumentoCliente::class, $doc);
        $this->assertSame('contrato', $doc->tipo);
        $this->assertSame('a1', $doc->modelo);
        $this->assertEquals(1, $doc->version);
        $this->assertSame('emitido', $doc->estado);
        $this->assertEquals($this->client->id, $doc->client_id);
        $this->assertEquals($this->credit->id, $doc->credit_id);
        $this->assertEquals($this->user->id, $doc->generado_por);

        // PDF en el disco fake, bajo la carpeta del cliente, con hash del binario exacto
        $this->assertNotNull($doc->pdf_path);
        $this->assertStringContainsString("documentos/cliente-{$this->client->id}/", $doc->pdf_path);
        Storage::disk('public')->assertExists($doc->pdf_path);
        $contenido = Storage::disk('public')->get($doc->pdf_path);
        $this->assertStringStartsWith('%PDF', $contenido);
        $this->assertSame(hash('sha256', $contenido), $doc->sha256);

        // El snapshot congelado reconstruye el MISMO vm (reimpresión exacta)
        $this->assertIsArray($doc->snapshot);
        $this->assertNotEmpty($doc->snapshot);
        $vm = $this->generador()->vmDesdeSnapshot($doc->snapshot);
        $this->assertTrue($vm->gps, 'a1 emite con GPS.');
        $this->assertSame(
            $this->ordinalRepresentantes(ModelosContrato::get('a1')),
            $vm->ord->de('representantes'),
            'a1 con GPS: representantes se corre a la posición 10 (DÉCIMO).'
        );

        // Regenerar jamás pisa: crea v2
        $doc2 = $this->generador()->generar($this->client, $this->credit, [$this->vehiculo->id], 'a1', $this->datosWizard('a1'));
        $this->assertEquals(2, $doc2->version);
        $this->assertSame('contrato', $doc2->tipo);
        $this->assertSame(2, DocumentoCliente::where('tipo', 'contrato')->count());

        // Word al vuelo desde el snapshot, misma ruta que el Anexo 1
        $word = $this->get(route('clients.documentos.word', $doc->id));
        $word->assertOk();
        $word->assertSee('GARANTÍA MOBILIARIA');
        $word->assertSee(mb_strtoupper($this->client->fullName()));
    }

    public function test_validacion_bloquea_lo_imposible(): void
    {
        $this->mundo();
        Storage::fake('public');

        // La spec F2 no fija el TIPO exacto de la excepción de validación del
        // servicio (en el área legal fue una ValidacionContratoException con la
        // lista de errores): aquí se exige un Throwable cuyo mensaje señale el
        // campo bloqueante — validación SUAVE, solo lo imposible bloquea.

        // 1) Deudor sin nombre: el contrato no sabría a quién obliga. Un
        // override vacío hace fallback a la ficha (diseño correcto), así que
        // para el caso imposible hay que vaciar también la ficha.
        $this->client->update(['nombre' => '', 'apellido_pat' => '', 'apellido_mat' => '']);
        $datos = $this->datosWizard('a1');
        $datos['deudores'][0]['nombre'] = '';
        $e = $this->capturarExcepcion(
            fn () => $this->generador()->generar($this->client, $this->credit, [$this->vehiculo->id], 'a1', $datos)
        );
        $this->assertMatchesRegularExpression('/nombre|deudor/iu', $e->getMessage());

        // 2) Sin vehículo seleccionado: no hay bien que gravar.
        $e = $this->capturarExcepcion(
            fn () => $this->generador()->generar($this->client, $this->credit, [], 'a1', $this->datosWizard('a1'))
        );
        $this->assertMatchesRegularExpression('/veh[ií]culo|bien|placa/iu', $e->getMessage());

        // Nada llegó a emitirse: la validación corta ANTES de persistir.
        $this->assertSame(0, DocumentoCliente::count());
    }

    /**
     * Regresión (26/08): el wizard envía la fecha 'd/m/Y' y Carbon::parse la
     * interpretaba como m/d/Y — con día > 12 reventaba la vista previa
     * ("Could not parse '24/08/2026'").
     */
    public function test_fecha_en_formato_del_wizard_no_revienta(): void
    {
        $this->mundo();

        $datos = $this->datosWizard('a1');
        $datos['fecha'] = '24/08/2026';

        $html = $this->generador()->previsualizar($this->client, $this->credit, [$this->vehiculo->id], 'a1', $datos);

        $this->assertStringContainsString('24 DE AGOSTO DEL 2026', $html);
    }
}
