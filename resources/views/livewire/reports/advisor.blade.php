<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE DE ASESORES DE CREDITO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Asesores de Crédito</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 py-1">
                                <div class="flex-shrink-0" style="width: 220px;">
                                    <label class="form-label mb-0 small">Asesor</label>
                                    <select class="form-select form-select-sm" wire:model="ejecutivo">
                                        <option value="Todos">Todos</option>
                                        @foreach($asesores as $a)
                                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Mes</label>
                                    <select class="form-select form-select-sm" wire:model="selemes">
                                        @foreach($months as $key => $nombre)
                                            <option value="{{ $key }}">{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 110px;">
                                    <label class="form-label mb-0 small">Año</label>
                                    <select class="form-select form-select-sm" wire:model="selecano">
                                        @for($y = 2015; $y <= 2028; $y++)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search" wire:loading.attr="disabled">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <button class="btn btn-sm btn-success flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- TABLA DIARIA --}}
                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1280px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="align-middle text-center">N°</th>
                                    <th rowspan="2" class="align-middle text-center">FECHA</th>
                                    <th colspan="5" class="text-center">CLIENTES</th>
                                    <th rowspan="2" class="align-middle text-center">IMP. A<br>COBRAR</th>
                                    <th colspan="2" class="text-center">COBRADOS</th>
                                    <th colspan="2" class="text-center">NO COBRADOS</th>
                                </tr>
                                <tr>
                                    <th class="text-center">NUEVO</th>
                                    <th class="text-center">REN./REF.</th>
                                    <th class="text-center">CANC.</th>
                                    <th class="text-center">TOTAL</th>
                                    <th class="text-center">CAPITAL</th>
                                    <th class="text-center">CANT.</th>
                                    <th class="text-center">IMPORTE</th>
                                    <th class="text-center">CANT.</th>
                                    <th class="text-center">IMPORTE</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    @php $st = $row['color'] ? 'color:'.$row['color'].';' : ''; @endphp
                                    <tr>
                                        <td style="{{ $st }}">{{ $row['d'] }}</td>
                                        <td style="{{ $st }}">{{ $row['fecha'] }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ $row['nuevo'] }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ $row['renov'] }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ $row['canc'] }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ $row['total'] }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ number_format($row['imp_cobrar'], 2) }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ $row['cob_cnt'] }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ number_format($row['cob_imp'], 2) }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ number_format($row['noc_cnt'], 0) }}</td>
                                        <td style="{{ $st }}" class="text-end">{{ number_format($row['noc_imp'], 2) }}</td>
                                    </tr>
                                @endforeach

                                {{-- Total --}}
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td colspan="2" rowspan="2" class="text-center">Total</td>
                                    <td class="text-end">{{ number_format($tot['nuevo'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['renov'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['canc'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['total'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['capital'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['imp_cobrar'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['cob_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['cob_imp'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['noc_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($tot['noc_imp'], 2) }}</td>
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-end">{{ number_format($avg['nuevo'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['renov'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['canc'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['total'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['capital'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['imp_cobrar'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['cob_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['cob_imp'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['noc_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($avg['noc_imp'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <br>

                    {{-- TABLA MENSUAL DEL AÑO --}}
                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1280px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th rowspan="2" class="align-middle text-center">N°</th>
                                    <th rowspan="2" class="align-middle text-center">MES</th>
                                    <th colspan="5" class="text-center">CLIENTES</th>
                                    <th rowspan="2" class="align-middle text-center">IMP. A<br>COBRAR</th>
                                    <th colspan="2" class="text-center">COBRADOS</th>
                                    <th colspan="2" class="text-center">NO COBRADOS</th>
                                </tr>
                                <tr>
                                    <th class="text-center">X.C.N.</th>
                                    <th class="text-center">X.R.C.</th>
                                    <th class="text-center">CANC.</th>
                                    <th class="text-center">TOTAL</th>
                                    <th class="text-center">CAPITAL</th>
                                    <th class="text-center">CANT.</th>
                                    <th class="text-center">IMPORTE</th>
                                    <th class="text-center">CANT.</th>
                                    <th class="text-center">IMPORTE</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($monthlyHistory['rows'] as $r)
                                    <tr>
                                        <td>{{ $r['n'] }}</td>
                                        <td>{{ $r['mes'] }}</td>
                                        <td class="text-end">{{ number_format($r['nuevo'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['renov'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['canc'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['total'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['capital'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['imp_cobrar'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['cob_cnt'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['cob_imp'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['noc_cnt'], 2) }}</td>
                                        <td class="text-end">{{ number_format($r['noc_imp'], 2) }}</td>
                                    </tr>
                                @endforeach

                                {{-- Totales --}}
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td colspan="2" rowspan="2" class="text-center">Totales</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['nuevo'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['renov'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['canc'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['total'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['capital'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['imp_cobrar'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['cob_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['cob_imp'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['noc_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['tot']['noc_imp'], 2) }}</td>
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['nuevo'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['renov'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['canc'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['total'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['capital'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['imp_cobrar'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['cob_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['cob_imp'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['noc_cnt'], 2) }}</td>
                                    <td class="text-end">{{ number_format($monthlyHistory['avg']['noc_imp'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
