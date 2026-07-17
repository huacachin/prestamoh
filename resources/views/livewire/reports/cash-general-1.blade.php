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
                                <x-scroll-bottom-btn scrollable="#tabla-caja-1" />
                                <a href="{{ route('exports.reports.cash-general-1', ['selemes' => $selemes, 'selecano' => $selecano, 'seletipl' => $seletipl]) }}"
                                   class="btn btn-sm btn-success" target="_blank">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                                </a>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="printme">
                        <div id="tabla-caja-1" class="table-responsive" style="max-height: 70vh; overflow: auto;">
                            <table class="table table-bordered table-striped table-hover table-nowrap">
                                <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                    <tr>
                                        <th rowspan="3" class="align-middle text-center">N°</th>
                                        <th colspan="10" class="text-center">INGRESOS</th>
                                        <th rowspan="3" class="align-middle text-center">ASES.</th>
                                        <th rowspan="3" class="align-middle text-center">T.C.</th>
                                        <th colspan="5" class="text-center">EGRESOS</th>
                                        <th rowspan="3" class="align-middle text-center">ADM.</th>
                                        <th rowspan="3" class="align-middle text-center">ASES.</th>
                                        <th rowspan="3" class="align-middle text-center">T.C.</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="2" class="align-middle text-center">CDG</th>
                                        <th rowspan="2" class="align-middle text-center col-cliente">CLIENTE</th>
                                        <th rowspan="2" class="align-middle text-center">DETALLE</th>
                                        <th rowspan="2" class="align-middle text-center">N° CUOTAS</th>
                                        <th colspan="6" class="text-center">CUOTAS</th>
                                        <th rowspan="2" class="align-middle text-center">CDG</th>
                                        <th rowspan="2" class="align-middle text-center col-cliente">CLIENTE</th>
                                        <th rowspan="2" class="align-middle text-center">MONTO</th>
                                        <th rowspan="2" class="align-middle text-center">%</th>
                                        <th rowspan="2" class="align-middle text-center">S/</th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">TOTAL</th>
                                        <th class="text-center">CAPITAL</th>
                                        <th class="text-center">INTERES</th>
                                        <th class="text-center">EXCEDENTE</th>
                                        <th class="text-center">MORA</th>
                                        <th class="text-center">MORA ACUM.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($days as $day)
                                    {{-- Encabezado del día --}}
                                    <tr style="background-color: #B0B0B0;">
                                        <td colspan="21"><strong>{{ $day['date_label'] }}</strong></td>
                                    </tr>

                                    @php
                                        $maxRows = max(count($day['ingresos']), count($day['egresos']));
                                    @endphp

                                    @if($maxRows === 0)
                                        <tr>
                                            <td colspan="21"><span style="color:red;">SIN MOVIMIENTOS</span></td>
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
                                                        <a href="{{ route('cash.incomes', ['buscar' => $ing['credit_id'], 'tipo' => 2, 'desde' => $day['date'], 'hasta' => $day['date']]) }}" target="_blank">{{ $ing['credit_id'] }}</a>
                                                    </td>
                                                    <td class="col-cliente" title="{{ $ing['cliente'] }}"><span class="ellip">{{ $ing['cliente'] }}</span></td>
                                                    <td>{{ $ing['detalle'] }}</td>
                                                    <td class="text-center">{{ $ing['nro_cuotas'] }}</td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['total'], 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['capital'], 2) }}</span></td>
                                                    <td class="text-end">
                                                        <span class="text-primary">{{ number_format($ing['interes'], 2) }}</span>
                                                        @if(!empty($ing['obs_interes']))
                                                            <i class="ti ti-info-circle" style="color:#b8860b; cursor:help;"
                                                               title="{{ $ing['obs_interes'] }}"></i>
                                                        @endif
                                                    </td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['excedente'] ?? 0, 2) }}</span></td>
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['mora'] - $ing['mora_acum'], 2) }}</span></td>
                                                    <td class="text-end"><span style="color:#b8860b;">{{ number_format($ing['mora_acum'], 2) }}</span></td>
                                                    <td>{{ $ing['asesor'] }}</td>
                                                    <td class="text-center fw-bold">{{ $tcLabels[$ing['tipo_planilla']] ?? '?' }}</td>
                                                @else
                                                    <td colspan="12"></td>
                                                @endif

                                                {{-- EGRESOS --}}
                                                @if($egr)
                                                    @php
                                                        $egrRowStyle = in_array($egr['tipo_planilla'], [1, 3]) ? 'color: red;' : '';
                                                    @endphp
                                                    <td class="text-center" style="{{ $egrRowStyle }}">
                                                        <a href="{{ route('credits.show', $egr['credit_id']) }}" target="_blank">{{ $egr['credit_id'] }}</a>
                                                    </td>
                                                    <td class="col-cliente" style="{{ $egrRowStyle }}" title="{{ $egr['cliente'] }}"><span class="ellip">{{ $egr['cliente'] }}</span>@if($egr['cod_rem'])<span style="color:red;font-size:9px;"> ({{ $egr['cod_rem'] }})</span>@endif</td>
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
                                                    <td colspan="9"></td>
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
                                        <td class="text-end"><strong>{{ number_format($day['sub_excedente'], 2) }}</strong></td>
                                        <td class="text-end"><strong>{{ number_format($day['sub_mora'], 2) }}</strong></td>
                                        <td class="text-end" style="color:#b8860b;"><strong>{{ number_format($day['sub_mora_acum'], 2) }}</strong></td>
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
                                        <td class="text-center"><strong>{{ number_format($day['sub_ingresos'] + $day['sub_excedente'] + $day['sub_mora'] + $day['sub_mora_acum'], 2) }}</strong></td>
                                        <td colspan="13"></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="21" class="py-3 text-muted text-center">Sin movimientos para el periodo seleccionado</td>
                                    </tr>
                                @endforelse
                                </tbody>

                                @if(count($days) > 0)
                                    <tfoot>
                                        {{-- Sub Total General --}}
                                        <tr style="background-color: #ffffff;">
                                            <td colspan="5" class="text-end" style="color:#000;"><strong>Sub Total General</strong></td>
                                            <td class="text-end" style="color:#0d6efd;"><strong>{{ number_format($Tcpi, 2) }}</strong></td>
                                            <td class="text-end" style="color:#0d6efd;"><strong>{{ number_format($Tcpi2, 2) }}</strong></td>
                                            <td class="text-end" style="color:#0d6efd;"><strong>{{ number_format($Tint, 2) }}</strong></td>
                                            <td class="text-end" style="color:#0d6efd;"><strong>{{ number_format($Texc, 2) }}</strong></td>
                                            <td class="text-end" style="color:#0d6efd;"><strong>{{ number_format($Tmor4, 2) }}</strong></td>
                                            <td class="text-end" style="color:#b8860b;"><strong>{{ number_format($TmorAcum, 2) }}</strong></td>
                                            <td colspan="4"></td>
                                            <td class="text-end" style="color:#000;"><strong>{{ number_format($toff, 2) }}</strong></td>
                                            <td></td>
                                            <td class="text-end" style="color:#000;"><strong>{{ number_format($toff2, 2) }}</strong></td>
                                            <td colspan="3"></td>
                                        </tr>
                                        {{-- TOTAL GENERAL --}}
                                        <tr style="background-color: #ffffff;">
                                            <td colspan="5" class="text-end" style="color:#000;">
                                                <strong>REPORTE GENERAL <span style="color:#dc3545;">CAJA 1 -</span> TOTAL <span style="color:#dc3545;">GENERAL</span></strong>
                                            </td>
                                            <td class="text-end" style="color:#dc3545;"><strong>{{ number_format($toff1, 2) }}</strong></td>
                                            <td colspan="9"></td>
                                            <td class="text-end" style="color:#dc3545;"><strong>{{ number_format($toff, 2) }}</strong></td>
                                            <td></td>
                                            <td class="text-end" style="color:#dc3545;"><strong>{{ number_format($toff2, 2) }}</strong></td>
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
<span id="final"></span>
</style>
