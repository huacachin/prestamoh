<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE CREDITO MENSUAL</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Mensual</span></li>
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
                                <div class="flex-shrink-0" style="width: 200px;">
                                    <label class="form-label mb-0 small">Ejecutivo</label>
                                    <select class="form-select form-select-sm" wire:model="ejecutivo">
                                        <option value="Todos">Todos</option>
                                        @foreach($asesores as $a)
                                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Estado</label>
                                    <select class="form-select form-select-sm" wire:model="eestado">
                                        <option value="Vigente">Vigentes</option>
                                        <option value="Cancelado">Cancelados</option>
                                        <option value="Vencida">Vencida</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0" style="width: 140px;">
                                    <label class="form-label mb-0 small">Codigo</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="codio1" wire:keydown.enter="search">
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

                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 2200px; table-layout: fixed;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th style="width:40px;">N.</th>
                                    <th style="width:90px;">F.Cr.</th>
                                    <th style="width:90px;">F.Ve.</th>
                                    <th style="width:60px;">EXP.</th>
                                    <th style="width:70px;">COD.</th>
                                    <th style="width:40px;">N.C</th>
                                    <th style="width:90px;">DNI</th>
                                    <th style="width:200px;">CLIENTE</th>
                                    <th style="width:80px;">CAPITAL</th>
                                    <th style="width:40px;">%</th>
                                    <th style="width:80px;">INT.</th>
                                    <th style="width:90px;">T.P</th>
                                    <th style="width:80px;">C.</th>
                                    @for($d = 1; $d <= 12; $d++)
                                        <th style="width:90px;">{{ $d }}</th>
                                    @endfor
                                    <th style="width:90px;">TOTAL</th>
                                    <th style="width:80px;">MORA</th>
                                    <th style="width:80px;">OTROS</th>
                                    <th style="width:90px;">SALDOS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr>
                                        <td>{{ $row['n'] }}</td>
                                        <td>{{ $row['fecha_pres'] }}</td>
                                        <td>{{ $row['fecha_venc'] }}</td>
                                        <td style="{{ !$row['has_imagen'] ? 'background-color:yellow;' : '' }}">{{ $row['expediente'] }}</td>
                                        <td>
                                            <a href="{{ route('credits.show', $row['codigo']) }}" target="_blank">{{ $row['codigo'] }}</a>
                                        </td>
                                        <td>{{ $row['cuotas'] }}</td>
                                        <td>{{ $row['dni'] }}</td>
                                        <td>{{ $row['cliente'] }}</td>
                                        <td class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                        <td class="text-end">{{ $row['interes_pct'] }}</td>
                                        <td class="text-end">{{ number_format($row['interes'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['apagar'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['cuota'], 2) }}</td>
                                        @foreach($row['cuotas_cols'] as $cuota)
                                            @php
                                                $style = 'white-space:nowrap; text-align:center;';
                                                if ($cuota['bg']) $style .= 'background-color:'.$cuota['bg'].';';
                                                if ($cuota['color']) $style .= 'color:'.$cuota['color'].';';
                                            @endphp
                                            <td style="{{ $style }}">
                                                @if($cuota['fecha'])
                                                    <small style="font-size:9px;">{{ substr($cuota['fecha'], 5) }}</small><br>
                                                    {{ number_format($cuota['monto'], 2) }}
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-end">{{ number_format($row['pagado'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['mora'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['saldo'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="29" class="text-center text-muted" style="padding:8px;">No se encontraron créditos</td>
                                    </tr>
                                @endforelse

                                @if(count($rows) > 0)
                                    {{-- Total --}}
                                    <tr style="background-color:#f0f0f0; font-weight:500;">
                                        <td colspan="8">Total</td>
                                        <td class="text-end">{{ number_format($tot['capital'], 2) }}</td>
                                        <td></td>
                                        <td class="text-end">{{ number_format($tot['interes'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['apagar'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['cuota'], 2) }}</td>
                                        <td colspan="12"></td>
                                        <td class="text-end">{{ number_format($tot['pagado'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['mora'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['saldo'], 2) }}</td>
                                    </tr>

                                    {{-- MORA --}}
                                    <tr>
                                        <td colspan="3" style="color:red; text-align:center;"><b>{{ number_format($morosidadPct, 2) }}%</b></td>
                                        <td style="background-color:red; color:white;">MORA</td>
                                        <td style="background-color:#005F8C; color:white;">{{ $sub['mora']['n'] }}</td>
                                        <td colspan="3" style="background-color:#005F8C; color:white;">TOTAL MORA</td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($sub['mora']['capital'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['mora']['interes'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($sub['mora']['apagar'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['mora']['cuota'], 2) }}</b></td>
                                        <td colspan="12" style="background-color:yellow;"></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['mora']['pagado'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['mora']['mora'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['mora']['otros'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($sub['mora']['saldo'], 2) }}</b></td>
                                    </tr>

                                    {{-- ACTIVOS --}}
                                    <tr>
                                        <td colspan="3" style="color:green; text-align:center;"><b>{{ number_format($activosPct, 2) }}%</b></td>
                                        <td style="background-color:green; color:white;">ACTIVOS</td>
                                        <td style="background-color:#005F8C; color:white;">{{ $sub['activo']['n'] }}</td>
                                        <td colspan="3" style="background-color:#005F8C; color:white;">TOTAL ACTIVOS</td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($sub['activo']['capital'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['activo']['interes'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($sub['activo']['apagar'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['activo']['cuota'], 2) }}</b></td>
                                        <td colspan="12" style="background-color:yellow;"></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['activo']['pagado'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['activo']['mora'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($sub['activo']['otros'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($sub['activo']['saldo'], 2) }}</b></td>
                                    </tr>

                                    {{-- TOTAL --}}
                                    <tr>
                                        <td colspan="3" style="color:blue; text-align:center;"><b>100.00%</b></td>
                                        <td style="background-color:#005F8C; color:white;">TOTAL</td>
                                        <td style="background-color:#005F8C; color:white;">{{ $sub['mora']['n'] + $sub['activo']['n'] }}</td>
                                        <td colspan="3" style="background-color:#005F8C; color:white;">TOTAL</td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($tot['capital'], 2) }}</b></td>
                                        <td style="background-color:yellow;"></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($tot['interes'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($tot['apagar'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($tot['cuota'], 2) }}</b></td>
                                        <td colspan="12" style="background-color:yellow;"></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($tot['pagado'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($tot['mora'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow;"><b>{{ number_format($tot['otros'], 2) }}</b></td>
                                        <td class="text-end" style="background-color:yellow; color:red;"><b>{{ number_format($tot['saldo'], 2) }}</b></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
