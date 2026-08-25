{{-- Cierre y firmas. En las plantillas maestras cada firmante aparece DOS
     VECES (dos juegos completos de firmas para notaría): se replica igual.
     Datos de apoderada/acreedor desde $vm->constante(...) — nunca hardcodeados.
     Se corrige el typo del original: ACREEDEDOR → ACREEDOR. --}}
@php
    $apoderada = $vm->constante('apoderada');
    $acreedor = $vm->constante('acreedor');

    // Un juego = una celda por cada deudor + una celda de la apoderada;
    // se repite el juego completo 2 veces. null = celda de la apoderada.
    $celdas = [];
    for ($juego = 0; $juego < 2; $juego++) {
        foreach ($vm->deudores as $d) {
            $celdas[] = $d;
        }
        $celdas[] = null;
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
                                DNI N° {{ $celda['gerente']['dni'] }}<br>
                                {{ $celda['g']->deudor() }}
                            @else
                                {{ mb_strtoupper($celda['nombre']) }}<br>
                                DNI N° {{ $celda['dni'] }}<br>
                                {{ $celda['g']->deudor() }}
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
