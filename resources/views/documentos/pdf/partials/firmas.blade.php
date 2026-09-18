{{-- Cierre y firmas. UN SOLO juego y el ACREEDOR primero (05/09, corrección
     de Antony): antes se replicaba el juego completo dos veces —leído así de
     las maestras, donde el bloque aparecía repetido— y el deudor iba delante.
     El rótulo bajo la línea es COLECTIVO ($vm->g), no el género individual:
     a.3 pone "LOS DEUDORES" en las dos cajas aunque los firmantes sean de
     distinto sexo, y a.2 pone "LA DEUDORA". Con el género por firmante salía
     "EL DEUDOR" en una caja y "LA DEUDORA" en la otra.
     Datos de apoderada/acreedor desde $vm->constante(...) — nunca hardcodeados.
     Se corrige el typo del original: ACREEDEDOR → ACREEDOR. --}}
@php
    $apoderada = $vm->constante('apoderada');
    $acreedor = $vm->constante('acreedor');

    // Una columna por PARTE (18/09, maestro Desktop/deudor1.jpeg): el
    // acreedor a la izquierda y TODOS los deudores a la derecha, uno debajo
    // de otro. Antes las cajas se repartían de dos en dos, así que con dos
    // deudores el segundo caía en la columna del acreedor y parecía firmar
    // por él.
    //
    // 'acreedor' = la caja de la apoderada; 'vacio' = hueco para alinear.
    $filas = [];
    foreach ($vm->deudores as $i => $d) {
        $filas[] = [$i === 0 ? 'acreedor' : 'vacio', $d];
    }
    if ($filas === []) {
        $filas[] = ['acreedor', 'vacio'];
    }
@endphp

<div class="firmas">
    <p class="parrafo">EN SEÑAL DE CONFORMIDAD, LAS PARTES FIRMAN EL PRESENTE DOCUMENTO EN {{ mb_strtoupper($vm->constante('ciudad_firma')) }}, EL {{ $vm->fechaSimple }}.</p>

    <table class="tabla-firmas">
        @foreach ($filas as $fila)
            <tr>
                @foreach ($fila as $celda)
                    <td>
                        @if ($celda === 'vacio')
                            {{-- Hueco: la columna del acreedor solo lleva
                                 caja en la primera fila. --}}
                        @else
                        <div class="linea-firma">
                            @if ($celda === 'acreedor')
                                {{ mb_strtoupper($apoderada['nombre']) }}<br>
                                DNI N° {{ $apoderada['dni'] }}<br>
                                APODERADA DE:<br>
                                {{ mb_strtoupper($acreedor['nombre']) }}<br>
                                DNI N° {{ $acreedor['dni'] }}<br>
                                EL ACREEDOR
                            @elseif ($celda['esJuridica'])
                                {{ mb_strtoupper($celda['nombre']) }}<br>
                                RUC N° {{ $celda['ruc'] }}<br>
                                GERENTE GENERAL: {{ mb_strtoupper($celda['gerente']['nombre']) }}<br>
                                {{ $celda['gerente']['documentoTipo'] ?? 'DNI' }} N° {{ $celda['gerente']['dni'] }}<br>
                                {{ $vm->g->deudor() }}
                            @else
                                {{ mb_strtoupper($celda['nombre']) }}<br>
                                {{ $celda['documentoTipo'] ?? 'DNI' }} N° {{ $celda['dni'] }}<br>
                                {{ $vm->g->deudor() }}
                            @endif
                        </div>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
</div>
