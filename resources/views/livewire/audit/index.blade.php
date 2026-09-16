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

    @php
        $subjectLabels = [
            'App\\Models\\Credit' => 'Crédito',
            'App\\Models\\Payment' => 'Pago',
            'App\\Models\\Client' => 'Cliente',
            'App\\Models\\User' => 'Usuario',
            'App\\Models\\Income' => 'Ingreso',
            'App\\Models\\Expense' => 'Egreso',
            'App\\Models\\CashOpening' => 'Apertura Caja',
        ];
    @endphp

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body pb-2">
                    {{-- Filtros --}}
                    <div class="row g-2 mb-2">
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Desde</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="desde">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Hasta</label>
                            <input type="date" class="form-control form-control-sm" wire:model.live="hasta">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Usuario</label>
                            <select class="form-select form-select-sm" wire:model.live="causer">
                                <option value="">Todos</option>
                                @foreach($usuarios as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Acción</label>
                            {{-- Réplica del filtro de acciones de newtaxivan: el tipo se
                                 deriva del verbo inicial de la descripción. --}}
                            <select class="form-select form-select-sm" wire:model.live="accion">
                                <option value="">Todas</option>
                                @foreach(\App\Livewire\Audit\Index::ACCIONES as $tipo => $cfg)
                                    <option value="{{ $tipo }}">{{ $cfg['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label mb-0 small">Buscar acción</label>
                            <input type="text" class="form-control form-control-sm" placeholder="Ej. pago, anuló, usuario…"
                                   wire:model.live.debounce.400ms="buscar" autocomplete="off"
       list="hist_audit" data-search-history="audit">
<datalist id="hist_audit" wire:ignore></datalist>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-sm btn-secondary" wire:click="limpiar">
                                <i class="ti ti-eraser f-s-12"></i> Limpiar
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive tableFixHead">
                        <table class="table table-bordered table-striped table-hover table-sm">
                            <thead class="bg-primary">
                                <tr>
                                    <th style="width:150px;">Fecha / Hora</th>
                                    <th style="width:180px;">Usuario</th>
                                    <th>Acción</th>
                                    <th style="width:160px;">Afectado</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($logs as $log)
                                <tr>
                                    <td class="text-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($log->causer)
                                            {{ $log->causer->name }}
                                            <small class="text-muted">({{ $log->causer->username }})</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php $tipoAccion = $this->clasificar($log->description); @endphp
                                        @if($tipoAccion)
                                            <span class="badge bg-{{ \App\Livewire\Audit\Index::ACCIONES[$tipoAccion]['badge'] }} me-1">
                                                {{ \App\Livewire\Audit\Index::ACCIONES[$tipoAccion]['label'] }}
                                            </span>
                                        @endif
                                        {{ $log->description }}
                                    </td>
                                    <td class="text-nowrap">
                                        @if($log->subject_type)
                                            {{ $subjectLabels[$log->subject_type] ?? class_basename($log->subject_type) }}
                                            @if($log->subject_id) <span class="text-muted">#{{ $log->subject_id }}</span> @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-muted">No hay registros de auditoría para el filtro seleccionado</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
