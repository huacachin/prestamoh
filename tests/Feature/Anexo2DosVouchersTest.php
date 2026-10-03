<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Documentos\GeneradorAnexo2;
use App\Services\Documentos\Ocr\LectorDeVoucher;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Obs. 5.1 del Área Legal (29/09/2026): el desembolso pudo salir en DOS
 * operaciones (el ejemplo del área: BCP + Compartamos). El Anexo 2 admite
 * un segundo voucher con su propia foto, lectura y transcripción; entre los
 * dos montos deben sumar el importe del crédito, y la constancia los apila
 * (foto + DETALLES cada uno). Los anexos ya emitidos (un voucher, sin la
 * lista 'vouchers' en el snapshot) se siguen pintando igual.
 */
class Anexo2DosVouchersTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Headquarter::create(['name' => 'Sede Dos Vouchers', 'status' => 'active']);
        $u = User::factory()->create(['username' => 'dos-vouchers', 'headquarter_id' => $sede->id]);
        $this->seed(PermissionCatalogSeeder::class);
        $u->givePermissionTo('clientes');
        $this->actingAs($u);

        // Sin lectura automática salvo en el test que la finge: con la clave
        // del .env local, subir una foto llamaría a la API de verdad y la
        // lectura fallida vaciaría los campos tecleados.
        config(['services.anthropic.habilitado' => false]);

        $this->client = Client::create([
            'nombre' => 'LEON', 'apellido_pat' => 'MALCA', 'apellido_mat' => 'ROBER',
            'tipo_documento' => 'DNI', 'documento' => '46781299',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-09-11',
            'importe' => 15000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
    }

    /** @return array<string, mixed> datos del generador con dos vouchers */
    private function datosDos(string $monto1 = '10,000.00', string $monto2 = '5,000.00'): array
    {
        return [
            'vouchers' => [
                ['banco' => 'bcp', 'modalidad' => 'transferencia', 'transcripcion' => 'DETALLES: BCP; ¡TRANSFERENCIA EXITOSA!; S/ 10,000.00; VIERNES, 11 SEPTIEMBRE 2026', 'monto' => $monto1, 'imagen_path' => null],
                ['banco' => 'interbank', 'modalidad' => 'deposito', 'transcripcion' => 'DETALLES: AGENCIA HUAYCAN; FECHA 11/09/26; DEPOSITO; IMPORTE 5,000.00', 'monto' => $monto2, 'imagen_path' => null],
            ],
            'fecha' => '02/10/2026',
        ];
    }

    private function modal()
    {
        return Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalAnexo2')
            ->set('anexo2Banco', 'bcp')->set('anexo2Modalidad', 'transferencia')
            ->set('anexo2Transcripcion', 'DETALLES: BCP; ¡TRANSFERENCIA EXITOSA!; S/ 10,000.00; VIERNES, 11 SEPTIEMBRE 2026')
            ->set('anexo2Monto', '10,000.00');
    }

    public function test_el_generador_cuadra_la_suma_de_los_dos_vouchers_y_los_lleva_al_snapshot(): void
    {
        $this->assertSame([], GeneradorAnexo2::validar($this->client, $this->credit, $this->datosDos()));

        $errores = GeneradorAnexo2::validar($this->client, $this->credit, $this->datosDos('10,000.00', '4,000.00'));
        $this->assertCount(1, $errores);
        $this->assertStringContainsString('La suma de los dos vouchers (S/ 14,000.00) no coincide con el monto del crédito (S/ 15,000.00)', $errores[0]);

        // Un voucher solo: mensajes y cuadre de siempre.
        $uno = ['banco' => 'bcp', 'modalidad' => 'transferencia', 'transcripcion' => 'DETALLES: ALGO', 'monto' => '14,000.00'];
        $this->assertStringContainsString('El monto del voucher (S/ 14,000.00) no coincide', GeneradorAnexo2::validar($this->client, $this->credit, $uno)[0]);

        $snap = GeneradorAnexo2::construirSnapshot($this->client, $this->credit, $this->datosDos());
        $this->assertCount(2, $snap['vouchers']);
        $this->assertSame('bcp', $snap['banco'], 'el primero sigue en primer nivel');
        $this->assertSame('interbank', $snap['vouchers'][1]['banco']);
        $this->assertEqualsWithDelta(5000.0, $snap['vouchers'][1]['monto'], 0.001);
    }

    public function test_la_constancia_apila_los_dos_vouchers_y_una_sola_cuando_es_uno(): void
    {
        $html = GeneradorAnexo2::previsualizar($this->client, $this->credit, $this->datosDos());
        $this->assertSame(2, substr_count($html, 'DETALLES:</strong>'));
        $this->assertStringContainsString('AGENCIA HUAYCAN', $html);
        $this->assertSame(2, substr_count($html, 'La imagen del comprobante se insertará al generar'));

        $uno = ['banco' => 'bcp', 'modalidad' => 'transferencia', 'transcripcion' => 'DETALLES: SOLO UNO', 'monto' => '15,000.00'];
        $this->assertSame(1, substr_count(GeneradorAnexo2::previsualizar($this->client, $this->credit, $uno), 'DETALLES:</strong>'));
    }

    /**
     * 03/10: una comilla doble dentro del x-data (que va entre comillas dobles)
     * cortaba el atributo en el navegador, Alpine no inicializaba el modal y
     * "Generar Anexo 2" no respondía. Se lee el atributo como lo hace el
     * navegador (DOMDocument) y tiene que llegar entero.
     */
    public function test_el_x_data_del_modal_llega_entero_al_navegador(): void
    {
        $html = $this->modal()->html();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        $xData = $dom->getElementById('anexo2Modal')?->getAttribute('x-data') ?? '';

        $this->assertStringContainsString('destinoPegado()', $xData);
        $this->assertStringContainsString('pegar(evento)', $xData, 'el x-data se cortó antes del final');
        $this->assertStringNotContainsString('"', $xData, 'ninguna comilla doble dentro del x-data');
        $this->assertStringContainsString("querySelector('[data-preview=' + ref + ']')", $xData);
    }

    public function test_el_modal_abre_el_segundo_voucher_cuadra_la_suma_y_lo_quita(): void
    {
        $c = $this->modal();
        $c->assertSee('Agregar segundo voucher')->assertDontSee('Voucher 2');

        $c->call('agregarSegundoVoucherAnexo2')
            ->assertSee('Voucher 2')
            ->assertSee('La suma de los dos vouchers debe coincidir')
            ->set('anexo2Banco2', 'interbank')->set('anexo2Modalidad2', 'deposito')
            ->set('anexo2Transcripcion2', 'DETALLES: AGENCIA HUAYCAN; FECHA 11/09/26; DEPOSITO; IMPORTE 5,000.00')
            ->set('anexo2Monto2', '5,000.00')
            ->assertSee('suma de los dos vouchers: S/ 15,000.00')
            ->call('previsualizarAnexo2')
            ->assertNotDispatched('errorAlert');
        $this->assertSame(2, substr_count($c->get('htmlPreviewAnexo2'), 'DETALLES:</strong>'));

        // No cuadra la suma: no hay previa y se avisa.
        $c->set('anexo2Monto2', '4,000.00')
            ->assertSee('No coincide con el desembolso')
            ->call('previsualizarAnexo2')
            ->assertDispatched('errorAlert', fn ($name, $params) => str_contains($params['message'] ?? ($params[0]['message'] ?? ''), 'La suma de los dos vouchers'));
        $this->assertSame('', $c->get('htmlPreviewAnexo2'));

        // Quitar: vuelve a ser un voucher y lo del segundo se descarta.
        $c->call('quitarSegundoVoucherAnexo2')
            ->assertSee('Agregar segundo voucher');
        $this->assertFalse($c->get('anexo2DosVouchers'));
        $this->assertSame('', $c->get('anexo2Monto2'));
        $this->assertSame('', $c->get('anexo2Banco2'));
    }

    public function test_el_segundo_voucher_se_lee_solo_al_subir_su_foto(): void
    {
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);
        $this->app->bind(LectorDeVoucher::class, fn () => new class implements LectorDeVoucher
        {
            public function leer(string $rutaAbsoluta, string $banco, string $modalidad): array
            {
                return [
                    'transcripcion' => 'Agencia Huaycan; Deposito; Importe 5,000.00', 'monto' => '5,000.00',
                    'beneficiario' => 'LEON MALCA R.', 'dudas' => '', 'banco' => 'interbank', 'modalidad' => 'deposito',
                    'modelo' => 'modelo-de-prueba',
                ];
            }
        });

        $c = $this->modal()->call('agregarSegundoVoucherAnexo2')
            ->set('comprobante2', UploadedFile::fake()->image('voucher2.jpg'));

        $this->assertSame('DETALLES: AGENCIA HUAYCAN; DEPOSITO; IMPORTE 5,000.00', $c->get('anexo2Transcripcion2'));
        $this->assertSame('5,000.00', $c->get('anexo2Monto2'));
        $this->assertSame('interbank', $c->get('anexo2Banco2'));
        $this->assertSame('deposito', $c->get('anexo2Modalidad2'));
        // El primero no se toca.
        $this->assertSame('10,000.00', $c->get('anexo2Monto'));
        $this->assertSame('bcp', $c->get('anexo2Banco'));
        $c->assertSee('Formato del voucher 2:');
    }

    public function test_generar_con_dos_vouchers_emite_el_documento_con_los_dos_en_el_snapshot(): void
    {
        Storage::fake('public');

        $this->modal()->call('agregarSegundoVoucherAnexo2')
            ->set('anexo2Banco2', 'interbank')->set('anexo2Modalidad2', 'deposito')
            ->set('anexo2Transcripcion2', 'DETALLES: AGENCIA HUAYCAN; FECHA 11/09/26; DEPOSITO; IMPORTE 5,000.00')
            ->set('anexo2Monto2', '5,000.00')
            ->set('comprobante', UploadedFile::fake()->image('v1.jpg'))
            ->set('comprobante2', UploadedFile::fake()->image('v2.jpg'))
            ->call('generarAnexo2')
            ->assertHasNoErrors()
            ->assertNotDispatched('errorAlert')
            ->assertDispatched('anexo2-modal-close');

        $doc = DocumentoCliente::where('client_id', $this->client->id)->where('tipo', 'anexo2')->first();
        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->snapshot['vouchers']);
        $this->assertNotNull($doc->snapshot['vouchers'][0]['imagen_path']);
        $this->assertNotNull($doc->snapshot['vouchers'][1]['imagen_path']);
        $this->assertNotSame($doc->snapshot['vouchers'][0]['imagen_path'], $doc->snapshot['vouchers'][1]['imagen_path']);
        Storage::disk('public')->assertExists($doc->pdf_path);
    }
}
