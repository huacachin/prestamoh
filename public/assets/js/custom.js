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
                'enero','febrero','marzo','abril','mayo','junio',
                'julio','agosto','septiembre','octubre','noviembre','diciembre'
            ],
            monthNamesShort: ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'],
            dayNames: ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'],
            dayNamesShort: ['dom','lun','mar','mié','jue','vie','sáb'],
            dayNamesMin: ['Do','Lu','Ma','Mi','Ju','Vi','Sa'],
            weekHeader: 'Sm',
            dateFormat: 'yy-mm-dd',
            firstDay: 1,
            isRTL: false,
            showMonthAfterYear: false,
            yearSuffix: ''
        };
    }

    $.datepicker.setDefaults($.datepicker.regional['es']);
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

function questionDelete(id, role, name) {
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
            Livewire.dispatch('register_destroy', [id]);
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
    questionDelete(data['id'], data['role'] || '', data['name'] || '');
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
