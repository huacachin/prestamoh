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

    // Un único juego: primero el acreedor (null = celda de la apoderada),
    // después cada deudor.
    $celdas = [null];
    foreach ($vm->deudores as $d) {
        $celdas[] = $d;
    }
@endphp

<div class="firmas">
    <p class="parrafo">EN SEÑAL DE CONFORMIDAD, LAS PARTES FIRMAN EL PRESENTE DOCUMENTO EN {{ mb_strtoupper($vm->constante('ciudad_firma')) }}, EL {{ $vm->fechaSimple }}.</p>

    <table class="tabla-firmas">
        @foreach (array_chunk($celdas, 2) as $fila)
            <tr>
                @foreach ($fila as $celda)
                    <td>
                        <div class="linea-firma">
                            @if ($celda === null)
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
                    </td>
                @endforeach
                @if (count($fila) === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
</div>
