<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">REPORTE DE PAGO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-report-analytics f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Reportes</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Pagos</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros: Buscar por --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-3 py-1">
                                <div class="d-flex flex-column">
                                    <label class="form-label mb-1 small fw-semibold">BUSCAR X</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="1" id="tipo1">
                                            <label class="form-check-label" for="tipo1">A/</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="2" id="tipo2">
                                            <label class="form-check-label" for="tipo2">Motivo</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="3" id="tipo3">
                                            <label class="form-check-label" for="tipo3">Asesor</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" wire:model.live="tipo" value="4" id="tipo4">
                                            <label class="form-check-label" for="tipo4">Usuario</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Filtros: texto + fechas --}}
                    <div class="row my-2">
                        <div class="col-12">
                            <div class="d-flex flex-wrap align-items-end gap-2 py-1">
                                <div class="flex-grow-1" style="min-width: 250px; max-width:400px;">
                                    <label class="form-label mb-0 small">Texto</label>
                                    <input type="text" class="form-control form-control-sm"
                                           wire:model.live.debounce.500ms="compra"
                                           placeholder="Ingrese el texto a buscar">
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Inicio</label>
                                    <input type="date" class="form-control form-control-sm" wire:model.live="fei">
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Fin</label>
                                    <input type="date" class="form-control form-control-sm" wire:model.live="fef">
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <button class="btn btn-sm btn-success flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Tabla --}}
                    <div class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover" style="font-size: 11px; min-width: 1280px;">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="text-center" style="background:#949696;">Nº</th>
                                    <th class="text-center">Fecha</th>
                                    <th class="text-center" style="background:#949696;">Hora</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-center" style="background:#949696;">Asesor</th>
                                    <th>A</th>
                                    <th style="background:#949696;">Motivo</th>
                                    <th class="text-center">S/.</th>
                                    @if($isAdmin)
                                        <th class="text-center" style="background:#949696;">Map</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr>
                                        <td class="text-center">{{ $row['n'] }}</td>
                                        <td>{{ $row['fecha'] }}</td>
                                        <td>{{ $row['hora'] }}</td>
                                        <td>{{ $row['usuario'] }}</td>
                                        <td>{{ $row['asesor'] }}</td>
                                        <td>{{ $row['cliente'] }}</td>
                                        <td>{{ $row['detalle'] }}</td>
                                        <td class="text-end">{{ number_format($row['monto'], 2) }}</td>
                                        @if($isAdmin)
                                            <td class="text-center">
                                                @if($row['latitud'] && $row['longitud'])
                                                    <a target="_blank" href="https://maps.google.com/?q={{ $row['latitud'] }},{{ $row['longitud'] }}">
                                                        <i class="ti ti-world"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isAdmin ? 9 : 8 }}" class="text-center py-4 text-muted">
                                            No se encontraron pagos en el rango seleccionado
                                        </td>
                                    </tr>
                                @endforelse

                                {{-- Totales --}}
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td colspan="5" rowspan="6" class="text-center">Total</td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end">{{ number_format($totals['total'], 2) }}</td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Fijos</td>
                                    <td class="text-end">{{ number_format($totals['fijos'], 2) }}</td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center" style="color:red;">Otros</td>
                                    <td class="text-end">{{ number_format($totals['otros'], 2) }}</td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Capital</td>
                                    <td class="text-end">{{ number_format($totals['capital'], 2) }}</td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Interes</td>
                                    <td class="text-end">{{ number_format($totals['interes'], 2) }}</td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Mora</td>
                                    <td class="text-end">{{ number_format($totals['mora'], 2) }}</td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
