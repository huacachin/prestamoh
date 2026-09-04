
<!-- latest jquery-->
<!-- TODO: optimizar esto, datepicker no debería ir con jquery -->
@if (true)
    <script src="{{ asset('assets/js/jquery-3.6.3.min.js') }}"></script>
@endif

<!-- jQuery UI (datepicker, igual al legacy) -->
<script src="{{ asset('assets/vendor/jquery-ui/jquery-ui.js') }}"></script>

<!-- select2 (desplegables con búsqueda: ubigeo de clientes) -->
<script src="{{ asset('assets/vendor/select/select2.min.js') }}"></script>

<!-- Bootstrap js-->
<script src="{{asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')}}"></script>

<!-- Simple bar js-->
<script src="{{asset('assets/vendor/simplebar/simplebar.js')}}"></script>


<!-- Customizer js-->


<!-- App js-->
<script src="{{asset('assets/js/script.js')}}"></script>


{{-- Handler global para el botón "ir al final" (componente x-scroll-bottom-btn).
     Se delega en document para que también capture botones agregados después
     (Livewire re-renders, modales, etc.). --}}
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-scroll-sel]');
    if (!btn) return;
    var sel = btn.getAttribute('data-scroll-sel');
    var isCont = btn.getAttribute('data-scroll-cont') === '1';
    var el = document.querySelector(sel);
    if (!el) { console.warn('[scroll-bottom-btn] no se encontró', sel); return; }

    // Toggle: si estás arriba → baja; si estás cerca del fondo → vuelve arriba.
    var icon = btn.querySelector('i');
    var setDir = function (dir) {
        if (icon) {
            icon.classList.toggle('ti-chevron-down', dir === 'down');
            icon.classList.toggle('ti-chevron-up', dir === 'up');
        }
        // El ícono muestra la acción del PRÓXIMO clic.
        btn.setAttribute('title', dir === 'up' ? 'Volver al inicio' : 'Ir al final');
    };

    if (isCont) {
        var nearBottom = (el.scrollTop + el.clientHeight) >= (el.scrollHeight - 8);
        if (nearBottom) {
            el.scrollTop = 0;
            setDir('down');
        } else {
            // scrollTop directo: más compatible que scrollTo({behavior:smooth}) en
            // contenedores con overflow horizontal + vertical.
            el.scrollTop = el.scrollHeight;
            setDir('up');
        }
    } else {
        var doc = document.documentElement;
        var nearBottomDoc = (window.innerHeight + window.scrollY) >= (doc.scrollHeight - 8);
        if (nearBottomDoc) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            setDir('down');
        } else {
            el.scrollIntoView({ behavior: 'smooth', block: 'end' });
            setDir('up');
        }
    }
});

/* ── Drag-to-scroll: arrastrar tablas desde la CABECERA ──
   Solo el thead arrastra: en el cuerpo el click-drag es el mismo gesto
   que seleccionar texto, y ahí gana la selección nativa (no se puede
   copiar si la tabla se corre). El scroll horizontal también queda
   cubierto por la scrollbar engrosada (CSS abajo) y Shift+rueda.
   Delegado en document → funciona también con tablas que Livewire
   re-renderiza. Cancela el click si hubo arrastre real (umbral 4px)
   para no abrir links sin querer. */
