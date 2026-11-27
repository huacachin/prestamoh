{{-- 20/11 (Antony, 29404: "he pagado una mora de 416.43, ¿dónde puedo visualizar ese pago? … no se
     visualiza en el cronograma de cuotas"): la mora acumulada cobrada aparte (filas 'MA') tiene su
     propio recibo, distinto del "Rec." de la cuota. Un botón ámbar por cobro, en la columna "Rec."
     de su cuota. Compartido por /payments/create y /credits/{id}/schedule.
     27/11 (Antony, 29397): el botón abre el recibo con SOLO la mora acumulada de esa cuota; el cobro
     entero (otras cuotas, otras moras) se veía en la fila de una cuota pagada semanas antes y parecía
     que todo se había pagado ese día. El recibo completo sigue en el "Rec." de las cuotas que pagó.
     Recibe: $recibosMora (App\Support\RecibosCuota::moraAcumuladaPorCuota de esa cuota) y $n (número de cuota). --}}
@foreach($recibosMora as $recMora)
    <button type="button" class="btn btn-sm btn-warning py-0 px-1"
            title="Mora acumulada de esta cuota: S/ {{ number_format($recMora['monto'], 2) }}, cobrada el {{ $recMora['fecha'] }} (recibo #{{ $recMora['lote'] }})"
            onclick="abrirRecibo(@js($recMora['ver']), @js('Recibo #'.$recMora['lote'].' · mora acumulada cuota '.$n))">
        <i class="ti ti-coin"></i>
    </button>
@endforeach
