{{-- Tabla del cronograma (parcial): columna única va directa al flujo;
     multi-columna dentro de ax-split. Recibe: grupo, esUltimo, claseCron,
     fmt, total, anchoCronCm.

     $anchoCronCm SOLO lo usa Word (16/09): su importador mide el `width: 100%`
     de una tabla anidada contra la PÁGINA y no contra la celda, así que el
     bloque salía con el ancho de la hoja entera metido en media hoja. Con un
     ancho absoluto y la rejilla declarada en <colgroup> no queda nada que
     deducir. dompdf no ve nada de esto: sale por la rama 'pdf'. --}}
@php $esWord = ($medio ?? 'pdf') === 'word'; @endphp
<table class="ax ax-cron {{ $claseCron }}" width="100%"
       style="margin-bottom: 0;@if($esWord) width: {{ $anchoCronCm }}cm; table-layout: fixed;@endif">
    @if ($esWord)
        {{-- Word arma la rejilla adivinando desde la primera fila, que aquí
             lleva un colspan: sin <colgroup> reparte las columnas a su
             criterio y la del "S/." sale gorda comiéndose FECHAS. --}}
        <colgroup>
            <col style="width: {{ round($anchoCronCm * 0.14, 2) }}cm">
            <col style="width: {{ round($anchoCronCm * 0.40, 2) }}cm">
            <col style="width: {{ round($anchoCronCm * 0.13, 2) }}cm">
            <col style="width: {{ round($anchoCronCm * 0.33, 2) }}cm">
        </colgroup>
    @endif
    <tr>
        <th class="azul" style="width: 14%;">N°</th>
        <th class="azul" style="width: 40%;">FECHAS</th>
        <th class="azul" colspan="2" style="width: 46%;">CUOTAS</th>
    </tr>
    @foreach ($grupo as $fila)
        <tr>
            <td class="num">{{ $fila['n'] }}</td>
            <td class="num">{{ $fila['fecha'] }}</td>
            <td class="sim">S/.</td>
            <td class="montod">{{ $fmt($fila['monto']) }}</td>
        </tr>
    @endforeach
    @if ($esUltimo)
        <tr class="total">
            <td colspan="2" style="text-align: center;">Total</td>
            {{-- 'none' y no '0': con ancho 0 y estilo sin declarar, Word
                 repinta la línea de rejilla y separa el S/. del importe. --}}
            <td class="sim" style="border-right: none;">S/.</td>
            <td class="montod">{{ $fmt($d['cronograma']['total']) }}</td>
        </tr>
    @endif
</table>
