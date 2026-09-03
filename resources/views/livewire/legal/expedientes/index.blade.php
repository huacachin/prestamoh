<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EXPEDIENTES JUDICIALES</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Área Legal</span></a>
                </li>
                <li class="d-flex active"><a href="{{ route('legal.expedientes.index') }}" class="f-s-14">Expedientes</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    @php
                        $badgeEstados = [
                            'en_tramite' => 'bg-info text-dark',
                            'en_ejecucion' => 'bg-primary',
                            'condonado' => 'bg-warning text-dark',
                            'cancelado' => 'bg-success',
                            'desistido' => 'bg-secondary',
                            'archivado' => 'bg-dark',
                        ];
                    @endphp

                    {{-- Contadores por estado (clicables: filtran el listado) --}}
                    <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                        @foreach(\App\Models\ExpedienteJudicial::ESTADOS_PRINCIPAL as $clave => $etiqueta)
                            <button type="button"
                                    class="badge border-0 {{ $badgeEstados[$clave] ?? 'bg-secondary' }} {{ $filtroEstado !== '' && $filtroEstado !== $clave ? 'opacity-50' : '' }}"
                                    style="cursor:pointer; {{ $filtroEstado === $clave ? 'outline: 2px solid #212529;' : '' }}"
                                    title="{{ $filtroEstado === $clave ? 'Quitar filtro' : 'Filtrar por '.$etiqueta }}"
                                    wire:click="filtrarEstado('{{ $clave }}')">
                                {{ $etiqueta }}: {{ $porEstado[$clave] ?? 0 }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Filtros --}}
                    <form wire:submit.prevent="$refresh">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Buscar</b></label>
                                <input type="text" class="form-control form-control-sm" autocomplete="off"
                                       wire:model.live.debounce.300ms="buscar"
                                       placeholder="N° expediente, exp. interno o cliente">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Vía</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroVia">
                                    <option value="">Todas</option>
                                    @foreach(\App\Models\ExpedienteJudicial::VIAS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Estado</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroEstado">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\ExpedienteJudicial::ESTADOS_PRINCIPAL as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Asesor</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroAsesor">
                                    <option value="">Todos</option>
                                    @foreach($asesores as $a)
                                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="filtroRevision"
                                           wire:model.live="requiereRevision">
                                    <label class="form-check-label small" for="filtroRevision">
                                        Solo con datos por revisar
                                    </label>
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="filtroVencidos"
                                           wire:model.live="conPlazosVencidos">
                                    <label class="form-check-label small" for="filtroVencidos">
                                        Con plazos vencidos
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @can('legal.judicial')
                                <a href="{{ route('legal.expedientes.create') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-plus f-s-12"></i> Nuevo expediente
                                </a>
                            @endcan
                        </div>
                    </form>

                    {{-- Tabla --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="70">Exp. interno</th>
                                    <th class="text-center" width="180">N° expediente</th>
                                    <th>Cliente</th>
                                    <th class="text-center" width="110">Vía</th>
                                    <th width="140">Juzgado</th>
                                    <th class="text-center" width="90">Estado</th>
                                    <th class="text-center" width="140">Cautelar</th>
                                    <th class="text-center" width="60">Plazos</th>
                                    <th width="110">Asesor</th>
                                    <th class="text-center" width="60">Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($expedientes as $e)
                                <tr wire:key="expediente-{{ $e->id }}">
                                    <td class="text-center fw-bold">{{ $e->exp_interno ?: '—' }}</td>
                                    <td class="text-center">
                                        <span class="font-monospace">{{ $e->nro_expediente }}</span>
                                    </td>
                                    <td>
                                        {{ $e->client?->fullName() ?? '—' }}
                                        @if($e->requiere_revision)
                                            <i class="ti ti-alert-triangle text-warning f-s-14"
                                               title="Importado con datos por revisar"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ \App\Models\ExpedienteJudicial::VIAS[$e->via] ?? ($e->via ?: '—') }}
                                    </td>
                                    <td>
                                        @if($e->juzgado)
                                            <span class="d-inline-block text-truncate" style="max-width: 140px;"
                                                  title="{{ $e->juzgado }}">{{ $e->juzgado }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeEstados[$e->estado] ?? 'bg-secondary' }}">
                                            {{ $e->estadoLabel() }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @forelse($e->cautelares as $c)
                                            <span class="badge bg-light text-dark border d-block mx-auto mb-1"
                                                  style="width:fit-content;"
                                                  title="Cuaderno cautelar {{ $c->nro_expediente }}">
                                                <i class="ti ti-shield-lock"></i> {{ $c->estadoLabel() }}
                                            </span>
                                        @empty
                                            <span class="text-muted">—</span>
                                        @endforelse
                                    </td>
                                    <td class="text-center">
                                        @if($e->plazos_pendientes_count > 0)
                                            <span class="badge {{ $e->plazos_vencidos_count > 0 ? 'bg-danger' : 'bg-secondary' }}"
                                                  title="{{ $e->plazos_vencidos_count > 0
                                                      ? $e->plazos_vencidos_count.' plazo(s) pendiente(s) ya vencido(s)'
                                                      : $e->plazos_pendientes_count.' plazo(s) pendiente(s)' }}">
                                                {{ $e->plazos_pendientes_count }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $e->asesor?->name ?? '—' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('legal.expedientes.show', $e->id) }}"
                                           class="btn btn-xs btn-success" style="padding: 2px 8px; font-size: 10px;">
                                            <i class="ti ti-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="py-4 text-muted text-center">No se encontraron expedientes judiciales</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $expedientes->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
