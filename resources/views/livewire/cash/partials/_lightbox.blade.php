{{--
    Lightbox de imágenes reutilizable (visor tipo galería).

    Espera que un x-data ANCESTRO defina:
      - open   (bool)            : si el visor está abierto
      - idx    (int)             : índice de la imagen actual
      - items  (array)           : [{ url, name }, ...]
      - close(), next(), prev()  : controles
      - manageUrl (string, opc.) : si está presente, muestra el botón "Gestionar adjuntos"

    Los atajos de teclado (Esc / ← / →) se registran en el x-data ancestro.
--}}
<div x-show="open" x-cloak x-transition.opacity
     @click.self="close()" class="huac-lb">
    <button type="button" class="huac-lb__btn huac-lb__close" @click="close()" title="Cerrar (Esc)">
        <i class="ti ti-x"></i>
    </button>
    <button type="button" class="huac-lb__btn huac-lb__nav huac-lb__nav--prev"
            x-show="items.length > 1" @click.stop="prev()" title="Anterior (←)">
        <i class="ti ti-chevron-left"></i>
    </button>
    <div class="huac-lb__stage" @click.stop>
        <img :src="items[idx]?.url" :alt="items[idx]?.name" class="huac-lb__img">
        <div class="huac-lb__caption">
            <span x-text="items[idx]?.name"></span>
            <span class="huac-lb__counter" x-show="items.length > 1"
                  x-text="(idx + 1) + ' / ' + items.length"></span>
            <template x-if="typeof manageUrl !== 'undefined' && manageUrl">
                <a :href="manageUrl" class="huac-lb__manage">
                    <i class="ti ti-settings"></i> Gestionar adjuntos
                </a>
            </template>
        </div>
    </div>
    <button type="button" class="huac-lb__btn huac-lb__nav huac-lb__nav--next"
            x-show="items.length > 1" @click.stop="next()" title="Siguiente (→)">
        <i class="ti ti-chevron-right"></i>
    </button>
</div>

<style>
    [x-cloak] { display: none !important; }
    .huac-lb { position: fixed; inset: 0; z-index: 1080; background: rgba(0,0,0,.88);
        display: flex; align-items: center; justify-content: center; padding: 20px; }
    .huac-lb__stage { max-width: 92vw; max-height: 92vh;
        display: flex; flex-direction: column; align-items: center; gap: 10px; }
    .huac-lb__img { max-width: 92vw; max-height: 82vh; object-fit: contain;
        background: #111; border-radius: 6px; box-shadow: 0 12px 60px rgba(0,0,0,.6); }
    .huac-lb__caption { color: rgba(255,255,255,.75); font-size: 13px;
        display: flex; gap: 14px; align-items: center; max-width: 92vw; text-align: center;
        flex-wrap: wrap; justify-content: center; }
    .huac-lb__counter { font-family: ui-monospace, monospace;
        background: rgba(255,255,255,.1); padding: 2px 10px; border-radius: 999px; }
    .huac-lb__manage { color: #fff; background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.25); padding: 4px 12px; border-radius: 999px;
        text-decoration: none; font-size: 12px; }
    .huac-lb__manage:hover { background: rgba(255,255,255,.22); }
    .huac-lb__btn { background: rgba(255,255,255,.08); color: #fff;
        border: 1px solid rgba(255,255,255,.18); width: 44px; height: 44px;
        border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
        font-size: 22px; cursor: pointer;
        transition: background .15s ease, transform .15s ease; }
    .huac-lb__btn:hover { background: rgba(255,255,255,.18); transform: scale(1.05); }
    .huac-lb__close { position: absolute; top: 16px; right: 16px; }
    .huac-lb__nav { position: absolute; top: 50%; transform: translateY(-50%); }
    .huac-lb__nav:hover { transform: translateY(-50%) scale(1.05); }
    .huac-lb__nav--prev { left: 16px; }
    .huac-lb__nav--next { right: 16px; }
</style>
