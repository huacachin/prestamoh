{{-- Filas de ABONO debajo de una cuota (17/10/2026, Antony: "al igual que la
     primera vista debe de haber un desglose"). Compartido por /credits/{id}/schedule
     y /payments/create. Mismas 13/14 columnas y el mismo formato que una fila del
     semanal (criterio del 02/10: tamaño normal, Pagado en verde, sin letra chica):
     la subordinación la dan la numeración n.k y las celdas vacías. Fondo amarillo
     si el abono fue después del vencimiento; fecha en rojo (domingo) o verde (sábado).
     La clase abono-pagada marca los abonos de cuotas ya pagadas: la pantalla de
     cobro los pliega por CSS hasta que se activa "Ver abonos de cuotas pagadas".

     Recibe: $abonosCuota (lista de App\Support\AbonosPorCuota), $n (número de
     cuota), $insId, $tieneExc, $formatoFecha ('d/m/Y' | 'Y-m-d') y $cuotaPagada (bool). --}}
@foreach($abonosCuota as $i => $ab)
    @php
        $stAb = $ab['color'] ? 'color:'.$ab['color'].';' : '';
        $cobro = $ab['cobro'] ?? ['lote' => null, 'total' => 0, 'mora' => 0, 'otras' => []];
        // Nota del cobro partido: solo cuando el mismo cobro tocó otra cuota o trajo mora.
        $nota = '';
        if ($cobro['otras'] !== [] || $cobro['mora'] > 0.009) {
            $partes = [];
            foreach ($cobro['otras'] as $o) {
                $partes[] = 'además S/ '.number_format($o['monto'], 2).' a la cuota '.$o['num'];
            }
            if ($cobro['mora'] > 0.009) {
                $partes[] = 'S/ '.number_format($cobro['mora'], 2).' de mora';
            }
            $nota = 'Cobro '.($cobro['lote'] ? '#'.$cobro['lote'].' ' : '').'de S/ '.number_format($cobro['total'], 2).' · '.implode(' · ', $partes);
        }
        $fechaAb = $ab['fecha'] !== '' ? \Carbon\Carbon::parse($ab['fecha'])->format($formatoFecha) : '';
        // Sin @if dentro de la celda: Livewire envuelve los bloques en marcadores <!--[if BLOCK]-->
        // y la numeración dejaría de leerse como ">21.1</td>".
        $tituloN = ! empty($ab['segun_caja'])
            ? 'Desglose según caja (cobro migrado del legacy): la suma de los abonos no cuadra con lo aplicado a la cuota; manda la fila de la cuota.'
            : '';
        $marcaN = $tituloN !== '' ? '<sup title="'.e($tituloN).'">*</sup>' : '';
    @endphp
    <tr class="fila-abono{{ $cuotaPagada ? ' abono-pagada' : '' }}" wire:key="abono-{{ $insId }}-{{ $i }}"
        @if($ab['tarde']) style="background-color:#fff3cd;" @endif>
        <td style="{{ $stAb }}" class="text-center">{{ $n }}.{{ $i + 1 }}{!! $marcaN !!}</td>
        <td colspan="{{ $tieneExc ? 4 : 3 }}" class="text-muted" style="white-space:normal; font-size:.9em;">{{ $nota }}</td>
        <td style="{{ $stAb }}" class="text-end">{{ number_format($ab['cap'], 2) }}</td>
        <td style="{{ $stAb }}" class="text-end">{{ number_format($ab['int'], 2) }}</td>
        <td style="{{ $stAb }}" class="text-end">{{ number_format($ab['monto'], 2) }}</td>
        <td style="{{ $stAb }}" class="text-end">{{ number_format($ab['saldo'], 2) }}</td>
        <td></td>
        <td class="text-center"><span class="badge bg-success">Pagado</span></td>
        <td style="{{ $stAb }}">
            {{ $fechaAb }}
            @if($ab['hora'])
                <small>{{ $ab['hora'] }}</small>
            @endif
        </td>
        <td class="text-center" style="white-space:nowrap;">
            @if($ab['recibo'])
                <button type="button" class="btn btn-sm btn-secondary py-0 px-1"
                        title="Ver recibo" onclick="abrirRecibo(@js($ab['recibo']))">
                    <i class="ti ti-eye"></i>
                </button>
            @endif
        </td>
    </tr>
@endforeach
