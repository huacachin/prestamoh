<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">RESUMEN DE CREDITOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Cartera</span></li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <form wire:submit.prevent="search">
                        <div class="row g-2 mb-2">
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Mes</label>
                                <select class="form-select form-select-sm" wire:model="selemes0">
                                    <option value="">Todos</option>
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
                                <label class="form-label mb-0 small">Año</label>
                                <select class="form-select form-select-sm" wire:model="selecano0">
                                    <option value="">Todos</option>
                                    @for($y = (int) date('Y') - 5; $y <= (int) date('Y') + 2; $y++)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Tipo</label>
                                <select class="form-select form-select-sm" wire:model="seletipl0">
                                    <option value="">Todos</option>
                                    <option value="1">Semanal</option>
                                    <option value="3">Mensual</option>
                                    <option value="4">Diario</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label mb-0 small">Exp</label>
                                <input type="text" class="form-control form-control-sm" wire:model="exp">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label mb-0 small">Código</label>
                                <input type="text" class="form-control form-control-sm" wire:model="codigo">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">DNI</label>
                                <input type="text" class="form-control form-control-sm" wire:model="cdni">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Nombre</label>
                                <input type="text" class="form-control form-control-sm" wire:model="cnombre">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Asesor</label>
                                <input type="text" class="form-control form-control-sm" wire:model="casesor">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Fecha I</label>
                                <input type="date" class="form-control form-control-sm" wire:model="fechai">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Fecha F</label>
                                <input type="date" class="form-control form-control-sm" wire:model="fechaf">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- TABLA DETALLE --}}
                    <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1800px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle" width="40">N°</th>
                                    <th rowspan="2" class="text-center align-middle" width="50">Exp</th>
                                    <th rowspan="2" class="text-center align-middle" width="60">Código</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">DNI</th>
                                    <th rowspan="2" class="text-center align-middle">Nombre y Apellidos</th>
                                    <th rowspan="2" class="text-center align-middle" width="50">Dt.</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Capital</th>
                                    <th colspan="4" class="text-center">Interés</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Total</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Pago</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Saldo</th>
                                    <th rowspan="2" class="text-center align-middle" width="90">Fec/Cred</th>
                                    <th rowspan="2" class="text-center align-middle" width="90">Fec/Venc</th>
                                    <th rowspan="2" class="text-center align-middle" width="90">Fec/Ult/Pag</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Cel/Titu</th>
                                    <th rowspan="2" class="text-center align-middle" width="70">Estado</th>
                                    <th rowspan="2" class="text-center align-middle" width="100">Tiempo</th>
                                    <th rowspan="2" class="text-center align-middle" width="70">Asesor</th>
                                </tr>
                                <tr>
                                    <th class="text-center" width="35">TC</th>
                                    <th class="text-center" width="35">%</th>
                                    <th class="text-center" width="60">S/</th>
                                    <th class="text-center" width="30">C.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $r)
                                @php
                                    $bg = $r['is_refi'] ? 'background-color:yellow;' : '';
                                    $tcStyle = match($r['tipo_planilla']) {
                                        1 => 'color:blue;', 3 => 'color:red;', default => '',
                                    };
                                    $estadoStyle = $r['estado'] === 'Vencida' ? 'color:red;' : '';
                                @endphp
                                <tr style="{{ $bg }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor='{{ $r['is_refi'] ? '#ffff00' : '' }}'">
                                    <td class="text-center">{{ $r['n'] }}</td>
                                    <td class="text-center">{{ $r['exp'] }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('credits.show', $r['codigo']) }}" target="_blank">{{ $r['codigo'] }}</a>
                                    </td>
                                    <td class="text-center">{{ $r['dni'] }}</td>
                                    <td>{{ $r['cliente'] }}</td>
                                    <td><span style="color:red;">{{ $r['cod_rem'] }}</span></td>
                                    <td class="text-end">{{ number_format($r['capital'], 2) }}</td>
                                    <td class="text-center fw-bold" style="{{ $tcStyle }}">{{ $r['tc_label'] }}</td>
                                    <td class="text-center">
                                        @if((int)$r['interes_pct'] == (float)$r['interes_pct'])
                                            {{ (int) $r['interes_pct'] }}
                                        @else
                                            {{ number_format($r['interes_pct'], 2) }}
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($r['interes_monto'], 2) }}</td>
                                    <td class="text-center">{{ $r['cuotas'] }}</td>
                                    <td class="text-end">{{ number_format($r['total'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['pago'], 2) }}</td>
                                    <td class="text-end">{{ number_format($r['saldo'], 2) }}</td>
                                    <td class="text-center">{{ $r['fecha_cred'] }}</td>
                                    <td class="text-center">{{ $r['fecha_venc'] }}</td>
                                    <td class="text-center">{{ $r['fecha_ult_pago'] }}</td>
                                    <td>{{ $r['celular'] }}</td>
                                    <td style="{{ $estadoStyle }}">{{ $r['estado'] }}</td>
                                    <td>{{ $r['tiempo'] }}</td>
                                    <td>{{ $r['asesor'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="22" class="py-3 text-muted text-center">Sin resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            @if(count($rows) > 0)
                                <tfoot>
                                    <tr style="background-color:#ffffff;">
                                        <td colspan="5" class="text-end" style="color:#000;"><b>Total Soles</b></td>
                                        <td></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['capital'], 2) }}</b></td>
                                        <td colspan="2"></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['interes'], 2) }}</b></td>
                                        <td></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['total'], 2) }}</b></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['pago'], 2) }}</b></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['saldo'], 2) }}</b></td>
                                        <td colspan="7"></td>
                                    </tr>
                                    <tr style="background-color:#ffffff;">
                                        <td colspan="5" class="text-end" style="color:#000;"><b>Total Dólares</b></td>
                                        <td></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['capital'] / $tc, 2) }}</b></td>
                                        <td colspan="2"></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['interes'] / $tc, 2) }}</b></td>
                                        <td></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['total'] / $tc, 2) }}</b></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['pago'] / $tc, 2) }}</b></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['saldo'] / $tc, 2) }}</b></td>
                                        <td colspan="7"></td>
                                    </tr>
                                    {{-- MORA --}}
                                    <tr>
                                        <td style="color:red;text-align:center;"><b>{{ number_format($morisidad['mora_pct'], 2) }}%</b></td>
                                        <td style="background-color:red;color:white;text-align:center;">MORA</td>
                                        <td style="background-color:#005F8C;color:white;text-align:center;">{{ $morisidad['mora_count'] }}</td>
                                        <td colspan="2" style="background-color:#005F8C;color:white;text-align:center;">TOTAL MORA</td>
                                        <td></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['mora_capital'], 2) }}</b></td>
                                        <td colspan="2" style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;"><b>{{ number_format($morisidad['mora_interes'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['mora_total'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['mora_saldo'], 2) }}</b></td>
                                        <td colspan="7" style="background-color:yellow;"></td>
                                    </tr>
                                    {{-- ACTIVOS --}}
                                    <tr>
                                        <td style="color:green;text-align:center;"><b>{{ number_format($morisidad['activos_pct'], 2) }}%</b></td>
                                        <td style="background-color:green;color:white;text-align:center;">ACTIVOS</td>
                                        <td style="background-color:#005F8C;color:white;text-align:center;">{{ $morisidad['activos_count'] }}</td>
                                        <td colspan="2" style="background-color:#005F8C;color:white;text-align:center;">TOTAL ACTIVOS</td>
                                        <td></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['activos_capital'], 2) }}</b></td>
                                        <td colspan="2" style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;"><b>{{ number_format($morisidad['activos_interes'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['activos_total'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['activos_saldo'], 2) }}</b></td>
                                        <td colspan="7" style="background-color:yellow;"></td>
                                    </tr>
                                    {{-- TOTAL --}}
                                    <tr>
                                        <td style="color:#005F8C;text-align:center;"><b>100.00%</b></td>
                                        <td style="background-color:#005F8C;color:white;text-align:center;">TOTAL</td>
                                        <td style="background-color:#005F8C;color:white;text-align:center;">{{ $morisidad['total_count'] }}</td>
                                        <td colspan="2" style="background-color:#005F8C;color:white;text-align:center;">TOTAL</td>
                                        <td></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['total_capital'], 2) }}</b></td>
                                        <td colspan="2" style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;"><b>{{ number_format($morisidad['total_interes'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['total_total'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td style="background-color:yellow;color:red;"><b>{{ number_format($morisidad['total_saldo'], 2) }}</b></td>
                                        <td colspan="7" style="background-color:yellow;"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    {{-- TABLAS RESUMEN --}}
                    @if(count($rows) > 0)
                        <div class="row mt-3 g-2">
                            {{-- Vigentes/Vencidas --}}
                            <div class="col-md-2">
                                <table class="table table-bordered table-sm text-center" style="font-size: 12px;">
                                    <thead class="bg-primary">
                                        <tr><th>Tipo</th><th>Total</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td style="color:green;"><b>Vigente</b></td><td style="color:green;"><b>{{ $vignt }}</b></td></tr>
                                        <tr><td style="color:red;"><b>Vencidas</b></td><td style="color:red;"><b>{{ $venc }}</b></td></tr>
                                        <tr><td><b>Total</b></td><td><b>{{ $vignt + $venc }}</b></td></tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Por Tipo Planilla --}}
                            <div class="col-md-5">
                                <table class="table table-bordered table-sm text-center" style="font-size: 12px;">
                                    <thead class="bg-primary">
                                        <tr>
                                            <th>Tipo</th><th>Cnt.</th><th colspan="2">Capital</th>
                                            <th>Interés</th><th>50%</th><th>33%</th><th>25%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $sm = $tipoTotals['totsem'] + $tipoTotals['totmen'];
                                        @endphp
                                        <tr>
                                            <td><b style="color:blue;">Semanal</b></td>
                                            <td><b>{{ $tipoTotals['sempo'] }}</b></td>
                                            <td rowspan="2"><b>{{ number_format($sm, 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totsem'], 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintesem'], 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintesem'] / 2, 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintesem'] / 3, 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintesem'] / 4, 2) }}</b></td>
                                        </tr>
                                        <tr>
                                            <td><b style="color:red;">Mensual</b></td>
                                            <td><b>{{ $tipoTotals['mempo'] }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totmen'], 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintemen'], 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintemen'] / 2, 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintemen'] / 3, 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintemen'] / 4, 2) }}</b></td>
                                        </tr>
                                        <tr>
                                            <td><b>Diario</b></td>
                                            <td><b>{{ $tipoTotals['dempo'] }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totdia'], 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totdia'], 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintdiario'], 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintdiario'] / 2, 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintdiario'] / 3, 2) }}</b></td>
                                            <td><b>{{ number_format($tipoTotals['totintdiario'] / 4, 2) }}</b></td>
                                        </tr>
                                        @php
                                            $totCnt = $tipoTotals['sempo'] + $tipoTotals['mempo'] + $tipoTotals['dempo'];
                                            $totCap = $tipoTotals['totsem'] + $tipoTotals['totmen'] + $tipoTotals['totdia'];
                                            $totInt = $tipoTotals['totintesem'] + $tipoTotals['totintemen'] + $tipoTotals['totintdiario'];
                                        @endphp
                                        <tr style="background-color:#CEE7FF;">
                                            <td><b>Total</b></td>
                                            <td><b>{{ $totCnt }}</b></td>
                                            <td><b>{{ number_format($totCap, 2) }}</b></td>
                                            <td><b>{{ number_format($totCap, 2) }}</b></td>
                                            <td><b>{{ number_format($totInt, 2) }}</b></td>
                                            <td><b>{{ number_format($totInt / 2, 2) }}</b></td>
                                            <td><b>{{ number_format($totInt / 3, 2) }}</b></td>
                                            <td><b>{{ number_format($totInt / 4, 2) }}</b></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Por % Interés --}}
                            <div class="col-md-5">
                                <table class="table table-bordered table-sm text-center" style="font-size: 12px;">
                                    <thead class="bg-primary">
                                        <tr>
                                            <th colspan="6">CRÉDITO</th>
                                        </tr>
                                        <tr>
                                            <th>%</th><th>Cnt.</th><th>Capital</th><th>Interés</th><th>Pagado</th><th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $sumCnt = 0; $sumCap = 0; $sumInt = 0; $sumPag = 0; $sumTot = 0;
                                        @endphp
                                        @foreach($byInteres as $b)
                                            <tr>
                                                <td>{{ $b['porce'] }}</td>
                                                <td>{{ $b['ncount'] }}</td>
                                                <td>{{ number_format($b['capital'], 2) }}</td>
                                                <td>{{ number_format($b['interes'], 2) }}</td>
                                                <td>{{ number_format($b['pago'], 2) }}</td>
                                                <td>{{ number_format($b['total'], 2) }}</td>
                                            </tr>
                                            @php
                                                $sumCnt += $b['ncount'];
                                                $sumCap += $b['capital'];
                                                $sumInt += $b['interes'];
                                                $sumPag += $b['pago'];
                                                $sumTot += $b['total'];
                                            @endphp
                                        @endforeach
                                        <tr style="background-color:#CEE7FF;">
                                            <td><b>Total</b></td>
                                            <td><b>{{ $sumCnt }}</b></td>
                                            <td><b>{{ number_format($sumCap, 2) }}</b></td>
                                            <td><b>{{ number_format($sumInt, 2) }}</b></td>
                                            <td><b>{{ number_format($sumPag, 2) }}</b></td>
                                            <td><b>{{ number_format($sumTot, 2) }}</b></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- GRÁFICOS --}}
                        @php
                            $totEstados = max(1, $vignt + $venc);
                            $vigPct = round(($vignt / $totEstados) * 100, 2);
                            $vencPct = round(($venc / $totEstados) * 100, 2);
                        @endphp
                        <div class="row mt-4 g-3">
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-body p-2">
                                        <div id="chart-vigentes-vencidas"
                                             wire:ignore
                                             data-vig="{{ $vigPct }}"
                                             data-ven="{{ $vencPct }}"
                                             style="min-height: 280px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-body p-2">
                                        <div id="chart-tipo-credito"
                                             wire:ignore
                                             data-sem="{{ $tipoTotals['sempo'] }}"
                                             data-men="{{ $tipoTotals['mempo'] }}"
                                             data-dia="{{ $tipoTotals['dempo'] }}"
                                             style="min-height: 280px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@script
