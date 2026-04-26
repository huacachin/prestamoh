<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE CREDITO DIARIO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-cash f-s-16"></i>
                    <a href="{{ route('payments.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Pagos</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Diario</span></li>
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
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 3600px; table-layout: fixed;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th style="width:40px;">N.</th>
                                    <th style="width:90px;">FECHA</th>
                                    <th style="width:60px;">EXP.</th>
                                    <th style="width:70px;">COD.</th>
                                    <th style="width:40px;">N.C</th>
                                    <th style="width:90px;">DNI</th>
                                    <th style="width:200px;">CLIENTE</th>
                                    <th style="width:80px;">CAPITAL</th>
                                    <th style="width:40px;">%</th>
                                    <th style="width:70px;">INT.</th>
                                    <th style="width:80px;">T.P</th>
                                    <th style="width:70px;">C.</th>
                                    @for($d = 1; $d <= 32; $d++)
                                        <th style="width:80px;">{{ $d }}</th>
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
                                        <td>{{ $row['fecha'] }}</td>
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
                                        @foreach($row['days'] as $day)
                                            @php
                                                $style = 'white-space:nowrap; text-align:center;';
                                                if ($day['bg']) $style .= 'background-color:'.$day['bg'].';';
                                                if ($day['color']) $style .= 'color:'.$day['color'].';';
                                                if ($day['weight']) $style .= 'font-weight:'.$day['weight'].';';
                                            @endphp
                                            <td style="{{ $style }}">
                                                <small style="font-size:9px;">{{ substr($day['fecha'], 5) }}</small><br>
                                                {{ number_format($day['monto'], 2) }}
                                            </td>
                                        @endforeach
                                        <td class="text-end">{{ number_format($row['pagado'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['mora'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['saldo'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="48" class="text-center text-muted" style="padding:8px;">No se encontraron créditos</td>
                                    </tr>
                                @endforelse

                                @if(count($rows) > 0)
                                    {{-- Totales --}}
                                    <tr style="background-color:#f0f0f0; font-weight:500;">
                                        <td colspan="7">Total</td>
                                        <td class="text-end">{{ number_format($tot['capital'], 2) }}</td>
                                        <td></td>
                                        <td class="text-end">{{ number_format($tot['interes'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['apagar'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['cuota'], 2) }}</td>
                                        <td colspan="32"></td>
                                        <td class="text-end">{{ number_format($tot['pagado'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['mora'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['otros'], 2) }}</td>
                                        <td class="text-end">{{ number_format($tot['saldo'], 2) }}</td>
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
