<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE ESTADISTICO DE CREDITOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Estadistico de Credito</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model.live="selemes">
                                        <option value="0000">Seleccione</option>
                                        <option value="01">Enero</option>
                                        <option value="02">Febrero</option>
                                        <option value="03">Marzo</option>
                                        <option value="04">Abril</option>
                                        <option value="05">Mayo</option>
                                        <option value="06">Junio</option>
                                        <option value="07">Julio</option>
                                        <option value="08">Agosto</option>
                                        <option value="09">Septiembre</option>
                                        <option value="10">Octubre</option>
                                        <option value="11">Noviembre</option>
                                        <option value="12">Diciembre</option>
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
                                        <option value="0000">Todos</option>
                                        <option value="1">Semanal</option>
                                        <option value="3">Mensual</option>
                                        <option value="4">Diario</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 200px;">
                                    <label class="form-label mb-0 small">Asesor</label>
                                    <select class="form-select form-select-sm" wire:model.live="nomasesores">
                                        <option value="Todos">Todos</option>
                                        @foreach($asesores as $key => $nombre)
                                            <option value="{{ $key }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Consultar
                                </button>
                                <button class="btn btn-sm btn-success flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- TABLA DIARIA del mes seleccionado --}}
                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1500px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle">Fecha</th>
                                    <th rowspan="2" class="text-center align-middle">Ingresos Creditos</th>
                                    <th rowspan="2" class="text-center align-middle">Egresos Capital</th>
                                    @foreach($rates as $rate)
                                        <th colspan="2" class="text-center">{{ $rate }}%</th>
                                    @endforeach
                                    <th rowspan="2" class="text-center align-middle">TOTAL</th>
                                </tr>
                                <tr>
                                    @foreach($rates as $rate)
                                        <th class="text-center">Cap.</th>
                                        <th class="text-center">Int.</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dailyRows as $row)
                                    <tr>
                                        <td style="{{ $row['is_sunday'] ? 'background-color:red; color:white;' : '' }}">
                                            {{ $row['fecha'] }}
                                        </td>
                                        <td class="text-end">{{ $row['ingresos'] != 0 ? rtrim(rtrim(number_format($row['ingresos'], 2, '.', ''), '0'), '.') : '' }}</td>
                                        <td class="text-end">{{ $row['egresos'] != 0 ? rtrim(rtrim(number_format($row['egresos'], 2, '.', ''), '0'), '.') : '' }}</td>
                                        @foreach($rates as $rate)
                                            @php
                                                $cell = $row['rates'][(string) $rate];
                                            @endphp
                                            <td class="text-end">{{ $cell['cap'] != 0 ? rtrim(rtrim(number_format($cell['cap'], 2, '.', ''), '0'), '.') : '' }}</td>
                                            <td class="text-end" style="color:red;">
                                                {{ $cell['int'] != 0 ? number_format($cell['int'], 2) : '' }}
                                            </td>
                                        @endforeach
                                        <td class="text-end" style="color:red;">
                                            {{ number_format($row['total_int'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                {{-- Totales --}}
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td>Total</td>
                                    <td class="text-end">{{ number_format($dailyTotals['ingresos'], 2) }}</td>
                                    <td class="text-end">{{ number_format($dailyTotals['egresos'], 2) }}</td>
                                    @foreach($rates as $rate)
                                        <td class="text-end">{{ number_format($dailyTotals['rates_cap'][(string) $rate], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($dailyTotals['rates_int'][(string) $rate], 2) }}</td>
                                    @endforeach
                                    <td class="text-end" style="color:red;">{{ number_format($dailyTotals['total_inter'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <br>

                    {{-- TABLA MENSUAL del año --}}
                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1500px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle">Fecha</th>
                                    <th rowspan="2" class="text-center align-middle">Ingresos Creditos</th>
                                    <th rowspan="2" class="text-center align-middle">Egresos Capital</th>
                                    @foreach($rates as $rate)
                                        <th colspan="2" class="text-center">{{ $rate }}%</th>
                                    @endforeach
                                    <th rowspan="2" class="text-center align-middle">TOTAL</th>
                                </tr>
                                <tr>
                                    @foreach($rates as $rate)
                                        <th class="text-center">Cap.</th>
                                        <th class="text-center">Int.</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyRows as $row)
                                    <tr>
                                        <td>{{ $row['mes_label'] }}</td>
                                        <td class="text-end">{{ $row['ingresos'] != 0 ? rtrim(rtrim(number_format($row['ingresos'], 2, '.', ''), '0'), '.') : '' }}</td>
                                        <td class="text-end">{{ $row['egresos'] != 0 ? rtrim(rtrim(number_format($row['egresos'], 2, '.', ''), '0'), '.') : '' }}</td>
                                        @foreach($rates as $rate)
                                            @php
                                                $cell = $row['rates'][(string) $rate];
                                            @endphp
                                            <td class="text-end">{{ $cell['cap'] != 0 ? rtrim(rtrim(number_format($cell['cap'], 2, '.', ''), '0'), '.') : '' }}</td>
                                            <td class="text-end" style="color:red;">
                                                {{ $cell['int'] != 0 ? number_format($cell['int'], 2) : '' }}
                                            </td>
                                        @endforeach
                                        <td class="text-end" style="color:red;">
                                            {{ number_format($row['total_int'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td>Total</td>
                                    <td class="text-end">{{ number_format($monthlyTotals['ingresos'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyTotals['egresos'], 2) }}</td>
                                    @foreach($rates as $rate)
                                        <td class="text-end">{{ number_format($monthlyTotals['rates_cap'][(string) $rate], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($monthlyTotals['rates_int'][(string) $rate], 2) }}</td>
                                    @endforeach
                                    <td class="text-end" style="color:red;">{{ number_format($monthlyTotals['total_inter'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
