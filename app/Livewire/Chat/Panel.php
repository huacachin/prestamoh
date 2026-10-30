<?php

namespace App\Livewire\Chat;

use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Services\Chat\AsistenteConsulta;
use Livewire\Component;

/**
 * Chat de consulta (23/10/2026, Antony): botón flotante y panel que viven en
 * el layout para cualquier usuario logueado (sin permisos por rol: es solo
 * lectura). El usuario escribe en lenguaje natural y AsistenteConsulta
 * responde con herramientas de lectura; aquí solo se maneja el hilo, el
 * tope diario y lo que se pinta. Mientras el panel no se abre no se consulta
 * nada: el componente está en todas las páginas.
 */
class Panel extends Component
{
    /** El panel ya se abrió en esta página: recién entonces se cargan el hilo, el gasto y el historial. */
    public bool $abierto = false;

    public ?int $conversacionId = null;

    public string $pregunta = '';

    private const MAX_LARGO = 1000;

    /** Lo llama el navegador al abrir el panel (o al cargar la página si quedó abierto). */
    public function abrirPanel(): void
    {
        if ($this->abierto) {
            return;
        }
        $this->abierto = true;
        // Retoma la última conversación reciente del usuario; si no hay, el primer envío abre una nueva.
        $this->conversacionId ??= ChatConversacion::where('user_id', auth()->id())
            ->where('ultima_actividad_at', '>=', now()->subHours(12))
            ->orderByDesc('ultima_actividad_at')->value('id');
    }

    public function enviar(): void
    {
        $this->abrirPanel();
        $texto = trim($this->pregunta);
        if ($texto === '') {
            return;
        }
        if (mb_strlen($texto) > self::MAX_LARGO) {
            $this->dispatch('errorAlert', ['message' => 'La pregunta es muy larga (máximo '.self::MAX_LARGO.' caracteres).']);

            return;
        }
        if (! config('chat.habilitado')) {
            $this->dispatch('errorAlert', ['message' => 'El chat de consulta está desactivado.']);

            return;
        }
        $tope = (int) config('chat.tope_preguntas_dia', 300);
        if (self::preguntasHoy() >= $tope) {
            $this->dispatch('errorAlert', ['message' => "Se alcanzó el tope de {$tope} preguntas de hoy para toda la oficina; avisa a sistemas si hace falta subirlo."]);

            return;
        }

        $conversacion = $this->conversacion() ?? ChatConversacion::create(['user_id' => auth()->id(), 'modelo' => config('chat.modelo')]);
        $this->conversacionId = $conversacion->id;
        $this->pregunta = '';

        // Varias vueltas con la API pueden pasar de los 30 s que PHP da por defecto.
        @set_time_limit(180);
        app(AsistenteConsulta::class)->preguntar($conversacion, auth()->user(), $texto);
        $this->dispatch('chat-respondido');
    }

    /** Las preguntas sugeridas del panel vacío se mandan tal cual. */
    public function sugerir(string $texto): void
    {
        $this->pregunta = $texto;
        $this->enviar();
    }

    public function nueva(): void
    {
        $this->abrirPanel();
        $this->conversacionId = null;
        $this->pregunta = '';
    }

    public function abrir(int $id): void
    {
        $this->abrirPanel();
        $this->conversacionId = ChatConversacion::where('user_id', auth()->id())->whereKey($id)->value('id');
    }

    private function conversacion(): ?ChatConversacion
    {
        if ($this->conversacionId === null) {
            return null;
        }

        return ChatConversacion::where('user_id', auth()->id())->find($this->conversacionId);
    }

    public static function preguntasHoy(): int
    {
        return ChatMensaje::where('rol', ChatMensaje::ROL_USUARIO)
            ->whereBetween('created_at', [today(), today()->endOfDay()])->count();
    }

    public function render()
    {
        if (! $this->abierto) {
            return view('livewire.chat.panel', [
                'mensajes' => collect(), 'gastoMes' => 0.0, 'preguntasHoy' => 0, 'historial' => collect(), 'modelo' => (string) config('chat.modelo'),
            ]);
        }
        $conversacion = $this->conversacion();

        return view('livewire.chat.panel', [
            'mensajes' => $conversacion?->mensajes()->get() ?? collect(),
            'gastoMes' => (float) ChatMensaje::where('rol', ChatMensaje::ROL_ASISTENTE)->where('created_at', '>=', now()->startOfMonth())->sum('costo_usd'),
            'preguntasHoy' => self::preguntasHoy(),
            'historial' => ChatConversacion::where('user_id', auth()->id())->orderByDesc('ultima_actividad_at')->limit(8)->get(['id', 'titulo', 'preguntas', 'ultima_actividad_at']),
            'modelo' => (string) config('chat.modelo'),
        ]);
    }
}
