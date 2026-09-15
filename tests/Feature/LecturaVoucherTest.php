<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Documentos\Ocr\LectorDeVoucher;
use App\Services\Documentos\Ocr\MensajeDeFallo;
use App\Services\Documentos\Ocr\VoucherIlegible;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lectura automática del voucher del Anexo 2 (15/09).
 *
 * Rellena la transcripción para que el operador la CONFIRME; nunca genera el
 * documento por sí sola. Y si falla, la pantalla sigue sirviendo a mano: sin
 * clave de API o con la imagen ilegible, el módulo no se rompe.
 *
 * El lector se inyecta por interfaz, así que estos tests NO gastan llamadas.
 */
class LecturaVoucherTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Credit $credit;

    private function mundo(): void
    {
        $sede = Headquarter::create(['name' => 'Sede Lectura', 'status' => 'active']);
        $u = User::factory()->create(['username' => 'lector-tester', 'headquarter_id' => $sede->id]);
        $this->seed(PermissionCatalogSeeder::class);
        $u->givePermissionTo('clientes');
        $this->actingAs($u);

        $this->client = Client::create([
            'nombre' => 'ROSA', 'apellido_pat' => 'QUISPE', 'apellido_mat' => 'MAMANI',
            'tipo_documento' => 'DNI', 'documento' => '46781234',
            'headquarter_id' => $sede->id, 'status' => 'active',
        ]);
        $this->credit = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-08-25',
            'importe' => 5000, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $sede->id,
        ]);
    }

    /** Doble del lector: devuelve lo pactado sin llamar a ninguna API. */
    private function lectorDevuelve(array $datos): void
    {
        $this->app->bind(LectorDeVoucher::class, fn () => new class($datos) implements LectorDeVoucher
        {
            public function __construct(private array $datos) {}

            public function leer(string $rutaAbsoluta, string $banco, string $modalidad): array
            {
                return array_merge([
                    'transcripcion' => '', 'monto' => '', 'beneficiario' => '',
                    'dudas' => '', 'modelo' => 'modelo-de-prueba',
                ], $this->datos);
            }
        });
    }

    private function lectorFalla(string $mensaje): void
    {
        $this->app->bind(LectorDeVoucher::class, fn () => new class($mensaje) implements LectorDeVoucher
        {
            public function __construct(private string $mensaje) {}

            public function leer(string $rutaAbsoluta, string $banco, string $modalidad): array
            {
                throw new VoucherIlegible($this->mensaje);
            }
        });
    }

    /** Componente con el modal del Anexo 2 listo y la foto subida. */
    private function modal()
    {
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);

        return Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('anexo2Banco', 'bcp')
            ->set('anexo2Modalidad', 'transferencia')
            ->set('comprobante', UploadedFile::fake()->image('voucher.jpg'));
    }

    public function test_rellena_la_transcripcion_y_el_monto(): void
    {
        $this->mundo();
        $this->lectorDevuelve([
            'transcripcion' => '¡Transferencia exitosa!; S/5,000.00; NÚMERO DE OPERACIÓN 07365498',
            'monto' => '5,000.00',
        ]);

        $c = $this->modal()->call('leerVoucher');

        // En mayúsculas y con "DETALLES:" delante (15/09, pedido del área):
        // lo que se ve en el formulario es lo que sale impreso.
        $this->assertSame('DETALLES: ¡TRANSFERENCIA EXITOSA!; S/5,000.00; NÚMERO DE OPERACIÓN 07365498', $c->get('anexo2Transcripcion'));
        $this->assertSame('5,000.00', $c->get('anexo2Monto'));
        $c->assertDispatched('successAlert');
    }

    public function test_muestra_las_dudas_declaradas(): void
    {
        $this->mundo();
        $this->lectorDevuelve([
            'transcripcion' => 'DEPOSITO ***25,000.00; CTA. 107022211004104749',
            'dudas' => 'El 7º dígito de la cuenta podría ser 0 u 8: el papel está doblado.',
        ]);

        $c = $this->modal()->call('leerVoucher');

        $this->assertStringContainsString('podría ser 0 u 8', $c->get('anexo2Dudas'));
        // Y se ven en pantalla, que es el punto: hay que mirar justo ahí.
        $c->assertSee('Revisa estos datos')->assertSee('podría ser 0 u 8');
    }

    public function test_si_la_lectura_falla_se_transcribe_a_mano(): void
    {
        $this->mundo();
        $this->lectorFalla('La imagen del voucher está vacía.');

        $c = $this->modal()->call('leerVoucher');

        $c->assertDispatched('errorAlert');
        // No se pisa nada: el operador sigue con el formulario intacto.
        $this->assertSame('', $c->get('anexo2Transcripcion'));
        $this->assertSame('', $c->get('anexo2Dudas'));
    }

    public function test_exige_foto_banco_y_modalidad(): void
    {
        $this->mundo();
        $this->lectorDevuelve(['transcripcion' => 'NO DEBERÍA LLEGAR']);
        config(['services.anthropic.habilitado' => true]);

        // Sin foto.
        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('anexo2Banco', 'bcp')
            ->set('anexo2Modalidad', 'transferencia')
            ->call('leerVoucher')
            ->assertDispatched('errorAlert')
            ->assertSet('anexo2Transcripcion', '');

        // Con foto pero sin elegir el combo.
        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'))
            ->call('leerVoucher')
            ->assertDispatched('errorAlert')
            ->assertSet('anexo2Transcripcion', '');
    }

    /**
     * Los fallos se traducen a algo que el operador pueda resolver: lo que
     * decide si reintenta, transcribe a mano o avisa a alguien. Se prueba con
     * los mensajes REALES que devuelve la API, no con los ya traducidos.
     */
    public function test_traduce_los_fallos_a_algo_accionable(): void
    {
        $casos = [
            // El más probable en el día a día.
            ['Your credit balance is too low to access the Anthropic API.', 'sin saldo'],
            ['invalid x-api-key', 'no está bien configurada'],
            ['Number of requests has exceeded your rate limit', 'saturada'],
            ['Connection timed out after 600000 milliseconds', 'No hubo conexión'],
            ['Could not process image', 'no pudo procesar esta imagen'],
            ['algo raro que nadie previó', 'No se pudo leer el voucher'],
        ];

        foreach ($casos as [$crudo, $esperado]) {
            $traducido = MensajeDeFallo::para(new \RuntimeException($crudo));

            $this->assertStringContainsString($esperado, $traducido, "No tradujo bien: {$crudo}");
            // Y el error en inglés de la API nunca llega al operador.
            $this->assertStringNotContainsString($crudo, $traducido);
        }
    }

    public function test_sin_clave_configurada_el_boton_no_existe(): void
    {
        $this->mundo();
        config(['services.anthropic.habilitado' => false]);

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->assertDontSee('Leer voucher')
            ->call('leerVoucher')
            ->assertDispatched('errorAlert');
    }
}
