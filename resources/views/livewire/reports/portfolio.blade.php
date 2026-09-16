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
                    {{-- Aviso de drill-down por tasa: se llegó pinchando el Cnt. de
                         "por % de interés", así que el reporte está acotado a esa tasa. --}}
                    @if($fInteres !== '')
                        @php
                            $filtrosSinTasa = array_filter([
                                'mes' => $selemes0, 'anio' => $selecano0, 'tipo' => $seletipl0,
                                'expediente' => $exp, 'codigo' => $codigo, 'dni' => $cdni,
                                'nombre' => $cnombre, 'asesor' => $casesor,
                                'desde' => $fechai, 'hasta' => $fechaf,
                            ], fn ($v) => $v !== '' && $v !== null);
                        @endphp
                        <div class="alert alert-info d-flex align-items-center justify-content-between py-1 px-2 mb-2 small">
                            <span>
                                <i class="ti ti-filter"></i>
                                Mostrando solo los créditos al <strong>{{ $fInteres }}%</strong> de interés.
                            </span>
                            <a href="{{ route('reports.portfolio', $filtrosSinTasa) }}" class="btn btn-sm btn-outline-secondary py-0">
                                <i class="ti ti-x"></i> Quitar filtro
                            </a>
                        </div>
                    @endif

                    {{-- Filtros --}}
                    <form wire:submit.prevent="search">
                        <div class="row g-2 mb-2">
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Mes</label>
                                <select class="form-select form-select-sm" wire:model="selemes0">
                                    <option value="">Seleccione</option>
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
                                    <option value="">Seleccione</option>
                                    @for($y = (int) date('Y') - 5; $y <= (int) date('Y') + 2; $y++)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Tipo</label>
                                <select class="form-select form-select-sm" wire:model="seletipl0">
                                    <option value="">Seleccione</option>
                                    <option value="1">Semanal</option>
                                    <option value="3">Mensual</option>
                                    <option value="4">Diario</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label mb-0 small">Exp</label>
                                <input type="text" name="exp" autocomplete="off" class="form-control form-control-sm" wire:model="exp"
       list="hist_portfolio_exp" data-search-history="portfolio_exp">
<datalist id="hist_portfolio_exp" wire:ignore></datalist>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label mb-0 small">Código</label>
                                <input type="text" name="codigo" autocomplete="off" class="form-control form-control-sm" wire:model="codigo"
       list="hist_portfolio_codigo" data-search-history="portfolio_codigo">
<datalist id="hist_portfolio_codigo" wire:ignore></datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">DNI</label>
                                <input type="text" name="cdni" autocomplete="off" class="form-control form-control-sm" wire:model="cdni"
       list="hist_portfolio_cdni" data-search-history="portfolio_cdni">
<datalist id="hist_portfolio_cdni" wire:ignore></datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Nombre</label>
                                <input type="text" name="cnombre" autocomplete="off" class="form-control form-control-sm" wire:model="cnombre"
       list="hist_portfolio_cnombre" data-search-history="portfolio_cnombre">
<datalist id="hist_portfolio_cnombre" wire:ignore></datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Asesor</label>
                                <input type="text" name="casesor" autocomplete="off" class="form-control form-control-sm" wire:model="casesor"
       list="hist_portfolio_casesor" data-search-history="portfolio_casesor">
