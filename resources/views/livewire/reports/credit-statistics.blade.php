<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE ESTADISTICO CREDITO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Rep. Estad. Credito</span></li>
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
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        @foreach($months as $num => $name)
                                            <option value="{{ $num }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 110px;">
                                    <label class="form-label mb-0 small">Anio</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @foreach($years as $yr)
                                            <option value="{{ $yr }}">{{ $yr }}</option>
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
                                <div class="flex-shrink-0" style="width: 200px;">
                                    <label class="form-label mb-0 small">Asesor</label>
                                    <select class="form-select form-select-sm" wire:model="filterAdvisor">
                                        <option value="">Todos</option>
                                        @foreach($asesores as $key => $nombre)
                                            <option value="{{ $key }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
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

                    {{-- Table --}}
                    <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-sm table-hover" style="font-size: 0.8rem;">
                            <thead class="bg-primary text-white" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle" style="position: sticky; left: 0; z-index: 3; background: inherit; min-width: 90px;">Fecha</th>
                                    <th rowspan="2" class="text-center align-middle" style="min-width: 50px;">Dia</th>
                                    <th colspan="2" class="text-center" style="min-width: 130px;">Total</th>
                                    @foreach($rates as $rate)
                                        <th colspan="2" class="text-center" style="min-width: 130px;">{{ number_format($rate, 1) }}%</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="text-center">Capital</th>
                                    <th class="text-center">Interes</th>
                                    @foreach($rates as $rate)
                                        <th class="text-center">Cap</th>
                                        <th class="text-center">Int</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['rows'] as $row)
                                    <tr class="{{ $row['is_sunday'] ? 'table-danger' : '' }}">
                                        <td class="text-center fw-semibold" style="position: sticky; left: 0; z-index: 1; {{ $row['is_sunday'] ? 'background: #f8d7da;' : 'background: #fff;' }}">
                                            {{ $row['date'] }}
                                        </td>
                                        <td class="text-center">
                                            {{ ucfirst($row['day_name']) }}
                                        </td>
                                        <td class="text-end {{ $row['total_capital'] > 0 ? 'fw-semibold' : 'text-muted' }}">
                                            {{ $row['total_capital'] > 0 ? number_format($row['total_capital'], 2) : '-' }}
                                        </td>
                                        <td class="text-end {{ $row['total_interes'] > 0 ? '' : 'text-muted' }}">
                                            {{ $row['total_interes'] > 0 ? number_format($row['total_interes'], 2) : '-' }}
                                        </td>
                                        @foreach($rates as $rate)
                                            @php
                                                $cell = $row['rates'][$rate] ?? ['capital' => 0, 'interes' => 0];
                                            @endphp
                                            <td class="text-end {{ $cell['capital'] > 0 ? '' : 'text-muted' }}">
                                                {{ $cell['capital'] > 0 ? number_format($cell['capital'], 2) : '-' }}
                                            </td>
                                            <td class="text-end {{ $cell['interes'] > 0 ? '' : 'text-muted' }}">
                                                {{ $cell['interes'] > 0 ? number_format($cell['interes'], 2) : '-' }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-primary text-white fw-bold">
                                <tr>
                                    <td class="text-center" style="position: sticky; left: 0; z-index: 1; background: inherit;">TOTALES</td>
                                    <td class="text-center">{{ $report['grand_total_count'] }}</td>
                                    <td class="text-end">{{ number_format($report['grand_total_capital'], 2) }}</td>
                                    <td class="text-end">{{ number_format($report['grand_total_interes'], 2) }}</td>
                                    @foreach($rates as $rate)
                                        <td class="text-end">
                                            {{ $report['totals'][$rate]['capital'] > 0 ? number_format($report['totals'][$rate]['capital'], 2) : '-' }}
                                        </td>
                                        <td class="text-end">
                                            {{ $report['totals'][$rate]['interes'] > 0 ? number_format($report['totals'][$rate]['interes'], 2) : '-' }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
