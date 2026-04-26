<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">RESUMEN DE CANCELADOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Resumen de Cancelados</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros principales --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model.live="selemes">
                                        <option value="00">Seleccione</option>
                                        @foreach($months as $key => $nombre)
                                            <option value="{{ $key }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 110px;">
                                    <label class="form-label mb-0 small">Año</label>
                                    <select class="form-select form-select-sm" wire:model.live="selecano">
                                        <option value="0000">Seleccione</option>
                                        @for($y = 2015; $y <= 2028; $y++)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 130px;">
                                    <label class="form-label mb-0 small">Tipo</label>
                                    <select class="form-select form-select-sm" wire:model.live="seletipl">
                                        <option value="">Seleccione</option>
                                        <option value="1">Semanal</option>
                                        <option value="3">Mensual</option>
                                        <option value="4">Diario</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 130px;">
                                    <label class="form-label mb-0 small">Intereses %</label>
                                    <select class="form-select form-select-sm" wire:model.live="intereses">
                                        <option value="">Seleccione</option>
                                        <option value="0">0</option>
                                        <option value="3">3</option>
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="12">12</option>
                                        <option value="15">15</option>
                                        <option value="20">20</option>
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <button class="btn btn-sm btn-success flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Filtros secundarios --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 110px;">
                                    <label class="form-label mb-0 small">Exp</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="exp">
                                </div>
                                <div class="flex-shrink-0" style="width: 110px;">
                                    <label class="form-label mb-0 small">Codigo</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="codigo">
                                </div>
                                <div class="flex-shrink-0" style="width: 130px;">
                                    <label class="form-label mb-0 small">Dni</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="cdni">
                                </div>
                                <div class="flex-shrink-0" style="width: 220px;">
                                    <label class="form-label mb-0 small">Nombre</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="cnombre">
                                </div>
                                <div class="flex-shrink-0" style="width: 180px;">
                                    <label class="form-label mb-0 small">Asesor</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="casesor">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tabla principal --}}
                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1500px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" colspan="2" class="text-center">N°</th>
                                    <th rowspan="2" class="text-center">Exp</th>
                                    <th rowspan="2">Codigo</th>
                                    <th rowspan="2">Dni</th>
                                    <th rowspan="2" style="min-width: 200px;">Nombre</th>
                                    <th colspan="3" class="text-center">Capital</th>
                                    <th colspan="4" class="text-center">Interes Ganado</th>
                                    <th rowspan="2" class="text-center">Total</th>
                                    <th colspan="3" class="text-center">Mora x Cob.</th>
                                    <th rowspan="2">Fec/Cred</th>
                                    <th rowspan="2">Fec/Venc</th>
                                    <th rowspan="2">Fecha Cancelado</th>
                                    <th rowspan="2" class="text-center">Estado</th>
                                    <th rowspan="2" class="text-center">Ases.</th>
                                </tr>
                                <tr>
                                    <th>Capital</th>
                                    <th>R./ Capital</th>
                                    <th>Capital Neto</th>
                                    <th>Detalles</th>
                                    <th>%</th>
                                    <th>S/</th>
                                    <th>Mora</th>
                                    <th>S/</th>
                                    <th>MxD</th>
                                    <th>Dias</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr style="{{ $row['bg'] }} color: {{ $row['color_texto'] }};">
                                        <td class="text-center">{{ $row['n'] }}</td>
                                        <td class="text-center" style="color: {{ $row['st_color'] }};">{{ $row['tot2'] }}</td>
                                        <td>{{ $row['exp'] }}</td>
                                        <td class="text-center">
                                            <a href="#" style="color: inherit;">{{ $row['codigo'] }}</a>
                                        </td>
                                        <td>{{ $row['dni'] }}</td>
                                        <td>
                                            {{ $row['nombre'] }}
                                            @if($row['cod_rem'])
                                                <font color="red">{{ $row['cod_rem'] }}</font>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['r_capital'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['capital_neto'], 2) }}</td>
                                        <td>{!! $row['detalles'] !!}</td>
                                        <td class="text-end">
                                            @if(intval($row['interes_pct']) == floatval($row['interes_pct']))
                                                {{ intval($row['interes_pct']) }}
                                            @else
                                                {{ $row['interes_pct'] }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $row['interes_s'] }}</td>
                                        <td class="text-end">{{ $row['mora'] }}</td>
                                        <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                                        <td class="text-end">{{ $row['mxd'] > 0 ? $row['mxd'] : '' }}</td>
                                        <td class="text-end">{{ $row['mora_s'] > 0 ? $row['mora_s'] : '' }}</td>
                                        <td class="text-end">{{ $row['dias'] }}</td>
                                        <td>{{ $row['fec_cred'] }}</td>
                                        <td>{{ $row['fec_venc'] }}</td>
                                        <td>{{ $row['fec_cancel'] }}</td>
                                        <td>{{ $row['estado'] }}</td>
                                        <td>{{ $row['asesor'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="22" class="text-center py-4 text-muted">
                                            No se encontraron creditos cancelados para el periodo seleccionado
                                        </td>
                                    </tr>
                                @endforelse

                                {{-- Total General --}}
                                <tr style="background-color: #f0f0f0; font-weight:500;">
                                    <td rowspan="2"></td>
                                    <td colspan="5" rowspan="2">Total General</td>
                                    <td class="text-end">{{ number_format($totals['cancecapi'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['canceinteg'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['todf1'], 2) }}</td>
                                    <td colspan="2"></td>
                                    <td class="text-end">{{ number_format($totals['canceinte'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['cancemora'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['totGP'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['montomorxdia'], 2) }}</td>
                                    <td colspan="7"></td>
                                </tr>
                                <tr style="background-color: #f0f0f0; font-weight:500;">
                                    <td colspan="5"></td>
                                    <td colspan="2" class="text-end">{{ number_format($totals['distribution_base'], 2) }}</td>
                                    <td colspan="10"></td>
                                </tr>

                                {{-- Distribucion --}}
                                <tr>
                                    <th colspan="3" class="text-center" style="background-color:#2874A6; color:white;">Detalle</th>
                                    <th class="text-center" style="background-color:#2874A6; color:white;">%</th>
                                    <th class="text-center" style="background-color:#2874A6; color:white;">S/</th>
                                    <th class="text-center" style="background-color:#2874A6; color:white;">%</th>
                                    <th class="text-center" style="background-color:#2874A6; color:white;">S/</th>
                                    <th class="text-center" style="background-color:#2874A6; color:white;">%</th>
                                    <th class="text-center" style="background-color:#2874A6; color:white;">S/</th>
                                    <th colspan="13"></th>
                                </tr>
                                @foreach($distribution as $dist)
                                    <tr>
                                        <td colspan="3">{{ $dist['label'] }}</td>
                                        <td class="text-center">{{ $dist['pct1'] }}</td>
                                        <td class="text-end">{{ number_format($dist['val1'], 2) }}</td>
                                        <td class="text-center">{{ $dist['pct2'] }}</td>
                                        <td class="text-end">{{ number_format($dist['val2'], 2) }}</td>
                                        <td class="text-center">{{ $dist['pct3'] }}</td>
                                        <td class="text-end">{{ number_format($dist['val3'], 2) }}</td>
                                        <td colspan="13"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
