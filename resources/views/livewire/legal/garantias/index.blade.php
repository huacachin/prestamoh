<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">GARANTÍAS MOBILIARIAS</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Área Legal</span></a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Garantías</a></li>
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
                            <div class="col-md-4">
                                <label class="form-label mb-0 small"><b>Buscar</b></label>
                                <input type="text" class="form-control form-control-sm" autocomplete="off"
                                       wire:model.live.debounce.300ms="buscar"
                                       placeholder="Cliente, documento o placa">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0 small"><b>Estado</b></label>
                                <select class="form-select form-select-sm" wire:model.live="estado">
                                    <option value="">Todos</option>
                                    @foreach(\App\Models\Garantia::ESTADOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                    <option value="por_renovar">Por renovar (vence en &le; {{ \App\Models\Garantia::DIAS_AVISO_RENOVACION }} días)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" id="filtroRevision"
                                           wire:model.live="requiereRevision">
                                    <label class="form-check-label small" for="filtroRevision">
                                        Solo con datos por revisar
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="ti ti-search f-s-12"></i> Buscar
                            </button>
                            @can('legal.garantias')
                                <a href="{{ route('legal.garantias.create') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-plus f-s-12"></i> Nueva garantía
                                </a>
                            @endcan
                        </div>
                    </form>

                    {{-- Tabla --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="70">N° Crédito</th>
                                    <th>Cliente</th>
                                    <th class="text-center" width="110">Placa(s)</th>
                                    <th class="text-end" width="100">Gravamen S/</th>
                                    <th class="text-center" width="70">GPS / Cust.</th>
                                    <th class="text-center" width="100">Estado</th>
                                    <th class="text-center" width="110">Vigencia hasta</th>
                                    <th class="text-center" width="60">Avisos</th>
                                    <th class="text-center" width="70">Opciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($garantias as $g)
                                @php
                                    $badgeEstado = [
                                        'en_constitucion' => 'bg-secondary',
                                        'vigente' => 'bg-success',
                                        'cancelada' => 'bg-dark',
                                        'en_ejecucion' => 'bg-warning text-dark',
                                        'ejecutada' => 'bg-danger',
                                    ][$g->estado] ?? 'bg-secondary';

                                    // Semáforo de vigencia: rojo = vencida, naranja = vence pronto
                                    $diasVigencia = $g->vigencia_hasta
                                        ? (int) now()->startOfDay()->diffInDays($g->vigencia_hasta->copy()->startOfDay(), false)
                                        : null;
                                @endphp
                                <tr>
                                    <td class="text-center fw-bold">{{ $g->credit_id ?? '—' }}</td>
                                    <td>
                                        {{ $g->client?->fullName() ?? '—' }}
                                        @if($g->requiere_revision)
                                            <i class="ti ti-alert-triangle text-warning f-s-14"
                                               title="Importada con datos por revisar"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @forelse($g->vehiculos as $v)
                                            <span class="badge bg-light text-dark border d-block mx-auto mb-1" style="width:fit-content;">
                                                {{ $v->placa }}
                                            </span>
                                        @empty
                                            <span class="text-muted">—</span>
                                        @endforelse
                                    </td>
                                    <td class="text-end">{{ number_format($g->monto_gravamen, 2) }}</td>
                                    <td class="text-center">
                                        <i class="ti ti-gps f-s-16 {{ $g->gps ? 'text-success' : 'text-muted opacity-25' }}"
                                           title="GPS: {{ $g->gps ? 'Sí' : 'No' }}"></i>
                                        <i class="ti ti-shield-lock f-s-16 {{ $g->custodia ? 'text-success' : 'text-muted opacity-25' }}"
                                           title="Custodia: {{ $g->custodia ? 'Sí' : 'No' }}"></i>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $badgeEstado }}">
                                            {{ \App\Models\Garantia::ESTADOS[$g->estado] ?? $g->estado }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if($g->vigencia_hasta)
                                            @if($diasVigencia < 0)
                                                <span class="badge bg-danger" title="Vigencia vencida">
                                                    {{ $g->vigencia_hasta->format('d/m/Y') }}
                                                </span>
                                            @elseif($diasVigencia <= \App\Models\Garantia::DIAS_AVISO_RENOVACION)
                                                <span class="badge text-white" style="background:#fd7e14;"
                                                      title="Vence en {{ $diasVigencia }} día(s) — por renovar">
                                                    {{ $g->vigencia_hasta->format('d/m/Y') }}
                                                </span>
                                            @else
                                                {{ $g->vigencia_hasta->format('d/m/Y') }}
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $g->avisos_count }}</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('legal.garantias.show', $g->id) }}"
                                           class="btn btn-xs btn-success" style="padding: 2px 8px; font-size: 10px;">
                                            <i class="ti ti-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-4 text-muted text-center">No se encontraron garantías</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $garantias->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