<datalist id="hist_portfolio_casesor" wire:ignore></datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Fecha I</label>
                                <input type="text" name="fechai" autocomplete="off" class="form-control form-control-sm dates" wire:model="fechai">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Fecha F</label>
                                <input type="text" name="fechaf" autocomplete="off" class="form-control form-control-sm dates" wire:model="fechaf">
                            </div>
                            {{-- col-md-3: con 2 no cabian los tres botones y el ultimo se salia 19px. --}}
                            <div class="col-md-3 d-flex align-items-end gap-2">
                                {{-- text-nowrap: Bootstrap 5 quito el nowrap que traian los botones en la
                                     v4, y en este ancho el icono se separaba del texto en dos lineas. --}}
                                <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <a href="{{ route('exports.reports.portfolio', ['selemes0' => $selemes0, 'selecano0' => $selecano0, 'seletipl0' => $seletipl0, 'exp' => $exp, 'codigo' => $codigo, 'cdni' => $cdni, 'cnombre' => $cnombre, 'casesor' => $casesor, 'fechai' => $fechai, 'fechaf' => $fechaf, 'fInteres' => $fInteres]) }}"
                                   class="btn btn-sm btn-success text-nowrap" target="_blank">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                                </a>
                                <x-scroll-bottom-btn scrollable="#tabla-cartera" />
                            </div>
                        </div>
                    </form>


                    {{-- ── RESUMEN EJECUTIVO ──────────────────────────────────────
                         El reporte abria con las 234 filas de detalle y dejaba los
                         graficos al final, a 1.700px de scroll: habia que atravesar
                         todo el detalle para saber como va la cartera. Ahora va
                         primero el titular, luego el reparto y al final el detalle. --}}
                    @if(count($rows) > 0)
                        @php
                            $saldoTot  = max(0.01, $morisidad['total_saldo']);
                            $moraPct   = $morisidad['mora_saldo'] / $saldoTot * 100;
                            $pagadoPct = $totals['total'] > 0 ? $totals['pago'] / $totals['total'] * 100 : 0;
                            $kpis = [
                                ['Capital colocado', $totals['capital'], '#0d6efd', 'ti-cash',
                                 count($rows).' créditos activos'],
                                ['Total a cobrar',   $totals['total'],   '#6f42c1', 'ti-receipt-2',
                                 'capital + interés'],
                                ['Cobrado',          $totals['pago'],    '#2eb85c', 'ti-checks',
                                 number_format($pagadoPct, 1).'% del total'],
                                ['Saldo pendiente',  $totals['saldo'],   '#fd7e14', 'ti-hourglass-low',
                                 'aún por cobrar'],
                                ['Saldo en mora',    $morisidad['mora_saldo'], '#dc3545', 'ti-alert-triangle',
                                 $morisidad['mora_count'].' créditos'],
                            ];
                        @endphp
                        <div class="kpi-band">
                            @foreach($kpis as [$titulo, $valor, $color, $icono, $pie])
                                <div class="kpi" style="--kpi: {{ $color }};">
                                    <div class="kpi-top"><i class="ti {{ $icono }}"></i> {{ $titulo }}</div>
                                    <div class="kpi-val">{{ number_format($valor, 2) }}</div>
                                    <div class="kpi-pie">{{ $pie }}</div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Barra de riesgo: el saldo repartido entre al dia y mora, en DINERO.
                             Es la lectura que faltaba y la que explica el aparente descuadre:
                             por NUMERO de creditos la mora es 23.5%, pero por IMPORTE es 13.68%.
                             Ambas cifras son correctas; miden cosas distintas. --}}
                        <div class="riesgo-wrap">
                            <div class="riesgo-tit">Saldo pendiente por cobrar · {{ number_format($morisidad['total_saldo'], 2) }}</div>
                            <div class="riesgo-barra">
                                <div class="riesgo-seg riesgo-ok" style="width: {{ 100 - $moraPct }}%;"
                                     title="Saldo de créditos al día">{{ number_format(100 - $moraPct, 1) }}%</div>
                                <div class="riesgo-seg riesgo-mora" style="width: {{ $moraPct }}%;"
                                     title="Saldo de créditos en mora">{{ number_format($moraPct, 1) }}%</div>
                            </div>
                            <div class="riesgo-leyenda">
                                <span><i class="ti ti-point-filled" style="color:#2eb85c;"></i>
                                    Al día <b>{{ number_format($morisidad['activos_saldo'], 2) }}</b>
                                    <small>({{ $morisidad['activos_count'] }} créditos)</small></span>
                                <span><i class="ti ti-point-filled" style="color:#dc3545;"></i>
                                    En mora <b>{{ number_format($morisidad['mora_saldo'], 2) }}</b>
                                    <small>({{ $morisidad['mora_count'] }} créditos)</small></span>
                            </div>
                        </div>
                    @endif


                    {{-- TABLAS RESUMEN --}}
                    @if(count($rows) > 0)
                        {{-- GRÁFICOS --}}
                        @php
                            // Los anillos reciben conteos; el porcentaje lo calcula
                            // el propio grafico, asi no puede desviarse del dato.
                        @endphp
                        <div class="row mt-4 g-3">
                            <div class="col-md-6">
                                <div class="card border">
                                    <div class="card-body p-2">
                                        <div id="chart-vigentes-vencidas"
                                             wire:ignore
                                             data-vig="{{ $vignt }}"
                                             data-ven="{{ $venc }}"
                                             {{-- El grafico reparte el SALDO, no el numero de creditos,
                                                  para que sus porcentajes sean los mismos que muestra el
                                                  pie del detalle (86.32% / 13.68%). Contando creditos daba
                                                  76.5% / 23.5%: correcto tambien, pero otra base, y las dos
                                                  cifras juntas en la misma pantalla se contradecian. --}}
                                             data-saldo-vig="{{ $morisidad['activos_saldo'] }}"
                                             data-saldo-ven="{{ $morisidad['mora_saldo'] }}"
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
                        {{-- Homologado con el legacy (resumencredito2.php): las tres tablas van
                             una al lado de otra, cada una del ancho de su contenido, y se
                             reacomodan solas cuando no caben. Ahi eran float:left; aqui es flex,
                             que hace lo mismo sin los problemas de limpiar el flotado. --}}
                        <div class="resumen-tablas">
                            {{-- Las dos tablas cortas se apilan en una columna para que
                                 la de tasas quepa a su lado en vez de quedar debajo con
                                 medio ancho vacio a la derecha. --}}
                            <div class="resumen-col">
                                {{-- Vigentes/Vencidas --}}
                                <div class="resumen-tabla">
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
                                <div class="resumen-tabla">
                                    <table class="table table-bordered table-sm text-center" style="font-size: 12px;">
                                        <thead class="bg-primary">
                                            <tr>
                                                <th>Tipo</th><th>Cnt.</th><th>Capital</th>
                                                <th>Interés</th><th>50%</th><th>33%</th><th>25%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><b style="color:blue;">Semanal</b></td>
                                                <td><b>{{ $tipoTotals['sempo'] }}</b></td>
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
                                                <td><b>{{ number_format($totInt, 2) }}</b></td>
                                                <td><b>{{ number_format($totInt / 2, 2) }}</b></td>
                                                <td><b>{{ number_format($totInt / 3, 2) }}</b></td>
                                                <td><b>{{ number_format($totInt / 4, 2) }}</b></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Por % Interés — repartida en DOS columnas.
                                 Con 24 tasas esta tabla medía 828px de alto frente a los
                                 128 y 160 de sus vecinas, y dejaba ~700px de hueco al lado
                                 de cada una. Partida por la mitad baja a ~460px y el bloque
                                 queda parejo. El total se calcula sobre TODAS las filas y se
                                 muestra una sola vez, debajo. --}}
                            @php
                                $sumCnt = 0; $sumCap = 0; $sumInt = 0; $sumPag = 0; $sumTot = 0;
                                foreach ($byInteres as $b) {
                                    $sumCnt += $b['ncount']; $sumCap += $b['capital'];
                                    $sumInt += $b['interes']; $sumPag += $b['pago'];
                                    $sumTot += $b['total'];
                                }
                                $mitad = (int) ceil(count($byInteres) / 2);
                                $bloques = $mitad > 0 ? array_chunk($byInteres, $mitad) : [];

                                // Filtros vigentes, para que el drill-down por tasa abra el
                                // MISMO universo que se está viendo (si no, el conteo de la
                                // celda no cuadraría con lo que se muestra al entrar).
                                $filtrosPuestos = array_filter([
                                    'mes'         => $selemes0,
                                    'anio'        => $selecano0,
                                    'tipo'        => $seletipl0,
                                    'expediente'  => $exp,
                                    'codigo'      => $codigo,
                                    'dni'         => $cdni,
                                    'nombre'      => $cnombre,
                                    'asesor'      => $casesor,
                                    'desde'       => $fechai,
                                    'hasta'       => $fechaf,
                                ], fn ($v) => $v !== '' && $v !== null);
                            @endphp
                            <div class="resumen-tabla tabla-tasas">
                                <div class="tasas-titulo">CRÉDITO · por % de interés</div>
                                <div class="tasas-cols">
                                    @foreach($bloques as $bloque)
                                        <table class="table table-bordered table-sm text-center" style="font-size: 12px;">
                                            <thead class="bg-primary">
                                                <tr>
                                                    <th>%</th><th>Cnt.</th><th>Capital</th>
                                                    <th>Interés</th><th>Pagado</th><th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($bloque as $b)
                                                    <tr @class(['tasa-activa' => (string) $fInteres === (string) $b['porce']])>
                                                        <td>{{ $b['porce'] }}</td>
                                                        {{-- Cnt. enlaza al detalle de esa tasa, en ventana nueva
                                                             (mismo patrón que los chips de /clients) y arrastrando
                                                             los filtros vigentes. --}}
                                                        <td>
                                                            <a href="{{ route('reports.portfolio', $filtrosPuestos + ['interes' => $b['porce']]) }}"
                                                               target="_blank" rel="noopener" class="cnt-link"
                                                               title="Ver los {{ $b['ncount'] }} créditos al {{ $b['porce'] }}% — se abre en una ventana nueva">
                                                                {{ $b['ncount'] }}
                                                            </a>
                                                        </td>
                                                        <td>{{ number_format($b['capital'], 2) }}</td>
                                                        <td>{{ number_format($b['interes'], 2) }}</td>
                                                        <td>{{ number_format($b['pago'], 2) }}</td>
                                                        <td>{{ number_format($b['total'], 2) }}</td>
                                                    </tr>
                                                @endforeach
                                                {{-- El total va en la SEGUNDA mitad para que caiga bajo
                                                     sus columnas y quede alineado. Suma TODAS las tasas,
                                                     no solo las de esta mitad: de ahi el rotulo. --}}
                                                @if($loop->last)
                                                    <tr style="background-color:#CEE7FF;">
                                                        <td><b>TOTAL</b></td>
                                                        <td><b>{{ $sumCnt }}</b></td>
                                                        <td><b>{{ number_format($sumCap, 2) }}</b></td>
                                                        <td><b>{{ number_format($sumInt, 2) }}</b></td>
                                                        <td><b>{{ number_format($sumPag, 2) }}</b></td>
                                                        <td><b>{{ number_format($sumTot, 2) }}</b></td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    @endif

                    {{-- El detalle va al final: es consulta, no resumen. --}}
                    {{-- TABLA DETALLE --}}
                    <div id="tabla-cartera" class="table-responsive" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-striped table-hover table-nowrap cartera-detalle">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="text-center align-middle" width="40">N°</th>
                                    <th rowspan="2" class="text-center align-middle" width="50">Exp</th>
                                    <th rowspan="2" class="text-center align-middle" width="60">Código</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">DNI</th>
                                    <th rowspan="2" width="200" class="text-center align-middle">Nombre y Apellidos</th>
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
                                    <td class="text-center">
                                        @if($r['client_id'])
                                            <a href="{{ route('clients.show', $r['client_id']) }}" target="_blank" style="color:inherit; text-decoration:none;">{{ $r['exp'] }}</a>
                                        @else
                                            {{ $r['exp'] }}
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('credits.show', $r['codigo']) }}" target="_blank">{{ $r['codigo'] }}</a>
                                    </td>
                                    <td class="text-center">{{ $r['dni'] }}</td>
                                    {{-- title: el nombre se recorta con puntos suspensivos, asi que
                                         el completo queda disponible al pasar el cursor. --}}
                                    <td class="col-cliente" title="{{ $r['cliente'] }}">{{ $r['cliente'] }}</td>
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
                </div>
            </div>
        </div>
    </div>

