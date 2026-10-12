{{--
    Visor de imágenes reutilizable (vouchers y adjuntos de caja, adjuntos y fotos
    GPS del cliente, capturas de auditoría).

    Espera que un x-data ANCESTRO defina:
      - open   (bool)            : si el visor está abierto
      - idx    (int)             : índice de la imagen actual
      - items  (array)           : [{ url, name }, ...]
      - close(), next(), prev()  : controles
      - manageUrl (string, opc.) : si está presente, muestra el botón "Gestionar adjuntos"

    10/10/2026 (Antony): en escritorio (≥ 768 px) es una VENTANA FLOTANTE sin
    fondo oscuro: la página sigue viva debajo, se arrastra solo desde la
    cabecera y se redimensiona por la esquina. En celular sigue a pantalla
    completa. Los atajos (Esc / ← / →) viven AQUÍ, no en el ancestro
    (duplicarlos haría saltar dos fotos por pulsación); las flechas no actúan
    mientras se escribe en un campo.

    12/10/2026 (Antony, "las imágenes se ven pequeñas"): la ventana abre al
    TAMAÑO REAL de la foto (tope 85 % de la pantalla) y la foto tiene zoom:
    botones − / % / + / Ajustar, rueda del mouse sobre la foto (acerca donde
    está el cursor), doble clic (ajustada ↔ 100 %), arrastre cuando no cabe,
    pellizco en el celular y teclas + − 0 1. Maximizar / Restaurar en la
    cabecera (también con doble clic en ella). JS: assets/js/ventanas-flotantes.js.
--}}
<div x-data="visorFlotante" x-show="open" x-cloak x-transition.opacity
     class="huac-lb" :class="{ 'huac-lb--flotante': flotante }"
     @click.self="flotante || close()"
     @keydown.escape.window="open && close()"
     @keydown.arrow-right.window="open && !escribiendo($event) && next()"
     @keydown.arrow-left.window="open && !escribiendo($event) && prev()"
     @keydown.window="tecla($event)">
    <div class="huac-lb__ventana" x-ref="ventana" tabindex="-1" @click.stop>
        <div class="huac-lb__cabecera"
             @pointerdown="iniciarArrastre($event)" @pointermove="arrastrar($event)"
             @pointerup="soltar($event)" @pointercancel="soltar($event)"
             @dblclick="maximizar()">
            <span class="huac-lb__titulo" x-text="items[idx]?.name" :title="items[idx]?.name"></span>
            <span class="huac-lb__counter" x-show="items.length > 1"
                  x-text="(idx + 1) + ' / ' + items.length"></span>
            {{-- Zoom de la foto --}}
            <button type="button" class="huac-lb__btn" @click.stop="alejar()" title="Alejar (−)">
                <i class="ti ti-zoom-out"></i>
            </button>
            <button type="button" class="huac-lb__btn huac-lb__zoom" @click.stop="real()" title="Tamaño real, 100 % (1)" x-text="porcentaje()"></button>
            <button type="button" class="huac-lb__btn" @click.stop="acercar()" title="Acercar (+)">
                <i class="ti ti-zoom-in"></i>
            </button>
            <button type="button" class="huac-lb__btn" @click.stop="ajustar()" title="Ajustar a la ventana (0)">
                <i class="ti ti-aspect-ratio"></i>
            </button>
            <button type="button" class="huac-lb__btn" x-show="flotante" @click.stop="maximizar()"
                    :title="previa ? 'Restaurar tamaño' : 'Maximizar ventana'">
                <i class="ti" :class="previa ? 'ti-minimize' : 'ti-maximize'"></i>
            </button>
            <button type="button" class="huac-lb__btn" x-show="items.length > 1" @click.stop="prev()" title="Anterior (←)">
                <i class="ti ti-chevron-left"></i>
            </button>
            <button type="button" class="huac-lb__btn" x-show="items.length > 1" @click.stop="next()" title="Siguiente (→)">
                <i class="ti ti-chevron-right"></i>
            </button>
            <template x-if="typeof manageUrl !== 'undefined' && manageUrl">
                <a :href="manageUrl" class="huac-lb__btn huac-lb__manage" title="Gestionar adjuntos">
                    <i class="ti ti-settings"></i>
                </a>
            </template>
            <button type="button" class="huac-lb__btn huac-lb__close" @click.stop="close()" title="Cerrar (Esc)">
                <i class="ti ti-x"></i>
            </button>
        </div>
        {{-- La foto se mueve con transform (translate + scale): rueda, doble clic, arrastre y pellizco. --}}
        <div class="huac-lb__cuerpo" x-ref="cuerpo" :class="{ 'huac-lb__cuerpo--mover': desplazable }"
             @wheel.prevent="rueda($event)" @dblclick="dobleClic($event)"
             @pointerdown="tocar($event)" @pointermove="moverFoto($event)"
             @pointerup="soltarFoto($event)" @pointercancel="soltarFoto($event)">
            <img :src="items[idx]?.url" :alt="items[idx]?.name" class="huac-lb__img" draggable="false"
                 x-ref="foto" @load="alCargar($event)">
        </div>
        {{-- Tirador visible de la esquina (el redimensionado es el nativo de CSS) --}}
        <span class="huac-lb__grip" x-show="flotante" aria-hidden="true"></span>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    /* Celular (y antes de decidir): visor a pantalla completa con fondo oscuro. */
    .huac-lb { position: fixed; inset: 0; z-index: 1080; background: rgba(0,0,0,.88);
        display: flex; align-items: center; justify-content: center; padding: 12px; }
    .huac-lb__ventana { position: relative; display: flex; flex-direction: column; max-width: 100%; max-height: 100%;
        background: #111; border-radius: 8px; box-shadow: 0 12px 60px rgba(0,0,0,.6);
        overflow: hidden; outline: none; }
    .huac-lb:not(.huac-lb--flotante) .huac-lb__ventana { width: 100%; height: 100%; }
    .huac-lb__cabecera { display: flex; align-items: center; gap: 6px; padding: 5px 8px; min-height: 40px;
        background: #1f2937; color: rgba(255,255,255,.85); font-size: 13px;
        user-select: none; -webkit-user-select: none; touch-action: none; }
    .huac-lb__titulo { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .huac-lb__counter { font-family: ui-monospace, monospace; white-space: nowrap;
        background: rgba(255,255,255,.1); padding: 2px 10px; border-radius: 999px; }
    .huac-lb__btn { flex: 0 0 auto; background: rgba(255,255,255,.08); color: #fff;
        border: 1px solid rgba(255,255,255,.18); width: 30px; height: 30px;
        border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
        font-size: 16px; cursor: pointer; text-decoration: none; transition: background .15s ease; }
    .huac-lb__btn:hover { background: rgba(255,255,255,.22); color: #fff; }
    .huac-lb__zoom { width: auto; min-width: 56px; padding: 0 8px; font-size: 12px; font-family: ui-monospace, monospace; }
    .huac-lb__cuerpo { flex: 1 1 auto; min-height: 0; position: relative; overflow: hidden; background: #111;
        touch-action: none; }
    .huac-lb__cuerpo--mover { cursor: grab; }
    .huac-lb__cuerpo--mover:active { cursor: grabbing; }
    .huac-lb__img { position: absolute; left: 0; top: 0; max-width: none; max-height: none; width: auto; height: auto;
        transform-origin: 0 0; display: block; user-select: none; -webkit-user-select: none; will-change: transform; }
    .huac-lb__grip { position: absolute; right: 2px; bottom: 2px; width: 14px; height: 14px; pointer-events: none;
        background: linear-gradient(135deg, transparent 0 50%, rgba(255,255,255,.55) 50% 58%, transparent 58% 70%,
            rgba(255,255,255,.55) 70% 78%, transparent 78% 90%, rgba(255,255,255,.55) 90% 98%, transparent 98%); }
    /* Escritorio: ventana flotante, sin fondo; la página sigue viva debajo. */
    .huac-lb--flotante { background: transparent; pointer-events: none; padding: 0; display: block; }
    .huac-lb--flotante .huac-lb__ventana { position: fixed; pointer-events: auto;
        max-width: none; max-height: none; min-width: 260px; min-height: 180px;
        resize: both; border: 1px solid rgba(255,255,255,.15); transition: none !important; }
    .huac-lb--flotante .huac-lb__cabecera { cursor: grab; }
    .huac-lb--flotante .huac-lb__cabecera:active { cursor: grabbing; }
</style>
