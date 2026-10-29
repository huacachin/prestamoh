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
 *     data-captura="fila" en una <table>: sale la cabecera y SOLO la fila
 *     desde la que se pulsó, sin las demás ni el pie (listados largos:
 *     Eliminar en /credits).
 *     data-captura-omitido="texto" en el contenedor de un <iframe>: en la
 *     foto aparece ese texto en lugar del iframe (que no se copia).
 *
 * Nunca frena el negocio: si html2canvas falla o tarda más de LIMITE_MS, la
 * acción sale igual, solo que sin foto. No es una captura del sistema
 * operativo (eso exige permiso en cada toma): es un redibujo del DOM.
 *
 * 23/10/2026 ("la app deja de responder" en las PCs de la oficina): html2canvas
 * clona el DOCUMENTO ENTERO aunque se fotografíe solo el modal, pide tres
 * getComputedStyle por cada elemento clonado y además analiza el documento de
 * cada <iframe> (la previa del contrato en Documentos, varias hojas). En el
 * cobro de un crédito de 70 cuotas eran ~3 800 elementos y 0,8-1,7 s con la
 * pantalla congelada en una PC lenta, justo antes del morph de la respuesta.
 * Ahora el clon se RECORTA a la zona (ver recorte()): se copian la <head>
 * (estilos), los ancestros de la zona, la zona entera y, vacíos, los hermanos
 * de esos ancestros para que la maqueta no se mueva; los iframes nunca.
 * window.CapturaAuditoria.ultima guarda la medida de la última foto (ms,
 * elementos copiados/descartados, tamaño) para diagnosticar en la oficina.
 *
 * 29/10/2026 (Antony: "¿hay forma de mejorar la calidad de las capturas?"): se
 * tomaban al 75 % y en JPEG 0.62, y la letra del tema (10-12 px) quedaba en
 * 7-9 px y manchada. Ahora 2x y JPEG 0.85: en "Confirmar cobro" la foto pasa de
 * 315 × 336 px y 13 KB a 840 × 898 px y 78 KB, con el mismo tiempo (~280 ms en
 * la Mac: lo que pesa es copiar el DOM, no dibujarlo más grande). Una zona muy
 * grande baja la escala (escalaPara) para no pasar de auditoria.capturas.max_bytes.
 */
