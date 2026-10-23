{{-- Chat de consulta (23/10/2026, Antony): botón flotante abajo a la derecha, a la IZQUIERDA del
     botón de subir (.go-top ocupa right 24-74 px / bottom 48-98 px: no se tapa), y panel encima de ambos.
     El panel va por debajo de la cabecera fija (z 1001) para no tapar sus menús; el hilo se carga
     recién al abrir ($wire.abrirPanel). Dentro de x-data y x-on solo comillas simples. --}}
<div x-data="chatPanel()" x-on:chat-respondido.window="$nextTick(() => { bajar(); enfocar(); })" class="chat-consulta">

    <button type="button" class="chat-boton" :class="{ 'chat-boton--abierto': abierto }" x-on:click="alternar()"
            title="Chat de consulta: pregunta por un cliente o un crédito">
        <i class="ti" :class="abierto ? 'ti-x' : 'ti-message-chatbot'"></i>
    </button>

    <div class="card chat-panel" x-show="abierto" x-cloak x-transition.opacity.duration.120ms>
        <div class="chat-cabecera">
            <div class="chat-titulo">
                <b><i class="ti ti-message-chatbot"></i> Chat de consulta</b>
                <div class="chat-sub">
                    @if($abierto)
                        Solo lectura · gasto del mes USD {{ number_format($gastoMes, 2) }} · hoy {{ $preguntasHoy }} {{ $preguntasHoy === 1 ? 'pregunta' : 'preguntas' }}
                    @else
                        Solo lectura · cargando…
                    @endif
                </div>
            </div>
            <div class="chat-acciones">
                <button type="button" title="Conversaciones anteriores" x-on:click="historialAbierto = ! historialAbierto"><i class="ti ti-history"></i></button>
                <button type="button" title="Nueva conversación" wire:click="nueva" x-on:click="historialAbierto = false"
                        wire:loading.attr="disabled" wire:target="enviar,sugerir"><i class="ti ti-plus"></i></button>
                <button type="button" title="Cerrar" x-on:click="cerrar()"><i class="ti ti-x"></i></button>
            </div>
        </div>

        <div class="chat-historial" x-show="historialAbierto" x-cloak wire:loading.class="chat-bloqueado" wire:target="enviar,sugerir">
            @forelse($historial as $h)
                <a href="#" wire:click.prevent="abrir({{ $h->id }})" x-on:click="historialAbierto = false"
                   class="chat-historial-item {{ $h->id === $conversacionId ? 'activa' : '' }}">
                    <span class="chat-historial-titulo">{{ $h->titulo ?? 'Sin título' }}</span>
                    <span class="chat-historial-meta">{{ $h->ultima_actividad_at?->format('d/m H:i') }} · {{ $h->preguntas }} {{ $h->preguntas === 1 ? 'pregunta' : 'preguntas' }}</span>
                </a>
            @empty
                <div class="chat-historial-vacio">Todavía no hay conversaciones.</div>
            @endforelse
        </div>

        <div class="chat-cuerpo" x-ref="cuerpo">
            @if($abierto && $mensajes->isEmpty())
                <div class="chat-vacio">
                    <p>Pregunta en tus palabras por un cliente o un crédito. Solo consulto datos del sistema; no registro nada.</p>
                    <div class="chat-sugerencias">
                        @foreach(['¿Cómo va el cliente con expediente 48?', '¿Qué cuotas debe el crédito 28842?', '¿Cuál fue el último pago del crédito 29479?', '¿Qué documentos tiene el cliente Acuña Hilares?'] as $s)
                            <button type="button" wire:click="sugerir(@js($s))" x-on:click="setTimeout(() => bajar(), 30)"
                                    wire:loading.attr="disabled" wire:target="enviar,sugerir">{{ $s }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach($mensajes as $m)
                @if($m->esAsistente())
                    <div class="chat-msg chat-msg--asistente {{ $m->error ? 'chat-msg--error' : '' }}" wire:key="m-{{ $m->id }}">
                        <div class="chat-md">{!! $m->contenidoHtml() !!}</div>
                        <div class="chat-meta">
                            @if($m->nombresDeHerramientas() !== [])
                                consultó {{ implode(', ', $m->nombresDeHerramientas()) }} ·
                            @endif
                            {{ number_format(($m->duracion_ms ?? 0) / 1000, 1) }} s · {{ number_format($m->tokens()) }} tokens · USD {{ number_format((float) $m->costo_usd, 4) }}
                        </div>
                    </div>
                @else
                    <div class="chat-msg chat-msg--usuario" wire:key="m-{{ $m->id }}">{{ $m->contenido }}</div>
                @endif
            @endforeach

            <div class="chat-msg chat-msg--asistente chat-pensando" wire:loading wire:target="enviar,sugerir,abrirPanel">
                <i class="ti ti-loader-2 chat-giro"></i> Consultando…
            </div>
        </div>

        <div class="chat-pie">
            <textarea wire:model="pregunta" rows="2" maxlength="1000" x-ref="entrada"
                      placeholder="Pregunta por un cliente o un crédito… (Enter envía, Shift+Enter baja de línea)"
                      x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.enviar(); setTimeout(() => bajar(), 30); }"
                      wire:loading.attr="disabled" wire:target="enviar,sugerir"></textarea>
            <button type="button" class="chat-enviar" wire:click="enviar" x-on:click="setTimeout(() => bajar(), 30)"
                    wire:loading.attr="disabled" wire:target="enviar,sugerir" title="Enviar">
                <i class="ti ti-send"></i>
            </button>
        </div>
    </div>

    <style>
        .chat-consulta .chat-boton {
            position: fixed; bottom: 48px; right: 90px; width: 50px; height: 50px; border-radius: 50%; border: 0;
            background: #009BDC; color: #fff; font-size: 26px; display: grid; place-items: center; cursor: pointer;
            box-shadow: 0 .3rem 1.2rem rgba(0, 0, 0, .28); z-index: 1003;
        }
        .chat-consulta .chat-boton:hover { background: #0b87bd; }
        .chat-consulta .chat-boton--abierto { background: #2d2f39; }
        .chat-consulta .chat-panel {
            position: fixed; bottom: 110px; right: 24px; width: 440px; max-width: calc(100vw - 32px);
            height: min(72vh, 660px); max-height: calc(100vh - 110px - 96px); z-index: 1000; display: flex; flex-direction: column; margin: 0;
            border: 0; border-radius: 15px; overflow: hidden; box-shadow: 0 14px 48px rgba(0, 0, 0, .3);
            transition: none !important; font-family: 'Poppins', sans-serif; background: #fff;
        }
        .chat-consulta .chat-cabecera { background: #009BDC; color: #fff; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .chat-consulta .chat-titulo b { font-size: 14px; }
        .chat-consulta .chat-sub { font-size: 10px; opacity: .9; }
        .chat-consulta .chat-acciones { display: flex; gap: 4px; }
        .chat-consulta .chat-acciones button { background: rgba(255, 255, 255, .15); border: 0; color: #fff; width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; cursor: pointer; font-size: 15px; }
        .chat-consulta .chat-acciones button:hover { background: rgba(255, 255, 255, .3); }
        .chat-consulta .chat-acciones button:disabled { opacity: .5; cursor: default; }
        .chat-consulta .chat-historial { background: #eef6fc; border-bottom: 1px solid #d8e6f0; max-height: 180px; overflow-y: auto; }
        .chat-consulta .chat-historial.chat-bloqueado { pointer-events: none; opacity: .6; }
        .chat-consulta .chat-historial-item { display: flex; flex-direction: column; padding: 6px 14px; text-decoration: none; color: #0b1b3d; border-bottom: 1px solid #e1ecf4; }
        .chat-consulta .chat-historial-item:hover, .chat-consulta .chat-historial-item.activa { background: #dcecf8; }
        .chat-consulta .chat-historial-titulo { font-size: 12px; font-weight: 500; }
        .chat-consulta .chat-historial-meta, .chat-consulta .chat-historial-vacio { font-size: 10px; color: #6b7280; }
        .chat-consulta .chat-historial-vacio { padding: 8px 14px; }
        .chat-consulta .chat-cuerpo { flex: 1; overflow-y: auto; padding: 12px; background: #f5f6fa; display: flex; flex-direction: column; gap: 8px; }
        .chat-consulta .chat-vacio { color: #4b5563; font-size: 12px; padding: 6px 2px; }
        .chat-consulta .chat-sugerencias { display: flex; flex-direction: column; gap: 6px; }
        .chat-consulta .chat-sugerencias button { text-align: left; background: #fff; border: 1px dashed rgba(35, 25, 40, .25); border-radius: 10px; padding: 7px 10px; font-size: 12px !important; color: #0b1b3d; cursor: pointer; }
        .chat-consulta .chat-sugerencias button:hover { background: #eef6fc; border-style: solid; }
        .chat-consulta .chat-sugerencias button:disabled { opacity: .5; cursor: default; }
        .chat-consulta .chat-msg { max-width: 92%; padding: 8px 11px; border-radius: 12px; font-size: 12.5px; line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
        .chat-consulta .chat-msg--usuario { align-self: flex-end; background: #009BDC; color: #fff; border-bottom-right-radius: 3px; }
        .chat-consulta .chat-msg--asistente { align-self: flex-start; background: #fff; color: #15264b; border: 1px solid #e5e7eb; border-bottom-left-radius: 3px; white-space: normal; }
        .chat-consulta .chat-msg--error { border-color: #f5c2c0; background: #fff5f5; }
        .chat-consulta .chat-md p { margin: 0 0 6px; }
        .chat-consulta .chat-md p:last-child { margin-bottom: 0; }
        .chat-consulta .chat-md ul, .chat-consulta .chat-md ol { padding-left: 18px; margin: 4px 0 6px; }
        .chat-consulta .chat-md table { font-size: 11px; border-collapse: collapse; margin: 4px 0 6px; }
        .chat-consulta .chat-md th, .chat-consulta .chat-md td { border: 1px solid #e5e7eb; padding: 2px 6px !important; font-size: 11px !important; text-align: left !important; }
        .chat-consulta .chat-md a { color: #0b87bd; text-decoration: underline; }
        .chat-consulta .chat-md code { background: #f1f5f9; padding: 0 3px; border-radius: 3px; }
        .chat-consulta .chat-meta { margin-top: 5px; font-size: 10px; color: #6b7280; }
        .chat-consulta .chat-pensando { color: #6b7280; }
        .chat-consulta .chat-giro { display: inline-block; animation: chatGiro 1s linear infinite; }
        @keyframes chatGiro { from { transform: rotate(0); } to { transform: rotate(360deg); } }
        .chat-consulta .chat-pie { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #e5e7eb; background: #fff; }
        .chat-consulta .chat-pie textarea { flex: 1; resize: none; border: 1px solid #d1d5db; border-radius: 10px; padding: 8px 10px; font-size: 13px !important; font-family: 'Poppins', sans-serif; line-height: 1.4; }
        .chat-consulta .chat-pie textarea:focus { outline: none; border-color: #009BDC; box-shadow: 0 0 0 3px rgba(0, 155, 220, .15); }
        .chat-consulta .chat-pie textarea:disabled { background: #f3f4f6; }
        .chat-consulta .chat-enviar { width: 42px; border: 0; border-radius: 10px; background: #009BDC; color: #fff; font-size: 18px; cursor: pointer; }
        .chat-consulta .chat-enviar:disabled { background: #9ca3af; cursor: default; }
        @media (max-width: 767.98px) {
            .chat-consulta .chat-panel { right: 8px; bottom: 104px; height: min(70vh, 560px); }
            .chat-consulta .chat-boton { right: 84px; }
        }
        @media print { .chat-consulta { display: none !important; } }
    </style>

    <script>
        function chatPanel() {
            return {
                abierto: false,
                historialAbierto: false,
                init() {
                    try { this.abierto = localStorage.getItem('chat-consulta-abierto') === '1'; } catch (e) {}
                    if (this.abierto) this.cargar();
                },
                alternar() { this.abierto ? this.cerrar() : this.abrir(); },
                abrir() {
                    this.abierto = true;
                    this.guardar();
                    this.cargar();
                    this.$nextTick(() => { this.bajar(); this.enfocar(); });
                },
                // El hilo se pide al servidor una sola vez por página, al abrir.
                cargar() { if (this.$wire && ! this.$wire.abierto) this.$wire.abrirPanel().then(() => this.$nextTick(() => this.bajar())); },
                cerrar() { this.abierto = false; this.historialAbierto = false; this.guardar(); },
                guardar() { try { localStorage.setItem('chat-consulta-abierto', this.abierto ? '1' : '0'); } catch (e) {} },
                bajar() { const c = this.$refs.cuerpo; if (c) c.scrollTop = c.scrollHeight; },
                enfocar() { if (this.abierto && this.$refs.entrada && ! this.$refs.entrada.disabled) this.$refs.entrada.focus(); },
            };
        }
    </script>
</div>
