{{-- Filas de ABONO debajo de una cuota (17/10/2026, Antony: "al igual que la
     primera vista debe de haber un desglose"). Compartido por /credits/{id}/schedule
     (todas abiertas, sin plegado) y /payments/create (plegables con Alpine:
     abiertas[ID] ?? default, sin round trips). Mismas 13/14 columnas que la tabla
     que las contiene; la fila lleva un fondo propio y amarillo si el abono fue
     después del vencimiento, y la fecha en rojo (domingo) o verde (sábado).

     Recibe: $abonosCuota (lista de App\Support\AbonosPorCuota), $n (número de
     cuota), $insId, $tieneExc, $formatoFecha ('d/m/Y' | 'Y-m-d'),
     $colapsable (bool) y $abiertaPorDefecto (bool). --}}
@foreach($abonosCuota as $i => $ab)
    @php
        $stAb = $ab['color'] ? 'color:'.$ab['color'].';' : '';
        $cobro = $ab['cobro'] ?? ['lote' => null, 'total' => 0, 'mora' => 0, 'otras' => []];
        // Nota del cobro partido: solo cuando el mismo cobro tocó otra cuota o trajo mora.
        $nota = '';
        if ($cobro['otras'] !== [] || $cobro['mora'] > 0.009) {
            $partes = ['S/ '.number_format($ab['monto'], 2).' a esta cuota'];
            foreach ($cobro['otras'] as $o) {
                $partes[] = 'S/ '.number_format($o['monto'], 2).' a la cuota '.$o['num'];
            }
            if ($cobro['mora'] > 0.009) {
                $partes[] = 'S/ '.number_format($cobro['mora'], 2).' de mora';
            }
            $nota = 'Cobro S/ '.number_format($cobro['total'], 2).($cobro['lote'] ? ' (#'.$cobro['lote'].')' : '').': '.implode(' · ', $partes);
        }
        $fechaAb = $ab['fecha'] !== '' ? \Carbon\Carbon::parse($ab['fecha'])->format($formatoFecha) : '';
    @endphp
    <tr class="fila-abono" wire:key="abono-{{ $insId }}-{{ $i }}" data-abonos="{{ $insId }}"
        style="background-color:{{ $ab['tarde'] ? '#fff3cd' : '#fbfbfb' }};"
        @if($colapsable) x-show="abiertas[{{ $insId }}] ?? {{ $abiertaPorDefecto ? 'true' : 'false' }}" x-cloak @endif>
        <td style="{{ $stAb }}" class="text-center">{{ $n }}.{{ $i + 1 }}</td>
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
