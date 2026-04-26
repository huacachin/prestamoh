<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE ESTADISTICO DE CAJA M.A.</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Rep. Estadística Caja</span></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <form wire:submit.prevent="search">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Mes</b></label>
                                <select class="form-select form-select-sm" wire:model="month">
                                    @foreach($months as $num => $name)
                                        <option value="{{ $num }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Año</b></label>
                                <select class="form-select form-select-sm" wire:model="year">
                                    @for($y = (int) date('Y') - 5; $y <= (int) date('Y') + 2; $y++)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="ti ti-search f-s-12"></i> Consultar
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="printme">
                        {{-- TABLA PRINCIPAL --}}
                        <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                            <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1700px;">
                                <thead class="bg-primary text-center" style="position: sticky; top: 0; z-index: 2;">
                                    <tr>
                                        <th rowspan="5" class="align-middle">Fecha</th>
                                        <th rowspan="5" class="align-middle">Capital T.</th>
                                        <th colspan="14">CREDITO</th>
                                        <th colspan="6" rowspan="2">OTROS MOVIMIENTOS</th>
                                    </tr>
                                    <tr>
                                        <th colspan="12">Ingreso - Caja</th>
                                        <th rowspan="4" class="align-middle">Egreso</th>
                                        <th rowspan="4" class="align-middle">Utilidad<br>Caja 3</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="3" class="align-middle">Capital2</th>
                                        <th colspan="10">Interés</th>
                                        <th rowspan="3" class="align-middle">Otros</th>
                                        <th colspan="3">Ingreso</th>
                                        <th colspan="3">Egreso</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3">Mensual</th>
                                        <th colspan="3">Semanal</th>
                                        <th colspan="3">Diario</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                        <th rowspan="2" class="align-middle">Fijos</th>
                                        <th rowspan="2" class="align-middle">Otros</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                        <th rowspan="2" class="align-middle">Fijos</th>
                                        <th rowspan="2" class="align-middle">Otros</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                    </tr>
                                    <tr>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $r)
                                    <tr style="{{ $r['is_sunday'] ? 'background-color:#ffe5e5;' : '' }}"
                                        onmouseover="this.style.backgroundColor='#CCFF66'"
                                        onmouseout="this.style.backgroundColor='{{ $r['is_sunday'] ? '#ffe5e5' : '' }}'">
                                        <td class="text-center">{{ $r['day'] }}/{{ str_pad($month,2,'0',STR_PAD_LEFT) }}/{{ $year }}</td>
                                        <td class="text-end">{{ number_format($r['capital_t'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['capital_cobrado'], 2) }}</td>
                                        <td class="text-center">{{ $r['mensual_n'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['mensual_s'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['mensual_mora'], 2) }}</td>
                                        <td class="text-center">{{ $r['semanal_n'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['semanal_s'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['semanal_mora'], 2) }}</td>
                                        <td class="text-center">{{ $r['diario_n'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['diario_s'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['diario_mora'], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($r['total_credito'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otros_ing'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otros_egr'], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($r['utilidad_caja3'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['ing_fijos'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['ing_otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['ing_total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['egr_fijos'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['egr_otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['egr_total'], 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="background-color:#ffffff;">
                                        <td style="color:#000;"><b>Total</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['capital_t'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['capital_cobrado'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $totals['mensual_n'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['mensual_s'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['mensual_mora'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $totals['semanal_n'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['semanal_s'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['semanal_mora'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $totals['diario_n'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['diario_s'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['diario_mora'], 2) }}</b></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($totals['total_credito'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['otros_ing'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['otros_egr'], 2) }}</b></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($totals['utilidad_caja3'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['ing_fijos'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['ing_otros'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['ing_total'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['egr_fijos'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['egr_otros'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($totals['egr_total'], 2) }}</b></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- DETALLES INGRESO/EGRESO/TOTAL/% --}}
                        <div class="mt-4">
                            <table class="table table-bordered table-sm" style="font-size: 12px;">
                                <thead class="bg-primary text-center">
                                    <tr>
                                        <th colspan="3">DETALLES</th>
                                        <th colspan="6">Mensual / Semanal</th>
                                        <th colspan="3">Diario</th>
                                        <th colspan="2">Otros</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3"><b>INGRESO</b></td>
                                        <td colspan="5" class="text-end" style="color:red;"><b>{{ number_format($detalleSummary['ing_ms'], 2) }}</b></td>
                                        <td></td>
                                        <td colspan="2" class="text-end" style="color:red;"><b>{{ number_format($detalleSummary['ing_d'], 2) }}</b></td>
                                        <td></td>
                                        <td colspan="2"></td>
                                        <td class="text-end">{{ number_format($detalleSummary['ing_total'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"><b>EGRESO</b></td>
                                        <td colspan="5" class="text-end">{{ number_format($detalleSummary['egr_ms'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2" class="text-end">{{ number_format($detalleSummary['egr_d'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2"></td>
                                        <td class="text-end">{{ number_format($detalleSummary['egr_total'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"><b>TOTAL</b></td>
                                        <td colspan="5" class="text-end">{{ number_format($detalleSummary['tot_ms'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2" class="text-end">{{ number_format($detalleSummary['tot_d'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2" class="text-end">{{ number_format($detalleSummary['tot_otros'], 2) }}</td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($detalleSummary['tot_total'], 2) }}</b></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"><b>%</b></td>
                                        <td colspan="5" class="text-end" style="color:red;"><b>{{ number_format($detalleSummary['pct_ms'], 2) }}%</b></td>
                                        <td></td>
                                        <td colspan="2" class="text-end" style="color:red;"><b>{{ number_format($detalleSummary['pct_d'], 2) }}%</b></td>
                                        <td></td>
                                        <td colspan="2"></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($detalleSummary['pct_total'], 2) }}%</b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- DISTRIBUCIÓN UTILIDAD --}}
                        <div class="mt-3">
                            <table class="table table-bordered table-sm" style="font-size: 12px; max-width: 700px;">
                                <thead class="bg-primary text-center">
                                    <tr>
                                        <th colspan="2">DETALLES</th>
                                        <th>%</th>
                                        <th colspan="2">M.S</th>
                                        <th colspan="2">D</th>
                                        <th>M.S + D</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($distribution as $dist)
                                    @php
                                        $isUtil = $dist['label'] === 'Utilidad';
                                        $isTotal = $dist['label'] === 'Total';
                                    @endphp
                                    <tr>
                                        <td colspan="2"><b>{{ $dist['label'] }}</b></td>
                                        <td class="text-center" style="{{ $isUtil || $isTotal ? 'color:red;' : '' }}"><b>{{ $dist['pct'] }}</b></td>
                                        <td colspan="2" class="text-end" style="{{ $isTotal ? 'color:red;' : '' }}"><b>{{ number_format($dist['ms'], 2) }}</b></td>
                                        <td colspan="2" class="text-end" style="{{ $isTotal ? 'color:red;' : '' }}"><b>{{ number_format($dist['d'], 2) }}</b></td>
                                        <td class="text-end" style="{{ $isUtil || $isTotal ? 'color:red;' : '' }}"><b>{{ number_format($dist['total'], 2) }}</b></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- TABLA 4: RESUMEN MENSUAL (Enero..mes seleccionado) --}}
                        <h6 class="mt-4 mb-2 fw-bold" style="color:red;">RESUMEN MENSUAL ({{ $year }})</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1700px;">
                                <thead class="bg-primary text-center" style="position: sticky; top: 0;">
                                    <tr>
                                        <th rowspan="5" class="align-middle">Mes</th>
                                        <th rowspan="5" class="align-middle">Capital T.</th>
                                        <th colspan="14">CREDITO</th>
                                        <th colspan="6" rowspan="2">OTROS MOVIMIENTOS</th>
                                    </tr>
                                    <tr>
                                        <th colspan="12">Ingreso - Caja</th>
                                        <th rowspan="4" class="align-middle">Egreso</th>
                                        <th rowspan="4" class="align-middle">Utilidad<br>Caja 3</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="3" class="align-middle">Capital</th>
                                        <th colspan="10">Interés</th>
                                        <th rowspan="3" class="align-middle">Otros</th>
                                        <th colspan="3">Ingreso</th>
                                        <th colspan="3">Egreso</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3">Mensual</th>
                                        <th colspan="3">Semanal</th>
                                        <th colspan="3">Diario</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                        <th rowspan="2" class="align-middle">Fijos</th>
                                        <th rowspan="2" class="align-middle">Otros</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                        <th rowspan="2" class="align-middle">Fijos</th>
                                        <th rowspan="2" class="align-middle">Otros</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                    </tr>
                                    <tr>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($monthRowsData as $r)
                                    <tr>
                                        <td><b>{{ $r['mes_nombre'] }}</b></td>
                                        <td class="text-end">{{ number_format($r['capineto'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['capital'], 2) }}</td>
                                        <td class="text-center">{{ $r['n1'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['mensual'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['mora3'], 2) }}</td>
                                        <td class="text-center">{{ $r['n2'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['semanal'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['mora1'], 2) }}</td>
                                        <td class="text-center">{{ $r['n3'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['diario'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['mora4'], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($r['total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otros2'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['egresov'], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($r['utilidad2'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['fijoi'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otrosi'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['ingT'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['fijoe'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otrose'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['egrT'], 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="background-color:#ffffff;">
                                        <td><b>Total</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['capineto_sum'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['capital'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $monthTotals['n1'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['mensual'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['mora3'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $monthTotals['n2'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['semanal'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['mora1'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $monthTotals['n3'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['diario'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['mora4'], 2) }}</b></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($monthTotals['total'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['otros2'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['egresov'], 2) }}</b></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($monthTotals['utilidad2'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['fijoi'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['otrosi'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['fijoi'] + $monthTotals['otrosi'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['fijoe'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['otrose'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($monthTotals['fijoe'] + $monthTotals['otrose'], 2) }}</b></td>
                                    </tr>
                                    <tr style="background-color:#f0f0f0;">
                                        <td><b>Promedio</b></td>
                                        <td class="text-end">{{ number_format($monthTotals['capineto_sum'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['capital'] / $monthsCount, 2) }}</td>
                                        <td class="text-center">{{ number_format($monthTotals['n1'] / $monthsCount, 0) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['mensual'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['mora3'] / $monthsCount, 2) }}</td>
                                        <td class="text-center">{{ number_format($monthTotals['n2'] / $monthsCount, 0) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['semanal'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['mora1'] / $monthsCount, 2) }}</td>
                                        <td class="text-center">{{ number_format($monthTotals['n3'] / $monthsCount, 0) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['diario'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['mora4'] / $monthsCount, 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($monthTotals['total'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['otros2'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['egresov'] / $monthsCount, 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($monthTotals['utilidad2'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['fijoi'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['otrosi'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format(($monthTotals['fijoi'] + $monthTotals['otrosi']) / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['fijoe'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format($monthTotals['otrose'] / $monthsCount, 2) }}</td>
                                        <td class="text-end">{{ number_format(($monthTotals['fijoe'] + $monthTotals['otrose']) / $monthsCount, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        {{-- DETALLES (acumulado mensual Ene-Mar) --}}
                        <div class="mt-4">
                            <table class="table table-bordered table-sm" style="font-size: 12px;">
                                <thead class="bg-primary text-center">
                                    <tr>
                                        <th colspan="3">DETALLES</th>
                                        <th colspan="6">Mensual / Semanal</th>
                                        <th colspan="3">Diario</th>
                                        <th colspan="2">Otros</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3"><b>INGRESO</b></td>
                                        <td colspan="5" class="text-end" style="color:red;"><b>{{ number_format($detalleSummaryMonth['ing_ms'], 2) }}</b></td>
                                        <td></td>
                                        <td colspan="2" class="text-end" style="color:red;"><b>{{ number_format($detalleSummaryMonth['ing_d'], 2) }}</b></td>
                                        <td></td>
                                        <td colspan="2"></td>
                                        <td class="text-end">{{ number_format($detalleSummaryMonth['ing_total'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"><b>EGRESO</b></td>
                                        <td colspan="5" class="text-end">{{ number_format($detalleSummaryMonth['egr_ms'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2" class="text-end">{{ number_format($detalleSummaryMonth['egr_d'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2"></td>
                                        <td class="text-end">{{ number_format($detalleSummaryMonth['egr_total'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"><b>TOTAL</b></td>
                                        <td colspan="5" class="text-end">{{ number_format($detalleSummaryMonth['tot_ms'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2" class="text-end">{{ number_format($detalleSummaryMonth['tot_d'], 2) }}</td>
                                        <td></td>
                                        <td colspan="2" class="text-end">{{ number_format($detalleSummaryMonth['tot_otros'], 2) }}</td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($detalleSummaryMonth['tot_total'], 2) }}</b></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"><b>%</b></td>
                                        <td colspan="5" class="text-end" style="color:red;"><b>{{ number_format($detalleSummaryMonth['pct_ms'], 2) }}%</b></td>
                                        <td></td>
                                        <td colspan="2" class="text-end" style="color:red;"><b>{{ number_format($detalleSummaryMonth['pct_d'], 2) }}%</b></td>
                                        <td></td>
                                        <td colspan="2"></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($detalleSummaryMonth['pct_total'], 2) }}%</b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- DISTRIBUCIÓN (acumulado mensual Ene-Mar) --}}
                        <div class="mt-3">
                            <table class="table table-bordered table-sm" style="font-size: 12px; max-width: 700px;">
                                <thead class="bg-primary text-center">
                                    <tr>
                                        <th colspan="2">DETALLES</th>
                                        <th>%</th>
                                        <th colspan="2">M.S</th>
                                        <th colspan="2">D</th>
                                        <th>M.S + D</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($distributionMonth as $dist)
                                    @php
                                        $isUtil = $dist['label'] === 'Utilidad';
                                        $isTotal = $dist['label'] === 'Total';
                                    @endphp
                                    <tr>
                                        <td colspan="2"><b>{{ $dist['label'] }}</b></td>
                                        <td class="text-center" style="{{ $isUtil || $isTotal ? 'color:red;' : '' }}"><b>{{ $dist['pct'] }}</b></td>
                                        <td colspan="2" class="text-end" style="{{ $isTotal ? 'color:red;' : '' }}"><b>{{ number_format($dist['ms'], 2) }}</b></td>
                                        <td colspan="2" class="text-end" style="{{ $isTotal ? 'color:red;' : '' }}"><b>{{ number_format($dist['d'], 2) }}</b></td>
                                        <td class="text-end" style="{{ $isUtil || $isTotal ? 'color:red;' : '' }}"><b>{{ number_format($dist['total'], 2) }}</b></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- TABLA: RESUMEN ANUAL --}}
                        <h6 class="mt-4 mb-2 fw-bold" style="color:red;">RESUMEN ANUAL</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1700px;">
                                <thead class="bg-primary text-center" style="position: sticky; top: 0;">
                                    <tr>
                                        <th rowspan="5" class="align-middle">Año</th>
                                        <th rowspan="5" class="align-middle">Capital T.</th>
                                        <th colspan="14">CREDITO</th>
                                        <th colspan="6" rowspan="2">OTROS MOVIMIENTOS</th>
                                    </tr>
                                    <tr>
                                        <th colspan="12">Ingreso - Caja</th>
                                        <th rowspan="4" class="align-middle">Egreso</th>
                                        <th rowspan="4" class="align-middle">Utilidad<br>Caja 3</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="3" class="align-middle">Capital</th>
                                        <th colspan="10">Interés</th>
                                        <th rowspan="3" class="align-middle">Otros</th>
                                        <th colspan="3">Ingreso</th>
                                        <th colspan="3">Egreso</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3">Mensual</th>
                                        <th colspan="3">Semanal</th>
                                        <th colspan="3">Diario</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                        <th rowspan="2" class="align-middle">Fijos</th>
                                        <th rowspan="2" class="align-middle">Otros</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                        <th rowspan="2" class="align-middle">Fijos</th>
                                        <th rowspan="2" class="align-middle">Otros</th>
                                        <th rowspan="2" class="align-middle">Total</th>
                                    </tr>
                                    <tr>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                        <th>N°</th><th>S/</th><th>Mora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($yearRowsData as $r)
                                    <tr>
                                        <td><b>{{ $r['idano'] }}</b></td>
                                        <td class="text-end">{{ number_format($r['capineto'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['capital'], 2) }}</td>
                                        <td class="text-center">{{ $r['n1'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['mensual'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['mora3'], 2) }}</td>
                                        <td class="text-center">{{ $r['n2'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['semanal'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['mora1'], 2) }}</td>
                                        <td class="text-center">{{ $r['n3'] ?: '' }}</td>
                                        <td class="text-end">{{ number_format($r['diario'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['mora4'], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($r['total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otros2'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['egresov'], 2) }}</td>
                                        <td class="text-end" style="color:red;">{{ number_format($r['utilidad2'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['fijoi'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otrosi'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['ingT'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['fijoe'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['otrose'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['egrT'], 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                    <tr style="background-color:#ffffff;">
                                        <td><b>Total</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['capineto'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['capital'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $yearTotals['n1'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['mensual'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['mora3'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $yearTotals['n2'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['semanal'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['mora1'], 2) }}</b></td>
                                        <td class="text-center"><b>{{ $yearTotals['n3'] }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['diario'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['mora4'], 2) }}</b></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($yearTotals['total'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['otros2'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['egresov'], 2) }}</b></td>
                                        <td class="text-end" style="color:red;"><b>{{ number_format($yearTotals['utilidad2'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['fijoi'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['otrosi'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['fijoi'] + $yearTotals['otrosi'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['fijoe'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['otrose'], 2) }}</b></td>
                                        <td class="text-end"><b>{{ number_format($yearTotals['fijoe'] + $yearTotals['otrose'], 2) }}</b></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
