<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">RESUMEN DE CANCELADOS</h4>
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
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $key => $nombre)
                                            <option value="{{ $key }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 110px;">
                                    <label class="form-label mb-0 small">Ano</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @foreach($years as $y)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Tipo</label>
                                    <select class="form-select form-select-sm" wire:model="filterTipo">
                                        <option value="">Todos</option>
                                        <option value="1">Semanal</option>
                                        <option value="3">Mensual</option>
                                        <option value="4">Diario</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 120px;">
                                    <label class="form-label mb-0 small">Interes %</label>
                                    <select class="form-select form-select-sm" wire:model="filterInteres">
                                        <option value="">Todos</option>
                                        @foreach($interesRates as $rate)
                                            <option value="{{ $rate }}">{{ $rate }}%</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 200px;">
                                    <label class="form-label mb-0 small">Buscar</label>
                                    <input type="text" class="form-control form-control-sm"
                                           wire:model="search"
                                           placeholder="Codigo, DNI, Nombre, Asesor...">
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="$refresh">
                                    <i class="ti ti-search f-s-12"></i> Filtrar
                                </button>
                                <button class="btn btn-sm btn-outline-secondary flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary">
                            <tr>
                                <th>N</th>
                                <th>Codigo</th>
                                <th>Cliente</th>
                                <th>DNI</th>
                                <th>Capital</th>
                                <th>Cap. Pagado</th>
                                <th>Cap. Neto</th>
                                <th>Int. %</th>
                                <th>Int. S/</th>
                                <th>Mora S/</th>
                                <th>Total</th>
                                <th>Fecha Credito</th>
                                <th>Fecha Cancel.</th>
                                <th>Asesor</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($data as $index => $row)
                                @php
                                    $rowClass = '';
                                    if ($row->capital_neto <= 0) {
                                        $rowClass = 'table-success';
                                    } elseif ($row->capital_pagado > 0 && $row->capital_neto > 0) {
                                        $rowClass = 'table-warning';
                                    } elseif ($row->capital_neto > 0) {
                                        $rowClass = 'table-danger';
                                    }
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->codigo }}</td>
                                    <td>{{ $row->cliente }}</td>
                                    <td>{{ $row->documento }}</td>
                                    <td class="text-end">{{ number_format($row->capital, 2) }}</td>
                                    <td class="text-end">{{ number_format($row->capital_pagado, 2) }}</td>
                                    <td class="text-end">{{ number_format($row->capital_neto, 2) }}</td>
                                    <td class="text-center">{{ $row->interes_pct }}%</td>
                                    <td class="text-end">{{ number_format($row->interes_monto, 2) }}</td>
                                    <td class="text-end">{{ number_format($row->mora_monto, 2) }}</td>
                                    <td class="text-end">{{ number_format($row->total_pagado, 2) }}</td>
                                    <td>{{ $row->fecha_credito?->format('d/m/Y') }}</td>
                                    <td>{{ $row->fecha_cancelacion?->format('d/m/Y') }}</td>
                                    <td>{{ $row->asesor }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="py-4 text-muted text-center">No se encontraron creditos cancelados para el periodo seleccionado</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                            <tr>
                                <td></td>
                                <td></td>
                                <td class="text-start fw-bold">TOTALES ({{ $data->count() }})</td>
                                <td></td>
                                <td class="text-end fw-bold">{{ number_format($totals->capital, 2) }}</td>
                                <td class="text-end fw-bold">{{ number_format($totals->capital_pagado, 2) }}</td>
                                <td class="text-end fw-bold">{{ number_format($totals->capital_neto, 2) }}</td>
                                <td></td>
                                <td class="text-end fw-bold">{{ number_format($totals->interes_monto, 2) }}</td>
                                <td class="text-end fw-bold">{{ number_format($totals->mora_monto, 2) }}</td>
                                <td class="text-end fw-bold">{{ number_format($totals->total_pagado, 2) }}</td>
                                <td colspan="3"></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
