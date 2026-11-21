{{-- 21/11 (Antony, 29036): "hay un check para la mora acumulada pero también hay que colocar un check
     editable en Mora (cuotas 16 a 31)". La mora del cobro (la de las cuotas vencidas) con su casilla y su
     importe, como la acumulada. No trae reglas nuevas, son atajos a lo que la pantalla ya tenía:
       · sin marcar, el cobro va con "Reserva Mora": esa mora no se cobra ahora y queda como mora acumulada;
       · el importe solo se cambia con el permiso de mora manual (es el Total Mora de siempre: al cobrar
         pide su motivo y deja rastro). Sin el permiso se ve fijo.
     Marcada por defecto, salvo que "Reserva Mora" ya esté encendida. Vive en el Alpine de
     calcularCancelacion(): mv, moraCalculada(), mvValor(), mvValida(), mvResto(). --}}
<div x-show="moraCalculada() > 0.005">
    <div class="calc-fila align-items-center">
        {{-- Casilla y texto en un label con flex (no .form-check): si el texto parte en dos líneas, la casilla no salta arriba. --}}
        <label class="d-flex align-items-center gap-2 mb-0 fw-normal" for="cancelacion-mora-cobro">
            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" id="cancelacion-mora-cobro" x-model="mv.sel">
            <span>
                Mora
                <span class="d-block small text-muted"
                      x-text="(hayCancelacion && p.mora_rotulo ? p.mora_rotulo + ' · ' : '') + 'calculada ' + soles(moraCalculada())"></span>
            </span>
        </label>
        <input type="number" step="0.01" min="0.01" autocomplete="off"
               class="form-control form-control-sm text-end input-rojo flex-shrink-0 calc-importe"
               aria-label="Mora a cobrar"
               x-model="mv.valor" :disabled="! mv.sel || ! puedeMora" x-on:keydown.enter.prevent="usar()"
               :title="puedeMora ? 'Mora que lleva este cobro' : 'El importe solo se cambia con el permiso de mora manual'">
    </div>
    <div class="small text-danger" x-show="! mvValida()">Escribe un importe mayor que 0 o desmarca la casilla.</div>
    <div class="small text-muted" x-show="! mv.sel">No se cobra ahora: el cobro va con «Reserva Mora» y queda como mora acumulada.</div>
    <div class="small text-muted" x-show="mv.sel && mvValida() && mvResto() > 0.005"
         x-text="'Quedan ' + soles(mvResto()) + ' sin cobrar: siguen como mora acumulada. Al cobrar se pide el motivo.'"></div>
    <div class="small text-muted" x-show="mv.sel && mvValida() && moraVigente() > moraCalculada() + 0.005">Es más que la calculada: al cobrar se pide el motivo.</div>
</div>
