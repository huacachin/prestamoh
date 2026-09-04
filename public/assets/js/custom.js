// custom.js — JS compartido extraído de master.blade.php
// =========================================================

// =========================================================
// jQuery UI Datepicker: locale español
// =========================================================
$(function () {
    if (typeof $.datepicker === 'undefined') return;
    if (!$.datepicker.regional['es']) {
        $.datepicker.regional['es'] = {
            closeText: 'Cerrar',
            prevText: 'Anterior',
            nextText: 'Siguiente',
            currentText: 'Hoy',
            monthNames: [
                'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
            ],
            monthNamesShort: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
            dayNames: ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'],
            dayNamesShort: ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'],
            dayNamesMin: ['Do','Lu','Ma','Mi','Ju','Vi','Sá'],
            weekHeader: 'Sm',
            dateFormat: 'yy-mm-dd',
            firstDay: 1,
            isRTL: false,
            showMonthAfterYear: false,
            yearSuffix: ''
        };
    }

    $.datepicker.setDefaults($.datepicker.regional['es']);
    // Rango de años amplio: el ±10 por defecto no alcanza para fechas de nacimiento
    $.datepicker.setDefaults({ yearRange: '1930:c+10' });
});

// =========================================================
// Helper: inicializar múltiples datepickers de Livewire
// Uso en Blade:
//   var wire = @this;
//   initLivewireDatepicker([['#selector', 'wire_prop'], ...], wire);
// =========================================================
// Registro global de datepickers para sobrevivir re-renders de Livewire
var _dpRegistry = [];

function _applyDatepickers(pairs, wire) {
    pairs.forEach(function (pair) {
        var $el = $(pair[0]);
        if (!$el.length) return;
        var currentVal = $el.val();
        // Destruir siempre antes de reinicializar (evita doble binding)
        try { $el.datepicker('destroy'); } catch (e) {}
        $el.datepicker({
            changeMonth: true,
            changeYear: true,
            yearRange: '1930:c+10',
            dateFormat: 'yy-mm-dd',
            onSelect: function (dateText) {
                wire.set(pair[1], dateText);
            }
        });
        // Restaurar valor visual sin disparar eventos de Livewire
        if (currentVal) $el.val(currentVal);
    });
}

function initLivewireDatepicker(pairs, wire) {
    _dpRegistry.push({ pairs: pairs, wire: wire });
    _applyDatepickers(pairs, wire);
}

// Hook de Livewire 3: reinicializar datepickers después de cada commit exitoso
document.addEventListener('livewire:initialized', function () {
    Livewire.hook('commit', function (_ref) {
        var succeed = _ref.succeed;
        succeed(function () {
            requestAnimationFrame(function () {
                _dpRegistry.forEach(function (cfg) {
                    _applyDatepickers(cfg.pairs, cfg.wire);
                });
            });
        });
    });
});

// =========================================================
// Modal helpers
// =========================================================
function openModal(id, opts) {
    opts = opts || {};
    var el = document.getElementById(id);
    if (!el) return;

    var opener = document.activeElement;
    if (opener) opener.setAttribute('data-open', id);

    var instance = bootstrap.Modal.getOrCreateInstance(el);
    instance.show();

    var focusSelector = opts.focus || '[autofocus], input:not([type=hidden]):not([disabled]), select, textarea, button';
    var onShown = function () {
        el.removeEventListener('shown.bs.modal', onShown);
        var target = el.querySelector(focusSelector);
        if (target) target.focus();
    };
    el.addEventListener('shown.bs.modal', onShown, { once: true });
}

function hideModal(id) {
    var modalEl = document.getElementById(id);
    if (!modalEl) return;

    var active = document.activeElement;
    if (active && modalEl.contains(active) && typeof active.blur === 'function') {
        active.blur();
    }

    var trigger = document.querySelector('[data-open="' + id + '"]');
    if (trigger) trigger.focus();

    requestAnimationFrame(function () {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    });
}

// =========================================================
// SweetAlert helpers
// =========================================================
function successAlert(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        confirmButtonText: 'OK',
    });
}

function alertError() {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Hubo un error. Contactar con el administrador',
        confirmButtonText: 'OK',
    });
}

// `event` (opcional) permite un canal propio de confirmación (p. ej.
// 'attachment_destroy' en galerías anidadas), para que el register_destroy
// global no llegue a otros componentes de la misma pantalla que también lo escuchan.
function questionDelete(id, role, name, event) {
    var msg = (role && name)
        ? '¿Está seguro de eliminar al ' + role + ' <span style="color:red;font-weight:bold">' + name + '</span>?'
        : "¿Está seguro que desea eliminar el registro?";
    Swal.fire({
        title: "Se va a eliminar el registro",
        html: msg,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Eliminar"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch(event || 'register_destroy', [id]);
            Swal.fire({
                title: "Eliminado!",
                text: "El registro se eliminado correctamente.",
                icon: "success"
            });
        }
    });
}

function questionGenerate() {
    Swal.fire({
        title: "Generar costo por placa",
        text: "Se eliminaran registros del mes actual y se generar el costo por placa, esto es de solo contingencia esta de acuerdo?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Generar"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('generate_cost_per_plates');
            Swal.fire({
                title: "Generado!",
                text: "Se genero el costo por placa con éxito!!!",
                icon: "success"
            });
        }
    });
}

