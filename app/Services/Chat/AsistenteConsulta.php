<?php

namespace App\Services\Chat;

use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Models\User;
use App\Services\Chat\Herramientas\Catalogo;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * El ciclo del chat de consulta (23/10/2026): guarda la pregunta, manda al
 * modelo las instrucciones, el historial y las herramientas, corre las
 * consultas de solo lectura que el modelo pida (hasta chat.max_herramientas
 * vueltas), guarda la respuesta con sus tokens y costo, y deja la pregunta
 * en la auditoría. Nunca lanza: si la API falla, la respuesta guardada
 * lleva el error en un mensaje entendible.
 */
final class AsistenteConsulta
{
    public function __construct(private ModeloDeChat $modelo, private Catalogo $herramientas) {}

    public function preguntar(ChatConversacion $conversacion, User $usuario, string $pregunta): ChatMensaje
    {
        $inicio = microtime(true);
        $pregunta = trim($pregunta);
        $mensajes = $this->historial($conversacion);
        $mensajes[] = ['role' => 'user', 'content' => $pregunta];
        $conversacion->mensajes()->create(['rol' => ChatMensaje::ROL_USUARIO, 'contenido' => $pregunta]);

        $definiciones = $this->herramientas->definiciones();
        $sistema = $this->instrucciones($usuario);
        $maxVueltas = max(1, (int) config('chat.max_herramientas', 8));
        $presupuesto = max(30, (int) config('chat.presupuesto_segundos', 120));
        $uso = ['entrada' => 0, 'salida' => 0, 'cache_lectura' => 0, 'cache_escritura' => 0];
        $usadas = [];
        $texto = '';
        $modeloUsado = (string) config('chat.modelo');
        $error = null;

        try {
            for ($vuelta = 1; $vuelta <= $maxVueltas + 1; $vuelta++) {
                $respuesta = $this->modelo->responder($sistema, $mensajes, $definiciones);
                $modeloUsado = $respuesta->modelo ?: $modeloUsado;
                $uso['entrada'] += $respuesta->tokensEntrada;
                $uso['salida'] += $respuesta->tokensSalida;
                $uso['cache_lectura'] += $respuesta->tokensCacheLectura;
                $uso['cache_escritura'] += $respuesta->tokensCacheEscritura;

                if (! $respuesta->pideHerramientas()) {
                    $texto = $this->textoFinal($respuesta);
                    break;
                }
                if ($vuelta > $maxVueltas) {
                    $texto = trim($respuesta->texto()."\n\nNecesité más consultas de las permitidas para esta pregunta; acótala (un cliente o un crédito a la vez).");
                    break;
                }
                if (microtime(true) - $inicio > $presupuesto) {
                    $texto = trim($respuesta->texto()."\n\nLa consulta está tardando demasiado; vuelve a preguntar algo más acotado.");
                    break;
                }

                $mensajes[] = ['role' => 'assistant', 'content' => $respuesta->contenidoParaHistorial()];
                $resultados = [];
                foreach ($respuesta->usosDeHerramienta() as $peticion) {
                    $t0 = microtime(true);
                    $resultado = $this->herramientas->ejecutar($peticion['name'], $peticion['input']);
                    $usadas[] = [
                        'nombre' => $peticion['name'],
                        'argumentos' => $peticion['input'],
                        'ms' => (int) round((microtime(true) - $t0) * 1000),
                        'error' => $resultado['ok'] ? null : ($resultado['error'] ?? 'falló'),
                    ];
                    $resultados[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $peticion['id'],
                        'content' => json_encode($resultado['ok'] ? $resultado['datos'] : ['error' => $resultado['error'] ?? 'falló'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION),
                        'is_error' => ! $resultado['ok'],
                    ];
                }
                $mensajes[] = ['role' => 'user', 'content' => $resultados];
            }
        } catch (Throwable $e) {
            Log::warning('El chat de consulta falló', ['modelo' => $modeloUsado, 'clase' => $e::class, 'error' => $e->getMessage()]);
            $error = self::traducir($e);
            $texto = $error;
        }

        $respuestaGuardada = $conversacion->mensajes()->create([
            'rol' => ChatMensaje::ROL_ASISTENTE,
            'contenido' => $texto !== '' ? $texto : 'No obtuve respuesta del modelo; intenta de nuevo.',
            'herramientas' => $usadas,
            'modelo' => $modeloUsado,
            'tokens_entrada' => $uso['entrada'],
            'tokens_salida' => $uso['salida'],
            'tokens_cache_lectura' => $uso['cache_lectura'],
            'tokens_cache_escritura' => $uso['cache_escritura'],
            'costo_usd' => Costos::usd($modeloUsado, $uso['entrada'], $uso['salida'], $uso['cache_lectura'], $uso['cache_escritura']),
            'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'error' => $error,
        ]);
        $conversacion->registrarRespuesta($respuestaGuardada, $pregunta);

        Audit::log('Consultó al chat: '.Str::limit($pregunta, 120), $conversacion, [
            'conversacion' => $conversacion->id,
            'herramientas' => array_values(array_unique(array_column($usadas, 'nombre'))),
            'tokens' => $respuestaGuardada->tokens(),
            'costo_usd' => (float) $respuestaGuardada->costo_usd,
            'error' => $error,
        ]);

        return $respuestaGuardada;
    }

    /**
     * Los últimos mensajes del hilo como turnos user/assistant (solo texto;
     * las consultas ya corridas no viajan: si hacen falta, el modelo las repite).
     *
     * @return list<array{role: string, content: string}>
     */
    private function historial(ChatConversacion $conversacion): array
    {
        // La relación ordena ascendente; aquí se quieren los ÚLTIMOS N, luego en orden cronológico.
        $previos = ChatMensaje::where('conversacion_id', $conversacion->id)
            ->orderByDesc('id')->limit(max(0, (int) config('chat.historial', 12)))->get()->reverse()->values();
        $turnos = [];
        foreach ($previos as $m) {
            $texto = trim((string) $m->contenido);
            if ($texto === '' || ($m->esAsistente() && $m->error !== null)) {
                continue;
            }
            $rol = $m->esAsistente() ? 'assistant' : 'user';
            if ($turnos !== [] && $turnos[count($turnos) - 1]['role'] === $rol) {
                $turnos[count($turnos) - 1]['content'] .= "\n\n".$texto;
            } else {
                $turnos[] = ['role' => $rol, 'content' => $texto];
            }
        }
        while ($turnos !== [] && $turnos[0]['role'] !== 'user') {
            array_shift($turnos);
        }
        if ($turnos !== [] && $turnos[count($turnos) - 1]['role'] === 'user') {
            array_pop($turnos);
        }

        return $turnos;
    }

    private function textoFinal(RespuestaModelo $respuesta): string
    {
        $texto = $respuesta->texto();

        return match ($respuesta->motivoParada) {
            'refusal' => $texto !== '' ? $texto : 'El modelo no quiso responder esa pregunta.',
            'max_tokens' => trim($texto."\n\n(Respuesta recortada por largo: pide una parte a la vez.)"),
            default => $texto,
        };
    }

    private function instrucciones(User $usuario): string
    {
        $hoy = Carbon::today()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY');
        $nombre = $usuario->username ?? $usuario->name;
        $rol = $usuario->roles->first()?->name ?? 'usuario';

        return <<<TXT
Eres el asistente de consulta del sistema de préstamos de Huacachín (microfinanzas en Lima, Perú). Respondes en español, con el vocabulario de la oficina: crédito, cuota, cronograma, cobro, mora, atraso, asesor, expediente, cancelado, refinanciado.

Hoy es {$hoy}. Te consulta {$nombre} (rol: {$rol}).

Reglas:
- Solo puedes LEER datos con las herramientas disponibles. No registras, modificas ni borras nada; si te lo piden, di que el chat es solo de consulta y en qué pantalla se hace.
- Nunca inventes ni calcules de memoria cifras, fechas o nombres: usa únicamente lo que devuelven las herramientas. Si una herramienta devuelve error o nada, dilo tal cual.
- Cuando mencionen a una persona, empieza por buscar_clientes. Si hay varias coincidencias, lista expediente, nombre y documento y pregunta cuál; si hay una sola, continúa con ficha_cliente sin preguntar.
- Para "cómo va", "cuánto debe", "está atrasado" basta ficha_cliente (trae el resumen de cada crédito activo). Usa cronograma_credito solo si piden cuotas una por una, pagos_credito si piden cobros o recibos, detalle_credito cuando dan el código del crédito, documentos_cliente para contratos y anexos.
- Di siempre de qué crédito (código) es cada cifra. Montos en soles con el formato S/ 1,234.56; fechas como 21/10/2026.
- Sé concreto y breve: primero la respuesta directa, luego los datos que la sustentan (listas o una tabla pequeña). No expliques qué herramientas usaste.
- Si las herramientas traen enlaces (ficha, cronograma, recibo, PDF), ofrécelos como enlaces Markdown con un texto corto, por ejemplo [ver cronograma](url). No inventes URLs.
- Si la pregunta no tiene que ver con clientes, créditos, cobros o documentos del sistema, responde en una línea que solo consultas datos del sistema.
TXT;
    }

    /** Mensaje para el usuario según el fallo de la API (la traza va al log). */
    public static function traducir(Throwable $e): string
    {
        $m = mb_strtolower($e->getMessage());
        $clase = $e::class;

        return match (true) {
            str_contains($m, 'credit balance') || str_contains($m, 'insufficient') || str_contains($m, 'billing') => 'El chat se quedó sin saldo en la cuenta de Anthropic: avisa a sistemas.',
            str_contains($m, 'x-api-key') || str_contains($m, 'authentication') || str_contains($m, 'invalid api key') || str_contains($clase, 'AuthenticationException') => 'El chat no pudo autenticarse con Anthropic (clave rechazada): avisa a sistemas.',
            str_contains($m, 'rate limit') || str_contains($m, 'overloaded') || str_contains($clase, 'RateLimitException') => 'El servicio del chat está saturado; intenta de nuevo en un momento.',
            str_contains($m, 'timed out') || str_contains($m, 'timeout') || str_contains($m, 'could not resolve') || str_contains($clase, 'APITimeoutException') || str_contains($clase, 'APIConnectionException') => 'No hubo conexión con el servicio del chat (o tardó demasiado); intenta de nuevo.',
            str_contains($m, 'falta chat_api_key') => 'El chat no está configurado (falta la clave de la API).',
            default => 'El chat no pudo responder esta vez; intenta de nuevo. Si sigue, avisa a sistemas.',
        };
    }
}
