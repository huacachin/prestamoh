{{-- ANEXO 1 — Cronograma de pagos. Documento COMPLETO renderizado desde el
     snapshot congelado ($d) por cualquiera de los tres medios ($medio:
     'pdf' | 'previa' | 'word'). Calca la estructura del Anexo 1 del área
     (membrete, bloques de datos y tabla del cronograma); el cronograma sale
     ÍNTEGRO de $d['cronograma'] (credit_installments) — nada se recalcula. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Anexo 1 — Crédito #{{ $d['credito']['numero'] }}</title>
    @include('documentos.pdf.estilos')
</head>
<body>
    <div class="pie-pagina">Anexo 1 — Crédito #{{ $d['credito']['numero'] }} — Página <span class="num"></span></div>

    <div class="membrete">{{ $d['marca'] }}</div>
    <div class="anexo-titulo">ANEXO 1</div>
    <div class="anexo-subtitulo">CRONOGRAMA DE PAGOS</div>

    <table class="datos">
        <tr><th colspan="2">DATOS DEL CLIENTE</th></tr>
        <tr>
            <th style="width: 35%;">CLIENTE</th>
            <td>{{ $d['cliente']['nombre'] }}</td>
        </tr>
        @if (filled($d['cliente']['documento']))
            <tr>
                <th>{{ $d['cliente']['documento_tipo'] }}</th>
                <td>{{ $d['cliente']['documento'] }}</td>
            </tr>
        @endif
        @if (filled($d['cliente']['domicilio']))
            <tr>
                <th>DOMICILIO</th>
                <td>{{ $d['cliente']['domicilio'] }}</td>
            </tr>
        @endif
        @if (filled($d['cliente']['celular']))
            <tr>
                <th>CELULAR</th>
                <td>{{ $d['cliente']['celular'] }}</td>
            </tr>
        @endif
        @if (filled($d['cliente']['correo']))
            <tr>
                <th>CORREO ELECTRÓNICO</th>
                <td>{{ $d['cliente']['correo'] }}</td>
            </tr>
        @endif
    </table>

    @if ($d['vehiculo'])
        <table class="datos">
            <tr><th colspan="2">DATOS DEL VEHÍCULO</th></tr>
            <tr>
                <th style="width: 35%;">PLACA DE RODAJE</th>
                <td>{{ $d['vehiculo']['placa'] ?: '—' }}</td>
            </tr>
            <tr>
                <th>MARCA</th>
                <td>{{ $d['vehiculo']['marca'] ?: '—' }}</td>
            </tr>
            <tr>
                <th>MODELO</th>
                <td>{{ $d['vehiculo']['modelo'] ?: '—' }}</td>
            </tr>
            <tr>
                <th>N° SERIE</th>
                <td>{{ $d['vehiculo']['nro_serie'] ?: '—' }}</td>
            </tr>
            @if ($d['vehiculo']['valor'] !== null)
                <tr>
                    <th>VALOR DEL VEHÍCULO</th>
                    <td>S/ {{ number_format((float) $d['vehiculo']['valor'], 2) }}</td>
                </tr>
            @endif
        </table>
    @endif

    <table class="datos">
        <tr><th colspan="2">DATOS DEL CRÉDITO</th></tr>
        <tr>
            <th style="width: 35%;">N° DE CRÉDITO</th>
            <td>{{ $d['credito']['numero'] }}</td>
        </tr>
        <tr>
            <th>MONEDA</th>
            <td>{{ $d['credito']['moneda'] }}</td>
        </tr>
        <tr>
            <th>MONTO DEL CRÉDITO</th>
            <td>S/ {{ number_format((float) $d['credito']['monto'], 2) }}</td>
        </tr>
        <tr>
            <th>FRECUENCIA DE PAGO</th>
            <td>{{ $d['credito']['frecuencia'] }}</td>
        </tr>
        <tr>
            <th>N° DE CUOTAS</th>
            <td>{{ $d['credito']['cuotas'] }}</td>
        </tr>
        <tr>
            <th>CUOTA</th>
            <td>S/ {{ number_format((float) $d['credito']['cuota'], 2) }}</td>
        </tr>
        <tr>
            <th>FECHA</th>
            <td>{{ $d['fecha'] }}</td>
        </tr>
    </table>

    <table class="cronograma">
        <thead>
            <tr>
                <th>N°</th>
                <th>FECHA</th>
                <th>CUOTA S/</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($d['cronograma']['filas'] as $fila)
                <tr>
                    <td class="num">{{ $fila['n'] }}</td>
                    <td class="fecha">{{ $fila['fecha'] }}</td>
                    <td class="monto">{{ number_format((float) $fila['monto'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">TOTAL</td>
                <td class="monto">{{ number_format((float) $d['cronograma']['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
