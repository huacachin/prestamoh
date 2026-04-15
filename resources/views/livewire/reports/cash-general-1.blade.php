<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">REPORTE GENERAL CAJA 1</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Rep. General Caja 1</span></li>
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
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="month">
                                        <option value="1">Enero</option>
                                        <option value="2">Febrero</option>
                                        <option value="3">Marzo</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Mayo</option>
                                        <option value="6">Junio</option>
                                        <option value="7">Julio</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Septiembre</option>
                                        <option value="10">Octubre</option>
                                        <option value="11">Noviembre</option>
                                        <option value="12">Diciembre</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 120px;">
                                    <label class="form-label mb-0 small">Anio</label>
                                    <select class="form-select form-select-sm" wire:model="year">
                                        @for($y = date('Y') - 5; $y <= date('Y') + 2; $y++)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Tipo</label>
                                    <select class="form-select form-select-sm" wire:model="filterTipo">
                                        <option value="">Todos</option>
                                        <option value="4">Diario</option>
                                        <option value="1">Semanal</option>
                                        <option value="3">Mensual</option>
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <button class="btn btn-sm btn-secondary flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="printme">
                        <div class="table-responsive" style="max-height: 650px; overflow: auto;">
                            <table class="table table-bordered table-striped table-hover table-sm" style="min-width: 1400px;">
                                <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                    <tr>
                                        <th rowspan="3" class="align-middle text-center">N&deg;</th>
                                        <th colspan="8" class="text-center">INGRESOS</th>
                                        <th rowspan="3" class="align-middle text-center">ASES.</th>
                                        <th rowspan="3" class="align-middle text-center">T.C.</th>
                                        <th colspan="3" class="text-center">EGRESOS</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="2" class="align-middle text-center">CDG</th>
                                        <th rowspan="2" class="align-middle text-center">CLIENTE</th>
                                        <th rowspan="2" class="align-middle text-center">DETALLE</th>
                                        <th rowspan="2" class="align-middle text-center">N&deg; CUOTAS</th>
                                        <th colspan="4" class="text-center">CUOTAS</th>
                                        <th rowspan="2" class="align-middle text-center">CDG</th>
                                        <th rowspan="2" class="align-middle text-center">CLIENTE</th>
                                        <th rowspan="2" class="align-middle text-center">MONTO</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">TOTAL</th>
                                        <th class="text-center">CAPITAL</th>
                                        <th class="text-center">INTERES</th>
                                        <th class="text-center">MORA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($report['days'] as $day)
                                        <tr style="background-color: #B0B0B0;">
                                            <td colspan="14"><strong>{{ $day['date_label'] }}</strong></td>
                                        </tr>
                                        @php
                                            $maxRows = max(count($day['ingresos']), count($day['egresos']));
                                        @endphp
                                        @for($i = 0; $i < $maxRows; $i++)
                                            @php
                                                $ingreso = $day['ingresos'][$i] ?? null;
                                                $egreso  = $day['egresos'][$i] ?? null;
                                                $rowStyle = '';
                                                if ($ingreso && in_array($ingreso['tipo_planilla'], [1, 3])) {
                                                    $rowStyle = 'color: red;';
                                                }
                                            @endphp
                                            <tr style="{{ $rowStyle }}">
                                                <td><strong>{{ $i + 1 }}</strong></td>
                                                {{-- INGRESOS --}}
                                                @if($ingreso)
                                                    <td>{{ $ingreso['credit_id'] }}</td>
                                                    <td>{{ $ingreso['cliente'] }}</td>
                                                    <td>{{ $ingreso['detalle'] }}</td>
                                                    <td class="text-center">{{ $ingreso['nro_cuotas'] }}</td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ingreso['total'], 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ingreso['capital'], 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ingreso['interes'], 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ingreso['mora'], 2) }}</span></td>
                                                    <td>{{ $ingreso['asesor'] }}</td>
                                                    <td class="text-center">
                                                        @if($ingreso['tipo_planilla'] == 4) D
                                                        @elseif($ingreso['tipo_planilla'] == 1) S
                                                        @elseif($ingreso['tipo_planilla'] == 3) M
                                                        @endif
                                                    </td>
                                                @else
                                                    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                                                @endif
                                                {{-- EGRESOS --}}
                                                @if($egreso)
                                                    <td>{{ $egreso['credit_id'] }}</td>
                                                    <td>{{ $egreso['cliente'] }}</td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($egreso['monto'], 2) }}</span></td>
                                                @else
                                                    <td></td><td></td><td></td>
                                                @endif
                                            </tr>
                                        @endfor
                                        {{-- Daily subtotal --}}
                                        <tr class="table-secondary">
                                            <td></td>
                                            <td colspan="4"><strong>SUB TOTAL</strong></td>
                                            <td class="text-end"><strong>{{ number_format($day['subtotal_ingresos'], 2) }}</strong></td>
                                            <td class="text-end"><strong>{{ number_format($day['subtotal_capital'], 2) }}</strong></td>
                                            <td class="text-end"><strong>{{ number_format($day['subtotal_interes'], 2) }}</strong></td>
                                            <td class="text-end"><strong>{{ number_format($day['subtotal_mora'], 2) }}</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-end"><strong>{{ number_format($day['subtotal_egresos'], 2) }}</strong></td>
                                        </tr>
                                        <tr style="background-color: #CEE7FF;">
                                            <td></td>
                                            <td colspan="4"><strong>TOTAL</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-center"><strong>{{ number_format($day['subtotal_ingresos'] + $day['subtotal_mora'], 2) }}</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="14" class="py-3 text-muted text-center">Sin movimientos para el periodo seleccionado</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(count($report['days']) > 0)
                                    <tfoot class="bg-primary" style="position: sticky; bottom: 0; z-index: 2;">
                                        <tr>
                                            <td></td>
                                            <td colspan="4"><strong>Sub Total General</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($report['grand_total_ingresos'], 2) }}</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($report['grand_total_capital'], 2) }}</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($report['grand_total_interes'], 2) }}</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($report['grand_total_mora'], 2) }}</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-end"><strong>{{ number_format($report['grand_total_egresos'], 2) }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td colspan="5">
                                                <strong>REPORTE GENERAL <span class="text-danger">CAJA 1 - </span>TOTAL <span class="text-danger">GENERAL</span></strong>
                                            </td>
                                            <td class="text-end"><strong class="text-danger">{{ number_format($report['grand_total_ingresos'] + $report['grand_total_mora'], 2) }}</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-end"><strong class="text-danger">{{ number_format($report['grand_total_egresos'], 2) }}</strong></td>
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
</div>
