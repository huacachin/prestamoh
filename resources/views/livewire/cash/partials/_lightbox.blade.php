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
    cabecera, se redimensiona por la esquina y recuerda posición y tamaño. En
    celular sigue a pantalla completa. Los atajos (Esc / ← / →) viven AQUÍ, no
    en el ancestro (duplicarlos haría saltar dos fotos por pulsación); las
    flechas no actúan mientras se escribe en un campo. JS: assets/js/ventanas-flotantes.js.
--}}
<div x-data="visorFlotante" x-show="open" x-cloak x-transition.opacity
     class="huac-lb" :class="{ 'huac-lb--flotante': flotante }"
     @click.self="flotante || close()"
     @keydown.escape.window="open && close()"
     @keydown.arrow-right.window="open && !escribiendo($event) && next()"
     @keydown.arrow-left.window="open && !escribiendo($event) && prev()">
    <div class="huac-lb__ventana" x-ref="ventana" tabindex="-1" @click.stop>
        <div class="huac-lb__cabecera"
             @pointerdown="iniciarArrastre($event)" @pointermove="arrastrar($event)"
             @pointerup="soltar($event)" @pointercancel="soltar($event)">
            <span class="huac-lb__titulo" x-text="items[idx]?.name" :title="items[idx]?.name"></span>
            <span class="huac-lb__counter" x-show="items.length > 1"
                  x-text="(idx + 1) + ' / ' + items.length"></span>
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
        <div class="huac-lb__cuerpo">
            <img :src="items[idx]?.url" :alt="items[idx]?.name" class="huac-lb__img" draggable="false">
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    /* Celular (y antes de decidir): visor a pantalla completa con fondo oscuro. */
    .huac-lb { position: fixed; inset: 0; z-index: 1080; background: rgba(0,0,0,.88);
        display: flex; align-items: center; justify-content: center; padding: 12px; }
    .huac-lb__ventana { display: flex; flex-direction: column; max-width: 100%; max-height: 100%;
        background: #111; border-radius: 8px; box-shadow: 0 12px 60px rgba(0,0,0,.6);
        overflow: hidden; outline: none; }
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
    .huac-lb__cuerpo { flex: 1 1 auto; min-height: 0; display: flex; align-items: center;
        justify-content: center; background: #111; }
    .huac-lb__img { max-width: 100%; max-height: 100%; width: auto; height: auto;
        object-fit: contain; display: block; }
    .huac-lb:not(.huac-lb--flotante) .huac-lb__img { max-width: calc(100vw - 24px); max-height: calc(100vh - 66px); }
    /* Escritorio: ventana flotante, sin fondo; la página sigue viva debajo. */
    .huac-lb--flotante { background: transparent; pointer-events: none; padding: 0; display: block; }
    .huac-lb--flotante .huac-lb__ventana { position: fixed; pointer-events: auto;
        max-width: none; max-height: none; min-width: 260px; min-height: 180px;
        resize: both; border: 1px solid rgba(255,255,255,.15); }
    .huac-lb--flotante .huac-lb__cabecera { cursor: grab; }
    .huac-lb--flotante .huac-lb__cabecera:active { cursor: grabbing; }
</style>
