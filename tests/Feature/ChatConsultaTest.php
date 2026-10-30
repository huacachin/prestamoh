<?php

namespace Tests\Feature;

use App\Livewire\Chat\Panel;
use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Headquarter;
use App\Models\User;
use App\Services\Chat\ModeloDeChat;
use App\Services\Chat\RespuestaModelo;
use App\Support\Audit;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Throwable;

/**
 * 23/10/2026 (Antony): chat de consulta. El usuario pregunta en el panel
 * flotante, el servicio manda la pregunta al modelo con herramientas de solo
 * lectura, corre las que el modelo pide y guarda la respuesta con tokens,
 * costo y auditoría. El modelo se reemplaza por un doble guionado: ningún
 * test llama a la API.
 */
class ChatConsultaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Headquarter $sede;

    /** Lo que recibió el doble del modelo en cada vuelta: [sistema, mensajes, herramientas]. */
    public array $llamadas = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['chat.habilitado' => true, 'chat.api_key' => 'sk-ant-falsa', 'chat.modelo' => 'claude-sonnet-5-5', 'chat.conexion' => 'mysql']);
        $this->sede = Headquarter::create(['name' => 'Sede Test', 'status' => 'active']);
        $this->user = User::factory()->create(['username' => 'chat-tester', 'headquarter_id' => $this->sede->id]);
        $this->actingAs($this->user);
        Activity::query()->delete();
    }

    /** El doble contesta las respuestas dadas en orden (o lanza la excepción dada) y anota lo que recibió. */
    private function modeloGuionado(array $respuestas): void
    {
        $test = $this;
        $this->app->instance(ModeloDeChat::class, new class($respuestas, $test) implements ModeloDeChat
        {
            public function __construct(private array $respuestas, private ChatConsultaTest $test) {}

            public function responder(string $sistema, array $mensajes, array $herramientas): RespuestaModelo
            {
                $this->test->llamadas[] = [$sistema, $mensajes, $herramientas];
                $r = array_shift($this->respuestas);
                if ($r instanceof Throwable) {
                    throw $r;
                }

                return $r ?? new RespuestaModelo('claude-sonnet-5-5', 'end_turn', [['type' => 'text', 'text' => 'sin guion']]);
            }
        });
    }

    private function pide(string $herramienta, array $input, int $entrada = 900, int $salida = 50): RespuestaModelo
    {
        return new RespuestaModelo('claude-sonnet-5-5', 'tool_use', [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => $herramienta, 'input' => $input]], $entrada, $salida);
    }

    private function contesta(string $texto, int $entrada = 1500, int $salida = 120, int $cache = 0): RespuestaModelo
    {
        return new RespuestaModelo('claude-sonnet-5-5', 'end_turn', [['type' => 'text', 'text' => $texto]], $entrada, $salida, $cache);
    }

    private function clienteConCredito(): Client
    {
        $client = Client::create([
            'nombre' => 'Mariela Ida', 'apellido_pat' => 'Chagua', 'apellido_mat' => 'Chavez', 'tipo_documento' => 'DNI', 'documento' => '70887721',
            'expediente' => '48', 'celular1' => '918512055', 'headquarter_id' => $this->sede->id, 'status' => 'active', 'asesor_id' => $this->user->id,
        ]);
        $credit = Credit::create([
            'client_id' => $client->id, 'fecha_prestamo' => '2026-09-18', 'importe' => 833.32, 'cuotas' => 4, 'tipo_planilla' => 1, 'interes' => 60,
            'interes_total' => 499.99, 'situacion' => 'Activo', 'estado' => 1, 'headquarter_id' => $this->sede->id, 'user_id' => $this->user->id,
        ]);
        foreach (['2026-09-25', '2026-10-02', '2026-10-13', '2026-10-30'] as $i => $venc) {
            $n = $i + 1;
            CreditInstallment::create([
                'credit_id' => $credit->id, 'num_cuota' => $n, 'fecha_vencimiento' => $venc,
                'importe_cuota' => 208.33, 'importe_interes' => 125.00, 'importe_excedente' => 0,
                'importe_aplicado' => $n <= 2 ? 208.33 : 0, 'interes_aplicado' => $n <= 2 ? 125.00 : 0, 'excedente_aplicado' => 0,
                'importe_mora' => 0, 'mora_interes' => 0, 'pagado' => $n <= 2, 'fecha_pago' => $n <= 2 ? $venc : null,
            ]);
        }

        return $client;
    }

    public function test_una_pregunta_corre_la_herramienta_que_pide_el_modelo_y_guarda_respuesta_tokens_costo_y_auditoria(): void
    {
        $this->clienteConCredito();
        $this->modeloGuionado([
            $this->pide('buscar_clientes', ['texto' => 'Chagua']),
            $this->contesta('Encontré a **Chagua Chavez Mariela Ida**: debe S/ 666.66.', cache: 1000),
        ]);

        $panel = Livewire::test(Panel::class)->set('pregunta', '¿cómo va Chagua?')->call('enviar');

        $panel->assertDispatched('chat-respondido')->assertSet('pregunta', '')
            ->assertSeeHtml('<strong>Chagua Chavez Mariela Ida</strong>')
            ->assertSee('consultó buscar_clientes');

        // Dos vueltas con el modelo: la primera lleva la pregunta y las herramientas; la segunda, el resultado de la consulta.
        $this->assertCount(2, $this->llamadas);
        [$sistema, $mensajes, $herramientas] = $this->llamadas[0];
        $this->assertStringContainsString('chat-tester', $sistema);
        $this->assertSame(['role' => 'user', 'content' => '¿cómo va Chagua?'], $mensajes[0]);
        $this->assertContains('buscar_clientes', array_column($herramientas, 'name'));
        $this->assertSame('object', $herramientas[0]['input_schema']['type']);
        $ultimo = end($this->llamadas[1][1]);
        $this->assertSame('tool_result', $ultimo['content'][0]['type']);
        $this->assertSame('toolu_1', $ultimo['content'][0]['tool_use_id']);
        $this->assertFalse($ultimo['content'][0]['is_error']);
        $this->assertStringContainsString('"expediente":"48"', $ultimo['content'][0]['content']);
        $this->assertStringContainsString('Chagua Chavez Mariela Ida', $ultimo['content'][0]['content']);

        // Lo guardado: pregunta, respuesta con la herramienta usada, tokens y costo (2400 + 1000 en caché de entrada, 170 de salida).
        $mensajesGuardados = ChatMensaje::orderBy('id')->get();
        $this->assertCount(2, $mensajesGuardados);
        $this->assertSame(['usuario', '¿cómo va Chagua?'], [$mensajesGuardados[0]->rol, $mensajesGuardados[0]->contenido]);
        $respuesta = $mensajesGuardados[1];
        $this->assertSame('asistente', $respuesta->rol);
        $this->assertSame('buscar_clientes', $respuesta->herramientas[0]['nombre']);
        $this->assertSame(['texto' => 'Chagua'], $respuesta->herramientas[0]['argumentos']);
        $this->assertNull($respuesta->herramientas[0]['error']);
        $this->assertSame([2400, 170, 1000], [$respuesta->tokens_entrada, $respuesta->tokens_salida, $respuesta->tokens_cache_lectura]);
        $this->assertSame('0.006700', (string) $respuesta->costo_usd);
        $this->assertNull($respuesta->error);

        $conversacion = ChatConversacion::firstOrFail();
        $this->assertSame([$this->user->id, 1, '¿cómo va Chagua?', '0.006700', 3400], [
            $conversacion->user_id, $conversacion->preguntas, $conversacion->titulo, (string) $conversacion->costo_usd, $conversacion->tokens_entrada,
        ]);

        $fila = Activity::query()->where('log_name', Audit::LOG)->where('subject_type', ChatConversacion::class)->firstOrFail();
        $this->assertSame('Consultó al chat: ¿cómo va Chagua?', $fila->description);
        $this->assertSame(['buscar_clientes'], $fila->properties['herramientas']);
    }

    public function test_la_segunda_pregunta_lleva_la_conversacion_anterior_como_contexto(): void
    {
        $this->modeloGuionado([$this->contesta('Primera respuesta.'), $this->contesta('Segunda respuesta.')]);

        $panel = Livewire::test(Panel::class)->set('pregunta', 'primera')->call('enviar');
        $panel->set('pregunta', 'segunda')->call('enviar');

        $mensajes = $this->llamadas[1][1];
        $this->assertSame([
            ['role' => 'user', 'content' => 'primera'],
            ['role' => 'assistant', 'content' => 'Primera respuesta.'],
            ['role' => 'user', 'content' => 'segunda'],
        ], $mensajes);
        $this->assertSame(2, ChatConversacion::firstOrFail()->preguntas);
        $this->assertSame(1, ChatConversacion::count(), 'la segunda pregunta sigue en el mismo hilo');
    }

    public function test_una_herramienta_desconocida_vuelve_al_modelo_como_error_sin_romper_la_respuesta(): void
    {
        $this->modeloGuionado([$this->pide('no_existe', ['x' => 1]), $this->contesta('No pude consultar eso.')]);

        Livewire::test(Panel::class)->set('pregunta', 'algo raro')->call('enviar');

        $resultado = end($this->llamadas[1][1])['content'][0];
        $this->assertTrue($resultado['is_error']);
        $this->assertStringContainsString("No existe la consulta 'no_existe'", $resultado['content']);
        $respuesta = ChatMensaje::where('rol', 'asistente')->firstOrFail();
        $this->assertSame('No pude consultar eso.', $respuesta->contenido);
        $this->assertStringContainsString('no_existe', $respuesta->herramientas[0]['error']);
        $this->assertNull($respuesta->error);
    }

    public function test_si_la_api_falla_la_respuesta_guardada_explica_el_error_en_vez_de_romper_la_pantalla(): void
    {
        $this->modeloGuionado([new RuntimeException('Your credit balance is too low to access the Anthropic API.')]);

        $panel = Livewire::test(Panel::class)->set('pregunta', '¿cómo va el 28842?')->call('enviar');

        $panel->assertDispatched('chat-respondido')->assertSeeHtml('chat-msg--error')->assertSee('sin saldo');
        $respuesta = ChatMensaje::where('rol', 'asistente')->firstOrFail();
        $this->assertStringContainsString('sin saldo', $respuesta->contenido);
        $this->assertSame($respuesta->contenido, $respuesta->error);
        $this->assertSame(1, ChatConversacion::firstOrFail()->preguntas);
    }

    public function test_el_tope_diario_rechaza_la_pregunta_sin_llamar_al_modelo(): void
    {
        config(['chat.tope_preguntas_dia' => 1]);
        $this->modeloGuionado([$this->contesta('no debería salir')]);
        $conversacion = ChatConversacion::factory()->create(['user_id' => $this->user->id]);
        ChatMensaje::factory()->create(['conversacion_id' => $conversacion->id, 'contenido' => 'pregunta de hoy']);

        Livewire::test(Panel::class)->set('pregunta', 'otra')->call('enviar')->assertDispatched('errorAlert')->assertNotDispatched('chat-respondido');

        $this->assertCount(0, $this->llamadas);
        $this->assertSame(1, ChatMensaje::count());
    }

    public function test_la_pregunta_vacia_no_hace_nada_y_la_muy_larga_se_rechaza_con_aviso(): void
    {
        $this->modeloGuionado([$this->contesta('no debería salir')]);

        Livewire::test(Panel::class)->set('pregunta', '   ')->call('enviar')->assertNotDispatched('chat-respondido');
        Livewire::test(Panel::class)->set('pregunta', str_repeat('a', 1001))->call('enviar')->assertDispatched('errorAlert');

        $this->assertCount(0, $this->llamadas);
        $this->assertSame(0, ChatMensaje::count());
    }

    public function test_nueva_conversacion_empieza_otro_hilo_y_abrir_solo_acepta_hilos_propios(): void
    {
        $this->modeloGuionado([$this->contesta('uno'), $this->contesta('dos')]);
        $ajena = ChatConversacion::factory()->create();

        $panel = Livewire::test(Panel::class)->set('pregunta', 'primera')->call('enviar')->call('nueva')->set('pregunta', 'segunda')->call('enviar');

        $this->assertSame(2, ChatConversacion::where('user_id', $this->user->id)->count());
        $this->assertSame([['role' => 'user', 'content' => 'segunda']], $this->llamadas[1][1], 'el hilo nuevo no arrastra el anterior');
        $panel->call('abrir', $ajena->id)->assertSet('conversacionId', null);
    }

    public function test_la_respuesta_se_pinta_como_markdown_seguro_sin_html_crudo_ni_imagenes(): void
    {
        $conversacion = ChatConversacion::factory()->create(['user_id' => $this->user->id]);
        $m = ChatMensaje::factory()->asistente()->create([
            'conversacion_id' => $conversacion->id,
            'contenido' => '**Saldo** S/ 1.00 [ficha](https://prestamoh.test/clients/1) ![baliza](https://malo.example/x.gif) <script>alert(1)</script> [mal](javascript:alert(1))',
        ]);

        $html = $m->contenidoHtml();

        $this->assertStringContainsString('<strong>Saldo</strong>', $html);
        $this->assertStringContainsString('<a target="_blank" rel="noopener" href="https://prestamoh.test/clients/1">ficha</a>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_sin_abrir_el_panel_no_se_consulta_el_hilo_y_al_abrirlo_se_retoma_la_conversacion_reciente(): void
    {
        $conversacion = ChatConversacion::factory()->create(['user_id' => $this->user->id, 'ultima_actividad_at' => now()->subHour()]);
        ChatMensaje::factory()->asistente()->create(['conversacion_id' => $conversacion->id, 'contenido' => 'Respuesta anterior visible.']);

        $panel = Livewire::test(Panel::class);
        $panel->assertSet('abierto', false)->assertSet('conversacionId', null)->assertDontSee('Respuesta anterior visible.');

        $panel->call('abrirPanel')->assertSet('abierto', true)->assertSet('conversacionId', $conversacion->id)->assertSee('Respuesta anterior visible.');
    }

    public function test_el_boton_del_chat_sale_en_el_layout_solo_con_clave_configurada_y_para_usuarios_logueados(): void
    {
        $this->seed(PermissionCatalogSeeder::class);

        $this->get(route('perfil'))->assertOk()->assertSee('chat-boton')->assertSee('Chat de consulta');

        config(['chat.habilitado' => false]);
        $this->get(route('perfil'))->assertOk()->assertDontSee('chat-boton');

        auth()->logout();
        $this->get(route('perfil'))->assertRedirect(route('login'));
    }

    /** 30/10 (Antony): "el chat de IA hay que desactivarlo" — apagado salvo CHAT_ACTIVO=true, aunque haya clave. */
    public function test_el_chat_queda_apagado_aunque_haya_clave_salvo_que_se_active(): void
    {
        $antes = [$_SERVER['CHAT_API_KEY'] ?? null, $_SERVER['CHAT_ACTIVO'] ?? null];
        try {
            $_SERVER['CHAT_API_KEY'] = 'sk-ant-falsa';
            unset($_SERVER['CHAT_ACTIVO']);
            $this->assertFalse((require config_path('chat.php'))['habilitado'], 'por defecto, apagado');

            $_SERVER['CHAT_ACTIVO'] = 'false';
            $this->assertFalse((require config_path('chat.php'))['habilitado']);

            $_SERVER['CHAT_ACTIVO'] = 'true';
            $this->assertTrue((require config_path('chat.php'))['habilitado'], 'encendido a propósito y con clave');

            $_SERVER['CHAT_API_KEY'] = '';
            $this->assertFalse((require config_path('chat.php'))['habilitado'], 'sin clave no hay chat');
        } finally {
            foreach (['CHAT_API_KEY' => $antes[0], 'CHAT_ACTIVO' => $antes[1]] as $clave => $valor) {
                if ($valor === null) {
                    unset($_SERVER[$clave]);
                } else {
                    $_SERVER[$clave] = $valor;
                }
            }
        }
    }
}
