@php
    // Ancho del papel según las columnas configuradas para la ticketera:
    // 32 cols = 58mm, 48 cols = 80mm (config/printer.php).
    $anchoMm = ((int) config('printer.columns', 48)) <= 32 ? 58 : 80;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo #{{ $t['numero'] }}</title>
    <style>
        @page { size: {{ $anchoMm }}mm auto; margin: 0; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #e9ecef;
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
            color: #000;
        }

        .ticket {
            width: {{ $anchoMm }}mm;
            margin: 12px auto;
            padding: 4mm 3mm;
            background: #fff;
        }

        .center { text-align: center; }
        .right  { text-align: right; }
        .bold   { font-weight: bold; }

        .empresa { font-size: 17px; font-weight: bold; letter-spacing: .5px; }
        .logo    { max-width: 100%; max-height: 22mm; margin-bottom: 3mm; }

        .titulo  { font-weight: bold; margin-top: 1mm; }
        .numero  { font-size: 20px; font-weight: bold; letter-spacing: 1px; }

        .sep     { border-top: 1px dashed #000; margin: 2mm 0; }
        .sep-dbl { border-top: 3px double #000; margin: 2mm 0; }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 4mm;
        }
        .row > span:last-child { text-align: right; white-space: nowrap; }

        .total { font-size: 15px; font-weight: bold; }

        .pie { margin-top: 2mm; }

        /* Barra de acciones: solo en pantalla */
        .toolbar {
            width: {{ $anchoMm }}mm;
            margin: 12px auto 0;
            display: flex;
            gap: 8px;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .toolbar button {
            flex: 1;
            padding: 8px 10px;
            font-size: 13px;
            border: 0;
            border-radius: 4px;
            cursor: pointer;
        }
        .toolbar .primary { background: #0d6efd; color: #fff; }
        .toolbar .ghost   { background: #ced4da; color: #212529; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .ticket { margin: 0; width: auto; padding: 0 2mm; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" class="primary" onclick="window.print()">Imprimir</button>
    <button type="button" class="ghost" onclick="window.close()">Cerrar</button>
</div>

<div class="ticket">

    <div class="center">
        @if($logo)
            <img src="{{ $logo }}" alt="" class="logo">
        @endif
        <div class="empresa">{{ config('printer.company_name', 'PRESTAMOS HUACACHIN') }}</div>
        @if($ruc = config('printer.company_ruc'))
            <div>RUC {{ $ruc }}</div>
        @endif
        @if($addr = config('printer.company_addr'))
            <div>{{ $addr }}</div>
        @endif
        @if($t['sede'])
            <div>{{ $t['sede'] }}</div>
        @endif
    </div>

    <div class="sep-dbl"></div>

    <div class="center">
        <div class="titulo">RECIBO DE PAGO</div>
        <div class="numero">#{{ $t['numero'] }}</div>
    </div>

    <div class="sep"></div>

    <div class="row"><span>Fecha:</span><span>{{ $t['fecha_hora'] }}</span></div>
    @if($t['cliente'])
        <div class="row"><span>Cliente:</span><span>{{ $t['cliente'] }}</span></div>
    @endif
    @if($t['documento'])
        <div class="row"><span>Doc:</span><span>{{ $t['documento'] }}</span></div>
    @endif
    <div class="row"><span>Credito:</span><span>#{{ $t['credit_id'] }}</span></div>
    @if($t['cobrador'])
        <div class="row"><span>Cobrador:</span><span>{{ $t['cobrador'] }}</span></div>
    @endif
    @if($t['asesor'])
        <div class="row"><span>Asesor:</span><span>{{ $t['asesor'] }}</span></div>
    @endif

    <div class="sep"></div>

    @if($t['cuotas'])
        <div class="row"><span>Cuotas:</span><span>{{ implode(',', $t['cuotas']) }}</span></div>
    @endif
    <div class="row"><span>Capital:</span><span>{{ number_format($t['capital'], 2) }}</span></div>
    <div class="row"><span>Interes:</span><span>{{ number_format($t['interes'], 2) }}</span></div>
    @if($t['excedente'] > 0.001)
        <div class="row"><span>Excedente:</span><span>{{ number_format($t['excedente'], 2) }}</span></div>
    @endif
    @if($t['mora'] > 0.001)
        <div class="row"><span>Mora:</span><span>{{ number_format($t['mora'], 2) }}</span></div>
    @endif

    <div class="sep"></div>

    <div class="row total"><span>TOTAL</span><span>S/ {{ number_format($t['total'], 2) }}</span></div>

    <div class="sep"></div>

    <div class="row"><span>Saldo restante:</span><span>S/ {{ number_format($t['saldo'], 2) }}</span></div>
    @if($t['proxima'])
        <div class="row"><span>Prox. vencimiento:</span><span>{{ $t['proxima'] }}</span></div>
    @endif

    <div class="sep-dbl"></div>

    <div class="center pie">
        <div>¡Gracias por su pago!</div>
        <div>Conserve este recibo.</div>
    </div>

</div>

</body>
</html>
