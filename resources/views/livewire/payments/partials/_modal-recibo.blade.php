{{--
    Modal del recibo (compartido por /payments/create, la ficha del crédito y el
    cronograma): iframe del recibo público en esta misma ventana. Se abre con
    abrirRecibo(url). allow=clipboard-write: sin eso el botón "Copiar imagen" del
    recibo no puede escribir al portapapeles desde dentro del iframe.

    10/10 (Antony): en escritorio es una VENTANA FLOTANTE (modalFlotante en
    assets/js/ventanas-flotantes.js): se abre al centro, sin fondo, la pantalla de
    atrás sigue viva y se arrastra desde la cabecera.
--}}
<div class="modal fade" id="modal-recibo" tabindex="-1" wire:ignore x-data="modalFlotante('recibo')">
    <div class="modal-dialog modal-dialog-centered" style="max-width:430px;" x-ref="dialogo">
        <div class="modal-content">
            <div class="modal-header py-2"
                 x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                 x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                <h6 class="modal-title">Recibo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="iframe-recibo" src="about:blank" allow="clipboard-write"
                        style="width:100%; height:75vh; border:0;"></iframe>
            </div>
        </div>
    </div>
</div>
<script>
    function abrirRecibo(url) {
        document.getElementById('iframe-recibo').src = url;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-recibo')).show();
    }
    // Al cerrar se descarga el iframe: no queda el recibo cargado de fondo
    document.getElementById('modal-recibo').addEventListener('hidden.bs.modal', function () {
        document.getElementById('iframe-recibo').src = 'about:blank';
    });
</script>
