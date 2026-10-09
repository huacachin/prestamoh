/*
 * Ventanas flotantes arrastrables (10/10/2026, pedido de Antony).
 *
 * Dos componentes Alpine que comparten el arrastre (Pointer Events: mouse y
 * dedo), el límite a la pantalla y la posición recordada en localStorage:
 *
 *  - `visorFlotante`: el visor de vouchers/fotos del partial
 *    livewire/cash/partials/_lightbox.blade.php. Se anida dentro del x-data que
 *    ya tiene el contrato del visor (open / idx / items / close / next / prev).
 *    En escritorio no hay fondo oscuro, la ventana se arrastra desde la
 *    cabecera, se redimensiona por la esquina (resize nativo de CSS) y recuerda
 *    posición y tamaño. Atajos: Esc cierra; ← → cambian de foto salvo que se
 *    esté escribiendo en un campo (la página de abajo está activa).
 *
 *  - `modalFlotante('clave')`: un modal de Bootstrap (p. ej. la confirmación
 *    del cobro en /payments/create) que en escritorio se abre SIN fondo ni
 *    bloqueo de la página y se arrastra desde su .modal-header. Bootstrap sigue
 *    mandando (modal.show()/hide(), data-bs-dismiss, eventos): solo cambian
 *    backdrop/focus y la posición del .modal-dialog (x-ref="dialogo").
 *
 * En celular (< 768 px) todo se comporta como siempre: a pantalla completa /
 * modal centrado con fondo.
 */
