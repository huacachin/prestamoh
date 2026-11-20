{{-- 20/11 (Antony, 29404): "en el modal de Calcular pagos, en cancelación, debemos agregarle un check para
     agregarle la suma de la mora acumulada, con la opción de poder editar la mora acumulada". Marcada, el
     cobro lleva además la mora acumulada de las cuotas ya pagadas por el mismo camino de "Cobrar mora
     acumulada" (13/11): importe editable hasta lo que se debe (cada cuota con su redondeo a 0.10) y, si se
     cobra menos, el resto sigue acumulado. Sin marcar por defecto, salvo que ya esté elegida para el cobro.
     Vive en el Alpine de calcularCancelacion(): ma, maValida(), maError(), maResto(), totalACobrar().
     Recibe $sufijo (los id de la casilla no se repiten entre las dos variantes de la pestaña). --}}
<div class="mt-2 pt-2 border-top" x-show="ma.debe > 0.005">
    <div class="d-flex align-items-center justify-content-between gap-2 small">
        {{-- Casilla y texto en fila propia (no .form-check): si el texto parte en dos líneas, la casilla no salta arriba. --}}
        <div class="d-flex align-items-center gap-2">
            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="cancelacion-mora-acum-{{ $sufijo }}" x-model="ma.sel">
            <label class="mb-0 fw-semibold" for="cancelacion-mora-acum-{{ $sufijo }}">
                Agregar mora acumulada
                <span class="fw-normal text-muted" x-text="'(' + ma.rotulo + ': ' + soles(ma.debe) + ')'"></span>
            </label>
        </div>
        <input type="number" step="0.01" min="0.01" :max="ma.tope" autocomplete="off"
               class="form-control form-control-sm text-end input-rojo flex-shrink-0" style="width:100px;"
               aria-label="Mora acumulada a cobrar"
               x-model="ma.valor" :disabled="! ma.sel" x-on:keydown.enter.prevent="usar()"
               :title="'Hasta ' + soles(ma.tope) + ' (lo que se debe, con el redondeo a 0.10 de cada cuota)'">
    </div>
    <div class="small text-danger mt-1" x-show="ma.sel && ! maValida()" x-text="maError()"></div>
    <div class="small text-muted mt-1" x-show="ma.sel && maValida() && maResto() > 0.005"
         x-text="'Quedan ' + soles(maResto()) + ' como mora acumulada: se cobra primero la de la cuota más antigua.'"></div>
    {{-- x-show en un div sin d-flex: el display:flex !important de Bootstrap le gana al x-show. --}}
    <div x-show="ma.sel && maValida()">
        <div class="d-flex justify-content-between align-items-baseline fw-bold mt-2">
            <span>Total a cobrar <span class="fw-normal text-muted small" x-text="moraVigente() > 0.005 ? '(monto + mora + mora acumulada)' : '(monto + mora acumulada)'"></span></span>
            <span x-text="soles(totalACobrar())"></span>
        </div>
    </div>
</div>
