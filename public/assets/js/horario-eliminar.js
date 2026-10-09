/*
 * Botones de eliminar fuera de horario (09/10/2026, pedido de Antony).
 *
 * Quien no es director solo puede eliminar de 6:00 a 11:00 de la mañana. Este
 * script deshabilita en pantalla todo botón cuyo wire:click / x-on:click /
 * @click llame a un método de eliminar (delete*, destroy, eliminar*, borrar,
 * anular, también vía $captura('delete', …)) cuando el reloj está fuera de la
 * ventana, y lo vuelve a habilitar cuando entra. Se reevalúa cada 30 s y tras
 * cada actualización de Livewire, así que una página abierta desde las 10:55
 * se bloquea sola a las 11:00 sin recargar. El servidor rechaza igual la
 * acción (hook BloquearEliminacionFueraDeHorario): esto es solo la señal.
 */
(function () {
    const cfg = window.HorarioEliminacion;
    if (!cfg || !cfg.activo || cfg.director) return;

    const patron = new RegExp(cfg.metodos, 'i');
    const ATRIBUTOS = ['wire:click', 'x-on:click', '@click', 'wire:click.prevent', 'x-on:click.prevent', '@click.prevent'];

    function esDeEliminar(el) {
        return ATRIBUTOS.some(a => { const v = el.getAttribute(a); return v && patron.test(v); });
    }

    function bloqueadoAhora() {
        const h = new Date().getHours();
        return !(h >= cfg.desde && h < cfg.hasta);
    }

    function evaluar() {
        const bloqueado = bloqueadoAhora();
        const candidatos = document.querySelectorAll('button, a.btn, a[wire\\:click], a[x-on\\:click]');
        candidatos.forEach(el => {
            if (!esDeEliminar(el)) return;
            if (bloqueado) {
                if (el.dataset.bloqueadoHorario) return;
                el.dataset.bloqueadoHorario = '1';
                el.dataset.tituloPrevio = el.getAttribute('title') || '';
                el.setAttribute('title', cfg.mensaje);
                el.classList.add('disabled');
                el.setAttribute('aria-disabled', 'true');
                if (el.tagName === 'BUTTON') el.disabled = true;
                el.style.pointerEvents = 'none';
                el.style.opacity = '.45';
            } else if (el.dataset.bloqueadoHorario) {
                delete el.dataset.bloqueadoHorario;
                if (el.dataset.tituloPrevio) el.setAttribute('title', el.dataset.tituloPrevio); else el.removeAttribute('title');
                delete el.dataset.tituloPrevio;
                el.classList.remove('disabled');
                el.removeAttribute('aria-disabled');
                if (el.tagName === 'BUTTON') el.disabled = false;
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
