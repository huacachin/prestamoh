<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE GENERAL CAJA 2</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Rep. General Caja 2</span></li>
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
                            <table class="table table-bordered table-striped table-hover table-sm">
                                <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                    <tr>
                                        <th class="text-center">N&deg;</th>
                                        <th class="text-center">FECHA</th>
                                        <th class="text-center">DATOS DEL CLIENTE</th>
                                        <th class="text-center">DETALLES</th>
                                        <th class="text-center">INGRESO</th>
                                        <th class="text-center">EGRESO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $runningIngreso = 0; $runningEgreso = 0; @endphp
                                    @forelse($report['days'] as $day)
                                        @foreach($day['items'] as $item)
                                            <tr>
                                                <td><strong>{{ $item['n'] }}</strong></td>
                                                <td><strong>{{ $item['fecha'] }}</strong></td>
                                                <td class="text-start">{{ $item['cliente'] }}</td>
                                                <td class="text-start">{{ $item['detalle'] }}</td>
                                                <td class="text-end">
                                                    @if($item['ingreso'] > 0)
                                                        <span class="text-primary">{{ number_format($item['ingreso'], 2) }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    @if($item['egreso'] > 0)
                                                        <span class="text-danger">{{ number_format($item['egreso'], 2) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        {{-- Daily total --}}
                                        <tr class="table-secondary">
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td><strong>TOTAL</strong></td>
                                            <td class="text-end">
                                                <strong><span class="text-primary">{{ number_format($day['total_ingreso'], 2) }}</span></strong>
                                            </td>
                                            <td class="text-end">
                                                <strong><span class="text-danger">{{ number_format($day['total_egreso'], 2) }}</span></strong>
                                            </td>
                                        </tr>
                                        <tr class="table-light">
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                            <td><strong>SALDO <span class="text-danger">FINAL-INICIAL</span></strong></td>
                                            <td class="text-end">
                                                <strong><span class="text-primary">{{ number_format($day['saldo'], 2) }}</span></strong>
                                            </td>
                                            <td></td>
                                        </tr>
                                        @php
                                            $runningIngreso += $day['total_ingreso'];
                                            $runningEgreso  += $day['total_egreso'];
                                        @endphp
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-3 text-muted text-center">Sin movimientos para el periodo seleccionado</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                @if(count($report['days']) > 0)
                                    <tfoot>
                                        <tr style="background-color:#ffffff;">
                                            <td colspan="4" style="color:#000;">
                                                <strong>REPORTE GENERAL <span style="color:#dc3545;">CAJA 2 - </span>TOTAL <span style="color:#dc3545;">GENERAL</span></strong>
                                            </td>
                                            <td class="text-end" colspan="2" style="color:#0d6efd;">
                                                <strong>{{ number_format($report['balance_general'], 2) }}</strong>
                                            </td>
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
