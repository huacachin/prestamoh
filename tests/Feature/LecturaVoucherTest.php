<?php

namespace Tests\Feature;

use App\Livewire\Clients\Documentos;
use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
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
                // Como el lector real: la pista del operador manda; si no la
                // hay, vale lo que "identificó" (lo pactado en $datos).
                $leido = array_merge([
                    'transcripcion' => '', 'monto' => '', 'beneficiario' => '',
                    'dudas' => '', 'banco' => '', 'modalidad' => '', 'modelo' => 'modelo-de-prueba',
                ], $this->datos);
                if ($banco !== '' && $modalidad !== '') {
                    $leido['banco'] = $banco;
                    $leido['modalidad'] = $modalidad;
                }
                $leido['pista_recibida'] = "{$banco}/{$modalidad}";

                return $leido;
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

    /**
     * 18/09: la foto sigue siendo obligatoria, pero el banco y la modalidad ya
     * NO se piden antes de leer — los identifica la propia lectura. Antes el
     * operador tenía que elegirlos a mano cada vez ("muy artesanal").
     */
    public function test_exige_foto_pero_ya_no_banco_ni_modalidad(): void
    {
        $this->mundo();
        $this->lectorDevuelve(['transcripcion' => 'S/5,000.00; NÚMERO DE OPERACIÓN 1', 'banco' => 'bcp', 'modalidad' => 'yape']);
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);

        // Sin foto: no hay nada que leer.
        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->call('leerVoucher')
            ->assertDispatched('errorAlert')
            ->assertSet('anexo2Transcripcion', '');

        // Con foto y SIN combo: lee igual y rellena el formato que identificó.
        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'))
            ->assertSet('anexo2Transcripcion', 'DETALLES: S/5,000.00; NÚMERO DE OPERACIÓN 1')
            ->assertSet('anexo2Banco', 'bcp')
            ->assertSet('anexo2Modalidad', 'yape')
            ->assertNotDispatched('errorAlert');
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

    public function test_sin_clave_configurada_no_se_lee_ni_al_subir(): void
    {
        $this->mundo();
        $this->lectorDevuelve(['transcripcion' => 'NO DEBERÍA LLEGAR']);
        config(['services.anthropic.habilitado' => false]);

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'))
            // La foto sube y se muestra, pero nadie la lee: se transcribe a mano.
            ->assertSet('anexo2Transcripcion', '')
            ->assertDontSee('Leer de nuevo')
            ->call('leerVoucher')
            ->assertDispatched('errorAlert');
    }

    // ─── 18/09: un voucher, dos gestos ─────────────────────────────────────

    /** Al subir la foto se lee sola: no hace falta apretar nada. */
    public function test_lee_sola_al_subir_la_foto(): void
    {
        $this->mundo();
        $this->lectorDevuelve(['transcripcion' => 'YAPEASTE S/5,000.00', 'monto' => '5,000.00', 'banco' => 'bcp', 'modalidad' => 'yape']);
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);

        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'));

        $this->assertSame('DETALLES: YAPEASTE S/5,000.00', $c->get('anexo2Transcripcion'));
        $this->assertSame('5,000.00', $c->get('anexo2Monto'));
        $c->assertDispatched('successAlert')
            ->assertSee('YAPE — BCP')            // el formato identificado, como resumen
            ->assertSee('Leer de nuevo');        // y la lectura se puede repetir
    }

    /** Si el operador ya fijó el formato, esa pista manda sobre lo que "identifique" la lectura. */
    public function test_la_pista_del_operador_manda(): void
    {
        $this->mundo();
        $this->lectorDevuelve(['transcripcion' => 'S/5,000.00', 'banco' => 'bbva', 'modalidad' => 'deposito']);
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('anexo2Banco', 'bcp')
            ->set('anexo2Modalidad', 'transferencia')
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'))
            ->assertSet('anexo2Banco', 'bcp')
            ->assertSet('anexo2Modalidad', 'transferencia');
    }

    /** Si la lectura no reconoce el formato, lo dice y deja los selectores a la vista. */
    public function test_si_no_reconoce_el_formato_lo_pide(): void
    {
        $this->mundo();
        $this->lectorDevuelve(['transcripcion' => 'S/5,000.00; OPERACIÓN 123']);   // sin banco/modalidad
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'))
            ->assertSet('anexo2Transcripcion', 'DETALLES: S/5,000.00; OPERACIÓN 123')
            ->assertSet('anexo2Banco', '')
            ->assertSee('No reconocí el formato del voucher')
            ->assertSee('Selecciona el banco');
    }

    /** Elegir o corregir el formato a mano NO borra lo ya leído (antes sí, y había que releer). */
    public function test_cambiar_el_formato_no_borra_la_transcripcion(): void
    {
        $this->mundo();
        $this->lectorDevuelve(['transcripcion' => 'S/5,000.00; OPERACIÓN 123', 'monto' => '5,000.00']);
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);

        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'))
            ->set('anexo2Banco', 'interbank')
            ->set('anexo2Modalidad', 'deposito')
            ->assertSet('anexo2Transcripcion', 'DETALLES: S/5,000.00; OPERACIÓN 123')
            ->assertSet('anexo2Monto', '5,000.00');
    }

    /** El monto se coteja con el desembolso y el beneficiario con el cliente, a la vista. */
    public function test_coteja_monto_y_beneficiario_antes_de_generar(): void
    {
        $this->mundo();
        config(['services.anthropic.habilitado' => true, 'services.anthropic.key' => 'sk-ant-falsa']);

        // Todo cuadra: S/5,000.00 es el importe del crédito y "Quispe Mamani Rosa" es la clienta
        // (la app del banco suele abreviar y poner otro orden).
        $this->lectorDevuelve(['transcripcion' => 'X', 'monto' => '5,000.00', 'beneficiario' => 'Rosa Quispe M.', 'banco' => 'bcp', 'modalidad' => 'yape']);
        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'));
        $this->assertSame(['monto' => true, 'beneficiario' => true], $c->viewData('chequeosAnexo2'));
        $c->assertSee('Coincide con el desembolso')->assertSee('coincide con el cliente');

        // No cuadra: otro monto y otra persona.
        $this->lectorDevuelve(['transcripcion' => 'X', 'monto' => '4,500.00', 'beneficiario' => 'Carlos Huaman Flores', 'banco' => 'bcp', 'modalidad' => 'yape']);
        $c = Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->set('anexo2CreditoId', $this->credit->id)
            ->set('comprobante', UploadedFile::fake()->image('v.jpg'));
        $this->assertSame(['monto' => false, 'beneficiario' => false], $c->viewData('chequeosAnexo2'));
        $c->assertSee('No coincide con el desembolso')->assertSee('verifica que el voucher sea de este cliente');
    }

    /** Se precarga el crédito activo más reciente que aún no tiene Anexo 2. */
    public function test_precarga_el_credito_sin_anexo_2(): void
    {
        $this->mundo();
        $viejo = $this->credit;
        $nuevo = Credit::create([
            'client_id' => $this->client->id, 'fecha_prestamo' => '2026-09-10',
            'importe' => 8000, 'cuotas' => 8, 'tipo_planilla' => 1, 'interes' => 10,
            'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->client->headquarter_id,
        ]);

        // Dos créditos activos: se precarga el más reciente (antes no se precargaba ninguno).
        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalAnexo2')
            ->assertSet('anexo2CreditoId', $nuevo->id)
            ->assertSet('anexo2Monto', '8000.00');

        // El nuevo ya tiene su Anexo 2: se precarga el que falta.
        DocumentoCliente::create([
            'client_id' => $this->client->id, 'credit_id' => $nuevo->id, 'tipo' => 'anexo2',
            'version' => 1, 'snapshot' => [], 'estado' => 'emitido',
        ]);
        Livewire::test(Documentos::class, ['id' => $this->client->id])
            ->call('abrirModalAnexo2')
            ->assertSet('anexo2CreditoId', $viejo->id);
    }
}
