/*
 * Captura de pantalla para la auditoría (08/10/2026, pedido de Antony).
 *
 * Antes de una acción importante (cobrar, guardar caja, emitir o anular un
 * documento, eliminar o revertir…), el navegador dibuja el bloque que el
 * usuario tiene delante —el modal o el formulario— con html2canvas y manda la
 * foto (JPEG, data URL) junto con la misma petición de Livewire, en la
 * propiedad `capturaAuditoria` del componente. En el servidor, el trait
 * ConCapturaDeAuditoria la deja lista y GuardarActividad la cuelga de cada
 * fila de auditoría de esa petición (properties.captura).
 *
 * En el blade se usa como un wire:click normal:
 *     <button x-on:click="$captura('pagar', true)">…</button>
 *     <form x-on:submit.prevent="$captura('save')">…</form>
 * y el bloque a fotografiar se marca con data-captura; si no hay, se toma el
 * .modal-content más cercano o la raíz del componente.
 *
 * Nunca frena el negocio: si html2canvas falla o tarda más de LIMITE_MS, la
 * acción sale igual, solo que sin foto. No es una captura del sistema
 * operativo (eso exige permiso en cada toma): es un redibujo del DOM.
 */
(function () {
    const ESCALA = 0.75;      // 1200 px de modal → 900 px de foto
    const CALIDAD = 0.62;     // JPEG; ~80-150 KB por captura
    const LIMITE_MS = 4000;   // más que esto, la acción sale sin foto

    function zona(el) {
        return el.closest('[data-captura]')
            || el.closest('.modal-content')
            || el.closest('[wire\\:id]')
            || document.body;
    }

    function conLimite(promesa, ms) {
        return new Promise((ok, ko) => {
            const t = setTimeout(() => ko(new Error('captura: tiempo agotado')), ms);
            promesa.then(v => { clearTimeout(t); ok(v); }, e => { clearTimeout(t); ko(e); });
        });
    }

    async function dibujar(el) {
        if (typeof html2canvas !== 'function') throw new Error('html2canvas no cargado');
        const canvas = await html2canvas(el, {
            scale: ESCALA,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            ignoreElements: e => e.classList && (e.classList.contains('modal-backdrop') || e.classList.contains('swal2-container')),
        });
        return canvas.toDataURL('image/jpeg', CALIDAD);
    }

    window.CapturaAuditoria = {
        /** Foto del bloque que contiene a `el` (o del que se pase), como data URL JPEG; null si falla. */
        async tomar(el) {
            try {
                return await conLimite(dibujar(zona(el)), LIMITE_MS);
            } catch (e) {
                console.warn('[auditoría] sin captura:', e && e.message ? e.message : e);
                return null;
            }
        },
    };

    function registrar() {
        window.Alpine.magic('captura', (el) => async (metodo, ...args) => {
            const raiz = el.closest('[wire\\:id]');
            const componente = raiz && window.Livewire ? window.Livewire.find(raiz.getAttribute('wire:id')) : null;
            if (!componente) {
                console.warn('[auditoría] $captura fuera de un componente Livewire');
                return;
            }
            const boton = el.tagName === 'BUTTON' ? el : null;
            if (boton) { boton.disabled = true; boton.style.cursor = 'progress'; }
            let foto = null;
            try {
                foto = await window.CapturaAuditoria.tomar(el);
            } finally {
                if (boton) { boton.disabled = false; boton.style.cursor = ''; }
            }
            // $set(..., false): deja el valor listo sin disparar petición; viaja con la llamada.
            if (foto) componente.$wire.$set('capturaAuditoria', foto, false);
            return componente.$wire.$call(metodo, ...args);
        });
    }

    if (window.Alpine) {
        registrar();
    } else {
        document.addEventListener('alpine:init', registrar);
    }
})();
