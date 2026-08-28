<div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="mb-0" style="color:red;">
            Vehículos <span class="text-muted small fw-normal">({{ $listado->count() }})</span>
        </h6>
        @if($puedeEditar && ! $creando && ! $editandoId)
            <button type="button" class="btn btn-sm btn-success" wire:click="nuevo">
                <i class="ti ti-plus"></i> Agregar vehículo
            </button>
        @endif
    </div>

    @if($msg)
        @php
            $cls = match($msgType) { 'ok' => 'alert-success', 'warn' => 'alert-warning', default => 'alert-danger' };
            $ico = match($msgType) { 'ok' => 'ti-circle-check', 'warn' => 'ti-alert-triangle', default => 'ti-alert-circle' };
        @endphp
        <div class="alert {{ $cls }} py-2 mb-2 d-flex align-items-center gap-2">
            <i class="ti {{ $ico }} f-s-16"></i><span class="small">{{ $msg }}</span>
        </div>
    @endif

    {{-- ── Formulario de alta / edición ── --}}
    @if($creando || $editandoId)
        <div class="border rounded p-2 mb-3" style="background:#fcfcfa;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-dark">
                    {{ $editandoId ? 'Editando vehículo' : 'Nuevo vehículo' }}
                </span>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 mb-2">
                    <ul class="mb-0 small">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Placa</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm text-uppercase @error('placa') is-invalid @enderror"
                               wire:model.defer="placa" maxlength="10" placeholder="ABC-123">
                        <button type="button" class="btn btn-danger" wire:click="consultarPlaca"
                                wire:loading.attr="disabled" wire:target="consultarPlaca" title="Buscar datos de la placa">
                            <span wire:loading.remove wire:target="consultarPlaca"><i class="ti ti-search"></i></span>
                            <span wire:loading wire:target="consultarPlaca" class="small">…</span>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Marca</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="marca" placeholder="Toyota">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Modelo</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="modelo" placeholder="Hiace">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Valor del vehículo (S/)</label>
                    <input type="number" step="0.01" min="0" style="background-color:#fff9db;"
                           class="form-control form-control-sm @error('valor') is-invalid @enderror"
                           wire:model.defer="valor" placeholder="0.00">
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">N° de Motor</label>
                    <input type="text" class="form-control form-control-sm text-uppercase" wire:model.defer="nro_motor">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">N° de Serie</label>
                    <input type="text" class="form-control form-control-sm text-uppercase" wire:model.defer="nro_serie">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Categoría</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="categoria" placeholder="M2-C3">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Año de Modelo</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="anio_modelo" placeholder="2018">
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Carrocería</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="carroceria" placeholder="Microbús">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Color</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="color" placeholder="Blanco">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small fw-semibold">Combustible</label>
                    <input type="text" class="form-control form-control-sm" wire:model.defer="combustible" placeholder="GNV / Gasolina">
                </div>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-dark" wire:click="guardar"
                        wire:loading.attr="disabled" wire:target="guardar">
                    <i class="ti ti-device-floppy"></i>
                    <span wire:loading.remove wire:target="guardar">{{ $editandoId ? 'Guardar cambios' : 'Agregar' }}</span>
                    <span wire:loading wire:target="guardar">Guardando…</span>
                </button>
                <button type="button" class="btn btn-sm btn-secondary" wire:click="cancelar">Cancelar</button>
            </div>
        </div>
    @endif

    {{-- ── Listado ── --}}
    @if($listado->isEmpty())
        <div class="text-center text-muted py-4 border rounded" style="border-style:dashed !important;">
            <i class="ti ti-car f-s-32 d-block mb-2"></i>
            <div class="small">Este cliente no tiene vehículos registrados.</div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover table-autofit mb-0" style="font-size: 12px;">
                <thead class="bg-primary">
                    {{-- Motor y serie son códigos largos (hasta 30 car.): van sin
                         cortar y con ancho propio; marca/modelo y color ceden espacio. --}}
                    <tr>
                        <th class="text-center" width="80">Placa</th>
                        <th width="190">Marca / Modelo</th>
                        <th class="text-center" width="180">N° Motor</th>
                        <th class="text-center" width="180">N° Serie</th>
                        <th class="text-center" width="60">Año</th>
                        <th width="120">Color</th>
                        <th class="text-end" width="95">Valor (S/)</th>
                        @if($puedeEditar)<th class="text-center" width="75">Op.</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($listado as $v)
                        <tr wire:key="veh-{{ $v->id }}">
                            <td class="text-center fw-bold">{{ $v->placa }}</td>
                            <td>{{ trim($v->marca.' '.$v->modelo) ?: '—' }}</td>
                            <td class="text-center" style="white-space:nowrap;">{{ $v->nro_motor ?: '—' }}</td>
                            <td class="text-center" style="white-space:nowrap;">{{ $v->nro_serie ?: '—' }}</td>
                            <td class="text-center">{{ $v->anio_modelo ?: '—' }}</td>
                            <td>{{ $v->color ?: '—' }}</td>
                            <td class="text-end">{{ $v->valor !== null ? number_format((float) $v->valor, 2) : '—' }}</td>
                            @if($puedeEditar)
                                <td class="text-center" style="white-space:nowrap;">
                                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-1"
                                            wire:click="editar({{ $v->id }})" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1"
                                            wire:click="eliminar({{ $v->id }})"
                                            wire:confirm="¿Eliminar el vehículo {{ $v->placa }}? Esta acción no se puede deshacer."
                                            title="Eliminar">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
