<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Documentos\GeneradorAnexo2;
use App\Support\Documentos\BancosVoucher;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;
use Throwable;

/**
 * F3 — Anexo 2 (constancia de entrega del monto de la obligación principal):
 * se emite cuando el dinero YA se transfirió al cliente. Transcribe el voucher
 * bancario según el catálogo banco × modalidad (BancosVoucher) y embebe la
 * foto del comprobante.
 *
 * Contrato de firmas de la spec F3 (los servicios se escriben en paralelo):
 *   GeneradorAnexo2::construirSnapshot($client, $credit, $datos) → array
 *   GeneradorAnexo2::previsualizar($client, $credit, $datos)     → HTML (medio 'previa')
 *   GeneradorAnexo2::generar($client, $credit, $datos)           → DocumentoCliente
 *
 * $datos = lo que deja el modal: 'banco', 'modalidad', 'campos' (valores de la
 * transcripción con las claves de BancosVoucher::campos), 'imagen_path'
 * (relativa al disk public, o null) y 'fecha' (d/m/Y del documento).
 *
 * Contrato del snapshot (claves EXACTAS):
 *   marca, fecha, cliente{nombre, documento_tipo, documento},
 *   credito{numero, monto}, banco, modalidad, titulo, banco_legal,
 *   transcripcion[[label, valor]...], imagen_path.
 */
