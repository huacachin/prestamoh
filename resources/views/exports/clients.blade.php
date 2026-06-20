@extends('exports.layout')

@php
    $hd = 'bgcolor="#2874A6" style="color:white;text-align:center;" ';
    $cell = 'style="border-style:dotted solid dotted solid;text-align:center;vertical-align:middle;"';
@endphp

@section('content')
    @if($esCesado)
        {{-- clienteex_x.php (9 cols) --}}
        <center><font color="red"><b>CLIENTES CESADOS</b></font></center>
        <table border="1" cellspacing="0">
            <thead>
                <tr>
                    <th {!! $hd !!}>Item</th>
                    <th {!! $hd !!}>Fecha</th>
                    <th {!! $hd !!}>Usuario</th>
                    <th {!! $hd !!}>Exp.</th>
                    <th {!! $hd !!}>Nombres Apellidos</th>
                    <th {!! $hd !!}>DNI</th>
                    <th {!! $hd !!}>Movil</th>
                    <th {!! $hd !!}>Telefono</th>
                    <th {!! $hd !!}>Asesor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clients as $i => $c)
                    @php
                        $nombre = trim(($c->apellido_pat ?? '').' '.($c->apellido_mat ?? '').' '.($c->nombre ?? ''));
                        $fecha = $c->fecha_registro ? \Illuminate\Support\Carbon::parse($c->fecha_registro)->format('d/m/Y') : '';
                        $asesor = $c->asesor?->name ?? $c->asesor?->username ?? '';
                    @endphp
                    <tr>
                        <td {!! $cell !!}>{{ $i + 1 }}</td>
                        <td {!! $cell !!} class="txt">{{ $fecha }}</td>
                        <td {!! $cell !!}>{{ $c->usuario }}</td>
                        <td {!! $cell !!}>{{ $c->expediente }}</td>
                        <td {!! $cell !!}>{{ $nombre }}</td>
                        <td {!! $cell !!} class="txt">{{ $c->documento }}</td>
                        <td {!! $cell !!} class="txt">{{ $c->celular1 }}</td>
                        <td {!! $cell !!} class="txt">{{ $c->celular2 }}</td>
                        <td {!! $cell !!}>{{ $asesor }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        {{-- clienteex.php (11 cols) --}}
        <center><font color="red"><b>CLIENTES</b></font></center>
        <table cellspacing="0" border="1">
            <thead>
                <tr>
                    <th {!! $hd !!}>N&deg;</th>
                    <th {!! $hd !!}>Fecha</th>
                    <th {!! $hd !!}>Usuario</th>
                    <th {!! $hd !!}>Exp.</th>
                    <th {!! $hd !!}>Nombres Apellidos</th>
                    <th {!! $hd !!}>DNI</th>
                    <th {!! $hd !!}>Movil</th>
                    <th {!! $hd !!}>T.Credito</th>
                    <th {!! $hd !!}>Giro</th>
                    <th {!! $hd !!}>Asesor</th>
                    <th {!! $hd !!}>Direccion</th>
                </tr>
            </thead>
            <tbody>
                @foreach($clients as $i => $c)
                    @php
                        $nombre = trim(($c->apellido_pat ?? '').' '.($c->apellido_mat ?? '').' '.($c->nombre ?? ''));
                        $fecha = $c->fecha_registro ? \Illuminate\Support\Carbon::parse($c->fecha_registro)->format('d/m/Y') : '';
                        $asesor = $c->asesor?->name ?? $c->asesor?->username ?? '';
                    @endphp
                    <tr>
                        <td {!! $cell !!}>{{ $i + 1 }}</td>
                        <td {!! $cell !!} class="txt">{{ $fecha }}</td>
                        <td {!! $cell !!}>{{ $c->usuario }}</td>
                        <td {!! $cell !!}>{{ $c->expediente }}</td>
                        <td {!! $cell !!}>{{ $nombre }}</td>
                        <td {!! $cell !!} class="txt">{{ $c->documento }}</td>
                        <td {!! $cell !!} class="txt">{{ $c->celular1 }}</td>
                        <td {!! $cell !!}>{{ $c->zona }}</td>
                        <td {!! $cell !!}>{{ $c->giro }}</td>
                        <td {!! $cell !!}>{{ $asesor }}</td>
                        <td {!! $cell !!}>{{ $c->direccion }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