(function () {
    const MIN_ANCHO = 260;
    const MIN_ALTO = 180;
    const MARGEN = 16;
    const CABECERA = 40;      // alto de la barra: lo mínimo que debe quedar visible
    const ASOMO = 120;        // al arrastrar, cuánto puede quedar fuera de pantalla

    const limitar = (v, min, max) => Math.min(Math.max(v, min), Math.max(min, max));
    const esEscritorio = () => window.matchMedia('(min-width: 768px)').matches;

    function leer(clave) {
        try {
            const g = JSON.parse(localStorage.getItem(clave) || 'null');
            return g && typeof g === 'object' ? g : null;
        } catch (e) { return null; }
    }

    function guardar(clave, geo) {
        try { localStorage.setItem(clave, JSON.stringify(geo)); } catch (e) { /* privado / lleno */ }
    }

    // Arrastre compartido. `c` es el componente: usa c.flotante, c.geo {x,y,w,h},
    // c.arrastre, c.aplicar() y c.clave.
    const Arrastre = {
        iniciar(c, e) {
            if (!c.flotante || !c.geo || e.button !== 0 || e.target.closest('button, a, input, select, textarea')) return;
            e.preventDefault();
            c.arrastre = { id: e.pointerId, dx: e.clientX - c.geo.x, dy: e.clientY - c.geo.y };
            try { e.currentTarget.setPointerCapture(e.pointerId); } catch (err) { /* nada */ }
        },
        mover(c, e) {
            if (!c.arrastre || e.pointerId !== c.arrastre.id) return;
            c.geo.x = limitar(e.clientX - c.arrastre.dx, ASOMO - c.geo.w, window.innerWidth - ASOMO);
            c.geo.y = limitar(e.clientY - c.arrastre.dy, 0, window.innerHeight - CABECERA);
            c.aplicar();
        },
        soltar(c, e) {
            if (!c.arrastre) return;
            try { e.currentTarget.releasePointerCapture(c.arrastre.id); } catch (err) { /* nada */ }
            c.arrastre = null;
            guardar(c.clave, c.geo);
        },
    };

    function visorFlotante() {
        return {
            clave: 'huac.visor-flotante',
            flotante: false,
            geo: null,        // { x, y, w, h } de la ventana en escritorio
            arrastre: null,   // { id, dx, dy } mientras se arrastra

            init() {
                // Sin $nextTick: Alpine retiene los ticks mientras dura la transición de x-show.
                this.$watch('open', (abierto) => { if (abierto) this.alAbrir(); });
                this.$nextTick(() => {
                    if (!window.ResizeObserver || !this.$refs.ventana) return;
                    // Redimensionado por la esquina: persistir el tamaño nuevo.
                    new ResizeObserver(() => {
                        if (this.flotante && this.open && !this.arrastre) this.leerTamano();
                    }).observe(this.$refs.ventana);
                });
            },

            alAbrir() {
                const v = this.$refs.ventana;
                if (!v) return;
                this.flotante = esEscritorio();
                if (!this.flotante) {
                    v.style.cssText = '';
                    return;
                }
                const g = this.geo || leer(this.clave) || {};
                const w = limitar(g.w || 460, MIN_ANCHO, window.innerWidth - 2 * MARGEN);
                const h = limitar(g.h || Math.min(Math.round(window.innerHeight * 0.8), 640), MIN_ALTO, window.innerHeight - 2 * MARGEN);
                const x = limitar(typeof g.x === 'number' ? g.x : window.innerWidth - w - MARGEN, 0, window.innerWidth - w);
                const y = limitar(typeof g.y === 'number' ? g.y : 72, 0, window.innerHeight - h);
                this.geo = { x, y, w, h };
                this.aplicar();
                try { v.focus({ preventScroll: true }); } catch (e) { /* nada */ }
            },

            aplicar() {
                const v = this.$refs.ventana;
                if (!v || !this.geo) return;
                v.style.left = this.geo.x + 'px';
                v.style.top = this.geo.y + 'px';
                v.style.width = this.geo.w + 'px';
                v.style.height = this.geo.h + 'px';
            },

            iniciarArrastre(e) { Arrastre.iniciar(this, e); },
            arrastrar(e) { Arrastre.mover(this, e); },
            soltar(e) { Arrastre.soltar(this, e); },

            leerTamano() {
                const r = this.$refs.ventana.getBoundingClientRect();
                if (!r.width || !r.height) return;
                const w = Math.round(r.width), h = Math.round(r.height);
                if (this.geo && w === this.geo.w && h === this.geo.h) return;
                this.geo = Object.assign({}, this.geo || {}, { w, h });
                guardar(this.clave, this.geo);
            },

            // Con la página viva debajo, las flechas no deben pelearse con lo que se escribe.
            escribiendo(e) {
                const t = e.target;
                return !!t && (['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName) || t.isContentEditable === true);
            },
        };
    }

    function modalFlotante(clave) {
        return {
            clave: 'huac.ventana.' + (clave || 'modal'),
            modal: null,
            flotante: false,
            geo: null,
            arrastre: null,

            init() {
                const el = this.$el;
                this.flotante = esEscritorio() && !!window.bootstrap;
                if (this.flotante) el.classList.add('modal-flotante');
                this.modal = window.bootstrap.Modal.getOrCreateInstance(el, this.flotante ? { backdrop: false, focus: false } : {});
                if (!this.flotante) return;
                // Bootstrap bloquea el scroll del body al abrir: con la ventana flotante la página sigue viva.
                el.addEventListener('show.bs.modal', () => { document.body.classList.add('con-modal-flotante'); this.colocar(); });
                el.addEventListener('hidden.bs.modal', () => document.body.classList.remove('con-modal-flotante'));
            },

            colocar() {
                const d = this.$refs.dialogo;
                if (!d) return;
                const g = this.geo || leer(this.clave) || {};
                const w = Math.min(parseInt(d.style.maxWidth, 10) || 420, window.innerWidth - 2 * MARGEN);
                const x = limitar(typeof g.x === 'number' ? g.x : window.innerWidth - w - MARGEN, 0, window.innerWidth - w);
                const y = limitar(typeof g.y === 'number' ? g.y : 72, 0, window.innerHeight - 3 * CABECERA);
                this.geo = { x, y, w, h: 0 };
                this.aplicar();
            },

            aplicar() {
                const d = this.$refs.dialogo;
                if (!d || !this.geo) return;
                d.style.left = this.geo.x + 'px';
                d.style.top = this.geo.y + 'px';
            },

            iniciarArrastre(e) { Arrastre.iniciar(this, e); },
            arrastrar(e) { Arrastre.mover(this, e); },
            soltar(e) { Arrastre.soltar(this, e); },
        };
    }

    function registrar() {
        window.Alpine.data('visorFlotante', visorFlotante);
        window.Alpine.data('modalFlotante', modalFlotante);
    }

    if (window.Alpine) {
        registrar();
    } else {
        document.addEventListener('alpine:init', registrar);
    }
})();
