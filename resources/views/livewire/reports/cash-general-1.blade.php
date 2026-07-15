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
                            <div class="col-md-6 d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="ti ti-search f-s-12"></i> Consultar
                                </button>
                                <div class="btn-group" role="group" aria-label="Vista">
                                    <button type="button" wire:click="$set('vista', 'resumen')"
                                            class="btn btn-sm {{ $vista === 'resumen' ? 'btn-dark' : 'btn-outline-dark' }}">
                                        <i class="ti ti-layout-list f-s-12"></i> Resumen
                                    </button>
                                    <button type="button" wire:click="$set('vista', 'detalle')"
                                            class="btn btn-sm {{ $vista === 'detalle' ? 'btn-dark' : 'btn-outline-dark' }}">
                                        <i class="ti ti-table f-s-12"></i> Detalle
                                    </button>
                                </div>
                                @if($vista === 'detalle')
                                <x-scroll-bottom-btn scrollable="#tabla-caja-1" />
                                @endif
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

                    {{-- ═══ VISTA RESUMEN: una fila por día, para comparar días de un vistazo ═══ --}}
                    @if($vista === 'resumen')
                    <div id="printme">
                        @php
                            // Total de caja del día (mismo cálculo que la fila TOTAL del detalle)
                            $totalDia = fn ($d) => $d['sub_ingresos'] + $d['sub_excedente'] + $d['sub_mora'] + $d['sub_mora_acum'];
                            $maxDia = collect($days)->map($totalDia)->max() ?: 1;
                            $maxEgr = collect($days)->max('sub_egresos') ?: 0;
                            $maxBarra = max($maxDia, $maxEgr) ?: 1;
                            $mejorDia = collect($days)->sortByDesc($totalDia)->first();
                            $netoMes = $toff1 - $toff;
                            $totNeto = 0;
                            $acum = 0;
                            // Delta % contra el mes anterior (null si no hay base de comparación)
                            $delta = fn ($curr, $prevVal) => ($prevVal ?? 0) != 0 ? round(($curr - $prevVal) / abs($prevVal) * 100, 1) : null;
                            $kpis = [
                                ['titulo' => 'INGRESOS DEL MES', 'valor' => $toff1, 'delta' => $delta($toff1, $prevStats['total'] ?? null), 'color' => '#0d6efd'],
                                ['titulo' => 'INTERÉS', 'valor' => $Tint, 'delta' => $delta($Tint, $prevStats['interes'] ?? null), 'color' => '#198754'],
                                ['titulo' => 'EGRESOS (CRÉDITOS)', 'valor' => $toff, 'delta' => $delta($toff, $prevStats['egresos'] ?? null), 'color' => '#fd7e14'],
                                ['titulo' => 'NETO DEL MES', 'valor' => $netoMes, 'delta' => $delta($netoMes, $prevStats['neto'] ?? null), 'color' => $netoMes >= 0 ? '#198754' : '#dc3545'],
                            ];
                        @endphp

                        {{-- KPIs del mes con comparativa vs mes anterior --}}
                        <div class="row g-2 mb-2">
                            @foreach($kpis as $kpi)
                                <div class="col-6 col-lg-3">
                                    <div class="border rounded p-2 h-100" style="background:#fafbfc;">
                                        <div class="text-muted" style="font-size:10px; letter-spacing:.5px;">{{ $kpi['titulo'] }}</div>
                                        <div class="fw-bold" style="font-size:17px; color:{{ $kpi['color'] }};">S/ {{ number_format($kpi['valor'], 2) }}</div>
                                        @if($kpi['delta'] !== null)
                                            <div style="font-size:10px;" class="{{ $kpi['delta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                <i class="ti ti-trending-{{ $kpi['delta'] >= 0 ? 'up' : 'down' }}"></i>
                                                {{ $kpi['delta'] >= 0 ? '+' : '' }}{{ number_format($kpi['delta'], 1) }}%
                                                <span class="text-muted">vs {{ $prevStats['label'] }}</span>
                                            </div>
                                        @else
                                            <div class="text-muted" style="font-size:10px;">sin datos de {{ $prevStats['label'] ?? 'mes anterior' }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Gráfico de barras por día: ingresos (azul) vs egresos (naranja) --}}
                        @if(count($days) > 0)
                        <div class="border rounded p-2 mb-2" style="background:#fff;">
                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap">
                                <span class="text-muted" style="font-size:10px; letter-spacing:.5px;">
                                    INGRESOS <span style="color:#0d6efd;">■</span> vs EGRESOS <span style="color:#fd7e14;">■</span> POR DÍA — click en una barra para ver su detalle
                                </span>
                                @if($mejorDia)
                                    <span style="font-size:10px;" class="text-muted">
                                        Mejor día: <b>{{ ucfirst(\Carbon\Carbon::parse($mejorDia['date'])->translatedFormat('D d/m')) }}</b>
                                        · S/ {{ number_format($totalDia($mejorDia), 2) }}
                                        &nbsp;|&nbsp; Promedio: S/ {{ number_format($toff1 / max(count($days), 1), 2) }}/día
                                    </span>
                                @endif
                            </div>
                            <div class="d-flex align-items-end gap-1" style="height:110px; overflow-x:auto;">
                                @foreach($days as $day)
                                    @php
                                        $tot = $totalDia($day);
                                        $hIng = max(2, round($tot / $maxBarra * 92));
                                        $hEgr = $day['sub_egresos'] > 0 ? max(2, round($day['sub_egresos'] / $maxBarra * 92)) : 0;
                                        $esMejor = $mejorDia && $day['date'] === $mejorDia['date'];
                                    @endphp
                                    <div class="d-flex flex-column align-items-center" style="min-width:26px; flex:1 1 0; cursor:pointer;"
                                         wire:click="verDia('{{ $day['date'] }}')"
                                         title="{{ ucfirst(\Carbon\Carbon::parse($day['date'])->translatedFormat('D d/m')) }} — Ingresos: S/ {{ number_format($tot, 2) }} · Egresos: S/ {{ number_format($day['sub_egresos'], 2) }}">
                                        <div class="d-flex align-items-end gap-1" style="height:92px;">
                                            <div style="width:9px; height:{{ $hIng }}px; background:{{ $esMejor ? '#0a58ca' : '#7cb9f5' }}; border-radius:2px 2px 0 0;"></div>
                                            @if($hEgr > 0)
                                                <div style="width:9px; height:{{ $hEgr }}px; background:#fdba74; border-radius:2px 2px 0 0;"></div>
                                            @endif
                                        </div>
                                        <span style="font-size:9px;" class="text-muted {{ $esMejor ? 'fw-bold' : '' }}">{{ (int) substr($day['date'], 8, 2) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Tarjetas por día --}}
                        <div class="row g-2">
                            @forelse($days as $day)
                                @php
                                    $tot = $totalDia($day);
                                    $neto = $tot - $day['sub_egresos'];
                                    $totNeto += $neto;
                                    $acum += $tot;
                                    $pct = max(2, round($tot / $maxDia * 100));
                                    $carbonDia = \Carbon\Carbon::parse($day['date']);
                                    $finde = $carbonDia->isWeekend();
                                    $esMejor = $mejorDia && $day['date'] === $mejorDia['date'];
                                @endphp
                                <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                                    <div class="caja1-card h-100 p-2 {{ $esMejor ? 'caja1-card--mejor' : '' }} {{ $finde ? 'caja1-card--finde' : '' }}"
                                         wire:click="verDia('{{ $day['date'] }}')"
                                         title="Click para ver el detalle de este día">

                                        {{-- Cabecera: día + badges --}}
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold" style="font-size:13px;">
                                                {{ ucfirst($carbonDia->translatedFormat('l d')) }}
                                                @if($esMejor)<i class="ti ti-trophy" style="color:#b8860b;" title="Mejor día del mes"></i>@endif
                                            </span>
                                            <span class="badge bg-light text-muted border" style="font-size:9px;">
                                                {{ count($day['ingresos']) }} {{ count($day['ingresos']) === 1 ? 'pago' : 'pagos' }}
                                            </span>
                                        </div>

                                        {{-- Total del día + barra vs mejor día --}}
                                        <div class="fw-bold text-primary" style="font-size:19px;">S/ {{ number_format($tot, 2) }}</div>
                                        <div class="caja1-barra mb-2"><div style="width:{{ $pct }}%;"></div></div>

                                        {{-- Desglose --}}
                                        <div class="row g-1 caja1-stats">
                                            <div class="col-4"><small>CAPITAL</small><span>{{ number_format($day['sub_capital'], 2) }}</span></div>
                                            <div class="col-4"><small>INTERÉS</small><span>{{ number_format($day['sub_interes'], 2) }}</span></div>
                                            <div class="col-4"><small>EXCED.</small><span>{{ $day['sub_excedente'] != 0 ? number_format($day['sub_excedente'], 2) : '—' }}</span></div>
                                            <div class="col-4"><small>MORA</small><span>{{ $day['sub_mora'] != 0 ? number_format($day['sub_mora'], 2) : '—' }}</span></div>
                                            <div class="col-4"><small>M. ACUM.</small><span style="color:#b8860b;">{{ $day['sub_mora_acum'] != 0 ? number_format($day['sub_mora_acum'], 2) : '—' }}</span></div>
                                            <div class="col-4"><small>ACUM. MES</small><span class="text-muted">{{ number_format($acum, 2) }}</span></div>
                                        </div>

                                        {{-- Egresos + neto --}}
                                        <div class="d-flex justify-content-between align-items-center border-top mt-2 pt-1">
                                            <span style="font-size:10px; color:#fd7e14;">
                                                <i class="ti ti-arrow-up-right"></i>
                                                @if(count($day['egresos']) > 0)
                                                    {{ count($day['egresos']) }} créd. · S/ {{ number_format($day['sub_egresos'], 2) }}
                                                @else
                                                    Sin egresos
                                                @endif
                                            </span>
                                            <span class="badge {{ $neto >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}" style="font-size:10px;">
                                                Neto {{ $neto >= 0 ? '+' : '' }}{{ number_format($neto, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 py-3 text-muted text-center">Sin movimientos para el periodo seleccionado</div>
                            @endforelse
                        </div>

                        {{-- Franja TOTAL MES --}}
                        @if(count($days) > 0)
                            <div class="caja1-totalmes mt-2 p-2 rounded d-flex flex-wrap align-items-center gap-3">
                                <span class="fw-bold" style="font-size:12px;">TOTAL MES</span>
                                <span><small>CAPITAL</small> {{ number_format($Tcpi2, 2) }}</span>
                                <span><small>INTERÉS</small> {{ number_format($Tint, 2) }}</span>
                                <span><small>EXCED.</small> {{ number_format($Texc, 2) }}</span>
                                <span><small>MORA</small> {{ number_format($Tmor4, 2) }}</span>
                                <span><small>M. ACUM.</small> <span style="color:#ffd27a;">{{ number_format($TmorAcum, 2) }}</span></span>
                                <span class="fw-bold"><small>INGRESOS</small> {{ number_format($toff1, 2) }}</span>
                                <span><small>EGRESOS</small> {{ number_format($toff, 2) }}</span>
                                <span class="fw-bold {{ $totNeto >= 0 ? 'text-success' : 'text-danger' }}" style="filter: brightness(1.6);">
                                    <small>NETO</small> {{ $totNeto >= 0 ? '+' : '' }}{{ number_format($totNeto, 2) }}
                                </span>
                            </div>
                        @endif
                    </div>
                    @else
                    {{-- ═══ VISTA DETALLE: tabla completa homóloga al legacy ═══ --}}
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
                                    <tr style="background-color: #B0B0B0;" id="dia-{{ $day['date'] }}">
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
                                                    <td class="text-end"><span class="text-primary">{{ number_format($ing['interes'], 2) }}</span></td>
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
                    @endif
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

    /* ── Tarjetas de la vista resumen ── */
    .caja1-card {
        background: #fff;
        border: 1px solid #e4e8ee;
        border-radius: 10px;
        cursor: pointer;
        transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
    }
    .caja1-card:hover {
        box-shadow: 0 6px 16px rgba(13, 110, 253, .14);
        transform: translateY(-2px);
        border-color: #9ec5fe;
    }
    .caja1-card--mejor { border-color: #d4af37; background: #fffdf4; }
    .caja1-card--finde { background: #fdf9f0; }
    .caja1-card--mejor.caja1-card--finde { background: #fffdf4; }

    .caja1-barra {
        height: 4px; border-radius: 2px; background: #eef2f7; overflow: hidden;
    }
    .caja1-barra > div { height: 100%; border-radius: 2px; background: #7cb9f5; }
    .caja1-card--mejor .caja1-barra > div { background: #d4af37; }

    .caja1-stats small {
        display: block; font-size: 8.5px; letter-spacing: .4px; color: #98a2b3;
    }
    .caja1-stats span { font-size: 11.5px; font-weight: 600; color: #344054; }

    .caja1-totalmes { background: #232a35; color: #fff; }
    .caja1-totalmes small {
        display: block; font-size: 8.5px; letter-spacing: .4px; color: #9aa4b2;
    }
    .caja1-totalmes > span { font-size: 12px; }
<span id="final"></span>
</style>

<script>
    document.addEventListener('livewire:init', () => {
        // Al hacer click en un día del resumen: la vista cambia a detalle y
        // desplazamos hasta el encabezado de ese día una vez re-renderizado.
        Livewire.on('scroll-to-day', ({ date }) => {
            setTimeout(() => {
                document.getElementById('dia-' + date)
                    ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 250);
        });
    });
</script>
