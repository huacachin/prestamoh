{{-- ANEXO 1 — Cronograma de pagos. Calca la estructura del Excel real del área
     legal ("2. Anexo 1 - Cronograma"): membrete, bloques de datos (cliente,
     vehículo, crédito) y la tabla del cronograma. Solo consume $vm; el
     cronograma sale ÍNTEGRO de $vm->cronograma (credit_installments) y los
     montos de $vm->montoCifra() — nada se recalcula aquí. --}}
@php $deudor = $vm->deudores[0]; @endphp

<div class="membrete">{{ $vm->constante('marca_documentos') }}</div>
<div class="anexo-titulo">ANEXO 1</div>
<div class="anexo-subtitulo">CRONOGRAMA DE PAGOS</div>

<table class="datos">
    <tr><th colspan="2">DATOS DEL CLIENTE</th></tr>
    <tr>
        <th style="width: 35%;">CLIENTE</th>
        <td>{{ $deudor['nombre'] }}</td>
    </tr>
    <tr>
        <th>{{ $deudor['esJuridica'] ? 'RUC' : 'DNI' }}</th>
        <td>{{ $deudor['esJuridica'] ? $deudor['ruc'] : $deudor['dni'] }}</td>
    </tr>
    <tr>
        <th>DOMICILIO</th>
        <td>{{ $deudor['domicilio'] }}</td>
    </tr>
    <tr>
        <th>CORREO ELECTRÓNICO</th>
        <td>{{ $deudor['correo'] }}</td>
    </tr>
</table>

<table class="datos">
    <tr><th colspan="2">{{ count($vm->bienes) > 1 ? 'DATOS DE LOS VEHÍCULOS' : 'DATOS DEL VEHÍCULO' }}</th></tr>
    @foreach ($vm->bienes as $bien)
        @if (count($vm->bienes) > 1)
            <tr><th colspan="2">VEHÍCULO {{ $loop->iteration }}</th></tr>
        @endif
        <tr>
            <th style="width: 35%;">PLACA DE RODAJE</th>
            <td>{{ $bien['placa'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>MARCA</th>
            <td>{{ $bien['marca'] }}</td>
        </tr>
        <tr>
            <th>MODELO</th>
            <td>{{ $bien['modelo'] }}</td>
        </tr>
        <tr>
            <th>N° SERIE</th>
            <td>{{ $bien['serie'] ?: '—' }}</td>
        </tr>
        <tr>
            <th>VALOR DEL VEHÍCULO</th>
            <td>S/ {{ number_format((float) $bien['valor'], 2) }}</td>
        </tr>
    @endforeach
</table>

<table class="datos">
    <tr><th colspan="2">DATOS DEL CRÉDITO</th></tr>
    <tr>
        <th style="width: 35%;">N° DE CONTRATO</th>
        <td>{{ $vm->numero }}</td>
    </tr>
    <tr>
        <th>MONEDA</th>
        <td>SOLES</td>
    </tr>
    <tr>
        <th>MONTO DEL CRÉDITO</th>
        <td>{{ $vm->montoCifra('obligacion') }}</td>
    </tr>
    <tr>
        <th>FRECUENCIA DE PAGO</th>
        <td>{{ mb_strtoupper($vm->frecuencia) }}</td>
    </tr>
    <tr>
        <th>N° DE CUOTAS</th>
        <td>{{ $vm->numCuotas }}</td>
    </tr>
    <tr>
        <th>CUOTA</th>
        <td>{{ $vm->montoCifra('cuota') }}</td>
    </tr>
    <tr>
        <th>FECHA DE INICIO</th>
        <td>{{ $vm->fechaSimple }}</td>
    </tr>
</table>

<table class="cronograma">
    <tr>
        <th>N°</th>
        <th>FECHA</th>
        <th>CUOTA S/</th>
    </tr>
    @foreach ($vm->cronograma['filas'] as $fila)
        <tr>
            <td class="num">{{ $fila['n'] }}</td>
            <td class="fecha">{{ $fila['fecha'] }}</td>
            <td class="monto">{{ number_format((float) $fila['monto'], 2) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td colspan="2">TOTAL</td>
        <td class="monto">{{ number_format((float) $vm->cronograma['total'], 2) }}</td>
    </tr>
</table>
