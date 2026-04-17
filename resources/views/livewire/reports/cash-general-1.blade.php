<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE GENERAL CAJA 1</h4>
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
                    {{-- Filtros --}}
                    <form wire:submit.prevent="search">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Mes</b></label>
                                <select class="form-select form-select-sm" wire:model.live="selemes">
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
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Año</b></label>
                                <select class="form-select form-select-sm" wire:model.live="selecano">
                                    @for($y = (int) date('Y') - 5; $y <= (int) date('Y') + 2; $y++)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Tipo</b></label>
                                <select class="form-select form-select-sm" wire:model.live="seletipl">
                                    <option value="0000">Todos</option>
                                    <option value="4">Diario</option>
                                    <option value="1">Semanal</option>
                                    <option value="3">Mensual</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="ti ti-search f-s-12"></i> Consultar
                                </button>
                                <a href="#" class="btn btn-sm btn-success">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                                </a>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="printme">
                        <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                            <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1600px;">
                                <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                    <tr>
                                        <th rowspan="3" class="align-middle text-center">N°</th>
                                        <th colspan="8" class="text-center">INGRESOS</th>
                                        <th rowspan="3" class="align-middle text-center">ASES.</th>
                                        <th rowspan="3" class="align-middle text-center">T.C.</th>
                                        <th colspan="5" class="text-center">EGRESOS</th>
                                        <th rowspan="3" class="align-middle text-center">ADM.</th>
                                        <th rowspan="3" class="align-middle text-center">ASES.</th>
                                        <th rowspan="3" class="align-middle text-center">T.C.</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="2" class="align-middle text-center">CDG</th>
                                        <th rowspan="2" class="align-middle text-center">CLIENTE</th>
                                        <th rowspan="2" class="align-middle text-center">DETALLE</th>
                                        <th rowspan="2" class="align-middle text-center">N° CUOTAS</th>
                                        <th colspan="4" class="text-center">CUOTAS</th>
                                        <th rowspan="2" class="align-middle text-center">CDG</th>
                                        <th rowspan="2" class="align-middle text-center">CLIENTE</th>
                                        <th rowspan="2" class="align-middle text-center">MONTO</th>
                                        <th rowspan="2" class="align-middle text-center">%</th>
                                        <th rowspan="2" class="align-middle text-center">S/</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">TOTAL</th>
                                        <th class="text-center">CAPITAL</th>
                                        <th class="text-center">INTERES</th>
                                        <th class="text-center">MORA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($days as $day)
                                    {{-- Encabezado del día --}}
                                    <tr style="background-color: #B0B0B0;">
                                        <td colspan="19"><strong>{{ $day['date_label'] }}</strong></td>
                                    </tr>

                                    @php
                                        $maxRows = max(count($day['ingresos']), count($day['egresos']));
                                    @endphp

                                    @if($maxRows === 0)
                                        <tr>
                                            <td colspan="19"><span style="color:red;">SIN MOVIMIENTOS</span></td>
                                        </tr>
                                    @else
                                        @for($i = 0; $i < $maxRows; $i++)
                                            @php
                                                $ing = $day['ingresos'][$i] ?? null;
                                                $egr = $day['egresos'][$i] ?? null;
                                                $rowStyle = ($ing && in_array($ing['tipo_planilla'], [1, 3])) ? 'color: red;' : '';
                                                $tcLabels = [1 => 'S', 3 => 'M', 4 => 'D'];
                                            @endphp
                                            <tr style="{{ $rowStyle }}"
                                                onmouseover="this.style.backgroundColor='#CCFF66'"
                                                onmouseout="this.style.backgroundColor=''">
                                                <td class="text-center"><strong>{{ $i + 1 }}</strong></td>

                                                {{-- INGRESOS --}}
                                                @if($ing)
                                                    <td class="text-center">
                                                        <a href="{{ route('credits.show', $ing['credit_id']) }}" target="_blank">{{ $ing['credit_id'] }}</a>
                                                    </td>
                                                    <td>{{ $ing['cliente'] }}</td>
                                                    <td>{{ $ing['detalle'] }}</td>
                                                    <td class="text-center">{{ $ing['nro_cuotas'] }}</td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['total'], 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['capital'], 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['interes'], 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['mora'], 2) }}</span></td>
                                                    <td>{{ $ing['asesor'] }}</td>
                                                    <td class="text-center fw-bold">{{ $tcLabels[$ing['tipo_planilla']] ?? '?' }}</td>
                                                @else
                                                    <td colspan="10"></td>
                                                @endif

                                                {{-- EGRESOS --}}
                                                @if($egr)
                                                    @php
                                                        $egrRowStyle = in_array($egr['tipo_planilla'], [1, 3]) ? 'color: red;' : '';
                                                    @endphp
                                                    <td class="text-center" style="{{ $egrRowStyle }}">
                                                        <a href="{{ route('credits.show', $egr['credit_id']) }}" target="_blank">{{ $egr['credit_id'] }}</a>
                                                    </td>
                                                    <td style="{{ $egrRowStyle }}">{{ $egr['cliente'] }}</td>
                                                    <td class="text-end" style="{{ $egrRowStyle }}"><span class="text-primary">{{ number_format($egr['monto'], 2) }}</span></td>
                                                    <td class="text-end" style="color: red;">
                                                        @if((int)$egr['interes_pct'] == (float)$egr['interes_pct'])
                                                            {{ (int) $egr['interes_pct'] }}
                                                        @else
                                                            {{ number_format($egr['interes_pct'], 2) }}
                                                        @endif
                                                    </td>
                                                    <td class="text-end" style="{{ $egrRowStyle }}"><span class="text-primary">{{ number_format($egr['interes_monto'], 2) }}</span></td>
                                                    <td style="{{ $egrRowStyle }}">{{ $egr['usuario'] }}</td>
                                                    <td style="{{ $egrRowStyle }}">{{ $egr['asesor'] }}</td>
                                                    <td class="text-center fw-bold" style="{{ $egrRowStyle }}">{{ $tcLabels[$egr['tipo_planilla']] ?? '?' }}</td>
                                                @else
                                                    <td colspan="8"></td>
                                                @endif
                                            </tr>
                                        @endfor
                                    @endif

                                    {{-- SUB TOTAL del día --}}
                                    <tr style="background-color: #f0f0f0;">
                                        <td></td>
                                        <td colspan="4"><strong>SUB TOTAL</strong></td>
                                        <td class="text-end"><strong>{{ number_format($day['sub_ingresos'], 2) }}</strong></td>
                                        <td class="text-end"><strong>{{ number_format($day['sub_capital'], 2) }}</strong></td>
                                        <td class="text-end"><strong>{{ number_format($day['sub_interes'], 2) }}</strong></td>
                                        <td class="text-end"><strong>{{ number_format($day['sub_mora'], 2) }}</strong></td>
                                        <td colspan="4"></td>
                                        <td class="text-end"><strong>{{ number_format($day['sub_egresos'], 2) }}</strong></td>
                                        <td></td>
                                        <td class="text-end"><strong>{{ number_format($day['sub_egresos_interes'], 2) }}</strong></td>
                                        <td colspan="3"></td>
                                    </tr>

                                    {{-- TOTAL del día --}}
                                    <tr style="background-color: #CEE7FF;">
                                        <td></td>
                                        <td colspan="5"><strong>TOTAL</strong></td>
                                        <td></td>
                                        <td class="text-center"><strong>{{ number_format($day['sub_ingresos'] + $day['sub_mora'], 2) }}</strong></td>
                                        <td colspan="11"></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="19" class="py-3 text-muted text-center">Sin movimientos para el periodo seleccionado</td>
                                    </tr>
                                @endforelse
                                </tbody>

                                @if(count($days) > 0)
                                    <tfoot class="bg-primary">
                                        {{-- Sub Total General --}}
                                        <tr>
                                            <td colspan="5" class="text-end"><strong>Sub Total General</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($Tcpi, 2) }}</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($Tcpi2, 2) }}</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($Tint, 2) }}</strong></td>
                                            <td class="text-end"><strong class="text-primary">{{ number_format($Tmor4, 2) }}</strong></td>
                                            <td colspan="4"></td>
                                            <td class="text-end"><strong>{{ number_format($toff, 2) }}</strong></td>
                                            <td></td>
                                            <td class="text-end"><strong>{{ number_format($toff2, 2) }}</strong></td>
                                            <td colspan="3"></td>
                                        </tr>
                                        {{-- TOTAL GENERAL --}}
                                        <tr>
                                            <td colspan="5" class="text-end">
                                                <strong>REPORTE GENERAL <span class="text-danger">CAJA 1 -</span> TOTAL <span class="text-danger">GENERAL</span></strong>
                                            </td>
                                            <td class="text-end"><strong class="text-danger">{{ number_format($toff1, 2) }}</strong></td>
                                            <td colspan="7"></td>
                                            <td class="text-end"><strong class="text-danger">{{ number_format($toff, 2) }}</strong></td>
                                            <td></td>
                                            <td class="text-end"><strong class="text-danger">{{ number_format($toff2, 2) }}</strong></td>
                                            <td colspan="3"></td>
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

<style>
    @media print {
        .breadcrumb, .btn, form { display: none !important; }
        #printme { width: 100%; }
    }
</style>
