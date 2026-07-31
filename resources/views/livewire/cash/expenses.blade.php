<div class="container-fluid"
     x-data="{
        open: false, idx: 0, items: [], manageUrl: '',
        openLightbox(items, manageUrl) {
            if (! items || ! items.length) return;
            this.items = items;
            this.manageUrl = manageUrl || '';
            this.idx = 0;
            this.open = true;
        },
        close() { this.open = false; },
        next() { this.idx = (this.idx + 1) % this.items.length; },
        prev() { this.idx = (this.idx - 1 + this.items.length) % this.items.length; },
     }"
     @keydown.escape.window="open && close()"
     @keydown.arrow-right.window="open && next()"
     @keydown.arrow-left.window="open && prev()">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">EGRESOS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Egresos</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <form wire:submit.prevent="$refresh">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-5">
                                <label class="form-label mb-0 small"><b>BUSCAR X</b></label>
                                <div class="d-flex gap-3 mb-1">
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.defer="tipo" value="1" id="tipoA">
                                        <label class="form-check-label small" for="tipoA">A</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.defer="tipo" value="2" id="tipoMotivo">
                                        <label class="form-check-label small" for="tipoMotivo">Motivo</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.defer="tipo" value="3" id="tipoUsuario">
                                        <label class="form-check-label small" for="tipoUsuario">Usuario</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.defer="tipo" value="4" id="tipoRespons">
                                        <label class="form-check-label small" for="tipoRespons">Respons.</label>
                                    </div>
                                </div>
                                <input type="text" name="compra" autocomplete="off" class="form-control form-control-sm"
                                       wire:model.defer="compra"
                                       placeholder="Ingrese el texto a buscar">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Fecha Inicio</b></label>
                                <input type="text" name="fei" autocomplete="off" class="form-control form-control-sm dates" wire:model.defer="fei">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Fecha Fin</b></label>
                                <input type="text" name="fef" autocomplete="off" class="form-control form-control-sm dates" wire:model.defer="fef">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @can('caja.egresos')
                                <a href="{{ route('cash.expenses.create') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-plus f-s-12"></i> Agregar Nuevo
                                </a>
                            @endcan
                            <a href="{{ route('exports.expenses', ['tipo' => $tipo, 'compra' => $compra, 'fei' => $fei, 'fef' => $fef]) }}"
                               target="_blank"
                               class="btn btn-sm btn-success">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                        </div>
                    </form>

                    @php
                        $puedeEditarHistorico = auth()->user()->can('caja.editar-historico');
                        $puedeEliminar       = auth()->user()->can('caja.eliminar');
                        $userId = auth()->id();
                        $hoy = now()->format('Y-m-d');
                    @endphp

                    {{-- Tabla Desktop con FAB scroll --}}
                    <div class="position-relative d-none d-md-block">
                        <div id="expensesTable" class="table-responsive" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-hover table-autofit expenses-legacy">
                            <thead style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="text-center" style="background:#949696;">Op.</th>
                                    <th class="text-center" style="background:#005F8C;">N°</th>
                                    <th class="text-center" style="background:#949696;"><i class="ti ti-camera"></i></th>
                                    <th class="text-center col-fecha" style="background:#005F8C;">Fecha</th>
                                    <th class="text-center" style="background:#949696;">Usuario</th>
                                    <th class="text-center" style="background:#005F8C;">A</th>
                                    <th class="col-wrap" style="background:#949696;">Motivo</th>
                                    <th class="text-end" style="background:#005F8C;">S/.</th>
                                    <th class="text-center" style="background:#949696;">T.Comp.</th>
                                    <th class="text-center" style="background:#005F8C;">Respons.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($expenses as $expense)
                                @php
                                    // Legacy: filas alternadas $color = ["#ffffff", "#F2F2EC"]
                                    // ($contador % 2): 1ª fila (contador=1) => #F2F2EC, 2ª => #ffffff
                                    $rowBg = ($loop->iteration % 2 === 0) ? '#ffffff' : '#F2F2EC';
                                    $rowColor = ($expense->modo === 'Otros') ? 'color: red;' : '';
                                    $canEdit = $puedeEditarHistorico || (
                                        $expense->date?->format('Y-m-d') === $hoy
                                        && $expense->user_id === $userId
                                    );
                                @endphp
                                <tr style="background-color: {{ $rowBg }}; {{ $rowColor }}"
                                    data-bg="{{ $rowBg }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=this.getAttribute('data-bg')">
                                    <td class="text-center">
                                        @if($canEdit)
                                            <a href="{{ route('cash.expenses.edit', $expense->id) }}" title="Editar">
                                                <i class="ti ti-edit f-s-16 text-primary"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">
                                        @if(isset($galleries[$expense->id]))
                                            {{-- Tiene fotos → abre el lightbox inline. Los items se pasan
                                                 inline (no en un mapa de x-data) para que Livewire los
                                                 regenere frescos en cada render/filtro. --}}
                                            <a href="#"
                                               @click.prevent="openLightbox({{ \Illuminate\Support\Js::from($galleries[$expense->id]['items']) }}, {{ \Illuminate\Support\Js::from($galleries[$expense->id]['manageUrl']) }})"
                                               title="Ver adjuntos ({{ $expense->attachments_count ?? 0 }})"
                                               style="cursor: zoom-in;">
                                                <i class="ti ti-camera-filled f-s-16 text-info"></i>
                                                @if(($expense->attachments_count ?? 0) > 0)
                                                    <span class="badge bg-info" style="font-size:9px; padding:1px 4px;">
                                                        {{ $expense->attachments_count }}
                                                    </span>
                                                @endif
                                            </a>
                                        @else
                                            {{-- Sin fotos → va a la galería para subir --}}
                                            <a href="{{ route('cash.expenses.gallery', $expense->id) }}"
                                               title="Subir adjuntos">
                                                <i class="ti ti-camera f-s-16 text-muted"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center col-fecha">{{ $expense->date?->format('d/m/Y') }}</td>
                                    <td>{{ $expense->user?->username ?? $expense->user?->name ?? '-' }}</td>
                                    <td class="text-center">{{ $expense->reason }}</td>
                                    <td class="col-wrap">{{ $expense->detail }}</td>
                                    <td class="text-end fw-bold">{{ number_format($expense->total, 2) }}</td>
                                    <td class="text-center">{{ $expense->document_type }}</td>
                                    <td>{{ $expense->in_charge }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="expenses-foot">
                                <tr>
                                    <td colspan="7" class="text-end fw-bold">Total General:</td>
                                    <td class="text-end fw-bold">{{ number_format($totalGeneral, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end"><b>Fijos:</b></td>
                                    <td class="text-end fw-bold"><font color="red">{{ number_format($tofijo, 2) }}</font></td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end"><b><font color="red">Otros:</font></b></td>
                                    <td class="text-end fw-bold">{{ number_format($totros, 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>Diario</b></td>
                                    <td class="text-end fw-bold">{{ number_format($sumdiario, 2) }}</td>
                                    <td colspan="2" class="text-end fw-bold">{{ number_format($valor1, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>Mensual</b></td>
                                    <td class="text-end fw-bold">{{ number_format($summensu, 2) }}</td>
                                    <td colspan="2" class="text-end fw-bold">{{ number_format($valor2, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>D.M</b></td>
                                    <td class="text-end fw-bold">{{ number_format($sumdm, 2) }}</td>
                                    <td colspan="2" class="text-end fw-bold">{{ number_format($sumdm/2, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6"></td>
                                    <td class="text-center"><b>Fijos Total</b></td>
                                    <td colspan="3" class="text-end fw-bold"><font color="red">{{ number_format($valor3, 2) }}</font></td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>{{-- /#expensesTable --}}

                        {{-- Botón flotante toggle para scroll dentro de la tabla (1er clic baja, 2do sube) --}}
                        <div class="expenses-fab">
                            <button type="button"
                                    class="btn btn-sm btn-primary rounded-circle shadow"
                                    data-scroll-sel="#expensesTable"
                                    data-scroll-cont="1"
                                    title="Ir al final">
                                <i class="ti ti-chevron-down"></i>
                            </button>
                        </div>
                    </div>{{-- /position-relative --}}

                    <style>
                        /* Colores legacy (gastos.php): cabecera azul/gris, filas blanco/#F2F2EC */
                        .expenses-legacy thead th { color: #fff; }
                        /* Homologado al legacy (gastos.php + ideasweb.css .texto/.tableM): fuente
                           Tahoma compacta — filas 12px, cabecera 10px mayúsculas. Con los anchos en %
                           esto reproduce el ancho de texto del sistema viejo y evita el salto a 2
                           líneas que provoca la fuente (más ancha) del tema. A y Motivo van sin ancho
                           (absorben el sobrante), igual que el legacy. */
                        .expenses-legacy { font-family: Tahoma, Verdana, Geneva, sans-serif; }
                        /* Cada columna se dimensiona a su contenido (tbody) en una sola línea; si la
                           tabla excede el ancho, el contenedor .table-responsive da scroll horizontal
                           (igual que el .table-scroll del legacy). Sin anchos en % que aprieten. */
                        /* El !important es obligado: _custom.scss define un global
                           `th, td { white-space: normal !important }` que gana a cualquier
                           selector por especifico que sea. Sin esto las columnas parten
                           a dos lineas aunque se les fije nowrap. */
                        .expenses-legacy th, .expenses-legacy td {
                            padding: 3px 6px; vertical-align: middle;
                            white-space: nowrap !important;
                        }
                        /* Motivo/Detalle: la unica columna que envuelve, y la que absorbe
                           todo el ancho sobrante de la tabla. El width:100% sobre una sola
                           columna es el modo de lograrlo: las demas van en nowrap y no
                           pueden encogerse por debajo de su texto. El min-width es el suelo
                           cuando la tabla se estrecha; sin max-width, para poder crecer. */
                        .expenses-legacy th.col-wrap, .expenses-legacy td.col-wrap {
                            white-space: normal !important;
                            width: 100%;
                            min-width: 260px;
                            max-width: none;
                            overflow-wrap: break-word;
                        }
                        /* Fecha: un poco más de aire. */
                        .expenses-legacy th.col-fecha, .expenses-legacy td.col-fecha { min-width: 92px; }
                        /* tfoot legacy: texto negro sobre fondo claro (no azul) */
                        .expenses-legacy tfoot.expenses-foot td {
                            color: #000;
                            background-color: #F2F2EC;
                            position: -webkit-sticky;
                            position: sticky;
                            bottom: 0;
                            z-index: 3;
                        }
                        .expenses-fab {
                            position: fixed;
                            bottom: 24px;
                            left: 24px;
                            display: flex;
                            flex-direction: column;
                            gap: 8px;
                            z-index: 1050;
                        }
                        .expenses-fab .btn {
                            width: 42px; height: 42px; padding: 0;
                            display: inline-flex; align-items: center; justify-content: center;
                            opacity: 0.9;
                            transition: opacity .15s ease, transform .15s ease;
                        }
                        .expenses-fab .btn:hover { opacity: 1; transform: scale(1.08); }
                        .expenses-fab .ti { font-size: 18px; }
                    </style>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($expenses as $expense)
                            @php
                                $isOtros = $expense->modo === 'Otros';
                                $canEdit = $puedeEditarHistorico || (
                                    $expense->date?->format('Y-m-d') === $hoy
                                    && $expense->user_id === $userId
                                );
                            @endphp
                            <div class="card mb-2 shadow-sm {{ $isOtros ? 'border-danger' : '' }}">
                                <div class="card-body p-3" style="{{ $isOtros ? 'color: red;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="mb-0">{{ $expense->reason }}</h6>
                                            <small class="text-muted">{{ $expense->date?->format('d/m/Y') }}</small>
                                        </div>
                                        <span class="badge bg-primary">S/ {{ number_format($expense->total, 2) }}</span>
                                    </div>
                                    <div class="row g-1 mt-1" style="font-size: 12px;">
                                        <div class="col-12"><b>Motivo:</b> {{ $expense->detail }}</div>
                                        <div class="col-6"><b>Usuario:</b> {{ $expense->user?->username ?? '-' }}</div>
                                        <div class="col-6"><b>T.Comp.:</b> {{ $expense->document_type ?: '-' }}</div>
                                        <div class="col-12"><b>Respons.:</b> {{ $expense->in_charge ?: '-' }}</div>
                                    </div>
                                    @if($canEdit)
                                        <div class="mt-2">
                                            <a href="{{ route('cash.expenses.edit', $expense->id) }}" class="btn btn-xs btn-outline-primary" style="padding: 2px 8px; font-size: 10px;">
                                                <i class="ti ti-edit"></i> Editar
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No se encontraron resultados</div>
                        @endforelse
                        <div class="card mt-2">
                            <div class="card-body p-2" style="font-size: 12px;">
                                <div><b>Total General:</b> S/ {{ number_format($totalGeneral, 2) }}</div>
                                <div style="color: red;"><b>Fijos:</b> S/ {{ number_format($tofijo, 2) }}</div>
                                <div><b>Otros:</b> S/ {{ number_format($totros, 2) }}</div>
                                <hr class="my-1">
                                <div><b>Diario:</b> S/ {{ number_format($sumdiario, 2) }} → {{ number_format($valor1, 2) }}</div>
                                <div><b>Mensual:</b> S/ {{ number_format($summensu, 2) }} → {{ number_format($valor2, 2) }}</div>
                                <div><b>D.M:</b> S/ {{ number_format($sumdm, 2) }} → {{ number_format($sumdm/2, 2) }}</div>
                                <div style="color: red;"><b>Fijos Total:</b> S/ {{ number_format($valor3, 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<span id="final"></span>

    {{-- Lightbox compartido (visor de adjuntos al hacer clic en la cámara) --}}
    @include('livewire.cash.partials._lightbox')
</div>
