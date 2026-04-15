<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE DE ASESOR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Reporte de Asesor</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filters --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 overflow-auto py-1">
                                <div class="flex-shrink-0" style="width: 220px;">
                                    <label class="form-label mb-0 small">Asesor</label>
                                    <select class="form-select form-select-sm" wire:model="advisorId">
                                        <option value="">-- Todos --</option>
                                        @foreach($advisors as $advisor)
                                            <option value="{{ $advisor->id }}">{{ $advisor->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 120px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @for($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }} - {{ \Carbon\Carbon::create()->month($m)->isoFormat('MMMM') }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 100px;">
                                    <label class="form-label mb-0 small">Anio</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @for($y = 2020; $y <= 2030; $y++)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <button class="btn btn-sm btn-outline-secondary flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Report Table --}}
                    <div class="table-responsive tableFixHead mt-2">
                        <table class="table table-bordered table-hover table-sm" style="font-size: 0.85rem;">
                            <thead class="bg-primary text-white">
                                <tr class="text-center">
                                    <th rowspan="2" class="align-middle" style="width:40px;">N&deg;</th>
                                    <th rowspan="2" class="align-middle" style="width:95px;">Fecha</th>
                                    <th rowspan="2" class="align-middle" style="width:50px;">Dia</th>
                                    <th rowspan="2" class="align-middle">Nuevos</th>
                                    <th rowspan="2" class="align-middle">Renov.</th>
                                    <th rowspan="2" class="align-middle">Canc.</th>
                                    <th rowspan="2" class="align-middle">Total</th>
                                    <th rowspan="2" class="align-middle">Capital</th>
                                    <th colspan="2" class="text-center">Cobrados</th>
                                    <th colspan="2" class="text-center">No Cobrados</th>
                                </tr>
                                <tr class="text-center">
                                    <th>Cant.</th>
                                    <th>Importe</th>
                                    <th>Cant.</th>
                                    <th>Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reportData as $row)
                                    <tr class="text-center
                                        @if($row->day_of_week === 0) table-danger
                                        @elseif($row->day_of_week === 6) table-success
                                        @endif
                                    ">
                                        <td>{{ $row->num }}</td>
                                        <td class="text-nowrap">{{ $row->fecha }}</td>
                                        <td>{{ ucfirst($row->dia) }}</td>
                                        <td>{{ $row->nuevos ?: '' }}</td>
                                        <td>{{ $row->renovaciones ?: '' }}</td>
                                        <td>{{ $row->cancelaciones ?: '' }}</td>
                                        <td><strong>{{ $row->total_creditos ?: '' }}</strong></td>
                                        <td class="text-end">{{ $row->capital > 0 ? number_format($row->capital, 2) : '' }}</td>
                                        <td>{{ $row->cobrados_cant ?: '' }}</td>
                                        <td class="text-end">{{ $row->cobrados_importe > 0 ? number_format($row->cobrados_importe, 2) : '' }}</td>
                                        <td>{{ $row->no_cobrados_cant ?: '' }}</td>
                                        <td class="text-end">{{ $row->no_cobrados_importe > 0 ? number_format($row->no_cobrados_importe, 2) : '' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="text-center text-muted py-3">Sin datos para el periodo seleccionado</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($reportData) > 0)
                            <tfoot>
                                <tr class="text-center fw-bold table-dark">
                                    <td colspan="3">TOTALES</td>
                                    <td>{{ $totals->nuevos }}</td>
                                    <td>{{ $totals->renovaciones }}</td>
                                    <td>{{ $totals->cancelaciones }}</td>
                                    <td>{{ $totals->total_creditos }}</td>
                                    <td class="text-end">{{ number_format($totals->capital, 2) }}</td>
                                    <td>{{ $totals->cobrados_cant }}</td>
                                    <td class="text-end">{{ number_format($totals->cobrados_importe, 2) }}</td>
                                    <td>{{ $totals->no_cobrados_cant }}</td>
                                    <td class="text-end">{{ number_format($totals->no_cobrados_importe, 2) }}</td>
                                </tr>
                                <tr class="text-center fw-bold table-secondary">
                                    <td colspan="3">PROMEDIO / DIA</td>
                                    <td>{{ $averages->nuevos }}</td>
                                    <td>{{ $averages->renovaciones }}</td>
                                    <td>{{ $averages->cancelaciones }}</td>
                                    <td>{{ $averages->total_creditos }}</td>
                                    <td class="text-end">{{ number_format($averages->capital, 2) }}</td>
                                    <td>{{ $averages->cobrados_cant }}</td>
                                    <td class="text-end">{{ number_format($averages->cobrados_importe, 2) }}</td>
                                    <td>{{ $averages->no_cobrados_cant }}</td>
                                    <td class="text-end">{{ number_format($averages->no_cobrados_importe, 2) }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
