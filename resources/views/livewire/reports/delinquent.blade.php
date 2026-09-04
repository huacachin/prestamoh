<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">PENDIENTES POR COBRAR</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Pendientes por Cobrar</span></li>
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
       list="hist_delinquent_exp" data-search-history="delinquent_exp">
<datalist id="hist_delinquent_exp" wire:ignore></datalist>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label mb-0 small">Código</label>
                                <input type="text" name="codigo" autocomplete="off" class="form-control form-control-sm" wire:model="codigo"
       list="hist_delinquent_codigo" data-search-history="delinquent_codigo">
<datalist id="hist_delinquent_codigo" wire:ignore></datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">DNI</label>
                                <input type="text" name="cdni" autocomplete="off" class="form-control form-control-sm" wire:model="cdni"
       list="hist_delinquent_cdni" data-search-history="delinquent_cdni">
<datalist id="hist_delinquent_cdni" wire:ignore></datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Nombre</label>
                                <input type="text" name="cnombre" autocomplete="off" class="form-control form-control-sm" wire:model="cnombre"
       list="hist_delinquent_cnombre" data-search-history="delinquent_cnombre">
<datalist id="hist_delinquent_cnombre" wire:ignore></datalist>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small">Asesor</label>
                                <input type="text" name="casesor" autocomplete="off" class="form-control form-control-sm" wire:model="casesor"
       list="hist_delinquent_casesor" data-search-history="delinquent_casesor">
<datalist id="hist_delinquent_casesor" wire:ignore></datalist>
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
                                <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <a href="{{ route('exports.reports.delinquent', ['selemes0' => $selemes0, 'selecano0' => $selecano0, 'seletipl0' => $seletipl0, 'exp' => $exp, 'codigo' => $codigo, 'cdni' => $cdni, 'cnombre' => $cnombre, 'casesor' => $casesor, 'fechai' => $fechai, 'fechaf' => $fechaf]) }}"
                                   class="btn btn-sm btn-success text-nowrap" target="_blank">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                                </a>
                                <x-scroll-bottom-btn scrollable="#tabla-morosidad" />
                            </div>
                        </div>
                    </form>

                    {{-- TABLA --}}
                    <div id="tabla-morosidad" class="table-responsive" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-striped table-hover table-nowrap">
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
                                    <th rowspan="2" class="text-center align-middle" width="80">Cuota</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Pago</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Saldo</th>
                                    <th rowspan="2" class="text-center align-middle" width="90">Fec/Cred</th>
                                    <th rowspan="2" class="text-center align-middle" width="90">Fec/Venc</th>
                                    <th rowspan="2" class="text-center align-middle" width="80">Cel/Titu</th>
                                    <th rowspan="2" class="text-center align-middle" width="70">Estado</th>
                                    <th rowspan="2" class="text-center align-middle" width="100">Tiempo</th>
                                    <th rowspan="2" class="text-center align-middle" width="70">Asesor</th>
                                    <th rowspan="2" class="text-center align-middle" width="60">Op.</th>
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
                                    $bg = $r['estado'] === 'Vencida' ? 'background-color:yellow;' : '';
                                    $tcStyle = match($r['tipo_planilla']) {
                                        1 => 'color:blue;', 3 => 'color:red;', default => '',
                                    };
                                    $estadoStyle = $r['estado'] === 'Vencida' ? 'color:red;' : '';
                                @endphp
                                <tr style="{{ $bg }}">
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
                                    <td>{{ $r['cliente'] }}</td>
                                    <td><span style="color:red;">{{ $r['cod_rem'] }}</span></td>
                                    <td class="text-end">{{ number_format($r['cuota'], 2) }}</td>
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
                                    <td>{{ $r['celular'] }}</td>
                                    <td style="{{ $estadoStyle }}">{{ $r['estado'] }}</td>
                                    <td>{{ $r['tiempo'] }}</td>
                                    <td>{{ $r['asesor'] }}</td>
                                    <td class="text-center">
                                        @if($r['wa_phone'])
                                            <a href="https://api.whatsapp.com/send?phone=51{{ $r['wa_phone'] }}&text={{ $r['wa_msg'] }}" target="_blank" title="WhatsApp">
                                                <i class="ti ti-brand-whatsapp f-s-16 text-success"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="22" class="py-3 text-muted text-center">Sin cuotas pendientes</td>
                                </tr>
                            @endforelse
                            </tbody>
                            @if(count($rows) > 0)
                                <tfoot>
                                    <tr style="background-color:#ffffff;">
                                        <td colspan="5" class="text-end" style="color:#000;"><b>Total Soles</b></td>
                                        <td></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['cuota'], 2) }}</b></td>
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
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['cuota'] / $tc, 2) }}</b></td>
                                        <td colspan="2"></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['interes'] / $tc, 2) }}</b></td>
                                        <td></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['total'] / $tc, 2) }}</b></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['pago'] / $tc, 2) }}</b></td>
                                        <td class="text-end" style="color:#0d6efd;"><b>{{ number_format($totals['saldo'] / $tc, 2) }}</b></td>
                                        <td colspan="7"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </div>
<span id="final"></span>
</div>
