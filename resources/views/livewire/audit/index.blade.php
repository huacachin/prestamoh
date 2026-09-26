<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">AUDITORÍA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-shield-lock f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Seguridad</span></a>
                </li>
                <li class="breadcrumb-item active"><span>Auditoría</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-4 col-xl">
                            <label class="form-label mb-0 small">Desde</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="desde">
                        </div>
                        <div class="col-6 col-md-4 col-xl">
                            <label class="form-label mb-0 small">Hasta</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="hasta">
                        </div>
                        <div class="col-6 col-md-4 col-xl">
                            <label class="form-label mb-0 small">Usuario</label>
                            <select class="form-select form-select-sm" wire:model.live="causer">
                                <option value="">Todos</option>
                                @foreach($this->usuarios as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-4 col-xl">
                            <label class="form-label mb-0 small">Módulo</label>
                            {{-- Modelo afectado (subject_type), con las etiquetas de config/auditoria.php --}}
                            <select class="form-select form-select-sm" wire:model.live="modulo">
                                <option value="">Todos</option>
                                @foreach($this->modulos as $clase => $etiqueta)
                                    <option value="{{ $clase }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-4 col-xl">
                            <label class="form-label mb-0 small">Acción</label>
                            {{-- Réplica del filtro de acciones de newtaxivan: el tipo se
                                 deriva del event del registro automático o del verbo
                                 inicial de la descripción. --}}
                            <select class="form-select form-select-sm" wire:model.live="accion">
                                <option value="">Todas</option>
                                @foreach(\App\Livewire\Audit\Index::ACCIONES as $tipo => $cfg)
                                    <option value="{{ $tipo }}">{{ $cfg['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-4 col-xl">
                            <label class="form-label mb-0 small">Buscar acción</label>
                            <input type="text" class="form-control form-control-sm" placeholder="Ej. pago, anuló, usuario…"
                                   wire:model.live.debounce.400ms="buscar" autocomplete="off"
       list="hist_audit" data-search-history="audit">
<datalist id="hist_audit" wire:ignore></datalist>
                        </div>
                        <div class="col-12 col-xl-auto d-flex align-items-end">
                            <button class="btn btn-sm btn-secondary" wire:click="limpiar">
                                <i class="ti ti-eraser f-s-12"></i> Limpiar
                            </button>
                        </div>
                    </div>

                    {{-- El paginador va arriba Y abajo, y al cambiar de página se vuelve
                         al inicio de la lista (data-lista), no de la página entera. --}}
                    <div data-lista>
                    @if($logs->hasPages())
                        <div class="mb-2">
                            {{ $logs->links() }}
                        </div>
                    @endif
                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover table-sm">
                            <thead class="bg-primary">
                                <tr>
                                    <th style="width:150px;">Fecha / Hora</th>
                                    <th style="width:180px;">Usuario</th>
                                    <th>Acción</th>
                                    <th style="width:160px;">Afectado</th>
                                    <th style="width:70px;" class="text-center">Ver</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($logs as $log)
                                @php
                                    $tipoAccion = $this->clasificar($log->description, $log->event);
                                    $ctxUsuario = $log->user_name !== null ? ['nombre' => $log->user_name, 'username' => null] : ($log->properties['contexto']['usuario'] ?? null);
                                    $urlFicha = \App\Livewire\Audit\Index::urlFicha($log->subject_type, $log->subject_id);
                                    $conCambios = $log->attribute_changes?->isNotEmpty();
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($log->causer)
                                            {{ $log->causer->name }}
                                            <small class="text-muted">({{ $log->causer->username }})</small>
                                        @elseif(is_array($ctxUsuario) && ($ctxUsuario['nombre'] ?? $ctxUsuario['username'] ?? null))
                                            {{-- El usuario ya no existe: queda la copia guardada en el contexto --}}
                                            {{ $ctxUsuario['nombre'] ?? $ctxUsuario['username'] }}
                                            @if(!empty($ctxUsuario['username'])) <small class="text-muted">({{ $ctxUsuario['username'] }})</small> @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($tipoAccion)
                                            <span class="badge bg-{{ \App\Livewire\Audit\Index::ACCIONES[$tipoAccion]['badge'] }} me-1">
                                                {{ \App\Livewire\Audit\Index::ACCIONES[$tipoAccion]['label'] }}
                                            </span>
                                        @endif
                                        {{ $log->description }}
                                        @if($conCambios)
                                            <i class="ti ti-list-details text-primary ms-1" title="Tiene detalle de campos (antes / después)"></i>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if($log->subject_type)
                                            @if($urlFicha)
                                                <a href="{{ $urlFicha }}" target="_blank" rel="noopener" title="Abrir ficha en pestaña nueva">
                                                    {{ $this->etiquetaModulo($log->subject_type) }}
                                                    @if($log->subject_id) <span class="text-muted">#{{ $log->subject_id }}</span> @endif
                                                    <i class="ti ti-external-link f-s-12"></i>
                                                </a>
                                            @else
                                                {{ $this->etiquetaModulo($log->subject_type) }}
                                                @if($log->subject_id) <span class="text-muted">#{{ $log->subject_id }}</span> @endif
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2"
                                                wire:click="ver({{ $log->id }})" wire:loading.attr="disabled" wire:target="ver"
                                                title="Ver detalle del registro">
                                            <i class="ti ti-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-muted">No hay registros de auditoría para el filtro seleccionado</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $logs->links() }}
                    </div>
                    </div>{{-- /data-lista --}}
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Modal de detalle de un registro (Bootstrap 5, mismo patrón que los modales del área legal) ═══ --}}
    <div class="modal fade" id="auditDetalleModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);
                 $el.addEventListener('hidden.bs.modal', () => { if ($wire.detalle) $wire.cerrarDetalle(); });"
         x-on:audit-detalle-open.window="modal.show()"
         x-on:audit-detalle-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-list-details text-primary"></i>
                        Detalle del registro
                        @if($detalle) <span class="text-muted">#{{ $detalle['id'] }}</span> @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if($detalle)
                        <div class="row g-2 mb-2" style="font-size:13px;">
                            <div class="col-md-6">
                                <div><b>Fecha y hora:</b> {{ $detalle['fecha'] }}</div>
                                <div>
                                    <b>Usuario:</b>
                                    @if($detalle['usuario'])
                                        {{ $detalle['usuario']['nombre'] ?? '—' }}
                                        @if(!empty($detalle['usuario']['username'])) <span class="text-muted">({{ $detalle['usuario']['username'] }})</span> @endif
                                        @if(!empty($detalle['usuario']['rol'])) <span class="badge bg-light text-dark border">{{ $detalle['usuario']['rol'] }}</span> @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                                <div>
                                    <b>Módulo:</b>
                                    @if($detalle['modulo'])
                                        @if($detalle['subject_url'])
                                            <a href="{{ $detalle['subject_url'] }}" target="_blank" rel="noopener">
                                                {{ $detalle['modulo'] }}@if($detalle['subject_id']) #{{ $detalle['subject_id'] }}@endif
                                                <i class="ti ti-external-link f-s-12"></i>
                                            </a>
                                        @else
                                            {{ $detalle['modulo'] }}@if($detalle['subject_id']) #{{ $detalle['subject_id'] }}@endif
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div><b>IP:</b> {{ $detalle['ip'] ?? '—' }}</div>
                                <div class="text-break"><b>Navegador:</b> {{ $detalle['navegador'] ?? '—' }}</div>
                                <div><b>Ruta:</b> {{ $detalle['ruta'] ?? '—' }}</div>
                            </div>
                            <div class="col-12">
                                <b>Descripción:</b>
                                @if($detalle['accion'])
                                    <span class="badge bg-{{ $detalle['accion_badge'] }}">{{ $detalle['accion_label'] }}</span>
                                @endif
                                {{ $detalle['descripcion'] }}
                            </div>
                        </div>

                        @if($detalle['modo'] === 'updated')
                            <h6 class="mb-1" style="font-size:13px;"><i class="ti ti-arrows-exchange"></i> Campos modificados</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-2" style="font-size:12px;">
                                    <thead class="table-light">
                                        <tr><th style="width:30%;">Campo</th><th style="width:35%;">Antes</th><th style="width:35%;">Después</th></tr>
                                    </thead>
                                    <tbody>
                                    @forelse($detalle['cambios'] as $cambio)
                                        <tr>
                                            <td>{{ $cambio['campo'] }}</td>
                                            <td class="text-break"><pre class="mb-0 text-wrap font-monospace" style="font-size:12px;">{{ $cambio['antes'] }}</pre></td>
                                            <td class="text-break"><pre class="mb-0 text-wrap font-monospace" style="font-size:12px;">{{ $cambio['despues'] }}</pre></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted">Sin campos registrados</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @elseif($detalle['modo'] === 'created' || $detalle['modo'] === 'deleted')
                            <h6 class="mb-1" style="font-size:13px;">
                                <i class="ti ti-file-description"></i>
                                {{ $detalle['modo'] === 'created' ? 'Valores registrados' : 'Valores de la fila eliminada' }}
                            </h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-2" style="font-size:12px;">
                                    <thead class="table-light">
                                        <tr><th style="width:30%;">Campo</th><th>Valor</th></tr>
                                    </thead>
                                    <tbody>
                                    @forelse($detalle['valores'] as $fila)
                                        <tr>
                                            <td>{{ $fila['campo'] }}</td>
                                            <td class="text-break"><pre class="mb-0 text-wrap font-monospace" style="font-size:12px;">{{ $fila['valor'] }}</pre></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-center text-muted">Sin campos registrados</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if(count($detalle['propiedades']))
                            <h6 class="mb-1" style="font-size:13px;"><i class="ti ti-tags"></i> Propiedades</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm mb-0" style="font-size:12px;">
                                    <thead class="table-light">
                                        <tr><th style="width:30%;">Propiedad</th><th>Valor</th></tr>
                                    </thead>
                                    <tbody>
                                    @foreach($detalle['propiedades'] as $prop)
                                        <tr>
                                            <td>{{ $prop['campo'] }}</td>
                                            <td class="text-break"><pre class="mb-0 text-wrap font-monospace" style="font-size:12px;">{{ $prop['valor'] }}</pre></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        <div class="text-center text-muted py-3" wire:loading wire:target="ver">Cargando…</div>
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
