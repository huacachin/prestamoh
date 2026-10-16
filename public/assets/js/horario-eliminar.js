/*
 * Botones de eliminar fuera de regla (09/10/2026, pedido de Antony; desde el
 * 16/10/2026 sin ventana horaria: solo rige el mismo día).
 *
 * Quien no es director solo puede eliminar o anular lo que se registró ese
 * mismo día. Este script deshabilita en pantalla todo botón o enlace cuyo
 * wire:click / x-on:click / @click llame a un método de eliminar (delete*,
 * destroy, eliminar*, borrar, anular, questionDelete, también vía
 * $captura('delete', …)) cuando su data-creado (lo pinta @creadoEl con la
 * fecha del registro) no es hoy; sin data-creado no se toca. Se reevalúa cada
 * 30 s y tras cada actualización de Livewire, así que una página abierta antes
 * de medianoche se bloquea sola a las 00:00 sin recargar. El servidor rechaza
 * igual la acción (ConReglasDeEliminacion en cada método): esto es solo la señal.
 */
(function () {
    const cfg = window.HorarioEliminacion;
    if (!cfg || cfg.director || !cfg.mismoDia) return;

    const patron = new RegExp(cfg.metodos, 'i');
    const ATRIBUTOS = ['wire:click', 'x-on:click', '@click', 'wire:click.prevent', 'x-on:click.prevent', '@click.prevent'];

    function esDeEliminar(el) {
        return ATRIBUTOS.some(a => { const v = el.getAttribute(a); return v && patron.test(v); });
    }

    function hoy() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // Por qué está bloqueado este botón ahora, o null si puede usarse.
    function motivo(el, fechaHoy) {
        if (!el.hasAttribute('data-creado')) return null;
        const creado = el.getAttribute('data-creado');
        if (creado === '') return cfg.mensajeSinFecha;
        return creado === fechaHoy ? null : cfg.mensajeDia;
    }

    function evaluar() {
        const fechaHoy = hoy();
        document.querySelectorAll('button, a').forEach(el => {
            if (!esDeEliminar(el)) return;
            const porque = motivo(el, fechaHoy);
            if (porque) {
                if (!el.dataset.bloqueadoHorario) {
                    el.dataset.bloqueadoHorario = '1';
                    el.dataset.tituloPrevio = el.getAttribute('title') || '';
                    el.dataset.disabledPrevio = el.disabled ? '1' : '';
                    el.classList.add('disabled');
                    el.setAttribute('aria-disabled', 'true');
                    if (el.tagName === 'BUTTON') el.disabled = true;
                    el.style.pointerEvents = 'none';
                    el.style.opacity = '.45';
                }
                el.setAttribute('title', porque);
            } else if (el.dataset.bloqueadoHorario) {
                delete el.dataset.bloqueadoHorario;
                if (el.dataset.tituloPrevio) el.setAttribute('title', el.dataset.tituloPrevio); else el.removeAttribute('title');
                delete el.dataset.tituloPrevio;
                el.classList.remove('disabled');
                el.removeAttribute('aria-disabled');
                if (el.tagName === 'BUTTON') el.disabled = el.dataset.disabledPrevio === '1';
                delete el.dataset.disabledPrevio;
                el.style.pointerEvents = '';
                el.style.opacity = '';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', evaluar);
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morph.updated', () => queueMicrotask(evaluar));
    });
    document.addEventListener('livewire:navigated', evaluar);
    setInterval(evaluar, 30000);
    if (document.readyState !== 'loading') evaluar();
})();