function questionLogout() {
    Swal.fire({
        title: "Esta seguro que desea salir del sistema ?",
        text: "La sesión actual se cerrará y abandonará el sistema",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Salir"
    }).then(function (result) {
        if (result.isConfirmed) {
            Livewire.dispatch('logout');
        }
    });
}

// =========================================================
// Event listeners
// =========================================================
document.addEventListener('open-modal', function (event) {
    openModal(event.detail[0]['name'], { focus: '#' + event.detail[0]['focus'] });
});

window.addEventListener('modal-close', function (event) {
    hideModal(event.detail[0]['name']);
});

window.addEventListener('successAlert', function (event) {
    successAlert(event.detail[0]['message']);
});

window.addEventListener('questionDelete', function (event) {
    var data = event.detail[0];
    questionDelete(data['id'], data['role'] || '', data['name'] || '', data['event'] || '');
});

window.addEventListener('questionGenerate', function (event) {
    questionGenerate();
});

window.addEventListener('questionLogout', function (event) {
    questionLogout();
});

window.addEventListener('alertError', function (event) {
    alertError();
});

window.addEventListener('errorAlert', function (event) {
    var data = event.detail[0] || {};
    Swal.fire({
        icon: 'warning',
        title: 'Atención',
        text: data.message || 'Hubo un error',
        confirmButtonColor: '#3085d6',
    });
});

document.addEventListener('click', function (e) {
    var btn = e.target.closest('#down');
    if (!btn) return;

    var h = Math.max(
        document.body.scrollHeight,
        document.documentElement.scrollHeight
    );
    window.scrollTo({ top: h, behavior: 'smooth' });
});

window.addEventListener('url-open', function (event) {
    var url = (event.detail && event.detail[0] && event.detail[0].url)
           || (event.detail && event.detail.url);
    if (!url) return;
    window.open(url);
});

window.addEventListener('go-back', function (e) {
    var fb = (e.detail && e.detail.fallback) || '/';
    if (history.length > 1) history.back();
    else location.href = fb;
});

// ===== Historial de busqueda propio (datalist + localStorage) =====
// Portado de newtaxivan (custom.js): Chrome clasifica los buscadores como
// "search box" y NO guarda su historial de autofill aunque el input tenga
// name + autocomplete="on" — por eso el historial se maneja aqui.
// Uso: al input agregarle data-search-history="clave" y list="<id>", y poner
// un <datalist id="<id>" wire:ignore> al lado. Guarda las ultimas 10 en
// localStorage al cambiar el valor (blur) o enviar el form.
//
// TODO delegado en document (05/09): los listeners por-input morian cuando
// Livewire reemplazaba el nodo en re-renders .live (reporte de pagos, filtros
// de cartera...) y el historial dejaba de grabar. La delegacion sobrevive
// cualquier morph; el datalist se repuebla al enfocar por si un render lo vacio.
(function () {
    function readHistory(key) {
        try { return JSON.parse(localStorage.getItem(key)) || []; } catch (e) { return []; }
    }

    function keyOf(input) { return 'searchHist:' + input.dataset.searchHistory; }

    function render(input) {
        var id = input.getAttribute('list');
        var datalist = id ? document.getElementById(id) : null;
        if (!datalist) return;
        datalist.innerHTML = '';
        readHistory(keyOf(input)).forEach(function (v) {
            var opt = document.createElement('option');
            opt.value = v;
            datalist.appendChild(opt);
        });
    }

    function record(input) {
        var v = (input.value || '').trim();
        if (v.length < 2) return;
        var key = keyOf(input);
        var items = readHistory(key).filter(function (x) {
            return x.toLowerCase() !== v.toLowerCase();
        });
        items.unshift(v);
        localStorage.setItem(key, JSON.stringify(items.slice(0, 10)));
        render(input);
    }

    function esHist(el) { return el && el.matches && el.matches('input[data-search-history]'); }

    // focusout y no 'change': en inputs .live, Livewire re-asigna el value
    // programaticamente tras cada render y eso resetea el dirty-flag del
    // navegador — el change de blur nunca llega. Grabar al salir del campo
    // funciona siempre (y el dedup hace inofensivo grabar de mas).
    document.addEventListener('focusout', function (e) {
        if (esHist(e.target)) record(e.target);
    });
    // capture: el submit se registra aunque Livewire haga .prevent
    document.addEventListener('submit', function (e) {
        if (e.target && e.target.querySelectorAll) {
            e.target.querySelectorAll('input[data-search-history]').forEach(record);
        }
    }, true);
    document.addEventListener('focusin', function (e) {
        if (esHist(e.target)) render(e.target);
    });

    function renderAll() {
        document.querySelectorAll('input[data-search-history]').forEach(render);
    }
    document.addEventListener('DOMContentLoaded', renderAll);
    document.addEventListener('livewire:navigated', renderAll);
})();

// Tooltips Bootstrap con delegación en body: los elementos con
// data-bs-toggle="tooltip" funcionan aunque Livewire los re-renderice,
// y container:body evita que los recorte un contenedor con overflow
// (p.ej. la tabla del cronograma en /payments/create).
(function () {
    if (window.bootstrap && bootstrap.Tooltip) {
        new bootstrap.Tooltip(document.body, {
            selector: '[data-bs-toggle="tooltip"]',
            container: 'body',
        });
    }
})();
