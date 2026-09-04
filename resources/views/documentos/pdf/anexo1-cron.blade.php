{{-- Tabla del cronograma (parcial): columna única va directa al flujo;
     multi-columna dentro de ax-split. Recibe: grupo, esUltimo, claseCron,
     fmt, total. --}}
<table class="ax ax-cron {{ $claseCron }}" style="margin-bottom: 0;">
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
            <td class="sim" style="border-right: 0;">S/.</td>
            <td class="montod">{{ $fmt($d['cronograma']['total']) }}</td>
        </tr>
    @endif
</table>
