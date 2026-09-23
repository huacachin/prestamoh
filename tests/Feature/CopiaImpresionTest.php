<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contrato;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Garantia;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Documentos\CopiaImpresion;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

/**
 * Copia de IMPRESIÓN (22/09/2026): el botón "Imprimir" sirve el PDF emitido
 * rasterizado con Ghostscript (hojas como imagen), porque la fotocopiadora
 * tarda 15-20 s por hoja con las fuentes de dompdf. El emitido no se toca.
 * Si la copia no sale, se avisa en pantalla con el enlace al PDF normal.
 */
class CopiaImpresionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Credit $credit;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'impresion-tester', 'headquarter_id' => $sede->id]);
        $this->actingAs($this->user);
        $this->seed(PermissionCatalogSeeder::class);
        $this->user->givePermissionTo('clientes');

        $this->client = Client::create([
            'expediente' => '9010',
            'nombre' => 'TEODORO',
            'apellido_pat' => 'HUARANGA',
            'apellido_mat' => 'VASQUEZ',
            'tipo_documento' => 'DNI',
            'documento' => '80346073',
            'sexo' => 'M',
            'direccion' => 'UCV 232B LAS PALMERAS',
            'distrito' => 'ATE',
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
            'celular1' => '987654321',
            'headquarter_id' => $sede->id,
            'asesor_id' => $this->user->id,
            'status' => 'active',
        ]);

        $this->credit = Credit::create([
            'client_id' => $this->client->id,
            'fecha_prestamo' => '2026-09-22',
            'importe' => 3000,
            'cuotas' => 4,
            'tipo_planilla' => 1,
            'interes' => 10,
            'situacion' => 'Activo',
            'estado' => 1,
            'headquarter_id' => $sede->id,
        ]);
    }

    /** Un PDF real de dompdf (dos hojas) guardado en el disco public (falso). */
    private function pdfEnDisco(string $path): string
    {
        $bin = Pdf::loadHTML('<h1>Documento de prueba</h1><p>Texto con tildes: áéíóú Ñ N° S/ 3,000.00</p>'
            .'<div style="page-break-before: always"></div><p>Segunda hoja</p>')->setPaper('a4')->output();
        Storage::disk('public')->put($path, $bin);

        return $bin;
    }

    /** Un documento del cliente emitido. */
    private function documento(string $tipo): DocumentoCliente
    {
        $path = "documentos/cliente-{$this->client->id}/{$tipo}-credito-{$this->credit->id}-v1.pdf";
        $bin = $this->pdfEnDisco($path);

        return DocumentoCliente::create([
            'client_id' => $this->client->id,
            'credit_id' => $this->credit->id,
            'tipo' => $tipo,
            'modelo' => $tipo === 'contrato' ? 'a1' : null,
            'version' => 1,
            'snapshot' => ['prueba' => true],
            'pdf_path' => $path,
            'sha256' => hash('sha256', $bin),
            'estado' => 'emitido',
            'generado_por' => $this->user->id,
        ]);
    }

    /** Un contrato del módulo legal emitido (garantía mínima). */
    private function contratoLegal(): Contrato
    {
        $garantia = Garantia::create([
            'credit_id' => $this->credit->id,
            'client_id' => $this->client->id,
            'tipo' => 'mobiliaria_vehicular',
            'tipo_persona' => 'natural',
            'gps' => true,
            'custodia' => false,
            'monto_gravamen' => 3000,
            'registrado_por' => $this->user->id,
        ]);
        $path = "legal/garantia-{$garantia->id}/cgm-2026-0001-v1.pdf";
        $bin = $this->pdfEnDisco($path);

        return Contrato::create([
            'garantia_id' => $garantia->id,
            'credit_id' => $this->credit->id,
            'client_id' => $this->client->id,
            'numero' => 'CGM 2026-0001',
            'tipo' => 'garantia_mobiliaria',
            'version' => 1,
            'parametros' => [],
            'datos_snapshot' => ['prueba' => true],
            'estado' => 'emitido',
            'pdf_path' => $path,
            'sha256' => hash('sha256', $bin),
            'generado_por' => $this->user->id,
        ]);
    }

    private static function hayGhostscript(): bool
    {
        $bin = (string) config('documentos.impresion.ghostscript', 'gs');

        return is_executable($bin) || (new ExecutableFinder)->find($bin) !== null;
    }

    /** Ghostscript simulado: exit 0 y deja en -sOutputFile lo que diga $contenido (o nada), con $mensajes en la salida. */
    private function ghostscriptSimulado(?string $contenido, string $mensajes = ''): void
    {
        Process::fake(['*' => function (PendingProcess $p) use ($contenido, $mensajes) {
            foreach ((array) $p->command as $arg) {
                if ($contenido !== null && str_starts_with((string) $arg, '-sOutputFile=')) {
                    file_put_contents(substr($arg, strlen('-sOutputFile=')), $contenido);
                }
            }

            return Process::result($mensajes, '', 0);
        }]);
    }

    private function asertarAvisoSinCopia(TestResponse $r, string $urlPdf): void
    {
        $r->assertOk();
        $r->assertHeader('X-Copia-Impresion', 'no-disponible');
        $r->assertSee('No se pudo preparar la copia rápida para imprimir');
        $r->assertSee($urlPdf, false);
    }

    private function sinTemporales(string $carpeta): void
    {
        $restos = array_filter(Storage::disk('public')->files($carpeta), fn ($f) => str_contains($f, '.tmp-'));
        $this->assertSame([], array_values($restos), 'no deben quedar temporales a medio escribir');
    }

    public function test_ruta_de_cache_y_color_por_tipo(): void
    {
        // La copia lleva en el nombre gris/color y la resolución: si la config cambia, se regenera sola.
        $this->assertSame(
            'documentos/cliente-32/contrato-credito-29426-v1-impresion-g300.pdf',
            CopiaImpresion::rutaCache('documentos/cliente-32/contrato-credito-29426-v1.pdf', false)
        );
        $this->assertSame(
            'documentos/cliente-32/anexo1-credito-29426-v1-impresion-c300.pdf',
            CopiaImpresion::rutaCache('documentos/cliente-32/anexo1-credito-29426-v1.pdf', true)
        );
        config(['documentos.impresion.dpi' => 200]);
        $this->assertStringEndsWith('-impresion-g200.pdf', CopiaImpresion::rutaCache('x/y.pdf', false));

        // La fotocopiadora cobra el color: contrato en gris, anexos a color.
        $this->assertFalse(CopiaImpresion::colorPara('contrato'));
        $this->assertTrue(CopiaImpresion::colorPara('anexo1'));
        $this->assertTrue(CopiaImpresion::colorPara('anexo2'));
        // Lo desconocido sale a color: no pierde nada.
        $this->assertTrue(CopiaImpresion::colorPara('otro'));
    }

    public function test_genera_la_copia_como_imagen_gris_para_el_contrato_y_color_para_el_anexo(): void
    {
        if (! self::hayGhostscript()) {
            $this->markTestSkipped('Ghostscript no está instalado en esta máquina');
        }

        $this->mundo();
        Storage::fake('public');

        $docs = [];
        foreach (['contrato' => '/DeviceGray', 'anexo1' => '/DeviceRGB'] as $tipo => $espacioColor) {
            $doc = $docs[] = $this->documento($tipo);

            $respuesta = $this->get(route('clients.documentos.imprimir', $doc->id));
            $respuesta->assertOk();
            $respuesta->assertHeader('X-Copia-Impresion', 'imagen');
            $this->assertStringStartsWith('application/pdf', (string) $respuesta->headers->get('content-type'));
            $this->assertStringContainsString('inline', (string) $respuesta->headers->get('content-disposition'));
            $this->assertStringContainsString($doc->nombreArchivo().'-impresion.pdf', (string) $respuesta->headers->get('content-disposition'));

            $cache = CopiaImpresion::rutaCache($doc->pdf_path, $tipo !== 'contrato');
            Storage::disk('public')->assertExists($cache);
            $copia = Storage::disk('public')->get($cache);

            $this->assertStringStartsWith('%PDF-', $copia);
            $this->assertStringNotContainsString('/FontName', $copia, 'la copia no debe llevar fuentes: es lo que atasca a la fotocopiadora');
            $this->assertSame(2, preg_match_all('#/Subtype\s*/Image#', $copia), 'una imagen por hoja');
            $this->assertStringContainsString($espacioColor, $copia);
            $this->assertSame($copia, $respuesta->streamedContent(), 'se sirve exactamente la copia guardada');

            // El emitido queda intacto.
            $this->assertSame($doc->sha256, hash('sha256', Storage::disk('public')->get($doc->pdf_path)));
        }
        $this->sinTemporales("documentos/cliente-{$this->client->id}");

        // Segunda vez: se sirve la copia guardada sin volver a correr Ghostscript.
        Process::fake();
        foreach ($docs as $doc) {
            $this->get(route('clients.documentos.imprimir', $doc->id))->assertOk()->assertHeader('X-Copia-Impresion', 'imagen');
        }
        Process::assertNothingRan();
    }

    public function test_si_el_emitido_es_mas_nuevo_que_la_copia_se_regenera(): void
    {
        $this->mundo();
        Storage::fake('public');
        $doc = $this->documento('contrato');
        $cache = CopiaImpresion::rutaCache($doc->pdf_path, false);

        // Copia vieja (de ayer) frente a un emitido de hoy.
        Storage::disk('public')->put($cache, '%PDF-1.7 copia vieja %%EOF');
        touch(Storage::disk('public')->path($cache), time() - 86400);

        Process::fake();
        $this->get(route('clients.documentos.imprimir', $doc->id));
        Process::assertRan(fn (PendingProcess $p) => in_array('-sDEVICE=pdfimage8', (array) $p->command, true));
    }

    public function test_sin_ghostscript_avisa_en_pantalla_y_ofrece_el_pdf_normal(): void
    {
        $this->mundo();
        Storage::fake('public');
        $doc = $this->documento('contrato');
        $original = Storage::disk('public')->get($doc->pdf_path);

        // Como si el binario no existiera (código 127): el servidor sin ghostscript.
        Process::fake(['*' => Process::result('', '', 127)]);

        $this->asertarAvisoSinCopia(
            $this->get(route('clients.documentos.imprimir', $doc->id)),
            route('clients.documentos.pdf', $doc->id)
        );
        Storage::disk('public')->assertMissing(CopiaImpresion::rutaCache($doc->pdf_path, false));
        $this->assertSame($original, Storage::disk('public')->get($doc->pdf_path), 'el emitido no se toca');
    }

    public function test_una_salida_corrupta_de_ghostscript_no_queda_en_cache(): void
    {
        $this->mundo();
        Storage::fake('public');
        $doc = $this->documento('anexo1');
        $carpeta = "documentos/cliente-{$this->client->id}";
        $urlPdf = route('clients.documentos.pdf', $doc->id);

        // exit 0 pero el archivo no es un PDF
        $this->ghostscriptSimulado('basura');
        $this->asertarAvisoSinCopia($this->get(route('clients.documentos.imprimir', $doc->id)), $urlPdf);
        $this->sinTemporales($carpeta);

        // exit 0, empieza como PDF pero quedó truncado (sin %%EOF: disco lleno)
        $this->ghostscriptSimulado('%PDF-1.7 '.str_repeat('x', 100));
        $this->asertarAvisoSinCopia($this->get(route('clients.documentos.imprimir', $doc->id)), $urlPdf);

        // exit 0 con un PDF "entero" pero Ghostscript avisó que dejó hojas sin dibujar
        $this->ghostscriptSimulado("%PDF-1.7\n/Type /Page\n/Type /Page\n%%EOF", '**** Error: Page drawing error occurred. page will be missing in the output');
        $this->asertarAvisoSinCopia($this->get(route('clients.documentos.imprimir', $doc->id)), $urlPdf);

        // exit 0 con un PDF entero pero con MENOS hojas que el original (el original tiene 2)
        $this->ghostscriptSimulado("%PDF-1.7\n/Type /Page\n%%EOF");
        $this->asertarAvisoSinCopia($this->get(route('clients.documentos.imprimir', $doc->id)), $urlPdf);

        Storage::disk('public')->assertMissing(CopiaImpresion::rutaCache($doc->pdf_path, true));
        $this->sinTemporales($carpeta);
    }

    public function test_si_ghostscript_se_pasa_de_tiempo_avisa(): void
    {
        $this->mundo();
        Storage::fake('public');
        $doc = $this->documento('contrato');

        Process::fake(['*' => fn () => throw new ProcessTimedOutException(new SymfonyProcess(['gs']), ProcessTimedOutException::TYPE_GENERAL)]);

        $this->asertarAvisoSinCopia(
            $this->get(route('clients.documentos.imprimir', $doc->id)),
            route('clients.documentos.pdf', $doc->id)
        );
        $this->sinTemporales("documentos/cliente-{$this->client->id}");
    }

    public function test_sin_permiso_clientes_responde_403(): void
    {
        $this->mundo();
        Storage::fake('public');
        $doc = $this->documento('contrato');

        $this->user->revokePermissionTo('clientes');

        $this->get(route('clients.documentos.imprimir', $doc->id))->assertForbidden();
    }

    public function test_el_analista_solo_imprime_documentos_de_su_cartera(): void
    {
        $this->mundo();
        Storage::fake('public');
        $doc = $this->documento('anexo1');

        $this->user->givePermissionTo('clientes.scope-propio');
        $otroAsesor = User::factory()->create(['username' => 'otro-asesor', 'headquarter_id' => $this->user->headquarter_id]);
        $this->client->update(['asesor_id' => $otroAsesor->id]);

        $this->get(route('clients.documentos.imprimir', $doc->id))->assertForbidden();
    }

    public function test_sin_pdf_guardado_responde_404(): void
    {
        $this->mundo();
        Storage::fake('public');
        $doc = $this->documento('contrato');
        Storage::disk('public')->delete($doc->pdf_path);

        $this->get(route('clients.documentos.imprimir', $doc->id))->assertNotFound();
    }

    public function test_el_contrato_legal_tambien_imprime_en_gris(): void
    {
        if (! self::hayGhostscript()) {
            $this->markTestSkipped('Ghostscript no está instalado en esta máquina');
        }

        $this->mundo();
        Storage::fake('public');
        $this->user->givePermissionTo(Permission::findOrCreate('legal.contratos', 'web'));
        $contrato = $this->contratoLegal();

        $respuesta = $this->get(route('legal.contratos.imprimir', $contrato->id));
        $respuesta->assertOk();
        $respuesta->assertHeader('X-Copia-Impresion', 'imagen');
        $this->assertStringContainsString('CGM-2026-0001-v1-impresion.pdf', (string) $respuesta->headers->get('content-disposition'));

        $copia = Storage::disk('public')->get(CopiaImpresion::rutaCache($contrato->pdf_path, false));
        $this->assertStringContainsString('/DeviceGray', $copia);
        $this->assertStringNotContainsString('/FontName', $copia);

        // Sin PDF guardado: 404. Sin el permiso del módulo: 403.
        Storage::disk('public')->delete($contrato->pdf_path);
        $this->get(route('legal.contratos.imprimir', $contrato->id))->assertNotFound();

        $this->user->revokePermissionTo('legal.contratos');
        $this->get(route('legal.contratos.imprimir', $contrato->id))->assertForbidden();
    }
}