(function () {
    var drag = null; // {el, startX, startLeft, moved}

    document.addEventListener('mousedown', function (e) {
        if (e.button !== 0) return; // solo botón izquierdo
        if (!e.target.closest('thead')) return; // el cuerpo selecciona texto, no arrastra
        var el = e.target.closest('.table-responsive');
        if (!el) return;
        if (el.scrollWidth <= el.clientWidth) return; // sin scroll horizontal, nada que arrastrar
        drag = { el: el, startX: e.pageX, startLeft: el.scrollLeft, moved: false };
    });

    document.addEventListener('mousemove', function (e) {
        if (!drag) return;
        var dx = e.pageX - drag.startX;
        if (Math.abs(dx) > 4) {
            drag.moved = true;
            drag.el.classList.add('is-dragging');
            document.body.style.userSelect = 'none';
        }
        drag.el.scrollLeft = drag.startLeft - dx;
    });

    function endDrag() {
        if (!drag) return;
        if (drag.moved) {
            // matar el click inmediato que dispararía un link/botón tras arrastrar
            var killer = function (ev) {
                ev.stopPropagation(); ev.preventDefault();
                document.removeEventListener('click', killer, true);
            };
            document.addEventListener('click', killer, true);
        }
        drag.el.classList.remove('is-dragging');
        document.body.style.userSelect = '';
        drag = null;
    }
    document.addEventListener('mouseup', endDrag);
    document.addEventListener('mouseleave', endDrag);

    // Cursor "grab" solo cuando la tabla realmente tiene scroll horizontal.
    document.addEventListener('mouseover', function (e) {
        var el = e.target.closest('.table-responsive');
        if (!el) return;
        el.classList.toggle('can-drag', el.scrollWidth > el.clientWidth);
    });
})();
</script>
<style>
    .table-responsive.can-drag thead { cursor: grab; }
    .table-responsive.is-dragging thead { cursor: grabbing; }
    /* Scrollbar siempre agarrable: el scroll horizontal no depende solo
       del arrastre desde la cabecera. */
    .table-responsive::-webkit-scrollbar { height: 10px; width: 10px; }
    .table-responsive::-webkit-scrollbar-track { background: #f1f1f4; }
    .table-responsive::-webkit-scrollbar-thumb { background: #b5b5c3; border-radius: 5px; }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: #8f8fa3; }
    @supports not selector(::-webkit-scrollbar) {
        .table-responsive { scrollbar-width: auto; scrollbar-color: #b5b5c3 #f1f1f4; }
    }
</style>

{{-- ── Datepicker jQuery UI en español (réplica del legacy pie.php) ── --}}
<style> .ui-datepicker { z-index: 9999 !important; } </style>
<script>
(function () {
    if (typeof jQuery === 'undefined' || !jQuery.datepicker) return;

    // Locale español (copiado literal del legacy)
    jQuery.datepicker.regional['es'] = {
        closeText: 'Cerrar', prevText: '< Ant', nextText: 'Sig >', currentText: 'Hoy',
        monthNames: ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'],
        monthNamesShort: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
        dayNames: ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'],
        dayNamesShort: ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'],
        dayNamesMin: ['D','L','M','X','J','V','S'],
        weekHeader: 'Sm', dateFormat: 'yy-mm-dd', firstDay: 1,
        isRTL: false, showMonthAfterYear: false, yearSuffix: ''
    };
    jQuery.datepicker.setDefaults(jQuery.datepicker.regional['es']);
    // El rango por defecto de jQuery UI es ±10 años (el selector de año se
    // quedaba en 2016 hacia atrás y no dejaba elegir una fecha de nacimiento).
    jQuery.datepicker.setDefaults({ yearRange: '1930:c+10' });

    // onSelect: dispara el evento input nativo para que Livewire capture el valor.
    function syncLivewire(input) {
        input.dispatchEvent(new Event('input',  { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function initDatepickers() {
        // .dates  → libre   .dates3 → máx hoy   .dates2 → mín -2 días
        jQuery('.dates:not(.hasDatepicker)').datepicker({
            changeMonth: true, changeYear: true, dateFormat: 'yy-mm-dd',
            onSelect: function () { syncLivewire(this); }
        });
        jQuery('.dates3:not(.hasDatepicker)').datepicker({
            changeMonth: true, changeYear: true, dateFormat: 'yy-mm-dd', maxDate: 0,
            onSelect: function () { syncLivewire(this); }
        });
        jQuery('.dates2:not(.hasDatepicker)').datepicker({
            changeMonth: true, changeYear: true, dateFormat: 'yy-mm-dd', minDate: -2,
            onSelect: function () { syncLivewire(this); }
        });
        // .dates-dyn → límites dinámicos vía data-mindate / data-maxdate (formato yy-mm-dd)
        jQuery('.dates-dyn:not(.hasDatepicker)').each(function () {
            var $el = jQuery(this);
            $el.datepicker({
                changeMonth: true, changeYear: true, dateFormat: 'yy-mm-dd',
                minDate: $el.data('mindate') || null,
                maxDate: $el.data('maxdate') || null,
                onSelect: function () { syncLivewire(this); }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initDatepickers);
    // Re-init tras cada render de Livewire (inputs nuevos en el DOM)
    document.addEventListener('livewire:navigated', initDatepickers);
    document.addEventListener('livewire:init', function () {
        if (window.Livewire && window.Livewire.hook) {
            window.Livewire.hook('morph.updated', initDatepickers);
            window.Livewire.hook('commit', function (payload) {
                if (payload && payload.respond) payload.respond(initDatepickers);
            });
        }
    });
})();
</script>

{{-- ── Autocompletador de correos (.correo-sugerencias) ──
     Al escribir "juan" ofrece juan@gmail.com, juan@hotmail.com…; con
     "juan@ho" filtra a hotmail. Panel flotante navegable con ↑↓/Enter,
     Esc o click afuera lo cierran. Delegado en document: sobrevive los
     morphs de Livewire sin re-init; la selección dispara input/change
     nativos para que wire:model la capture. --}}
<style>
    .correo-panel { position: absolute; z-index: 1056; background: #fff; border: 1px solid #e2e2ea;
                    border-radius: 8px; box-shadow: 0 8px 22px rgba(28, 36, 94, .14); padding: 4px; }
    .correo-opcion { display: block; width: 100%; border: 0; background: transparent; text-align: left;
                     padding: 5px 10px; border-radius: 6px; font-size: 12.5px; cursor: pointer;
                     white-space: nowrap; color: #333; }
    .correo-opcion b { color: #198754; font-weight: 600; }
    .correo-opcion:hover, .correo-opcion.activa { background: #eaf5ee; }
</style>
<script>
(function () {
    var DOMINIOS = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'icloud.com', 'live.com'];
    var panel = null, dueno = null, indice = -1, opciones = [];

    function cerrar() {
        if (panel) panel.remove();
        panel = null; dueno = null; indice = -1; opciones = [];
    }

    function sugerencias(valor) {
        valor = valor.trim();
        if (valor === '' || /\s/.test(valor)) return [];
        var at = valor.indexOf('@');
        if (at === 0) return [];
        var local = at === -1 ? valor : valor.slice(0, at);
        var resto = at === -1 ? '' : valor.slice(at + 1).toLowerCase();
        return DOMINIOS
            .filter(function (d) { return d.indexOf(resto) === 0 && d !== resto; })
            .map(function (d) { return local + '@' + d; });
    }

    function elegir(correo) {
        if (!dueno) return;
        dueno.value = correo;
        dueno.dispatchEvent(new Event('input',  { bubbles: true }));
        dueno.dispatchEvent(new Event('change', { bubbles: true }));
        var campo = dueno;
        cerrar();
        campo.focus();
    }

    function resaltar(nuevo) {
        if (!panel) return;
        var botones = panel.querySelectorAll('.correo-opcion');
        if (!botones.length) return;
        indice = (nuevo + botones.length) % botones.length;
        botones.forEach(function (b, i) { b.classList.toggle('activa', i === indice); });
    }

    function pintar(input) {
        cerrar();
        opciones = sugerencias(input.value);
        if (!opciones.length) return;
        dueno = input;
        panel = document.createElement('div');
        panel.className = 'correo-panel';
        opciones.forEach(function (correo) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'correo-opcion';
            var at = correo.indexOf('@');
            var usuario = document.createElement('span');
            usuario.textContent = correo.slice(0, at);   // textContent: sin inyección
            var dominio = document.createElement('b');
            dominio.textContent = '@' + correo.slice(at + 1);
            b.appendChild(usuario); b.appendChild(dominio);
            b.addEventListener('mousedown', function (e) { e.preventDefault(); elegir(correo); });
            panel.appendChild(b);
        });
        var padre = input.parentElement;
        if (getComputedStyle(padre).position === 'static') padre.style.position = 'relative';
        panel.style.top = (input.offsetTop + input.offsetHeight + 2) + 'px';
        panel.style.left = input.offsetLeft + 'px';
        panel.style.minWidth = Math.min(input.offsetWidth, 320) + 'px';
        padre.appendChild(panel);
    }

    document.addEventListener('input', function (e) {
        if (e.target.matches && e.target.matches('input.correo-sugerencias')) pintar(e.target);
    });

    document.addEventListener('keydown', function (e) {
        if (!panel || e.target !== dueno) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); resaltar(indice + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); resaltar(indice - 1); }
        else if (e.key === 'Enter' && indice >= 0) { e.preventDefault(); elegir(opciones[indice]); }
        else if (e.key === 'Escape' || e.key === 'Tab') { cerrar(); }
    });

    document.addEventListener('click', function (e) {
        if (panel && !panel.contains(e.target) && e.target !== dueno) cerrar();
    });

    // Si Livewire re-renderiza mientras el panel está abierto, se cierra
    // para no dejarlo huérfano apuntando a un input que ya no existe.
    document.addEventListener('livewire:init', function () {
        if (window.Livewire && window.Livewire.hook) {
            window.Livewire.hook('morph.updated', function () { if (panel) cerrar(); });
        }
    });
})();
</script>

{{-- ── select2 en selects .select2-simple / .select2-tags (ubigeo) ──
     Mismo esquema de re-init que el datepicker. Los wrappers llevan
     wire:key con la cascada, así el morph reemplaza el nodo entero y el
     widget viejo se va con él; aquí solo barremos contenedores huérfanos
     e inicializamos los selects nuevos. El change de select2 es jQuery,
     no nativo: se re-dispara como evento nativo para que wire:model lo
     escuche. --}}
<script>
(function () {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;

    function initSelect2() {
        // contenedores huérfanos (su select original ya no está en el DOM)
        jQuery('.select2-container').each(function () {
            var prev = this.previousElementSibling;
            if (!prev || !jQuery(prev).hasClass('select2-hidden-accessible')) jQuery(this).remove();
        });

        jQuery('select.select2-simple, select.select2-tags').each(function () {
            if (jQuery(this).hasClass('select2-hidden-accessible')) return; // ya montado
            var conTexto = jQuery(this).hasClass('select2-tags');
            jQuery(this).select2({
                width: '100%',
                language: { noResults: function () { return 'Sin resultados'; } },
                tags: conTexto,                       // texto libre (historial / casos borde)
                placeholder: jQuery(this).data('placeholder') || '',
                // El botón de limpiar solo donde existe la opción vacía
                // (distrito); en requeridos como provincia no tiene sentido.
                allowClear: conTexto && !!this.querySelector('option[value=""]')
            }).on('select2:select select2:unselect select2:clear', function () {
                this.dispatchEvent(new Event('input',  { bubbles: true }));
                this.dispatchEvent(new Event('change', { bubbles: true }));
            }).on('select2:open', function () {
                var campo = document.querySelector('.select2-container--open .select2-search__field');
                if (!campo) return;
                // jQuery 3.6 + select2: el buscador NO recibe foco al abrir
                // (bug conocido) — sin esto, escribir de inmediato no filtra.
                campo.focus();
                // Hint en el cajón de búsqueda; .select2-sin-hint (p. ej.
                // tipo de documento) queda fuera a pedido.
                if (! jQuery(this).hasClass('select2-sin-hint')) {
                    campo.setAttribute('placeholder', 'Busca o escribe...');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initSelect2);
    document.addEventListener('livewire:navigated', initSelect2);
    document.addEventListener('livewire:init', function () {
        if (window.Livewire && window.Livewire.hook) {
            window.Livewire.hook('morph.updated', function () { queueMicrotask(initSelect2); });
        }
    });
})();
</script>

@stack('scripts')
@yield('script')
