<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">TRÁMITES NOTARIALES</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Área Legal</span></a>
                </li>
                <li class="d-flex active"><a href="{{ route('legal.notaria') }}" class="f-s-14">Notaría</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    @php
                        $badgeEstados = [
                            'firmado_oficina' => 'bg-secondary',
                            'en_notaria' => 'bg-info text-dark',
                            'firmado' => 'bg-primary',
                            'por_recoger' => 'bg-warning text-dark',
                            'recogido' => 'bg-success',
                            'archivado' => 'bg-dark',
                            'no_firmo' => 'bg-danger',
                        ];
                    @endphp

                    {{-- Contadores por estado (clicables: filtran el listado) --}}
                    <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                        @foreach(\App\Models\TramiteNotarial::ESTADOS as $clave => $etiqueta)
                            <button type="button"
                                    class="badge border-0 {{ $badgeEstados[$clave] ?? 'bg-secondary' }} {{ $filtroEstado !== '' && $filtroEstado !== $clave ? 'opacity-50' : '' }}"
                                    style="cursor:pointer; {{ $filtroEstado === $clave ? 'outline: 2px solid #212529;' : '' }}"
                                    title="{{ $filtroEstado === $clave ? 'Quitar filtro' : 'Filtrar por '.$etiqueta }}"
                                    wire:click="filtrarEstado('{{ $clave }}')">
                                {{ $etiqueta }}: {{ $porEstado[$clave] ?? 0 }}
                            </button>
                        @endforeach

                        <span class="vr mx-1"></span>

                        {{-- Alerta de varados: lo que el Excel nunca mostró --}}
                        <button type="button"
                                class="badge border-0 bg-danger {{ $soloVarados ? '' : ($varadosCount === 0 ? 'opacity-50' : '') }}"
                                style="cursor:pointer; font-size:12px; {{ $soloVarados ? 'outline: 2px solid #212529;' : '' }}"
                                title="Trámites abiertos con {{ \App\Models\TramiteNotarial::DIAS_VARADO }}+ días sin moverse (clic para filtrar)"
                                wire:click="alternarVarados">
                            <i class="ti ti-alert-triangle"></i>
                            VARADOS ({{ \App\Models\TramiteNotarial::DIAS_VARADO }}+ días): {{ $varadosCount }}
                        </button>
                    </div>

                    {{-- Filtros --}}
                    <form wire:submit.prevent="$refresh">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Buscar</b></label>
                                <input type="text" class="form-control form-control-sm" autocomplete="off"
                                       wire:model.live.debounce.300ms="buscar"
                                       placeholder="Cliente (nombre/documento), placa o descripción">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Estado</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroEstado">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\TramiteNotarial::ESTADOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Tipo</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroTipo">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\TramiteNotarial::TIPOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="filtroVarados"
                                           wire:model.live="soloVarados">
                                    <label class="form-check-label small" for="filtroVarados">
                                        Solo varados
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @can('legal.notaria')
                                <button type="button" class="btn btn-sm btn-danger" wire:click="nuevo">
                                    <i class="ti ti-plus f-s-12"></i> Nuevo trámite
                                </button>
                            @endcan
                        </div>
                    </form>

                    {{-- Tabla --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="40">N°</th>
                                    <th width="130">Tipo</th>
                                    <th>Cliente / Descripción</th>
                                    <th class="text-center" width="110">Garantía</th>
                                    <th width="130">Notaría</th>
                                    <th class="text-center" width="110">Estado</th>
                                    <th class="text-center" width="90">Días en estado</th>
                                    <th width="110">Responsable</th>
                                    <th class="text-center" width="130">Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($tramites as $t)
                                @php
                                    $dias = $t->diasEnEstado();
                                    $abierto = in_array($t->estado, \App\Models\TramiteNotarial::ESTADOS_ABIERTOS, true);
                                    $varado = $abierto && $dias >= \App\Models\TramiteNotarial::DIAS_VARADO;
                                    $transiciones = \App\Models\TramiteNotarial::TRANSICIONES[$t->estado] ?? [];
                                @endphp
                                <tr wire:key="tramite-{{ $t->id }}">
                                    <td class="text-center fw-bold">{{ $t->id }}</td>
                                    <td>
                                        {{ \App\Models\TramiteNotarial::TIPOS[$t->tipo] ?? $t->tipo }}
                                        @if($t->requiere_revision)
                                            <i class="ti ti-alert-triangle text-warning f-s-14"
                                               title="Importado con datos por revisar"></i>
                                        @endif
                                    </td>
                                    <td>
                                        @if($t->client)
                                            {{ $t->client->fullName() }}
                                            @if($t->descripcion)
                                                <div class="text-muted" style="font-size:10px;">{{ $t->descripcion }}</div>
                                            @endif
                                        @else
                                            {{ $t->descripcion ?: '—' }}
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($t->garantia_id)
                                            <a href="{{ route('legal.garantias.show', $t->garantia_id) }}"
                                               class="fw-bold" title="Ver la garantía">
                                                #{{ $t->garantia_id }}
                                            </a>
                                            @foreach($t->garantia?->vehiculos ?? [] as $v)
                                                <span class="badge bg-light text-dark border d-block mx-auto mb-1" style="width:fit-content;">
                                                    {{ $v->placa }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $t->notaria ?: '—' }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeEstados[$t->estado] ?? 'bg-secondary' }}">
                                            {{ \App\Models\TramiteNotarial::ESTADOS[$t->estado] ?? $t->estado }}
                                        </span>
                                    </td>
                                    <td class="text-center {{ $varado ? 'bg-danger text-white fw-bold' : '' }}"
                                        @if($varado) title="Varado: {{ $dias }} días sin moverse (límite {{ \App\Models\TramiteNotarial::DIAS_VARADO }})" @endif>
                                        {{ $dias }}
                                        <div style="font-size:9px;" class="{{ $varado ? 'text-white' : 'text-muted' }}">
                                            desde {{ $t->estado_desde->format('d/m/Y') }}
                                        </div>
                                    </td>
                                    <td>{{ $t->responsable?->name ?? '—' }}</td>
                                    <td class="text-center">
                                        @can('legal.notaria')
                                            <div class="d-flex justify-content-center gap-1">
                                                @if(count($transiciones))
                                                    <div class="btn-group">
                                                        <button type="button"
                                                                class="btn btn-xs btn-primary dropdown-toggle"
                                                                style="padding: 2px 8px; font-size: 10px;"
                                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                            Avanzar
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end" style="font-size: 11px;">
                                                            @foreach($transiciones as $destino)
                                                                <li>
                                                                    <button type="button" class="dropdown-item py-1"
                                                                            wire:click="avanzar({{ $t->id }}, '{{ $destino }}')">
                                                                        <i class="ti ti-arrow-right f-s-12"></i>
                                                                        {{ \App\Models\TramiteNotarial::ESTADOS[$destino] ?? $destino }}
                                                                    </button>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                                <button type="button" class="btn btn-xs btn-success"
                                                        style="padding: 2px 8px; font-size: 10px;"
                                                        wire:click="editar({{ $t->id }})">
                                                    Editar
                                                </button>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-4 text-muted text-center">No se encontraron trámites notariales</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="8">TOTAL</td>
                                    <td class="text-center fw-bold">{{ $tramites->total() }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{ $tramites->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Modal crear/editar trámite notarial ═══ --}}
    <div class="modal fade" id="tramiteModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:tramite-modal-open.window="modal.show()"
         x-on:tramite-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-writing-sign text-danger"></i>
                        {{ $editingId ? "Editar trámite notarial #{$editingId}" : 'Nuevo trámite notarial' }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="guardar">
                        {{-- Documento: garantía o cliente + descripción --}}
                        <div class="border rounded p-2 mb-3" style="background:#f8f9fa;">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small"><b>Tipo de trámite</b> <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('tipo') is-invalid @enderror"
                                            wire:model="tipo">
                                        @foreach(\App\Models\TramiteNotarial::TIPOS as $clave => $etiqueta)
                                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label mb-0 small"><b>Garantía asociada</b> <span class="text-muted">(opcional)</span></label>
                                    @if($garantia_id)
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-dark" style="font-size:11px;">
                                                <i class="ti ti-scale"></i> {{ $garantiaLabel }}
                                            </span>
                                            @unless($editingId)
                                                <button type="button" class="btn btn-xs btn-outline-danger"
                                                        style="padding: 1px 6px; font-size: 10px;"
                                                        wire:click="quitarGarantia">
                                                    <i class="ti ti-x"></i> Quitar
                                                </button>
                                            @endunless
                                        </div>
                                    @elseif($editingId)
                                        <div class="form-text mt-1" style="font-size:10px;">Sin garantía asociada.</div>
                                    @else
                                        <input type="text" class="form-control form-control-sm @error('garantia_id') is-invalid @enderror"
                                               autocomplete="off"
                                               wire:model.live.debounce.400ms="garantiaBusqueda"
                                               placeholder="Busca por placa, cliente o N° de garantía (mín. 2 caracteres)">
                                        @error('garantia_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                        @if($garantiasEncontradas->isNotEmpty())
                                            <div class="list-group mt-1" style="max-height: 160px; overflow-y: auto;">
                                                @foreach($garantiasEncontradas as $garantia)
                                                    <button type="button"
                                                            class="list-group-item list-group-item-action py-1 px-2"
                                                            style="font-size: 11px;"
                                                            wire:key="garantia-encontrada-{{ $garantia->id }}"
                                                            wire:click="seleccionarGarantia({{ $garantia->id }})">
                                                        <b>Garantía #{{ $garantia->id }}</b>
                                                        @if($garantia->vehiculos->isNotEmpty())
                                                            <span class="badge bg-light text-dark border">{{ $garantia->vehiculos->pluck('placa')->implode(', ') }}</span>
                                                        @endif
                                                        <span class="text-muted">— {{ $garantia->client?->fullName() ?? 'Sin cliente' }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @elseif(mb_strlen(trim($garantiaBusqueda)) >= 2)
                                            <div class="form-text text-danger">Sin coincidencias entre las garantías registradas.</div>
                                        @endif
                                    @endif
                                </div>

                                {{-- Cliente directo: solo para trámites SIN garantía --}}
                                @unless($garantia_id)
                                    <div class="col-md-6">
                                        <label class="form-label mb-0 small"><b>Cliente</b> <span class="text-muted">(opcional, sin garantía)</span></label>
                                        @if($client_id)
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-dark" style="font-size:11px;">
                                                    <i class="ti ti-user"></i> {{ $clienteNombre }}
                                                </span>
                                                @unless($editingId)
                                                    <button type="button" class="btn btn-xs btn-outline-danger"
                                                            style="padding: 1px 6px; font-size: 10px;"
                                                            wire:click="quitarCliente">
                                                        <i class="ti ti-x"></i> Quitar
                                                    </button>
                                                @endunless
                                            </div>
                                        @elseif($editingId)
                                            <div class="form-text mt-1" style="font-size:10px;">Sin cliente asociado.</div>
                                        @else
                                            <input type="text" class="form-control form-control-sm @error('client_id') is-invalid @enderror"
                                                   autocomplete="off"
                                                   wire:model.live.debounce.400ms="clienteBusqueda"
                                                   placeholder="Busca por nombre o documento (mín. 2 caracteres)">
                                            @error('client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                            @if($clientesEncontrados->isNotEmpty())
                                                <div class="list-group mt-1" style="max-height: 160px; overflow-y: auto;">
                                                    @foreach($clientesEncontrados as $cliente)
                                                        <button type="button"
                                                                class="list-group-item list-group-item-action py-1 px-2"
                                                                style="font-size: 11px;"
                                                                wire:key="cliente-encontrado-{{ $cliente->id }}"
                                                                wire:click="seleccionarCliente({{ $cliente->id }})">
                                                            <b>{{ $cliente->fullName() }}</b>
                                                            <span class="text-muted">— {{ $cliente->documento }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @elseif(mb_strlen(trim($clienteBusqueda)) >= 2)
                                                <div class="form-text text-danger">Sin coincidencias entre los clientes activos.</div>
                                            @endif
                                        @endif
                                    </div>
                                @endunless

                                <div class="col-md-6">
                                    <label class="form-label mb-0 small">
                                        <b>Descripción</b>
                                        @unless($garantia_id) <span class="text-danger">*</span> @endunless
                                    </label>
                                    <input type="text" class="form-control form-control-sm @error('descripcion') is-invalid @enderror"
                                           wire:model="descripcion" maxlength="255"
                                           placeholder="Ej. Carta notarial por incumplimiento, testimonio...">
                                    @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Datos del trámite --}}
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Notaría</b></label>
                                <input type="text" class="form-control form-control-sm @error('notaria') is-invalid @enderror"
                                       wire:model="notaria" maxlength="120" list="notariasExistentes"
                                       autocomplete="off" placeholder="Ej. Notaría Hinojosa">
                                <datalist id="notariasExistentes">
                                    @foreach($notarias as $n)
                                        <option value="{{ $n }}"></option>
                                    @endforeach
                                </datalist>
                                @error('notaria') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @unless($editingId)
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small"><b>Estado inicial</b> <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm @error('estadoInicial') is-invalid @enderror"
                                            wire:model="estadoInicial">
                                        <option value="firmado_oficina">{{ \App\Models\TramiteNotarial::ESTADOS['firmado_oficina'] }}</option>
                                        <option value="en_notaria">{{ \App\Models\TramiteNotarial::ESTADOS['en_notaria'] }}</option>
                                    </select>
                                    @error('estadoInicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small"><b>Fecha</b> <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control form-control-sm @error('fecha') is-invalid @enderror"
                                           wire:model="fecha" max="{{ now()->toDateString() }}">
                                    @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endunless

                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Costo S/</b></label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm @error('costo') is-invalid @enderror"
                                       wire:model="costo" placeholder="0.00">
                                @error('costo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-{{ $editingId ? 4 : 8 }}">
                                <label class="form-label mb-0 small"><b>Responsable</b></label>
                                <select class="form-select form-select-sm @error('responsable_id') is-invalid @enderror"
                                        wire:model="responsable_id">
                                    <option value="">— Sin asignar —</option>
                                    @foreach($usuarios as $usuario)
                                        <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                                    @endforeach
                                </select>
                                @error('responsable_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if($editingId)
                                <div class="col-md-4">
                                    <label class="form-label mb-0 small"><b>Ubicación del archivo</b></label>
                                    <input type="text" class="form-control form-control-sm @error('ubicacion_archivo') is-invalid @enderror"
                                           wire:model="ubicacion_archivo" maxlength="255"
                                           placeholder="Ej. Archivador legal, cajón 2">
                                    @error('ubicacion_archivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="col-12">
                                <label class="form-label mb-0 small"><b>Nota</b></label>
                                <textarea class="form-control form-control-sm @error('nota') is-invalid @enderror"
                                          rows="2" wire:model="nota" maxlength="2000"
                                          placeholder="Observaciones del trámite (opcional)"></textarea>
                                @error('nota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if($editingId)
                                <div class="col-12">
                                    <div class="form-text" style="font-size:10px;">
                                        <i class="ti ti-info-circle"></i>
                                        El estado no se edita aquí: cambia solo con el botón «Avanzar» del listado.
                                    </div>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-danger"
                            wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                        <i class="ti ti-device-floppy"></i>
                        <span wire:loading.remove wire:target="guardar">{{ $editingId ? 'Guardar cambios' : 'Registrar trámite' }}</span>
                        <span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Mini-modal: responsable del recojo (transición a 'recogido') ═══ --}}
    <div class="modal fade" id="recojoModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:recojo-modal-open.window="modal.show()"
         x-on:recojo-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-package text-success"></i>
                        Confirmar recojo
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2">
                        El trámite <b>#{{ $recojoTramiteId }}</b> no tiene responsable asignado.
                        Indica quién recogió el documento de la notaría:
                    </p>
                    <label class="form-label mb-0 small"><b>Responsable</b> <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm @error('recojoResponsableId') is-invalid @enderror"
                            wire:model="recojoResponsableId">
                        <option value="">— Seleccionar —</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                    @error('recojoResponsableId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-success"
                            wire:click="confirmarRecojo" wire:loading.attr="disabled" wire:target="confirmarRecojo">
                        <i class="ti ti-check"></i>
                        <span wire:loading.remove wire:target="confirmarRecojo">Marcar recogido</span>
                        <span wire:loading wire:target="confirmarRecojo">Guardando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
