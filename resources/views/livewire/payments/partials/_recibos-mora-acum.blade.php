{{-- 20/11 (Antony, 29404: "he pagado una mora de 416.43, ¿dónde puedo visualizar ese pago? … no se
     visualiza en el cronograma de cuotas"): la mora acumulada cobrada aparte (filas 'MA') tiene su
     propio recibo, distinto del "Rec." de la cuota. Un botón ámbar por cobro, en la columna "Rec."
     de su cuota. Compartido por /payments/create y /credits/{id}/schedule.
     Recibe: $recibosMora (App\Support\RecibosCuota::moraAcumuladaPorCuota de esa cuota) y $n (número de cuota). --}}
@foreach($recibosMora as $recMora)
    <button type="button" class="btn btn-sm btn-warning py-0 px-1"
            title="Ver recibo de la mora acumulada: S/ {{ number_format($recMora['monto'], 2) }} cobrada el {{ $recMora['fecha'] }}"
            onclick="abrirRecibo(@js($recMora['ver']), @js('Recibo · mora acumulada cuota '.$n))">
        <i class="ti ti-coin"></i>
    </button>
@endforeach
