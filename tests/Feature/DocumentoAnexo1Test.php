<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo1;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F1 — Anexo 1 (cronograma de pagos): snapshot congelado, versionado con PDF
 * en disco y descargas PDF/Word desde la ficha del cliente.
 *
 * Regla de oro: el cronograma SIEMPRE sale de credit_installments ordenado por
 * num_cuota (la relación NO ordena), y la cuota "del documento" es la moda de
 * las cuotas usando claves string (countBy con float trunca).
 */
class DocumentoAnexo1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Credit $credit;

    private Vehiculo $vehiculo;

    /**
     * Mundo mínimo. La sede se crea explícita y se asigna al usuario porque el
     * autoincrement de MySQL no se resetea entre tests: jamás asumir id=1.
     */
    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);

        $this->user = User::factory()->create(['username' => 'doc-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);

        $this->client = Client::create([
            'expediente' => '9001',
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

        // Cuotas insertadas DESORDENADAS (3,1,4,2) a propósito: el cronograma
        // del documento debe salir ordenado por num_cuota, no por id.
        foreach ([3, 1, 4, 2] as $n) {
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
            // sin valor: el Anexo 1 debe poder emitirse igual (valor null)
        ]);
    }

    private function generador(): GeneradorAnexo1
    {
        return app(GeneradorAnexo1::class);
    }

    /** Permiso de página (las rutas van bajo permission:clientes). */
    private function darPermisoClientes(): void
    {
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');
    }

    public function test_construir_snapshot_respeta_el_contrato(): void
    {
        $this->mundo();

        $d = $this->generador()->construirSnapshot($this->client, $this->credit, $this->vehiculo);

        // Claves exactas del contrato, en cada nivel.
        $this->assertEqualsCanonicalizing(
            ['marca', 'fecha', 'cliente', 'vehiculo', 'credito', 'cronograma'],
            array_keys($d)
        );
        $this->assertEqualsCanonicalizing(
            ['nombre', 'documento_tipo', 'documento', 'domicilio', 'celular', 'correo'],
            array_keys($d['cliente'])
        );
        $this->assertEqualsCanonicalizing(
            ['numero', 'moneda', 'monto', 'frecuencia', 'cuotas', 'cuota'],
            array_keys($d['credito'])
        );
        $this->assertEqualsCanonicalizing(['filas', 'total'], array_keys($d['cronograma']));
        $this->assertEqualsCanonicalizing(
            ['placa', 'marca', 'modelo', 'nro_serie', 'valor'],
            array_keys($d['vehiculo'])
        );

        $this->assertSame(config('documentos.marca'), $d['marca']);
        $this->assertMatchesRegularExpression('~^\d{2}/\d{2}/\d{4}$~', $d['fecha']);

        // Cliente
        $this->assertStringContainsString('QUISPE', $d['cliente']['nombre']);
        $this->assertSame('46781234', $d['cliente']['documento']);
        $this->assertStringContainsString('DISTRITO DE', $d['cliente']['domicilio']);
        $this->assertSame('987654321', $d['cliente']['celular']);
        $this->assertSame('rosa.quispe@example.com', $d['cliente']['correo']);

        // Vehículo sin valor declarado
        $this->assertSame('ABC123', $d['vehiculo']['placa']);
        $this->assertNull($d['vehiculo']['valor']);

        // Crédito: numero = credits.id; cuota = moda con clave string (1300.00)
        $this->assertEquals($this->credit->id, $d['credito']['numero']);
        $this->assertEqualsWithDelta(5000.0, $d['credito']['monto'], 0.001);
        $this->assertEquals(4, $d['credito']['cuotas']);
        $this->assertStringContainsStringIgnoringCase('semanal', $d['credito']['frecuencia']);
        $this->assertEqualsWithDelta(1300.0, $d['credito']['cuota'], 0.001);

        // Cronograma: 4 filas ORDENADAS 1..4 aunque se insertaron 3,1,4,2
        $filas = $d['cronograma']['filas'];
        $this->assertCount(4, $filas);
        $this->assertEqualsCanonicalizing(['n', 'fecha', 'monto'], array_keys($filas[0]));
        $this->assertEquals([1, 2, 3, 4], array_column($filas, 'n'));
        foreach ($filas as $fila) {
            $this->assertEqualsWithDelta(1300.0, $fila['monto'], 0.001);
        }
        $this->assertEqualsWithDelta(5200.0, $d['cronograma']['total'], 0.001);
    }

    public function test_generar_crea_documento_versionado_con_pdf(): void
    {
        $this->mundo();
        Storage::fake('public');

        $doc = $this->generador()->generar($this->client, $this->credit, $this->vehiculo);

        $this->assertInstanceOf(DocumentoCliente::class, $doc);
        $this->assertSame('anexo1', $doc->tipo);
        $this->assertEquals(1, $doc->version);
        $this->assertSame('emitido', $doc->estado);
        $this->assertEquals($this->client->id, $doc->client_id);
        $this->assertEquals($this->credit->id, $doc->credit_id);
        $this->assertEquals($this->user->id, $doc->generado_por);

        // Snapshot congelado con el contrato
        $d = $doc->snapshot;
        $this->assertEqualsCanonicalizing(
            ['marca', 'fecha', 'cliente', 'vehiculo', 'credito', 'cronograma'],
            array_keys($d)
        );
        $this->assertEquals($this->credit->id, $d['credito']['numero']);
        $this->assertEqualsWithDelta(5200.0, $d['cronograma']['total'], 0.001);

        // PDF guardado + sha256 del archivo exacto
        $this->assertNotNull($doc->pdf_path);
        Storage::disk('public')->assertExists($doc->pdf_path);
        $contenido = Storage::disk('public')->get($doc->pdf_path);
        $this->assertStringStartsWith('%PDF', $contenido);
        $this->assertSame(hash('sha256', $contenido), $doc->sha256);

        // Regenerar jamás pisa: crea v2
        $doc2 = $this->generador()->generar($this->client, $this->credit, $this->vehiculo);
        $this->assertEquals(2, $doc2->version);
        $this->assertSame('anexo1', $doc2->tipo);
        $this->assertSame(2, DocumentoCliente::count());
    }

    public function test_override_de_valor_persiste_en_el_vehiculo(): void
    {
        $this->mundo();
        Storage::fake('public');

        $doc = $this->generador()->generar($this->client, $this->credit, $this->vehiculo, ['valor_vehiculo' => 12000]);

        $this->assertEqualsWithDelta(12000.0, $doc->snapshot['vehiculo']['valor'], 0.001);
        // El valor capturado al generar se guarda en el vehículo (decimal:2)
        $this->assertSame('12000.00', $this->vehiculo->fresh()->valor);
    }

    public function test_descargas_pdf_y_word_responden(): void
    {
        $this->mundo();
        $this->darPermisoClientes();
        Storage::fake('public');

        $doc = $this->generador()->generar($this->client, $this->credit, $this->vehiculo);

        // PDF: se sirve el archivo guardado al emitir
        $pdf = $this->get(route('clients.documentos.pdf', $doc->id));
        $pdf->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringContainsString(
            $doc->nombreArchivo().'.pdf',
            (string) $pdf->headers->get('content-disposition')
        );

        // Word: al vuelo desde el snapshot, mismo contenido
        $word = $this->get(route('clients.documentos.word', $doc->id));
        $word->assertOk();
        $word->assertHeader('content-type', 'application/msword; charset=UTF-8');
        $word->assertSee('CRONOGRAMA');
        $word->assertSee('QUISPE MAMANI');
    }

    public function test_apartado_del_cliente_lista_el_documento(): void
    {
        $this->mundo();
        $this->darPermisoClientes();
        Storage::fake('public');

        $this->generador()->generar($this->client, $this->credit, $this->vehiculo);

        $resp = $this->get(route('clients.documentos', $this->client->id));
        $resp->assertOk();
        $resp->assertSee('Anexo 1');
    }
}
