{{--
    Visor de recibos (compartido por /payments/create, la ficha del crédito y el
    cronograma). Se abre con abrirRecibo(url, titulo?).

    30/10 (Antony, /payments/create/29186: "la visualización de recibos debe ser
    multiventanas así como en el reporte de pagos, solo abre un recibo"): cada
    recibo abre su propia ventanita, en cascada y arrastrable desde la cabecera
    (ventanaLibre de assets/js/ventanas-flotantes.js); la tocada pasa al frente,
    el mismo recibo no se abre dos veces (pasa al frente) y Esc cierra la de
    arriba. Antes era un solo modal: abrir otro recibo reemplazaba al anterior.
    Cada ventana es un iframe del recibo público; allow=clipboard-write: sin eso
    el botón "Copiar imagen" del recibo no puede escribir al portapapeles.
--}}
<div id="visor-recibos" wire:ignore x-data="recibosFlotantes()"
     x-on:abrir-recibo.window="abrir($event.detail)"
     x-on:keydown.escape.window="cerrarUltimo()">
    <template x-teleport="body">
        <div class="recibos-ventanas">
            <template x-for="v in ventanas" :key="v.id">
                <div x-data="ventanaLibre({ x: v.x, y: v.y, w: 430 })" class="card shadow recibo-ventana"
                     :style="{ zIndex: v.z }" x-on:pointerdown="alFrente(v)">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2 py-2 recibo-ventana-cabecera"
                         style="background:#009bdc; color:#fff;"
                         x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                         x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                        <b style="font-size:13px;" x-text="v.titulo"></b>
                        <span class="d-flex align-items-center gap-2">
                            <a href="#" x-show="ventanas.length > 1" x-on:click.prevent="cerrarTodas()"
                               style="color:#fff; font-size:11px; text-decoration:underline;" x-text="'Cerrar todos (' + ventanas.length + ')'"></a>
                            <a href="#" x-on:click.prevent="cerrar(v.id)" title="Cerrar (Esc cierra la de arriba)"
                               style="color:#fff; text-decoration:none; font-size:20px; line-height:1;">&times;</a>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <iframe :src="v.url" allow="clipboard-write" style="width:100%; height:62vh; border:0; display:block;"></iframe>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
<style>
    /* Ventanitas de recibos: fijas, varias a la vez, arrastrables desde la cabecera.
       transition: none → el tema anima toda .card y la ventana iba detrás del mouse. */
    .recibo-ventana { position: fixed; width: 430px; max-width: 95vw; margin: 0; transition: none !important; }
    .recibo-ventana-cabecera { cursor: grab; user-select: none; -webkit-user-select: none; touch-action: none; }
    .recibo-ventana-cabecera:active { cursor: grabbing; }
    @media print { .recibos-ventanas { display: none !important; } }
</style>
<script>
    function abrirRecibo(url, titulo) {
        window.dispatchEvent(new CustomEvent('abrir-recibo', { detail: { url: url, titulo: titulo || 'Recibo' } }));
    }

    function recibosFlotantes() {
        return {
            ventanas: [], seq: 0, topZ: 1080,
            abrir(d) {
                const ya = this.ventanas.find(v => v.url === d.url);
                if (ya) { this.alFrente(ya); return; }
                const k = this.ventanas.length % 8;
                this.ventanas.push({ id: ++this.seq, url: d.url, titulo: d.titulo, x: 120 + k * 30, y: 70 + k * 30, z: ++this.topZ });
            },
            cerrar(id) { this.ventanas = this.ventanas.filter(v => v.id !== id); },
            cerrarTodas() { this.ventanas = []; },
            // Esc: si hay un modal de Bootstrap abierto, el Esc es suyo.
            cerrarUltimo() {
                if (! this.ventanas.length || document.querySelector('.modal.show')) return;
                this.cerrar(this.ventanas.reduce((a, b) => a.z >= b.z ? a : b).id);
            },
            alFrente(v) { if (v.z !== this.topZ) v.z = ++this.topZ; },
        };
    }
</script>