<script>
    if (typeof ApexCharts !== 'undefined') {
        const el1 = document.querySelector('#chart-vigentes-vencidas');
        const el2 = document.querySelector('#chart-tipo-credito');

        if (el1) {
            const vig = parseFloat(el1.dataset.vig) || 0;
            const ven = parseFloat(el1.dataset.ven) || 0;

            if (window.__portfolioChart1) {
                window.__portfolioChart1.updateSeries([{ data: [vig, ven] }], false);
            } else {
                window.__portfolioChart1 = new ApexCharts(el1, {
                    chart: { type: 'bar', height: 280, animations: { enabled: false }, toolbar: { show: false } },
                    title: { text: 'Resumen de Crédito', align: 'center', style: { fontSize: '14px', fontWeight: 'bold' } },
                    subtitle: { text: 'Distribución Vigentes vs Vencidas', align: 'center' },
                    series: [{ name: 'Porcentaje', data: [vig, ven] }],
                    xaxis: { categories: ['Vigente', 'Vencidas'] },
                    yaxis: { title: { text: 'Total Porcentaje %' } },
                    colors: ['#28a745', '#dc3545'],
                    plotOptions: { bar: { distributed: true, borderRadius: 6 } },
                    dataLabels: { enabled: true, formatter: v => v.toFixed(1) + ' %' },
                    legend: { show: false },
                });
                window.__portfolioChart1.render();
            }
        }

        if (el2) {
            const sem = parseFloat(el2.dataset.sem) || 0;
            const men = parseFloat(el2.dataset.men) || 0;
            const dia = parseFloat(el2.dataset.dia) || 0;

            if (window.__portfolioChart2) {
                window.__portfolioChart2.updateOptions({
                    series: [sem, men, dia],
                    labels: ['Semanal (' + sem + ')', 'Mensual (' + men + ')', 'Diario (' + dia + ')'],
                }, false, false, false);
            } else {
                window.__portfolioChart2 = new ApexCharts(el2, {
                    chart: { type: 'pie', height: 280, animations: { enabled: false } },
                    title: { text: 'CRÉDITO MENSUAL, SEMANAL Y DIARIO', align: 'center', style: { fontSize: '14px', fontWeight: 'bold' } },
                    series: [sem, men, dia],
                    labels: ['Semanal (' + sem + ')', 'Mensual (' + men + ')', 'Diario (' + dia + ')'],
                    colors: ['#0d6efd', '#dc3545', '#005F8C'],
                    legend: { position: 'bottom' },
                });
                window.__portfolioChart2.render();
            }
        }
    }
</script>
@endscript
</div>