(function () {
    const ESCALA = 2;              // nítida: cada px de pantalla son 2 de foto
    const CALIDAD = 0.85;          // JPEG; ~30-130 KB por captura
    const MAX_ANCHO = 2400;        // px de foto: una zona muy ancha baja la escala
    const MAX_PIXELES = 4000000;   // ~4 MP: la foto queda muy por debajo del tope de 1.5 MB del servidor
    const ESCALA_MIN = 0.75;       // nunca menos nítida que antes del 29/10
    const LIMITE_MS = 4000;        // más que esto, la acción sale sin foto

    /** 2x, salvo que la zona sea muy ancha o muy grande; nunca por debajo de ESCALA_MIN. */
    function escalaPara(ancho, alto) {
        if (!(ancho > 0 && alto > 0)) return ESCALA;
        const tope = Math.min(ESCALA, MAX_ANCHO / ancho, Math.sqrt(MAX_PIXELES / (ancho * alto)));
        return Math.max(ESCALA_MIN, Math.round(tope * 100) / 100);
    }

    function zona(el) {
        return el.closest('[data-captura]')
            || el.closest('.modal-content')
            || el.closest('[wire\\:id]')
            || document.body;
    }

    /** Con data-captura="fila" en una tabla, la fila (tbody > tr) desde la que se pulsó; si no, null. */
    function filaPulsada(z, el) {
        if (z.getAttribute('data-captura') !== 'fila') return null;
        const fila = el.closest('tr');
        return fila && fila !== z && z.contains(fila) && fila.parentElement && fila.parentElement.tagName === 'TBODY' ? fila : null;
    }

    /**
     * Predicado para `ignoreElements` de html2canvas: true = NO copiar ese
     * elemento (y html2canvas ya no pregunta por sus hijos). Se conservan:
     *   - lo que está dentro de <head> (hojas de estilo);
     *   - los ancestros de la zona (sin ellos no habría dónde colgarla);
     *   - la zona con todo lo suyo (salvo las otras filas si es "fila");
     *   - los hermanos de esos ancestros, pero VACÍOS: su caja sigue en la
     *     maqueta (barra lateral, columnas) y no se copia nada de adentro.
     * Los <iframe> no se copian nunca, estén donde estén; el fondo del modal
     * y la alerta de SweetAlert tampoco.
     */
    function recorte(z, fila) {
        const head = document.head;
        const cuenta = { copiados: 0, descartados: 0 };
        const ignorar = function (e) {
            let conservar;
            if (e.tagName === 'IFRAME' || (e.classList && (e.classList.contains('modal-backdrop') || e.classList.contains('swal2-container')))) {
                conservar = false;
            } else if (fila !== null && ((e !== fila && e.parentElement === fila.parentElement) || e.tagName === 'TFOOT')) {
                // Modo "fila": ni las otras filas ni el pie (los TOTALES de la página no son de esa fila).
                conservar = false;
            } else {
                const padre = e.parentElement;
                conservar = z.contains(e) || e.contains(z) || (head !== null && head.contains(e)) || (padre !== null && padre.contains(z));
            }
            if (conservar) cuenta.copiados++; else cuenta.descartados++;
            return !conservar;
        };
        return { ignorar, cuenta };
    }

    /** Vence a los `ms`: marca estado.vencido (html2canvas no se puede cancelar y sigue por detrás) y rechaza. */
    function conLimite(promesa, ms, estado) {
        return new Promise((ok, ko) => {
            const t = setTimeout(() => { estado.vencido = true; ko(new Error('captura: tiempo agotado')); }, ms);
            promesa.then(v => { clearTimeout(t); ok(v); }, e => { clearTimeout(t); ko(e); });
        });
    }

    function alClonar(doc) {
        // El botón pulsado está deshabilitado mientras se fotografía (anti doble clic):
        // en la copia que se dibuja se lo deja normal para que no salga gris.
        doc.querySelectorAll('[data-capturando]').forEach(b => { b.disabled = false; b.style.cursor = ''; });
        // Donde había un iframe que no se copió, su contenedor queda SOLO con la nota de
        // qué falta (lo demás que tuviera, p. ej. la leyenda de la previa, no tiene sentido sin ella).
        doc.querySelectorAll('[data-captura-omitido]').forEach(c => {
            const nota = doc.createElement('div');
            nota.textContent = c.getAttribute('data-captura-omitido');
            nota.style.cssText = 'padding:14px 8px;text-align:center;color:#6c757d;font-size:12px;font-style:italic;';
            while (c.firstChild) c.removeChild(c.firstChild);
            c.appendChild(nota);
        });
        // Hojas <style> sueltas en el body (las de otro componente de la misma
        // pantalla) que el recorte pudo dejar fuera: se copian todas a la cabecera
        // del clon; repetir una regla no cambia nada.
        const css = Array.prototype.map.call(document.body.querySelectorAll('style'), s => s.textContent).join('\n');
        if (css !== '' && doc.head) {
            const hoja = doc.createElement('style');
            hoja.textContent = css;
            doc.head.appendChild(hoja);
        }
    }

    async function dibujar(z, fila, estado) {
        if (typeof html2canvas !== 'function') throw new Error('html2canvas no cargado');
        const { ignorar, cuenta } = recorte(z, fila);
        // Con data-captura="fila" la foto lleva solo la cabecera y esa fila: se mide eso, no la tabla entera.
        const caja = z.getBoundingClientRect();
        const alto = fila ? (z.tHead ? z.tHead.getBoundingClientRect().height : 0) + fila.getBoundingClientRect().height : caja.height;
        const escala = escalaPara(caja.width, alto);
        const inicio = performance.now();
        const canvas = await html2canvas(z, {
            scale: escala,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            ignoreElements: ignorar,
            onclone: alClonar,
        });
        if (estado.vencido) {
            // La acción ya salió sin foto: ni se codifica el JPEG ni se pisa el error anotado por tomar().
            window.CapturaAuditoria.ultima = { error: 'captura: tiempo agotado', ms: Math.round(performance.now() - inicio), copiados: cuenta.copiados, descartados: cuenta.descartados };
            return null;
        }
        const foto = canvas.toDataURL('image/jpeg', CALIDAD);
        window.CapturaAuditoria.ultima = {
            ms: Math.round(performance.now() - inicio),
            copiados: cuenta.copiados,
            descartados: cuenta.descartados,
            escala,
            ancho: canvas.width,
            alto: canvas.height,
            kb: Math.round(foto.length * 0.75 / 1024),
            zona: z.tagName.toLowerCase() + (z.id ? '#' + z.id : '') + (z.className ? '.' + String(z.className).trim().split(/\s+/).join('.') : ''),
        };
        return foto;
    }

    window.CapturaAuditoria = {
        /** Medida de la última foto ({ms, copiados, descartados, ancho, alto, kb, zona}) o del último fallo ({error}). */
        ultima: null,
        /** Foto del bloque que contiene a `el`, como data URL JPEG; null si falla. */
        async tomar(el) {
            const estado = { vencido: false };
            try {
                const z = zona(el);
                return await conLimite(dibujar(z, filaPulsada(z, el), estado), LIMITE_MS, estado);
            } catch (e) {
                const mensaje = e && e.message ? e.message : String(e);
                window.CapturaAuditoria.ultima = { error: mensaje };
                console.warn('[auditoría] sin captura:', mensaje);
                return null;
            }
        },
    };

    function registrar() {
        window.Alpine.magic('captura', (el) => async (metodo, ...args) => {
            // Livewire.find(id) devuelve directamente el $wire del componente.
            const raiz = el.closest('[wire\\:id]');
            const wire = raiz && window.Livewire ? window.Livewire.find(raiz.getAttribute('wire:id')) : null;
            if (!wire) {
                console.warn('[auditoría] $captura fuera de un componente Livewire');
                return;
            }
            const boton = el.tagName === 'BUTTON' ? el : null;
            if (boton) { boton.disabled = true; boton.style.cursor = 'progress'; boton.dataset.capturando = '1'; }
            let foto = null;
            try {
                foto = await window.CapturaAuditoria.tomar(el);
            } finally {
                if (boton) { boton.disabled = false; boton.style.cursor = ''; delete boton.dataset.capturando; }
            }
            // $set(..., false): deja el valor listo sin disparar petición; viaja con la llamada.
            if (foto) wire.$set('capturaAuditoria', foto, false);
            return wire.$call(metodo, ...args);
        });
    }

    if (window.Alpine) {
        registrar();
    } else {
        document.addEventListener('alpine:init', registrar);
    }
})();
