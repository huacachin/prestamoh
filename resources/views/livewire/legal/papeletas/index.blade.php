<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PAPELETAS DE TRÁNSITO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Área Legal</span></a>
                </li>
                <li class="d-flex active"><a href="{{ route('legal.papeletas') }}" class="f-s-14">Papeletas</a></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    @php
                        $badgeEntidades = [
                            'SAT' => 'bg-primary',
                            'ATU' => 'bg-danger',
                            'SAT_CALLAO' => 'bg-info text-dark',
                            'SUTRAN' => 'bg-warning text-dark',
                        ];
                        $badgeEstados = [
                            'pendiente' => 'bg-warning text-dark',
                            'en_recurso' => 'bg-info text-dark',
                            'fraccionada' => 'bg-secondary',
                            'pagada' => 'bg-success',
                            'anulada' => 'bg-dark',
                            'judicializada' => 'bg-danger',
                        ];
                    @endphp

                    {{-- Deuda viva (no pagadas/anuladas): los totales que el Excel sumaba a mano --}}
                    <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                        <span class="small text-muted me-1" style="min-width: 130px;"><b>Deuda por entidad:</b></span>
                        @foreach(\App\Models\Papeleta::ENTIDADES as $clave => $etiqueta)
                            @php $fila = $deudaPorEntidad->get($clave); @endphp
                            <span class="badge {{ $badgeEntidades[$clave] ?? 'bg-secondary' }} {{ $fila ? '' : 'opacity-50' }}"
                                  title="{{ $fila->cantidad ?? 0 }} papeleta(s) no pagadas ni anuladas">
                                {{ $etiqueta }}: S/ {{ number_format((float) ($fila->total ?? 0), 2) }}
                            </span>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                        <span class="small text-muted me-1" style="min-width: 130px;"><b>Deuda por responsable:</b></span>
                        @foreach(\App\Models\Papeleta::RESPONSABLES as $clave => $etiqueta)
                            @php $fila = $deudaPorResponsable->get($clave); @endphp
                            <span class="badge bg-light text-dark border {{ $fila ? '' : 'opacity-50' }}"
                                  title="{{ $fila->cantidad ?? 0 }} papeleta(s) no pagadas ni anuladas">
                                {{ $etiqueta }}: S/ {{ number_format((float) ($fila->total ?? 0), 2) }}
                            </span>
                        @endforeach
                        @if($deudaPorResponsable->has('sin_asignar'))
                            <span class="badge bg-danger"
                                  title="{{ $deudaPorResponsable->get('sin_asignar')->cantidad }} papeleta(s) sin responsable asignado">
                                Sin asignar: S/ {{ number_format((float) $deudaPorResponsable->get('sin_asignar')->total, 2) }}
                            </span>
                        @endif
                    </div>

                    {{-- Filtros --}}
                    <form wire:submit.prevent="$refresh">
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Buscar</b></label>
                                <input type="text" class="form-control form-control-sm" autocomplete="off"
                                       wire:model.live.debounce.300ms="buscar"
                                       placeholder="N° papeleta, placa o conductor">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Entidad</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroEntidad">
                                    <option value="">Todas</option>
                                    @foreach(\App\Models\Papeleta::ENTIDADES as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Estado</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroEstado">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\Papeleta::ESTADOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-0 small"><b>Responsable</b></label>
                                <select class="form-select form-select-sm" wire:model.live="filtroResponsable">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\Papeleta::RESPONSABLES as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="filtroRevision"
                                           wire:model.live="soloRevision">
                                    <label class="form-check-label small" for="filtroRevision">
                                        Requieren revisión
                                    </label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="filtroPorVencer"
                                           wire:model.live="soloPorVencer">
                                    <label class="form-check-label small" for="filtroPorVencer">
                                        Con recursos por vencer ({{ \App\Models\PapeletaRecurso::DIAS_AVISO }} días)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @can('legal.papeletas')
                                <button type="button" class="btn btn-sm btn-danger"
                                        wire:click="$dispatch('abrir-papeleta-modal')">
                                    <i class="ti ti-plus f-s-12"></i> Nueva papeleta
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
                                    <th class="text-center" width="90">Entidad</th>
                                    <th width="130">N° Papeleta</th>
                                    <th width="120">Placa</th>
                                    <th class="text-center" width="90">Falta</th>
                                    <th class="text-center" width="90">F. Infracción</th>
                                    <th class="text-end" width="90">Monto S/</th>
                                    <th width="130">Responsable</th>
                                    <th class="text-center" width="100">Estado</th>
                                    <th class="text-center" width="70">Recursos</th>
                                    <th class="text-center" width="140">Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($papeletas as $papeleta)
                                @php
                                    // Recursos pendientes vencidos o por vencer (campana legal)
                                    $tienePorVencer = $papeleta->recursos->contains(
                                        fn ($r) => $r->resultado === 'pendiente'
                                            && $r->plazo_vence
                                            && $r->plazo_vence->toDateString() <= $fechaAviso
                                    );
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $papeletas->firstItem() + $loop->index }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeEntidades[$papeleta->entidad] ?? 'bg-secondary' }}">
                                            {{ \App\Models\Papeleta::ENTIDADES[$papeleta->entidad] ?? $papeleta->entidad }}
                                        </span>
                                    </td>
                                    <td class="font-monospace fw-bold">
                                        {{ $papeleta->nro_papeleta }}
                                        @if($papeleta->requiere_revision)
                                            <i class="ti ti-alert-triangle text-danger"
                                               title="Requiere revisión (datos por validar)"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <b>{{ $papeleta->vehiculo?->placa ?? '—' }}</b>
                                        @if($papeleta->vehiculo && trim("{$papeleta->vehiculo->marca} {$papeleta->vehiculo->modelo}") !== '')
                                            <small class="text-muted d-block">
                                                {{ trim("{$papeleta->vehiculo->marca} {$papeleta->vehiculo->modelo}") }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ $papeleta->codigo_falta ?? '—' }}
                                        @if($papeleta->puntos !== null)
                                            <small class="text-muted d-block">{{ $papeleta->puntos }} pts</small>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $papeleta->fecha_infraccion?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-end">{{ $papeleta->monto !== null ? number_format((float) $papeleta->monto, 2) : '—' }}</td>
                                    <td>
                                        {{ \App\Models\Papeleta::RESPONSABLES[$papeleta->responsable_pago] ?? '—' }}
                                        @if($papeleta->responsable_pago === 'conductor' && $papeleta->conductor_nombre)
                                            <small class="text-muted d-block">{{ $papeleta->conductor_nombre }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeEstados[$papeleta->estado] ?? 'bg-secondary' }}">
                                            {{ \App\Models\Papeleta::ESTADOS[$papeleta->estado] ?? $papeleta->estado }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if($papeleta->recursos_count > 0)
                                            <span class="badge {{ $tienePorVencer ? 'bg-danger' : 'bg-info text-dark' }}"
                                                  @if($tienePorVencer) title="Tiene recursos pendientes vencidos o por vencer" @endif>
                                                @if($tienePorVencer)<i class="ti ti-alarm"></i>@endif
                                                {{ $papeleta->recursos_count }}
                                            </span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @can('legal.papeletas')
                                            <button type="button" class="btn btn-xs btn-info"
                                                    style="padding: 2px 8px; font-size: 10px;"
                                                    wire:click="$dispatch('abrir-recursos-modal', { papeletaId: {{ $papeleta->id }} })">
                                                Recursos
                                            </button>
                                            <button type="button" class="btn btn-xs btn-success"
                                                    style="padding: 2px 8px; font-size: 10px;"
                                                    wire:click="$dispatch('abrir-papeleta-modal', { papeletaId: {{ $papeleta->id }} })">
                                                Editar
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="py-4 text-muted text-center">No se encontraron papeletas</td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="10">TOTAL</td>
                                    <td class="text-center fw-bold">{{ $papeletas->total() }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{ $papeletas->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Modal de alta/edición de papeletas --}}
    <livewire:legal.papeletas.papeleta-modal />

    {{-- Modal de recursos de una papeleta --}}
    <livewire:legal.papeletas.recursos-modal />
</div>
