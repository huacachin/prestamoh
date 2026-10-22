/*
 * Ventanas flotantes arrastrables (10/10/2026, pedido de Antony).
 *
 * Dos componentes Alpine que comparten el arrastre (Pointer Events: mouse y
 * dedo) y el límite a la pantalla. Siempre se abren AL CENTRO (Antony: "que
 * siempre levante al centro, no al lado derecho"); solo el visor recuerda el
 * tamaño al que se dejó.
 *
 *  - `visorFlotante`: el visor de vouchers/fotos del partial
 *    livewire/cash/partials/_lightbox.blade.php. Se anida dentro del x-data que
 *    ya tiene el contrato del visor (open / idx / items / close / next / prev).
 *    En escritorio no hay fondo oscuro, la ventana abre al tamaño real de la
 *    foto (tope 85 % de la pantalla), se arrastra desde la cabecera, se
 *    redimensiona por la esquina (resize nativo de CSS) y se maximiza. La foto
 *    tiene zoom (botones, rueda, doble clic, arrastre, pellizco, teclas + − 0 1).
 *    Atajos: Esc cierra; ← → cambian de foto salvo que se esté escribiendo en
 *    un campo (la página de abajo está activa).
 *
 *  - `modalFlotante('clave')`: un modal de Bootstrap (la confirmación del cobro
 *    en /payments/create, el recibo en payments/partials/_modal-recibo) que en
 *    escritorio se abre SIN fondo ni bloqueo de la página y se arrastra desde
 *    su .modal-header. Bootstrap sigue mandando (modal.show()/hide(),
 *    data-bs-dismiss, eventos): solo cambian backdrop/focus y la posición del
 *    .modal-dialog (x-ref="dialogo"). Nunca más alta que la pantalla: el CSS la
 *    limita a 100vh - 32 px con el cuerpo en scroll y, si el contenido crece o la
 *    pantalla se achica, la ventana sube para que el pie siga a la vista (22/10).
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
    const VENTANA_MAX = 0.85; // la ventana abre al tamaño real de la foto, hasta este tanto de la pantalla
    const ZOOM_MIN = 0.1;
    const ZOOM_MAX = 8;
    const ZOOM_PASO = 1.25;   // por clic en + / − y por tecla

    const limitar = (v, min, max) => Math.min(Math.max(v, min), Math.max(min, max));
    const esEscritorio = () => window.matchMedia('(min-width: 768px)').matches;
    const centrado = (w, h) => ({
        x: Math.max(0, Math.round((window.innerWidth - w) / 2)),
        y: Math.max(MARGEN, Math.round((window.innerHeight - h) / 2)),
    });

    // Arrastre compartido. `c` es el componente: usa c.flotante, c.geo {x,y,w,h},
    // c.arrastre y c.aplicar().
    const Arrastre = {
        iniciar(c, e) {
            if (!c.flotante || !c.geo || e.button !== 0 || e.target.closest('button, a, input, select, textarea')) return;
            e.preventDefault();
            c.arrastre = { id: e.pointerId, dx: e.clientX - c.geo.x, dy: e.clientY - c.geo.y };
            try { e.currentTarget.setPointerCapture(e.pointerId); } catch (err) { /* nada */ }
            // los iframes (recibo) no deben quedarse con el puntero a mitad del arrastre
            document.body.classList.add('arrastrando-ventana');
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
            document.body.classList.remove('arrastrando-ventana');
        },
    };

    function visorFlotante() {
        return {
            flotante: false,
            geo: null,        // { x, y, w, h } de la ventana en escritorio
            arrastre: null,   // { id, dx, dy } mientras se arrastra
            previa: null,     // geo antes de maximizar (null = no está maximizada)
            dimensionar: false, // true hasta que carga la primera foto tras abrir: la ventana toma su tamaño real

            // Zoom de la foto (12/10): escala respecto al tamaño real (1 = 100 %). En modo
            // 'ajuste' la foto sigue al tamaño de la ventana; 'libre' = el usuario hizo zoom.
            escala: 1,
            modo: 'ajuste',
            natural: { w: 0, h: 0 },
            pos: { x: 0, y: 0 },
            desplazable: false,
            panning: null,    // { id, x, y, px, py } mientras se arrastra la foto
            toques: new Map(), // pointerId → { x, y } (pellizco con dos dedos)
            pellizco: null,   // { dist, escala } al empezar el pellizco

            init() {
                // Sin $nextTick: Alpine retiene los ticks mientras dura la transición de x-show.
                this.$watch('open', (abierto) => { if (abierto) this.alAbrir(); });
                // Al cambiar de foto vuelve ajustada; alCargar la reencaja cuando llega.
                this.$watch('idx', () => { this.modo = 'ajuste'; });
                this.$nextTick(() => {
                    if (!window.ResizeObserver || !this.$refs.cuerpo) return;
                    // Redimensionado por la esquina o maximizar: la foto ajustada se reencaja; con zoom libre se recoloca.
                    new ResizeObserver(() => { if (this.open) this.reencajar(); }).observe(this.$refs.cuerpo);
                });
            },

            alAbrir() {
                const v = this.$refs.ventana;
                if (!v) return;
                this.previa = null;
                this.modo = 'ajuste';
                this.flotante = esEscritorio();
                this.dimensionar = this.flotante;
                if (!this.flotante) {
                    v.style.cssText = '';
                    return;
                }
                // Provisional hasta que cargue la foto: alCargar lleva la ventana a su tamaño real.
                const w = limitar(Math.round(window.innerWidth * 0.5), MIN_ANCHO, window.innerWidth - 2 * MARGEN);
                const h = limitar(Math.round(window.innerHeight * 0.6), MIN_ALTO, window.innerHeight - 2 * MARGEN);
                this.geo = Object.assign({ w, h }, centrado(w, h));
                this.aplicar();
                try { v.focus({ preventScroll: true }); } catch (e) { /* nada */ }
                // Las galerías traen la lista desde el inicio: la primera foto puede estar cargada ya
                // (sin evento load al abrir), así que se dimensiona con lo que hay.
                const img = this.$refs.foto;
                if (img && img.complete && img.naturalWidth) {
                    // Al siguiente cuadro, con la capa ya visible y medible.
                    requestAnimationFrame(() => { if (this.open && this.dimensionar) this.alCargar({ target: img }); });
                }
            },

            // La foto ya cargó: recién abierta, la ventana toma el tamaño real de la foto
            // (tope VENTANA_MAX de la pantalla) y se centra; siempre, la foto queda ajustada.
            alCargar(e) {
                const img = e.target;
                this.natural = { w: img.naturalWidth || 0, h: img.naturalHeight || 0 };
                if (this.dimensionar && this.flotante && this.natural.w) {
                    this.dimensionar = false;
                    const v = this.$refs.ventana, c = this.$refs.cuerpo;
                    // Si todavía no hay medidas (la capa aún oculta), vale lo que ocupan borde y cabecera.
                    const extraW = v.offsetWidth ? v.offsetWidth - c.clientWidth : 2;
                    const extraH = v.offsetHeight ? v.offsetHeight - c.clientHeight : CABECERA + 2;
                    const w = limitar(this.natural.w + extraW, MIN_ANCHO, Math.round(window.innerWidth * VENTANA_MAX));
                    const h = limitar(this.natural.h + extraH, MIN_ALTO, Math.round(window.innerHeight * VENTANA_MAX));
                    this.geo = Object.assign({ w, h }, centrado(w, h));
                    this.aplicar();
                }
                this.ajustar();
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

            // Maximizar ↔ restaurar (botón de la cabecera o doble clic en ella).
            maximizar() {
                if (!this.flotante || !this.geo) return;
                if (this.previa) {
                    this.geo = this.previa;
                    this.previa = null;
                } else {
                    this.previa = Object.assign({}, this.geo);
                    this.geo = { x: MARGEN, y: MARGEN, w: window.innerWidth - 2 * MARGEN, h: window.innerHeight - 2 * MARGEN };
                }
                this.aplicar();
            },

            // ── Zoom ──
            escalaAjuste() {
                const c = this.$refs.cuerpo;
                if (!c || !this.natural.w || !this.natural.h) return 1;
                // Nunca por encima del tamaño real: una foto chica no se estira borrosa.
                return Math.min(c.clientWidth / this.natural.w, c.clientHeight / this.natural.h, 1);
            },
            ajustar() { this.modo = 'ajuste'; this.fijarEscala(this.escalaAjuste()); },
            real() { this.modo = 'libre'; this.fijarEscala(1); },
            acercar() { this.zoomHacia(this.escala * ZOOM_PASO); },
            alejar() { this.zoomHacia(this.escala / ZOOM_PASO); },
            porcentaje() { return Math.round(this.escala * 100) + ' %'; },

            // Escala absoluta `nueva` dejando fijo el punto (cx, cy) del cuerpo (por defecto el centro).
            zoomHacia(nueva, cx, cy) {
                const c = this.$refs.cuerpo;
                if (!c || !this.natural.w) return;
                nueva = limitar(nueva, Math.min(ZOOM_MIN, this.escalaAjuste()), ZOOM_MAX);
                if (cx === undefined) { cx = c.clientWidth / 2; cy = c.clientHeight / 2; }
                const f = nueva / this.escala;
                this.modo = 'libre';
                this.pos = { x: cx - (cx - this.pos.x) * f, y: cy - (cy - this.pos.y) * f };
                this.escala = nueva;
                this.colocarFoto();
            },
            fijarEscala(s) { this.escala = s; this.pos = { x: 0, y: 0 }; this.colocarFoto(); },

            // Centrada si cabe; si no, pegada a los bordes para no dejar vacío.
            colocarFoto() {
                const c = this.$refs.cuerpo, img = this.$refs.foto;
                if (!c || !img) return;
                const W = c.clientWidth, H = c.clientHeight;
                const w = this.natural.w * this.escala, h = this.natural.h * this.escala;
                this.pos.x = w <= W ? (W - w) / 2 : limitar(this.pos.x, W - w, 0);
                this.pos.y = h <= H ? (H - h) / 2 : limitar(this.pos.y, H - h, 0);
                img.style.transform = 'translate(' + this.pos.x + 'px, ' + this.pos.y + 'px) scale(' + this.escala + ')';
                this.desplazable = w > W || h > H;
            },
            reencajar() { if (this.modo === 'ajuste') this.ajustar(); else this.colocarFoto(); },

            // Rueda directa sobre la foto (Antony, 12/10): acerca o aleja donde está el cursor.
            rueda(e) {
                const r = this.$refs.cuerpo.getBoundingClientRect();
                this.zoomHacia(this.escala * Math.exp(-e.deltaY * 0.0015), e.clientX - r.left, e.clientY - r.top);
            },
            // Doble clic: ajustada ↔ 100 % (si ya se ve al 100 %, vuelve a ajustada).
            dobleClic(e) {
                const r = this.$refs.cuerpo.getBoundingClientRect();
                const ajuste = this.escalaAjuste();
                if (Math.abs(this.escala - ajuste) < 0.001 && ajuste < 1) {
                    this.zoomHacia(1, e.clientX - r.left, e.clientY - r.top);
                } else {
                    this.ajustar();
                }
            },
            // Teclas + − 0 1 (sin pelearse con lo que se escribe en la página de abajo).
            tecla(e) {
                if (!this.open || this.escribiendo(e) || e.ctrlKey || e.metaKey || e.altKey) return;
                if (e.key === '+' || e.key === '=') { e.preventDefault(); this.acercar(); }
                else if (e.key === '-') { e.preventDefault(); this.alejar(); }
                else if (e.key === '0') { e.preventDefault(); this.ajustar(); }
                else if (e.key === '1') { e.preventDefault(); this.real(); }
            },

            // ── Mover la foto con el puntero y pellizco con dos dedos (Pointer Events) ──
            tocar(e) {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                this.toques.set(e.pointerId, { x: e.clientX, y: e.clientY });
                try { this.$refs.cuerpo.setPointerCapture(e.pointerId); } catch (err) { /* nada */ }
                if (this.toques.size === 2) {
                    const [a, b] = Array.from(this.toques.values());
                    this.pellizco = { dist: Math.hypot(a.x - b.x, a.y - b.y) || 1 };
                    this.panning = null;
                } else if (this.toques.size === 1) {
                    this.panning = { id: e.pointerId, x: e.clientX, y: e.clientY, px: this.pos.x, py: this.pos.y };
                }
            },
            moverFoto(e) {
                if (!this.toques.has(e.pointerId)) return;
                this.toques.set(e.pointerId, { x: e.clientX, y: e.clientY });
                if (this.pellizco && this.toques.size >= 2) {
                    const [a, b] = Array.from(this.toques.values());
                    const r = this.$refs.cuerpo.getBoundingClientRect();
                    const dist = Math.hypot(a.x - b.x, a.y - b.y) || 1;
                    this.zoomHacia(this.escala * dist / this.pellizco.dist, (a.x + b.x) / 2 - r.left, (a.y + b.y) / 2 - r.top);
                    this.pellizco.dist = dist;
                    return;
                }
                if (this.panning && e.pointerId === this.panning.id && this.desplazable) {
                    e.preventDefault();
                    this.pos.x = this.panning.px + (e.clientX - this.panning.x);
                    this.pos.y = this.panning.py + (e.clientY - this.panning.y);
                    this.colocarFoto();
                }
            },
            soltarFoto(e) {
                this.toques.delete(e.pointerId);
                try { this.$refs.cuerpo.releasePointerCapture(e.pointerId); } catch (err) { /* nada */ }
                if (this.toques.size < 2) this.pellizco = null;
                if (this.panning && e.pointerId === this.panning.id) this.panning = null;
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
            clave: clave || 'modal',
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
                window.addEventListener('resize', () => this.reencajar());
            },

            // Al centro de la pantalla. Se mide el diálogo con el modal ya en display:block
            // (Bootstrap lo pone igual un instante después) para centrarlo también en vertical.
            colocar() {
                const d = this.$refs.dialogo;
                if (!d) return;
                this.vigilar(d);
                this.$el.style.display = 'block';
                const w = d.offsetWidth || Math.min(parseInt(d.style.maxWidth, 10) || 420, window.innerWidth - 2 * MARGEN);
                const h = d.offsetHeight || 3 * CABECERA;
                this.geo = Object.assign({ w, h }, centrado(w, h));
                this.aplicar();
            },

            aplicar() {
                const d = this.$refs.dialogo;
                if (!d || !this.geo) return;
                d.style.left = this.geo.x + 'px';
                d.style.top = this.geo.y + 'px';
            },

            // 22/10 (crédito 29099): el contenido cambia con la ventana abierta (Livewire rearma
            // la vista previa al responder Sí/No, el ticket trae 9+ cuotas) y la pantalla puede
            // achicarse. Cuando cambia el alto del diálogo se vuelve a encajar. El observador
            // vive en el nodo y no en el estado de Alpine: envuelto en el proxy reactivo, un
            // ResizeObserver nativo revienta con "Illegal invocation".
            vigilar(d) {
                if (d.dataset.vigilado || !window.ResizeObserver) return;
                d.dataset.vigilado = '1';
                new ResizeObserver(() => this.reencajar()).observe(d);
            },

            // La ventana no termina por debajo del borde de la pantalla (el CSS ya la limita a
            // 100vh - 32 px, con el cuerpo en scroll): si creció o la pantalla se achicó, sube.
            // Mientras se arrastra no se toca: ahí manda el cajero (puede asomarla por abajo).
            reencajar() {
                const d = this.$refs.dialogo;
                if (!d || !this.geo || this.arrastre || !this.$el.classList.contains('show')) return;
                const h = d.offsetHeight;
                if (!h) return;
                const yMax = Math.max(MARGEN, window.innerHeight - MARGEN - h);
                if (this.geo.y > yMax) {
                    this.geo.y = yMax;
                    this.aplicar();
                }
            },

            iniciarArrastre(e) { Arrastre.iniciar(this, e); },
            arrastrar(e) { Arrastre.mover(this, e); },
            soltar(e) { Arrastre.soltar(this, e); },
        };
    }

    /**
     * `ventanaLibre({ x, y, w })`: ventana suelta (position: fixed) que se arrastra
     * desde el elemento que lleve los x-on:pointer*. Para varias a la vez, p. ej.
     * los detalles de pago del simulador (10/10): cada una con su x-data.
     */
    function ventanaLibre(inicial) {
        inicial = inicial || {};

        return {
            flotante: true,
            geo: { x: inicial.x ?? 80, y: inicial.y ?? 80, w: inicial.w ?? 440, h: 0 },
            arrastre: null,

            init() {
                this.geo.x = limitar(this.geo.x, 0, window.innerWidth - Math.min(this.geo.w, window.innerWidth - MARGEN));
                this.geo.y = limitar(this.geo.y, 0, window.innerHeight - 3 * CABECERA);
                this.aplicar();
            },

            // $root y no $el: desde los x-on:pointer* de la cabecera, $el sería la cabecera;
            // $root es siempre la ventana (el elemento con x-data).
            aplicar() {
                this.$root.style.left = this.geo.x + 'px';
                this.$root.style.top = this.geo.y + 'px';
            },

            iniciarArrastre(e) { Arrastre.iniciar(this, e); },
            arrastrar(e) { Arrastre.mover(this, e); },
            soltar(e) { Arrastre.soltar(this, e); },
        };
    }

    function registrar() {
        window.Alpine.data('visorFlotante', visorFlotante);
        window.Alpine.data('modalFlotante', modalFlotante);
        window.Alpine.data('ventanaLibre', ventanaLibre);
    }

    if (window.Alpine) {
        registrar();
    } else {
        document.addEventListener('alpine:init', registrar);
    }
})();
