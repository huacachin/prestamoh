{{-- Cronograma en columnas PARA WORD: una sola tabla PLANA, sin anidar.

     16/09 — POR QUÉ EXISTE ESTE PARCIAL. En el PDF el cronograma en columnas
     se arma con una tabla contenedora (ax-split) y una tabla por columna
     dentro de cada celda. Word NO aplica las reglas de clase (`table.ax th,
     table.ax td { border… }`, `th.azul { background… }`) a una tabla ANIDADA:
     el cronograma salía sin un solo borde y sin la cabecera azul, mientras
     las tablas de datos de arriba —que son de primer nivel— se veían bien.

     Aquí se emiten los mismos datos como UNA tabla de primer nivel: 4 columnas
     por grupo (N° / FECHAS / S/. / monto) y una columna separadora entre
     grupos. La separadora no lleva bordes, así que con border-collapse queda
     un canal blanco y las líneas que se ven a cada lado son el borde exterior
     de cada grupo — igual que dos tablas separadas.

     Recibe: grupos, claseCron, fmt, anchoUtilCm. --}}
@php
    $nGrupos = count($grupos);
    // Alto de la columna más larga: las filas se recorren en paralelo.
    $filasMax = max(array_map('count', $grupos));
    // Reparto del ancho: canal de 0.25cm entre grupos, el resto a repartir.
    $sepCm = 0.25;
    $grupoCm = round(($anchoUtilCm - $sepCm * ($nGrupos - 1)) / $nGrupos, 2);
    // Mismos porcentajes que la tabla del PDF: 14 / 40 / 13 / 33.
    $cNum = round($grupoCm * 0.14, 2);
    $cFecha = round($grupoCm * 0.40, 2);
    $cSim = round($grupoCm * 0.13, 2);
    $cMonto = round($grupoCm * 0.33, 2);
    // Sin bordes en la columna separadora: los verticales los pone el vecino.
    $sinBorde = 'border: none;';
@endphp
<table class="ax ax-cron {{ $claseCron }}" width="100%"
       style="margin-bottom: 0; width: {{ $anchoUtilCm }}cm; table-layout: fixed;">
    <colgroup>
        @foreach ($grupos as $i => $grupo)
            @if ($i > 0)
                <col style="width: {{ $sepCm }}cm">
            @endif
            <col style="width: {{ $cNum }}cm">
            <col style="width: {{ $cFecha }}cm">
            <col style="width: {{ $cSim }}cm">
            <col style="width: {{ $cMonto }}cm">
        @endforeach
    </colgroup>
    <tr>
        @foreach ($grupos as $i => $grupo)
            @if ($i > 0)
                <td style="{{ $sinBorde }}"></td>
            @endif
            <th class="azul">N°</th>
            <th class="azul">FECHAS</th>
            <th class="azul" colspan="2">CUOTAS</th>
        @endforeach
    </tr>
    @for ($f = 0; $f < $filasMax; $f++)
        <tr>
            @foreach ($grupos as $i => $grupo)
                @if ($i > 0)
                    <td style="{{ $sinBorde }}"></td>
                @endif
                @if (isset($grupo[$f]))
                    <td class="num">{{ $grupo[$f]['n'] }}</td>
                    <td class="num">{{ $grupo[$f]['fecha'] }}</td>
                    <td class="sim">S/.</td>
                    <td class="montod">{{ $fmt($grupo[$f]['monto']) }}</td>
                @else
                    {{-- Grupo más corto: celdas vacías SIN borde, para que no
                         se dibuje una caja donde no hay cuota. --}}
                    <td colspan="4" style="{{ $sinBorde }}"></td>
                @endif
            @endforeach
        </tr>
    @endfor
    {{-- Total: en las columnas del ÚLTIMO grupo, como en el maestro. --}}
    <tr>
        @foreach ($grupos as $i => $grupo)
            @if ($i > 0)
                <td style="{{ $sinBorde }}"></td>
            @endif
            @if ($i === $nGrupos - 1)
                <td class="total-celda" colspan="2" style="text-align: center;">Total</td>
                <td class="total-celda sim" style="border-right: none;">S/.</td>
                <td class="total-celda montod">{{ $fmt($d['cronograma']['total']) }}</td>
            @else
                <td colspan="4" style="{{ $sinBorde }}"></td>
            @endif
        @endforeach
    </tr>
</table>