@script
<script>
    if (typeof ApexCharts !== 'undefined') {
        // Ambos anillos muestran el MISMO total en el centro (los créditos activos):
        // así se ve de un vistazo que son dos cortes del mismo universo, y que
        // cuadran con la tabla. Antes uno mostraba solo porcentajes y el otro
        // porcentajes con los conteos escondidos en la leyenda.
        const anillo = (el, series, etiquetas, colores, titulo) => {
            const total = series.reduce((a, b) => a + b, 0);
            const opciones = {
                chart: { type: 'donut', height: 300, animations: { enabled: false } },
                title: { text: titulo, align: 'center', style: { fontSize: '13px', fontWeight: 'bold' } },
                series: series,
                labels: etiquetas,
                colors: colores,
                stroke: { width: 2, colors: ['#fff'] },
                // El dato dentro del anillo es el CONTEO; el porcentaje va en la leyenda.
                dataLabels: {
                    enabled: true,
                    formatter: (_, opts) => opts.w.config.series[opts.seriesIndex] || '',
                    style: { fontSize: '12px', fontWeight: 600 },
                },
                legend: {
                    position: 'bottom',
                    formatter: (etiqueta, opts) => {
                        const v = opts.w.config.series[opts.seriesIndex];
                        const pct = total ? (v / total * 100).toFixed(1) : '0.0';
                        return etiqueta + ': ' + v + ' (' + pct + '%)';
                    },
                },
                plotOptions: {
                    pie: { donut: { size: '64%', labels: {
                        show: true,
                        total: { show: true, showAlways: true, label: 'créditos',
                                 fontSize: '12px', color: '#6c757d',
                                 formatter: () => total },
                    } } },
                },
                tooltip: { y: { formatter: v => dinero(v) } },
            };
            return new ApexCharts(el, opciones);
        };

        const el1 = document.querySelector('#chart-vigentes-vencidas');
        const el2 = document.querySelector('#chart-tipo-credito');

        if (el1) {
            const vig = parseInt(el1.dataset.vig) || 0;
            const ven = parseInt(el1.dataset.ven) || 0;
            // Las barras miden SALDO (misma base que el pie del detalle); el
            // numero de creditos acompaña en la etiqueta del eje.
            const sVig = parseFloat(el1.dataset.saldoVig) || 0;
            const sVen = parseFloat(el1.dataset.saldoVen) || 0;
            const totalSaldo = sVig + sVen;
            const dinero = v => v.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (window.__portfolioChart1) {
                window.__portfolioChart1.updateOptions({ series: [{ data: [sVig, sVen] }] }, false, false, false);
            } else {
                window.__portfolioChart1 = new ApexCharts(el1, {
                    chart: { type: 'bar', height: 300, animations: { enabled: false }, toolbar: { show: false } },
                    title: { text: 'Estado de la cartera · por saldo pendiente',
                             align: 'center', style: { fontSize: '13px', fontWeight: 'bold' } },
                    // El total va en el subtitulo: es el mismo 234 del anillo de al
                    // lado, y asi se sigue viendo que ambos parten del mismo conjunto.
                    subtitle: { text: 'Saldo total ' + dinero(totalSaldo) + ' · ' + (vig + ven) + ' créditos', align: 'center',
                                style: { fontSize: '11px', color: '#6c757d' } },
                    series: [{ name: 'Saldo', data: [sVig, sVen] }],
                    xaxis: { categories: ['Vigente (' + vig + ' créditos)', 'Vencidas (' + ven + ' créditos)'] },
                    yaxis: { title: { text: 'Saldo pendiente' },
                             labels: { formatter: v => (v/1000).toFixed(0) + ' k' } },
                    colors: ['#2eb85c', '#dc3545'],
                    plotOptions: { bar: {
                        distributed: true, borderRadius: 6, columnWidth: '42%',
                        // La etiqueta ENCIMA de la barra: dentro se pierde sobre el
                        // color y obliga a mirar dos veces para leer la cifra.
                        dataLabels: { position: 'top' },
                    } },
                    // La etiqueta lleva el conteo y, entre parentesis, su peso sobre
                    // el total: el dato y su proporcion sin tener que calcularla.
                    dataLabels: {
                        enabled: true,
                        // Mismo porcentaje que el pie del detalle, calculado sobre el saldo.
                        formatter: v => (totalSaldo ? (v / totalSaldo * 100).toFixed(2) : 0) + '%',
                        offsetY: -18,
                        style: { fontSize: '12px', fontWeight: 700, colors: ['#495057'] },
                    },
                    legend: { show: false },
                    tooltip: { y: { formatter: v => v + ' créditos' } },
                    grid: { borderColor: '#f1f3f5', padding: { top: 12 } },
                });
                window.__portfolioChart1.render();
            }
        }

        if (el2) {
            const sem = parseInt(el2.dataset.sem) || 0;
            const men = parseInt(el2.dataset.men) || 0;
            const dia = parseInt(el2.dataset.dia) || 0;
            if (window.__portfolioChart2) {
                window.__portfolioChart2.updateOptions({ series: [sem, men, dia] }, false, false, false);
            } else {
                window.__portfolioChart2 = anillo(el2, [sem, men, dia],
                    ['Semanal', 'Mensual', 'Diario'], ['#0d6efd', '#fd7e14', '#005F8C'],
                    'Por tipo de planilla · por N° de créditos');
                window.__portfolioChart2.render();
            }
        }
    }
