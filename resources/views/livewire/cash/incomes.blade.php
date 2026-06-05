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
            <h4 class="main-title title-modules" style="color:red;">INGRESOS</h4>
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
                    <a href="#" class="f-s-14">Ingresos</a>
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
                                        <input class="form-check-input me-1" type="radio" wire:model.defer="tipo" value="3" id="tipoAsesor">
                                        <label class="form-check-label small" for="tipoAsesor">Asesor</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input me-1" type="radio" wire:model.defer="tipo" value="4" id="tipoUsuario">
                                        <label class="form-check-label small" for="tipoUsuario">Usuario</label>
                                    </div>
                                </div>
                                <input type="text" class="form-control form-control-sm"
                                       wire:model.defer="compra"
                                       placeholder="Ingrese el texto a buscar">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Fecha Inicio</b></label>
                                <input type="text" autocomplete="off" class="form-control form-control-sm dates" wire:model.defer="fei">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Fecha Fin</b></label>
                                <input type="text" autocomplete="off" class="form-control form-control-sm dates" wire:model.defer="fef">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @can('caja.ingresos')
                                <a href="{{ route('cash.incomes.create') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-plus f-s-12"></i> Agregar Nuevo
                                </a>
                            @endcan
                            <a href="{{ route('exports.incomes', ['tipo' => $tipo, 'compra' => $compra, 'fei' => $fei, 'fef' => $fef]) }}"
                               target="_blank"
                               class="btn btn-sm btn-success">
                                <i class="ti ti-file-spreadsheet f-s-12"></i> Excel
                            </a>
                                <x-scroll-bottom-btn scrollable="#incomesTable" />
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
                        <div id="incomesTable" class="table-responsive" style="max-height: 70vh; overflow: auto;">
                        <table class="table table-bordered table-hover table-autofit incomes-legacy">
                            <thead style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="text-center" style="background:#949696;">Op</th>
                                    <th class="text-center" style="background:#005F8C;">N°</th>
                                    <th class="text-center" style="background:#949696;"><i class="ti ti-camera"></i></th>
                                    <th class="text-center col-fecha" style="background:#005F8C;">Fecha</th>
                                    <th class="text-center" style="background:#949696;">Usuario</th>
                                    <th class="text-center" style="background:#005F8C;">Asesor</th>
                                    <th class="text-center" style="background:#949696;">A</th>
                                    <th class="col-wrap" style="background:#005F8C;">Motivo</th>
                                    <th class="text-end" style="background:#949696;">S/.</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($rows as $row)
                                @php
                                    // Legacy: striping $color = ["#ffffff", "#F2F2EC"], $contador++ ANTES de pintar
                                    // → 1ª fila (iteration=1) => #F2F2EC, 2ª => #ffffff
                                    $rowBg = ($loop->iteration % 2 === 0) ? '#ffffff' : '#F2F2EC';
                                    // Legacy: solo modo='Otros' en rojo. CREDITO no recibe color especial.
                                    $rowColor = ($row['modo'] === 'Otros') ? 'color: red;' : '';
                                    $canEdit = $row['editable'] && ($puedeEditarHistorico || (
                                        $row['date']?->format('Y-m-d') === $hoy
                                        && $row['user_id'] === $userId
                                    ));
                                @endphp
                                <tr style="background-color: {{ $rowBg }}; {{ $rowColor }}"
                                    data-bg="{{ $rowBg }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=this.getAttribute('data-bg')">
                                    <td class="text-center">
                                        @if($canEdit)
                                            <a href="{{ route('cash.incomes.edit', $row['id']) }}" title="Editar">
                                                <i class="ti ti-edit f-s-16 text-primary"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">
                                        @if($row['kind'] === 'income')
                                            @if(isset($galleries[$row['id']]))
                                                {{-- Tiene fotos → abre el lightbox inline. Items inline
                                                     (no mapa x-data) para que Livewire los regenere
                                                     frescos en cada render/filtro. --}}
                                                <a href="#"
                                                   @click.prevent="openLightbox({{ \Illuminate\Support\Js::from($galleries[$row['id']]['items']) }}, {{ \Illuminate\Support\Js::from($galleries[$row['id']]['manageUrl']) }})"
                                                   title="Ver adjuntos ({{ $row['attachments_count'] ?? 0 }})"
                                                   style="cursor: zoom-in;">
                                                    <i class="ti ti-camera-filled f-s-16 text-info"></i>
                                                    @if(($row['attachments_count'] ?? 0) > 0)
                                                        <span class="badge bg-info" style="font-size:9px; padding:1px 4px;">
                                                            {{ $row['attachments_count'] }}
                                                        </span>
                                                    @endif
                                                </a>
                                            @else
                                                {{-- Sin fotos → va a la galería para subir --}}
                                                <a href="{{ route('cash.incomes.gallery', $row['id']) }}"
                                                   title="Subir adjuntos">
                                                    <i class="ti ti-camera f-s-16 text-muted"></i>
                                                </a>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-center col-fecha">{{ $row['date']?->format('d/m/Y') }}</td>
                                    <td>{{ $row['usuario'] }}</td>
                                    <td>{{ $row['asesor'] }}</td>
                                    <td class="text-center">{{ $row['reason'] }}</td>
                                    <td class="col-wrap">{{ $row['detail'] }}</td>
                                    <td class="text-end fw-bold">{{ number_format($row['total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-4 text-muted text-center">No se encontraron resultados</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="incomes-foot">
                                <tr>
                                    <td colspan="5" rowspan="6" class="text-center align-middle fw-bold" style="font-size: 14px;">Total</td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end fw-bold">{{ number_format($totalGeneral, 2) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Fijos</b></td>
                                    <td class="text-end fw-bold">{{ number_format($tofijo, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    {{-- Legacy: solo el rótulo "Otros" va en rojo (<font color=red>), el monto no --}}
                                    <td class="text-center"><b><font color="red">Otros</font></b></td>
                                    <td class="text-end fw-bold">{{ number_format($totros, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Capital</b></td>
                                    <td class="text-end fw-bold">{{ number_format($tocapi, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Interes</b></td>
                                    <td class="text-end fw-bold">{{ number_format($totinte, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="text-center"><b>Mora</b></td>
                                    <td class="text-end fw-bold">{{ number_format($totmora, 2) }}</td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        </div>{{-- /#incomesTable --}}

                        {{-- Botones flotantes para scroll dentro de la tabla --}}
                        <div class="incomes-fab">
                            <button type="button"
                                    class="btn btn-sm btn-dark rounded-circle shadow"
                                    title="Ir al inicio"
                                    onclick="document.getElementById('incomesTable').scrollTo({top:0, behavior:'smooth'})">
                                <i class="ti ti-chevron-up"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-primary rounded-circle shadow"
                                    title="Ver totales (ir al final)"
                                    onclick="document.getElementById('incomesTable').scrollTo({top:document.getElementById('incomesTable').scrollHeight, behavior:'smooth'})">
                                <i class="ti ti-chevron-down"></i>
                            </button>
                        </div>
                    </div>{{-- /position-relative --}}

                    <style>
                        /* Colores legacy (ingresos.php): cabecera azul/gris, filas blanco/#F2F2EC */
                        .incomes-legacy thead th { color: #fff; }
                        /* Homologado al legacy (ingresos.php + ideasweb.css .texto/.tableM): fuente
                           Tahoma compacta — filas 12px, cabecera 10px mayúsculas. Con los anchos en %
                           reproduce el ancho de texto del sistema viejo y evita el salto a 2 líneas
                           que provoca la fuente (más ancha) del tema. A y Motivo van sin ancho
                           (absorben el sobrante), igual que el legacy. */
                        .incomes-legacy { font-family: Tahoma, Verdana, Geneva, sans-serif; }
                        /* Cada columna se dimensiona a su contenido (tbody) en una sola línea; si la
                           tabla excede el ancho, el contenedor .table-responsive da scroll horizontal
                           (igual que el .table-scroll del legacy). Sin anchos en % que aprieten. */
                        .incomes-legacy th, .incomes-legacy td {
                            padding: 3px 6px; vertical-align: top; white-space: nowrap;
                        }
                        .incomes-legacy td { font-size: 12px; }
                        .incomes-legacy th { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
                        /* Motivo/Detalle: texto largo → envuelve (como el legacy), con tope de ancho. */
                        .incomes-legacy th.col-wrap, .incomes-legacy td.col-wrap {
                            white-space: normal; min-width: 180px; max-width: 320px;
                        }
                        /* Fecha: un poco más de aire. */
                        .incomes-legacy th.col-fecha, .incomes-legacy td.col-fecha { min-width: 92px; }
                        /* tfoot legacy: texto negro sobre fondo claro */
                        .incomes-legacy tfoot.incomes-foot td {
                            color: #000;
                            background-color: #F2F2EC;
                            position: -webkit-sticky;
                            position: sticky;
                            bottom: 0;
                            z-index: 3;
                        }
                        .incomes-fab {
                            position: fixed;
                            bottom: 24px;
                            left: 24px;
                            display: flex;
                            flex-direction: column;
                            gap: 8px;
                            z-index: 1050;
                        }
                        .incomes-fab .btn {
                            width: 42px;
                            height: 42px;
                            padding: 0;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            opacity: 0.9;
                            transition: opacity .15s ease, transform .15s ease;
                        }
                        .incomes-fab .btn:hover {
                            opacity: 1;
                            transform: scale(1.08);
                        }
                        .incomes-fab .ti { font-size: 18px; }
                    </style>

                    {{-- Cards Mobile --}}
                    <div class="d-md-none">
                        @forelse($rows as $row)
                            @php
                                $isOtros = $row['modo'] === 'Otros';
                                $isGat = stripos((string)($row['reason'] ?? ''), 'Gat') !== false;
                                $canEdit = $row['editable']
                                    && !$isGat
                                    && ($puedeEditarHistorico || (
                                        $row['date']?->format('Y-m-d') === $hoy
                                        && $row['user_id'] === $userId
                                    ));
                            @endphp
                            <div class="card mb-2 shadow-sm {{ $isOtros ? 'border-danger' : '' }}">
                                <div class="card-body p-3" style="{{ $isOtros ? 'color: red;' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="mb-0">{{ $row['reason'] }}</h6>
                                            <small class="text-muted">{{ $row['date']?->format('d/m/Y') }}</small>
                                        </div>
                                        <span class="badge bg-primary">S/ {{ number_format($row['total'], 2) }}</span>
                                    </div>
                                    <div class="row g-1 mt-1" style="font-size: 12px;">
                                        <div class="col-12"><b>Motivo:</b> {{ $row['detail'] }}</div>
                                        <div class="col-6"><b>Usuario:</b> {{ $row['usuario'] ?: '-' }}</div>
                                        <div class="col-6"><b>Asesor:</b> {{ $row['asesor'] ?: '-' }}</div>
                                    </div>
                                    @if($canEdit)
                                        <div class="mt-2">
                                            <a href="{{ route('cash.incomes.edit', $row['id']) }}" class="btn btn-xs btn-outline-primary" style="padding: 2px 8px; font-size: 10px;">
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
                                <div><b>Fijos:</b> S/ {{ number_format($tofijo, 2) }}</div>
                                <div style="color: red;"><b>Otros:</b> S/ {{ number_format($totros, 2) }}</div>
                                <div><b>Capital:</b> S/ {{ number_format($tocapi, 2) }}</div>
                                <div><b>Interés:</b> S/ {{ number_format($totinte, 2) }}</div>
                                <div><b>Mora:</b> S/ {{ number_format($totmora, 2) }}</div>
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
