<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE ESTADISTICO DE CREDITO MENSUAL - SEMANAL - DIARIO - DEL MES SIGUIENTE</h4>
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
                                {{-- Toggle columnas por tasa --}}
                                <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="Columnas por tasa">
                                    <input type="radio" class="btn-check" id="vm-ambos" value="ambos" wire:model.live="viewMode">
                                    <label class="btn btn-outline-dark" for="vm-ambos">Cap. + Int.</label>
                                    <input type="radio" class="btn-check" id="vm-cap" value="cap" wire:model.live="viewMode">
                                    <label class="btn btn-outline-dark" for="vm-cap">Solo Cap.</label>
                                    <input type="radio" class="btn-check" id="vm-int" value="int" wire:model.live="viewMode">
                                    <label class="btn btn-outline-dark" for="vm-int">Solo Int.</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i>
                                </button>
                                <x-scroll-bottom-btn class="flex-shrink-0" scrollable="#tabla-cred-1" />
                            </div>
                        </div>
                    </div>

                    {{-- KPIs del mes seleccionado --}}
                    @php $mesLabel = $months[$selemes] ?? ''; @endphp
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100 bg-light">
                                <div class="small text-muted text-uppercase">Capital Colocado <span class="text-lowercase">({{ $mesLabel }})</span></div>
                                <div class="f-s-18 fw-bold">S/ {{ number_format($dailyTotals['egresos'], 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100 bg-light">
                                <div class="small text-muted text-uppercase">Interés del Mes <span class="text-lowercase">(cronograma)</span></div>
                                <div class="f-s-18 fw-bold" style="color:red;">S/ {{ number_format($dailyTotals['total_inter'], 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100 bg-light">
                                <div class="small text-muted text-uppercase">Ingresos Créditos <span class="text-lowercase">({{ $mesLabel }})</span></div>
                                <div class="f-s-18 fw-bold">S/ {{ number_format($dailyTotals['ingresos'], 2) }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100 bg-light">
                                <div class="small text-muted text-uppercase">Créditos Otorgados</div>
                                <div class="f-s-18 fw-bold">{{ number_format($dailyTotals['creditos']) }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- TABLA DIARIA del mes seleccionado --}}
                    <div id="tabla-cred-1" class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover table-nowrap table-sticky-first">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle">Fecha</th>
                                    <th rowspan="2" class="text-center align-middle">Ingresos Creditos</th>
                                    <th rowspan="2" class="text-center align-middle">Egresos Capital</th>
                                    @foreach($dailyRates as $rate)
                                        <th colspan="{{ $viewMode === 'ambos' ? 2 : 1 }}" class="text-center">{{ $rate }}%</th>
                                    @endforeach
                                    <th rowspan="2" class="text-center align-middle">TOTAL</th>
                                </tr>
                                <tr>
                                    @foreach($dailyRates as $rate)
                                        @if($viewMode !== 'int')
                                            <th class="text-center">Cap.</th>
                                        @endif
                                        @if($viewMode !== 'cap')
                                            <th class="text-center">Int.</th>
                                        @endif
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
                                        @foreach($dailyRates as $rate)
                                            @php
                                                $cell = $row['rates'][(string) $rate];
                                            @endphp
                                            @if($viewMode !== 'int')
                                                <td class="text-end">{{ $cell['cap'] != 0 ? rtrim(rtrim(number_format($cell['cap'], 2, '.', ''), '0'), '.') : '' }}</td>
                                            @endif
                                            @if($viewMode !== 'cap')
                                                <td class="text-end" style="color:red; font-weight:bold;">
                                                    {{ $cell['int'] != 0 ? number_format($cell['int'], 2) : '' }}
                                                </td>
                                            @endif
                                        @endforeach
                                        <td class="text-end" style="color:red; font-weight:bold;">
                                            {{ number_format($row['total_int'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                {{-- Totales --}}
                                <tr class="row-total" style="font-weight:500;">
                                    <td>Total</td>
                                    <td class="text-end">{{ number_format($dailyTotals['ingresos'], 2) }}</td>
                                    <td class="text-end">{{ number_format($dailyTotals['egresos'], 2) }}</td>
                                    @foreach($dailyRates as $rate)
                                        @if($viewMode !== 'int')
                                            <td class="text-end">{{ number_format($dailyTotals['rates_cap'][(string) $rate], 2) }}</td>
                                        @endif
                                        @if($viewMode !== 'cap')
                                            <td class="text-end" style="color:red; font-weight:bold;">{{ number_format($dailyTotals['rates_int'][(string) $rate], 2) }}</td>
                                        @endif
                                    @endforeach
                                    <td class="text-end" style="color:red; font-weight:bold;">{{ number_format($dailyTotals['total_inter'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <br>

                    {{-- TABLA MENSUAL del año --}}
                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover table-nowrap table-sticky-first">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle">Fecha</th>
                                    <th rowspan="2" class="text-center align-middle">Ingresos Creditos</th>
                                    <th rowspan="2" class="text-center align-middle">Egresos Capital</th>
                                    @foreach($monthlyRates as $rate)
                                        <th colspan="{{ $viewMode === 'ambos' ? 2 : 1 }}" class="text-center">{{ $rate }}%</th>
                                    @endforeach
                                    <th rowspan="2" class="text-center align-middle">TOTAL</th>
                                </tr>
                                <tr>
                                    @foreach($monthlyRates as $rate)
                                        @if($viewMode !== 'int')
                                            <th class="text-center">Cap.</th>
                                        @endif
                                        @if($viewMode !== 'cap')
                                            <th class="text-center">Int.</th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyRows as $row)
                                    <tr>
                                        <td>{{ $row['mes_label'] }}</td>
                                        <td class="text-end">{{ $row['ingresos'] != 0 ? rtrim(rtrim(number_format($row['ingresos'], 2, '.', ''), '0'), '.') : '' }}</td>
                                        <td class="text-end">{{ $row['egresos'] != 0 ? rtrim(rtrim(number_format($row['egresos'], 2, '.', ''), '0'), '.') : '' }}</td>
                                        @foreach($monthlyRates as $rate)
                                            @php
                                                $cell = $row['rates'][(string) $rate];
                                            @endphp
                                            @if($viewMode !== 'int')
                                                <td class="text-end">{{ $cell['cap'] != 0 ? rtrim(rtrim(number_format($cell['cap'], 2, '.', ''), '0'), '.') : '' }}</td>
                                            @endif
                                            @if($viewMode !== 'cap')
                                                <td class="text-end" style="color:red; font-weight:bold;">
                                                    {{ $cell['int'] != 0 ? number_format($cell['int'], 2) : '' }}
                                                </td>
                                            @endif
                                        @endforeach
                                        <td class="text-end" style="color:red; font-weight:bold;">
                                            {{ number_format($row['total_int'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr class="row-total" style="font-weight:500;">
                                    <td>Total</td>
                                    <td class="text-end">{{ number_format($monthlyTotals['ingresos'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyTotals['egresos'], 2) }}</td>
                                    @foreach($monthlyRates as $rate)
                                        @if($viewMode !== 'int')
                                            <td class="text-end">{{ number_format($monthlyTotals['rates_cap'][(string) $rate], 2) }}</td>
                                        @endif
                                        @if($viewMode !== 'cap')
                                            <td class="text-end" style="color:red; font-weight:bold;">{{ number_format($monthlyTotals['rates_int'][(string) $rate], 2) }}</td>
                                        @endif
                                    @endforeach
                                    <td class="text-end" style="color:red; font-weight:bold;">{{ number_format($monthlyTotals['total_inter'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Columna "Fecha" fija al hacer scroll horizontal */
        .table-sticky-first th:first-child,
        .table-sticky-first td:first-child {
            position: sticky;
            left: 0;
            z-index: 1;
            background-color: var(--bs-body-bg, #fff);
        }

        /* Esquina superior izquierda: fija arriba y a la izquierda */
        .table-sticky-first thead th:first-child {
            z-index: 3;
            background-color: rgb(var(--bs-primary-rgb, 13, 110, 253));
        }

        /* Fila de totales fija al hacer scroll vertical */
        .table-sticky-first .row-total td {
            position: sticky;
            bottom: 0;
            z-index: 2;
            background-color: #f0f0f0;
        }

        /* Esquina inferior izquierda: fija abajo y a la izquierda */
        .table-sticky-first .row-total td:first-child {
            left: 0;
            z-index: 3;
        }
    </style>
</div>