</script>
@endscript

<style>
    /* ── Tabla de detalle: 21 columnas que no caben en pantalla ──
       Medido: pedia 1.638px sobre 1.354 disponibles, o sea 284px de corte.
       Bajando un punto la fuente y acotando el nombre se recuperan 225px y
       el desplazamiento queda en 59px, casi imperceptible.

       No se acorta mas el nombre a proposito: cabria del todo, pero a costa
       de truncar la mayoria, y el nombre es justo como se identifica al
       cliente. Mejor 59px de scroll que nombres cortados. */
    .cartera-detalle, .cartera-detalle td, .cartera-detalle th { font-size: 11px !important; }
    .cartera-detalle td.col-cliente,
    .cartera-detalle th:nth-child(5) {
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* ── Banda de indicadores ── */
    .kpi-band {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(178px, 1fr));
        gap: 10px;
        margin: 2px 0 14px;
    }
    .kpi {
        border: 1px solid #e9ecef;
        border-left: 4px solid var(--kpi, #adb5bd);
        border-radius: 8px;
        padding: 8px 12px;
        background: #fff;
    }
    .kpi-top {
        font-size: 11px; font-weight: 600; color: #6c757d;
        text-transform: uppercase; letter-spacing: .3px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .kpi-val {
        font-size: 1.35rem; font-weight: 700; line-height: 1.25;
        color: var(--kpi, #212529);
        font-variant-numeric: tabular-nums;
    }
    .kpi-pie { font-size: 11px; color: #868e96; }

    /* ── Barra de riesgo (reparto del saldo en dinero) ── */
    .riesgo-wrap { margin-bottom: 14px; }
    .riesgo-tit {
        font-size: 11px; font-weight: 600; color: #6c757d;
        text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px;
    }
    .riesgo-barra {
        display: flex; height: 26px; border-radius: 6px; overflow: hidden;
        border: 1px solid #e9ecef;
    }
    .riesgo-seg {
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700; color: #fff;
        min-width: 0; overflow: hidden; white-space: nowrap;
    }
    .riesgo-ok   { background: #2eb85c; }
    .riesgo-mora { background: #dc3545; }
    .riesgo-leyenda {
        display: flex; gap: 18px; flex-wrap: wrap;
        font-size: 12px; color: #495057; margin-top: 4px;
    }
    .riesgo-leyenda small { color: #868e96; }

    /* Columna con las dos tablas cortas apiladas: asi la de tasas cabe al lado. */
    .resumen-col { display: flex; flex-direction: column; gap: 22px; min-width: 0; }
    .tabla-tasas { min-width: 0; }

    /* Tabla de tasas repartida en dos columnas para no doblar en alto a sus
       vecinas. Las dos mitades comparten cabecera y el total va debajo, una
       sola vez, alineado con ellas. */
    .tabla-tasas { display: flex; flex-direction: column; }
    .tasas-titulo {
        background: #005F8C; color: #fff; text-align: center;
        font-size: 11px; font-weight: 700; letter-spacing: .3px;
        padding: 3px 6px; border-radius: 4px 4px 0 0;
    }
    .tasas-cols { display: flex; gap: 14px; align-items: flex-start; }
    .tasas-cols > table { margin-bottom: 0; width: auto; font-size: 11px; }
    /* Relleno apretado: con 12 columnas (dos mitades de 6) el padding por
       defecto se comia 55px de mas y la columna Total quedaba cortada. */
    .tasas-cols td, .tasas-cols th { padding: 2px 3px !important; }

    /* Cnt. es un enlace al detalle de esa tasa: se marca con subrayado punteado
       para que se note que es pinchable sin romper la densidad de la tabla. */
    .cnt-link {
        color: #005F8C; font-weight: 700; text-decoration: underline dotted;
        text-underline-offset: 2px;
    }
    .cnt-link:hover { color: #003f5e; text-decoration: underline; }
    /* Fila de la tasa por la que se está filtrando. */
    .tasas-cols tr.tasa-activa { background-color: #FFF3CD; }
    .tasas-cols tr.tasa-activa td { font-weight: 700; }

    /* ── Tablas resumen, homologadas con el legacy (resumencredito2.php) ──
       Alli eran tres <table> con float:left y margin-left:10px, cada una del
       ancho de su contenido. Con flex se logra la misma colocacion y el mismo
       reacomodo al estrecharse, sin tener que limpiar flotados. */
    /* Rejilla de dos columnas: izquierda las dos tablas cortas apiladas,
       derecha la de tasas. Las tablas LLENAN su celda en vez de medir lo que
       pida su contenido; asi los bordes quedan alineados y no hay islas
       flotando con aire alrededor (que era justo lo que se veia mal). */
    .resumen-tablas {
        margin: 22px 0 26px;
        display: grid;
        /* La de tasas lleva 12 columnas (dos mitades de 6) y necesita bastante
           mas ancho que la izquierda; con menos, se le cortaba la columna Total. */
        grid-template-columns: minmax(0, 0.68fr) minmax(0, 1fr);
        gap: 22px;
        align-items: start;
    }
    @media (max-width: 991.98px) {
        .resumen-tablas { grid-template-columns: minmax(0, 1fr); }
    }
    .resumen-tabla > table,
    .tasas-cols > table { width: 100%; }
    /* Los importes nunca parten de linea: "2,992,866.09" quedaba como
       "2,992,866." + "09" al estrecharse la celda. Si no cabe, la tabla
       se desplaza (ya tiene overflow-x). */
    .resumen-tabla td, .resumen-tabla th { white-space: nowrap !important; }
    .resumen-tabla {
        min-width: 0;
        /* Si una tabla no cabe, se desplaza ella sola: nunca la pagina. */
        overflow-x: auto;
    }
    .resumen-tabla > table { margin-bottom: 0; }

    /* Movil y tablet: al no caber las tres, cada una pasa a ancho completo.
       Es el mismo reacomodo que hacia el float:left del legacy, pero alli las
       tablas quedaban ilegibles porque su CSS esperaba atributos data-label
       que nunca llegaron a escribirse. Aqui se conserva la cabecera y se
       permite desplazamiento lateral, para no perder ninguna columna. */
    @media (max-width: 991.98px) {
        .resumen-tabla { flex: 1 1 100%; }
    }
</style>

<span id="final"></span>
</div>
