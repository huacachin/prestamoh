{{-- Recibo en PDF (dompdf). OJO: dompdf NO soporta flexbox — todo va con
     tablas. Papel: 80mm de ancho (seteado en PaymentController::reciboPdf). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; }
        body {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 8.5pt;
            line-height: 1.4;
            color: #000;
            padding: 8pt 10pt;
        }
        .center  { text-align: center; }
        .empresa { font-size: 11pt; font-weight: bold; }
        .numero  { font-size: 13pt; font-weight: bold; letter-spacing: 1pt; }
        .titulo  { font-weight: bold; }
        .sep     { border-top: 1px dashed #000; margin: 4pt 0; }
        .sep-dbl { border-top: 3px double #000; margin: 4pt 0; }
        table.fila { width: 100%; border-collapse: collapse; }
        table.fila td { padding: 0; vertical-align: top; }
        table.fila td.der { text-align: right; white-space: nowrap; }
        .total td { font-size: 10.5pt; font-weight: bold; }
        .logo { max-width: 120pt; max-height: 55pt; margin-bottom: 4pt; }
        .pie { margin-top: 5pt; }
    </style>
</head>
<body>

<div class="center">
    @if($logo)
        <img src="{{ $logo }}" class="logo" alt="">
    @endif
    <div class="empresa">{{ config('printer.company_name', 'HUACACHIN') }}</div>
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

<table class="fila">
    <tr><td>Fecha:</td><td class="der">{{ $t['fecha_hora'] }}</td></tr>
    @if(!empty($t['metodo']))
        <tr><td>Pago:</td><td class="der">{{ $t['metodo'] }}</td></tr>
    @endif
    @if($t['cliente'])
        <tr><td>Cliente:</td><td class="der">{{ $t['cliente'] }}</td></tr>
    @endif
    @if($t['documento'])
        <tr><td>Doc:</td><td class="der">{{ $t['documento'] }}</td></tr>
    @endif
    <tr><td>Credito:</td><td class="der">#{{ $t['credit_id'] }}</td></tr>
    @if($t['cobrador'])
        <tr><td>Cobrador:</td><td class="der">{{ $t['cobrador'] }}</td></tr>
    @endif
    @if($t['asesor'])
        <tr><td>Asesor:</td><td class="der">{{ $t['asesor'] }}</td></tr>
    @endif
</table>

<div class="sep"></div>

<table class="fila">
    @if($t['cuotas'])
        <tr><td>Cuotas:</td><td class="der">{{ implode(',', $t['cuotas']) }}</td></tr>
    @endif
    <tr><td>Capital:</td><td class="der">{{ number_format($t['capital'], 2) }}</td></tr>
    <tr><td>Interes:</td><td class="der">{{ number_format($t['interes'], 2) }}</td></tr>
    @if($t['excedente'] > 0.001)
        <tr><td>Excedente:</td><td class="der">{{ number_format($t['excedente'], 2) }}</td></tr>
    @endif
    @if($t['mora'] > 0.001)
        <tr><td>Mora:</td><td class="der">{{ number_format($t['mora'], 2) }}</td></tr>
    @endif
</table>

<div class="sep"></div>

<table class="fila total">
    <tr><td>TOTAL</td><td class="der">S/ {{ number_format($t['total'], 2) }}</td></tr>
</table>

<div class="sep"></div>

<table class="fila">
    <tr><td>Saldo restante:</td><td class="der">S/ {{ number_format($t['saldo'], 2) }}</td></tr>
    @if($t['proxima'])
        <tr><td>Prox. vencimiento:</td><td class="der">{{ $t['proxima'] }}</td></tr>
    @endif
</table>

<div class="sep-dbl"></div>

<div class="center pie">
    <div>¡Gracias por su pago!</div>
    <div>Conserve este recibo.</div>
</div>

</body>
</html>