class DocumentoAnexo2Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Credit $credit;

    /** PNG 1x1 válido (base64) para simular la foto del voucher sin depender de GD. */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * Mundo mínimo (mismo criterio que DocumentoAnexo1Test): sede y usuario
     * explícitos — el autoincrement de MySQL no se resetea entre tests, jamás
     * asumir id=1. Crédito activo de 5000 en 4 cuotas de 1300.
     */
    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);

        $this->user = User::factory()->create(['username' => 'anexo2-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);

        $this->client = Client::create([
            'expediente' => '9003',
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
    }

    private function generador(): GeneradorAnexo2
    {
        return app(GeneradorAnexo2::class);
    }

    /** Permiso de página (las rutas de descarga van bajo permission:clientes). */
    private function darPermisoClientes(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');
    }

    /**
     * $datos tal como los deja el modal: voucher BCP × transferencia con los
     * requeridos completos, monto que CUADRA con el crédito (5000) y sin foto.
     */
    private function datosVoucher(): array
    {
        return [
            'banco' => 'bcp',
            'modalidad' => 'transferencia',
            'campos' => [
                'monto' => '5,000.00',
                'fecha_hora' => '26/08/2026 10:00',
                'beneficiario' => 'NOMBRE',
                'cuenta_destino' => '****1234',
                'nro_operacion' => '00112233',
            ],
            'imagen_path' => null,
            'fecha' => '26/08/2026',
        ];
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

    public function test_snapshot_respeta_el_contrato(): void
    {
        $this->mundo();
        $datos = $this->datosVoucher();

        $d = $this->generador()->construirSnapshot($this->client, $this->credit, $datos);

        // Claves exactas del contrato, en cada nivel.
        $this->assertEqualsCanonicalizing(
            ['marca', 'fecha', 'cliente', 'credito', 'banco', 'modalidad', 'titulo', 'banco_legal', 'transcripcion', 'imagen_path'],
            array_keys($d)
        );
        $this->assertEqualsCanonicalizing(
            ['nombre', 'documento_tipo', 'documento'],
            array_keys($d['cliente'])
        );
        $this->assertEqualsCanonicalizing(['numero', 'monto'], array_keys($d['credito']));

        $this->assertSame(config('documentos.marca'), $d['marca']);
        $this->assertMatchesRegularExpression('~^\d{2}/\d{2}/\d{4}$~', $d['fecha']);
        $this->assertSame('26/08/2026', $d['fecha'], 'La fecha editable del modal manda sobre la del sistema.');

        // Cliente
        $this->assertStringContainsString('QUISPE', $d['cliente']['nombre']);
        $this->assertSame('DNI', $d['cliente']['documento_tipo']);
        $this->assertSame('46781234', $d['cliente']['documento']);

        // Crédito: numero = credits.id, monto float
        $this->assertEquals($this->credit->id, $d['credito']['numero']);
        $this->assertEqualsWithDelta(5000.0, $d['credito']['monto'], 0.001);

        // Voucher: claves del combo + derivados del catálogo (única fuente de verdad)
        $this->assertSame('bcp', $d['banco']);
        $this->assertSame('transferencia', $d['modalidad']);
        $this->assertSame(BancosVoucher::titulo('bcp', 'transferencia'), $d['titulo']);
        $this->assertSame(BancosVoucher::nombreLegal('bcp'), $d['banco_legal']);
        $this->assertNull($d['imagen_path']);

        // Transcripción no vacía, con labels EN EL ORDEN del catálogo (solo los
        // campos con valor) y pares label/valor listos para render.
        $this->assertNotEmpty($d['transcripcion']);

        $labelsEsperados = [];
        foreach (BancosVoucher::campos('bcp', 'transferencia') as $clave => [$label, $requerido]) {
            if (trim((string) ($datos['campos'][$clave] ?? '')) !== '') {
                $labelsEsperados[] = $label;
            }
        }
        $this->assertSame($labelsEsperados, array_column($d['transcripcion'], 'label'));

        foreach ($d['transcripcion'] as $fila) {
            $this->assertEqualsCanonicalizing(['label', 'valor'], array_keys($fila));
            $this->assertNotSame('', trim($fila['valor']));
        }
    }

    public function test_monto_descuadrado_bloquea(): void
    {
        $this->mundo();

        // El voucher dice 8,000.00 pero el crédito es de 5000: la constancia
        // jamás debe transcribir una entrega que no cuadra con la obligación.
        $datos = $this->datosVoucher();
        $datos['campos']['monto'] = '8,000.00';

        $e = $this->capturarExcepcion(
            fn () => $this->generador()->previsualizar($this->client, $this->credit, $datos)
        );

        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertStringContainsString('no coincide', $e->getMessage());
    }

    public function test_campos_requeridos_bloquean(): void
    {
        $this->mundo();

        // Sin N° de operación no hay voucher trazable: requerido por catálogo.
        $datos = $this->datosVoucher();
        unset($datos['campos']['nro_operacion']);

        $e = $this->capturarExcepcion(
            fn () => $this->generador()->previsualizar($this->client, $this->credit, $datos)
        );

        // El mensaje debe señalar el campo faltante por su LABEL del catálogo
        // (el label sale de BancosVoucher, nunca de un literal a ciegas).
        [$labelNroOperacion] = BancosVoucher::campos('bcp', 'transferencia')['nro_operacion'];
        $this->assertStringContainsString($labelNroOperacion, $e->getMessage());
    }

    public function test_generar_versiona_con_imagen(): void
    {
        $this->mundo();
        Storage::fake('public');

        // Foto del comprobante ya subida al disk public (patrón Gallery).
        $imagenPath = "documentos/cliente-{$this->client->id}/voucher-test.png";
        Storage::disk('public')->put($imagenPath, base64_decode(self::PNG_1X1));

        $datos = $this->datosVoucher();
        $datos['imagen_path'] = $imagenPath;

        $doc = $this->generador()->generar($this->client, $this->credit, $datos);

        $this->assertInstanceOf(DocumentoCliente::class, $doc);
        $this->assertSame('anexo2', $doc->tipo);
        $this->assertEquals(1, $doc->version);
        $this->assertSame('emitido', $doc->estado);
        $this->assertEquals($this->client->id, $doc->client_id);
        $this->assertEquals($this->credit->id, $doc->credit_id);
        $this->assertEquals($this->user->id, $doc->generado_por);

        // Snapshot congelado con la foto referenciada (relativa al disk public)
        $this->assertSame($imagenPath, $doc->snapshot['imagen_path']);
        $this->assertEquals($this->credit->id, $doc->snapshot['credito']['numero']);

        // PDF en el disco fake, bajo la carpeta del cliente, con hash del binario exacto
        $this->assertNotNull($doc->pdf_path);
        $this->assertStringContainsString("documentos/cliente-{$this->client->id}/", $doc->pdf_path);
        Storage::disk('public')->assertExists($doc->pdf_path);
        $contenido = Storage::disk('public')->get($doc->pdf_path);
        $this->assertStringStartsWith('%PDF', $contenido);
        $this->assertSame(hash('sha256', $contenido), $doc->sha256);

        // Regenerar jamás pisa: crea v2
        $doc2 = $this->generador()->generar($this->client, $this->credit, $datos);
        $this->assertEquals(2, $doc2->version);
        $this->assertSame('anexo2', $doc2->tipo);
        $this->assertSame(2, DocumentoCliente::where('tipo', 'anexo2')->count());
    }

    public function test_word_descarga_con_detalles(): void
    {
        $this->mundo();
        $this->darPermisoClientes();
        Storage::fake('public');

        $doc = $this->generador()->generar($this->client, $this->credit, $this->datosVoucher());

        // Word al vuelo desde el snapshot, misma ruta que Anexo 1 y Contrato
        $word = $this->get(route('clients.documentos.word', $doc->id));
        $word->assertOk();
        $word->assertSee('CONSTANCIA DE ENTREGA');
        $word->assertSee('DETALLES');
    }
}
