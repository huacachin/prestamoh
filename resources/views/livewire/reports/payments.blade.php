{{-- 22/10 (Antony): la raíz lleva el visor flotante de vouchers (partial _lightbox, contrato
     open / idx / items / close / next / prev) y el estado de la ventana "Direcciones" (dir),
     que cada fila llena con verDirecciones() y muestra el modal flotante del final. --}}
<div class="container-fluid"
     x-data="{
        open: false, idx: 0, items: [],
        verVouchers(items) { this.items = items; this.idx = 0; this.open = true; },
        close() { this.open = false; },
        next() { this.idx = (this.idx + 1) % this.items.length; },
        prev() { this.idx = (this.idx - 1 + this.items.length) % this.items.length; },
        dir: null,
        verDirecciones(d) { this.dir = d; this.$dispatch('direcciones-open'); },
     }">
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
                                    <input type="text" name="compra" autocomplete="off" class="form-control form-control-sm"
                                           wire:model.live.debounce.500ms="compra"
                                           placeholder="Ingrese el texto a buscar"
       list="hist_reporte_pagos" data-search-history="reporte_pagos">
<datalist id="hist_reporte_pagos" wire:ignore></datalist>
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Inicio</label>
                                    <input type="text" name="fei" autocomplete="off" class="form-control form-control-sm dates" wire:model.live.debounce.500ms="fei">
                                </div>
                                <div class="flex-shrink-0" style="width: 150px;">
                                    <label class="form-label mb-0 small">Fecha Fin</label>
                                    <input type="text" name="fef" autocomplete="off" class="form-control form-control-sm dates" wire:model.live.debounce.500ms="fef">
                                </div>
                                <button class="btn btn-sm btn-dark flex-shrink-0" wire:click="search">
                                    <i class="ti ti-search f-s-12"></i> Buscar
                                </button>
                                <a href="{{ route('exports.reports.payments', ['tipo' => $tipo, 'compra' => $compra, 'fei' => $fei, 'fef' => $fef, 'cliente' => $clienteId, 'credito' => $creditoId]) }}"
                                   target="_blank"
                                   class="btn btn-sm btn-success flex-shrink-0">
                                    <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                                </a>
                                <button type="button" class="btn btn-sm btn-secondary flex-shrink-0" onclick="window.print()">
                                    <i class="ti ti-printer f-s-12"></i>
                                </button>
                                <x-scroll-bottom-btn scrollable="#tabla-pagos-rep" />
                            </div>
                            {{-- Filtro por crédito o cliente (26/09, viene del cronograma / ficha):
                                 en su propia línea, alineado con los filtros. Con crédito, el reporte
                                 trae SOLO los pagos de ese crédito. --}}
                            @if($creditoFiltrado || $clienteFiltrado)
                                <div class="d-flex align-items-center gap-2 py-1 small flex-wrap">
                                    @if($creditoFiltrado)
                                        <span class="text-muted">Crédito:</span>
                                        <a href="{{ route('credits.show', $creditoFiltrado->id) }}" class="badge bg-dark text-decoration-none" style="font-size:11px;" title="Ir al crédito">
                                            <i class="ti ti-file-invoice"></i> #{{ $creditoFiltrado->id }}
                                        </a>
                                    @endif
                                    @if($clienteFiltrado)
                                        <span class="text-muted">Cliente:</span>
                                        <span class="badge bg-dark" style="font-size:11px;">
                                            <i class="ti ti-user"></i> {{ $clienteFiltrado->fullName() }}
                                        </span>
                                    @endif
                                    <a href="#" class="text-danger text-decoration-none" wire:click.prevent="quitarFiltro"
                                       title="Quitar el filtro (vuelve al reporte de hoy)">
                                        <i class="ti ti-x"></i> quitar filtro
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tabla --}}
                    <div id="tabla-pagos-rep" class="table-responsive" style="max-height: 650px; overflow:auto;">
                        <table class="table table-bordered table-striped table-hover table-autofit">
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
                                    {{-- 22/10 (Antony): foto del voucher del cobro (se abre en la ventana movible). --}}
                                    <th class="text-center" style="background:#949696;">Voucher</th>
                                    @if($isAdmin)
                                        {{-- Como en el legacy, solo para los niveles altos; ahora abre las
                                             direcciones del cliente (los cobros nuevos no traen coordenadas). --}}
                                        <th class="text-center">Map</th>
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
                                        <td class="text-center" style="white-space:nowrap;">
                                            @if($row['vouchers'])
                                                @php $nV = count($row['vouchers']); @endphp
                                                <button type="button" class="btn btn-link p-0 align-middle"
                                                        title="Ver voucher{{ $nV > 1 ? ' ('.$nV.' fotos)' : '' }}"
                                                        x-on:click="verVouchers(@js($row['vouchers']))">
                                                    <img src="{{ $row['vouchers'][0]['thumb'] }}" alt="Voucher" loading="lazy"
                                                         style="height:28px; width:auto; max-width:60px; border-radius:3px; border:1px solid #dee2e6; object-fit:cover;">
                                                    @if($nV > 1)
                                                        <span class="badge bg-secondary ms-1 align-middle">{{ $nV }}</span>
                                                    @endif
                                                </button>
                                            @endif
                                        </td>
                                        @if($isAdmin)
                                            <td class="text-center" style="white-space:nowrap;">
                                                @php
                                                    $dir = $row['direcciones'];
                                                    $nUb = $dir ? count($dir['ubicaciones']) : 0;
                                                @endphp
                                                @if($dir && ($nUb > 0 || $dir['direccion'] || $dir['pago']))
                                                    <button type="button" class="btn btn-link p-0 {{ $nUb ? 'text-success' : 'text-secondary' }}"
                                                            title="Ver direcciones{{ $nUb ? ' ('.$nUb.' con GPS)' : ' (sin GPS)' }}"
                                                            x-on:click="verDirecciones(@js($dir))">
                                                        <i class="ti {{ $nUb ? 'ti-map-pin' : 'ti-map-pin-off' }} f-s-16"></i>@if($nUb > 1)<sup>{{ $nUb }}</sup>@endif
                                                    </button>
                                                @else
                                                    <i class="ti ti-map-pin-off text-muted" title="Sin dirección registrada"></i>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isAdmin ? 10 : 9 }}" class="text-center py-4 text-muted">
                                            No se encontraron pagos en el rango seleccionado
                                        </td>
                                    </tr>
                                @endforelse

                                {{-- Totales --}}
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td colspan="5" rowspan="{{ $totals['excedente'] > 0 ? 7 : 6 }}" class="text-center">Total</td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end">{{ number_format($totals['total'], 2) }}</td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Fijos</td>
                                    <td class="text-end">{{ number_format($totals['fijos'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center" style="color:red;">Otros</td>
                                    <td class="text-end">{{ number_format($totals['otros'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Capital</td>
                                    <td class="text-end">{{ number_format($totals['capital'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Interes</td>
                                    <td class="text-end">{{ number_format($totals['interes'], 2) }}</td>
                                    <td></td>
                                    <td></td>
                                    @if($isAdmin)<td></td>@endif
                                </tr>
                                @if($totals['excedente'] > 0)
                                    <tr style="background-color:#f0f0f0; font-weight:500;">
                                        <td class="text-center">Excedente</td>
                                        <td class="text-end">{{ number_format($totals['excedente'], 2) }}</td>
                                        <td></td>
                                        <td></td>
                                        @if($isAdmin)<td></td>@endif
                                    </tr>
                                @endif
                                <tr style="background-color:#f0f0f0; font-weight:500;">
                                    <td class="text-center">Mora</td>
                                    <td class="text-end">{{ number_format($totals['mora'], 2) }}</td>
                                    <td></td>
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

    {{-- Ventana flotante "Direcciones" (modalFlotante, arrastrable; mismo patrón que la confirmación
         del cobro). Todo su contenido lo pinta Alpine con `dir`, por eso va con wire:ignore. --}}
    <div class="modal fade" id="direccionesModal" tabindex="-1" aria-hidden="true" wire:ignore
         x-data="modalFlotante('direcciones')"
         x-on:direcciones-open.window="modal.show()">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;" x-ref="dialogo">
            <div class="modal-content">
                <div class="modal-header py-2"
                     x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                     x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                    <h6 class="modal-title mb-0"><i class="ti ti-map-pin text-success"></i> Direcciones</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body py-2 small">
                    <template x-if="dir">
                        <div>
                            <div class="fw-semibold mb-2" x-text="dir.cliente"></div>
                            <div class="mb-2">
                                <div><span class="text-muted">Dirección:</span> <span x-text="dir.direccion || '—'"></span></div>
                                <div x-show="dir.referencia"><span class="text-muted">Referencia:</span> <span x-text="dir.referencia"></span></div>
                                <div x-show="dir.lugar"><span class="text-muted">Lugar:</span> <span x-text="dir.lugar"></span></div>
                                <div x-show="dir.zona"><span class="text-muted">Zona:</span> <span x-text="dir.zona"></span></div>
                            </div>
                            <div class="text-muted">Ubicaciones GPS</div>
                            <template x-if="! dir.ubicaciones.length">
                                <div class="fst-italic text-muted py-1">Sin coordenadas en la ficha (pestaña GPS).</div>
                            </template>
                            <template x-for="u in dir.ubicaciones" :key="u.id">
                                <div class="d-flex justify-content-between align-items-center gap-2 py-1 border-top">
                                    <div>
                                        <div class="fw-semibold" x-text="u.nombre"></div>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            <span x-text="u.coordenadas"></span><span x-show="u.fecha"> · <span x-text="u.fecha"></span></span>
                                        </div>
                                    </div>
                                    <a :href="u.url" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success text-nowrap">
                                        <i class="ti ti-world"></i> Google Maps
                                    </a>
                                </div>
                            </template>
                            <template x-if="dir.pago">
                                <div class="d-flex justify-content-between align-items-center gap-2 py-1 border-top">
                                    <div class="fw-semibold">Lugar del pago</div>
                                    <a :href="dir.pago" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary text-nowrap">
                                        <i class="ti ti-world"></i> Google Maps
                                    </a>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Visor flotante de vouchers: el mismo partial que caja, galerías y auditoría. --}}
    @include('livewire.cash.partials._lightbox')
<span id="final"></span>
</div>
