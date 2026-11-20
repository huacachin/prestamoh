{{-- 20/11 (Antony, 29404): "en el modal de Calcular pagos, en cancelación, debemos agregarle un check para
     agregarle la suma de la mora acumulada, con la opción de poder editar la mora acumulada". Marcada, el
     cobro lleva además la mora acumulada de las cuotas ya pagadas por el mismo camino de "Cobrar mora
     acumulada" (13/11): importe editable hasta lo que se debe (cada cuota con su redondeo a 0.10) y, si se
     cobra menos, el resto sigue acumulado. Sin marcar por defecto, salvo que ya esté elegida para el cobro.
     Es una fila del bloque "Moras" del resumen de la pestaña (reordenada el 20/11) y vive en el Alpine de
     calcularCancelacion(): ma, maValida(), maError(), maResto(). --}}
<div x-show="ma.debe > 0.005">
    <div class="calc-fila align-items-center">
        {{-- Casilla y texto en un label con flex (no .form-check): si el texto parte en dos líneas, la casilla no salta arriba. --}}
        <label class="d-flex align-items-center gap-2 mb-0 fw-normal" for="cancelacion-mora-acum">
            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="cancelacion-mora-acum" x-model="ma.sel">
            {{-- Dos renglones a propósito (título + de qué cuotas y cuánto se debe): en uno solo se partía a medias. --}}
            <span>
                Agregar mora acumulada
                <span class="d-block small text-muted" x-text="ma.rotulo + ' · debe ' + soles(ma.debe)"></span>
            </span>
        </label>
        <input type="number" step="0.01" min="0.01" :max="ma.tope" autocomplete="off"
               class="form-control form-control-sm text-end input-rojo flex-shrink-0 calc-importe"
               aria-label="Mora acumulada a cobrar"
               x-model="ma.valor" :disabled="! ma.sel" x-on:keydown.enter.prevent="usar()"
               :title="'Hasta ' + soles(ma.tope) + ' (lo que se debe, con el redondeo a 0.10 de cada cuota)'">
    </div>
    <div class="small text-danger" x-show="ma.sel && ! maValida()" x-text="maError()"></div>
    <div class="small text-muted" x-show="ma.sel && maValida() && maResto() > 0.005"
         x-text="'Quedan ' + soles(maResto()) + ' como mora acumulada: se cobra primero la de la cuota más antigua.'"></div>
</div>
