<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">FICHA DE CLIENTE</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Detalle</span></li>
            </ul>
        </div>
    </div>

    {{-- Acciones --}}
    <div class="row my-2">
        <div class="col-12">
            <div class="d-flex gap-2 py-1">
                <a href="{{ route('clients.edit', $client->id) }}" class="btn btn-sm btn-outline-success">
                    <i class="ti ti-edit"></i> Editar
                </a>
                <a href="{{ route('credits.create', $client->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-credit-card"></i> Nuevo Crédito
                </a>
                <button class="btn btn-sm btn-success" onclick="window.print()">
                    <i class="ti ti-file-spreadsheet"></i>
                </button>
                <a href="{{ route('clients.index') }}" class="btn btn-sm btn-secondary ms-auto">Volver</a>
            </div>
        </div>
    </div>

    {{-- Ficha personal --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-bordered mb-0" style="font-size: 13px;">
                <tr>
                    <td colspan="4" class="bg-primary text-white" style="font-weight:500; padding:6px 12px;">Ficha Personal</td>
                </tr>
                <tr>
                    <td colspan="4" style="background-color:#e9ecef; font-weight:500; padding:6px 12px;">Datos Personales</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0; width:15%;">Nombres :</td>
                    <td style="width:35%;">{{ $client->fullName() }}</td>
                    <td style="background-color:#f0f0f0; width:15%;">Fecha Nacimiento</td>
                    <td>{{ $client->fecha_nacimiento ? $client->fecha_nacimiento->format('d/m/Y') : '' }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">N° Expediente</td>
                    <td>{{ $client->expediente }}</td>
                    <td style="background-color:#f0f0f0;">Nacionalidad</td>
                    <td>Peruano</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">Dirección</td>
                    <td>{{ $client->direccion }}</td>
                    <td style="background-color:#f0f0f0;">Móvil</td>
                    <td>{{ $client->celular1 }} {{ $client->celular2 }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">Registrado el</td>
                    <td>{{ $client->fecha_registro ? $client->fecha_registro->format('d/m/Y') : '' }}</td>
                    <td style="background-color:#f0f0f0;">DNI</td>
                    <td>{{ $client->documento }}</td>
                </tr>
                <tr>
                    <td colspan="4" style="background-color:#e9ecef; font-weight:500; padding:6px 12px;">Historial de Pagos</td>
                </tr>
            </table>
        </div>
    </div>

    {{-- Tabla de créditos del cliente --}}
    <div class="card shadow-sm mt-2">
        <div class="card-body pb-2">
            <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1800px;">
                    <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                        <tr>
                            <th class="text-center">N°</th>
                            <th class="text-center">Usuario</th>
                            <th class="text-center">Codigo</th>
                            <th class="text-center">Cod. Ant.</th>
                            <th class="text-center">F. Crédito</th>
                            <th class="text-center">F. Vcto</th>
                            <th class="text-center">F. Pago</th>
                            <th class="text-center">F. Cancelado</th>
                            <th class="text-center">Capital</th>
                            <th class="text-center">%</th>
                            <th class="text-center">Interés</th>
                            <th class="text-center">C.</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Capital R.</th>
                            <th class="text-center">Interés G.</th>
                            <th class="text-center">Mora</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Saldo Capital</th>
                            <th class="text-center" style="color:red;">S/</th>
                            <th class="text-center" style="color:red;">MxD</th>
                            <th class="text-center" style="color:red;">Días</th>
                            <th class="text-center" style="color:red;">Gat.</th>
                            <th class="text-center">Asesor</th>
                            <th class="text-center">Op.</th>
                            <th class="text-center">Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $tdStyle = ($row['bg'] ? '--bs-table-bg:'.$row['bg'].'; --bs-table-striped-bg:'.$row['bg'].'; --bs-table-hover-bg:'.$row['bg'].'; background-color:'.$row['bg'].' !important;' : '') . ' color:'.$row['color'].' !important;';
                            @endphp
                            <tr style="{{ $tdStyle }}">
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['n'] }}</td>
                                <td style="{{ $tdStyle }}">{{ $row['usuario'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">
                                    @if($row['estado_activado'])
                                        <span class="badge bg-success" style="font-size:9px;">A</span>
                                    @else
                                        <span class="badge bg-secondary" style="font-size:9px;">D</span>
                                    @endif
                                    {{ $row['codigo'] }}
                                </td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['cod_ant'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_credito'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_vcto'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_pago'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_cancelado'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['interes_pct'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['interes'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['cuotas'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['total'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['capital_r'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['interes_g'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['mora'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['total_pag'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">
                                    @if(round($row['saldo_capital'], 2) != 0)
                                        {{ number_format($row['saldo_capital'], 2) }}
                                    @endif
                                </td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ $row['mora_s'] > 0 ? $row['mora_s'] : '' }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ $row['mxd'] > 0 ? $row['mxd'] : '' }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['dias'] > 0 ? $row['dias'] : '' }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ $row['gat'] > 0 ? number_format($row['gat'], 2) : '' }}</td>
                                <td style="{{ $tdStyle }}">{{ $row['asesor'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">
                                    <a href="{{ route('credits.schedule', $row['codigo']) }}" target="_blank" title="Ver cronograma">
                                        <i class="ti ti-edit f-s-14"></i>
                                    </a>
                                </td>
                                <td style="{{ $tdStyle }}" class="text-center">
                                    @if(!$row['estado_activado'])
                                        <a href="{{ route('payments.create', $row['codigo']) }}" target="_blank" title="Registrar pago">
                                            <i class="ti ti-edit f-s-14"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="25" class="text-center text-muted" style="padding:8px;">Sin créditos registrados</td>
                            </tr>
                        @endforelse

                        @if(count($rows) > 0)
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="3" class="text-center">Total</td>
                                <td colspan="5"></td>
                                <td class="text-end">{{ number_format($totals['capital'], 2) }}</td>
                                <td></td>
                                <td class="text-end">{{ number_format($totals['interes_t'], 2) }}</td>
                                <td></td>
                                <td class="text-end">{{ number_format($totals['total_a_pag'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['capital_r'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['interes_g'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['mora'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['total_pag'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['saldo_capital'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['mora_x_dia'], 2) }}</td>
                                <td colspan="6"></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
