<div>
    {{-- ═══ Modal de recursos de una papeleta (hijo de Legal\Papeletas\Index) ═══ --}}
    <div class="modal fade" id="recursosModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:recursos-modal-open.window="modal.show()"
         x-on:recursos-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-gavel text-danger"></i>
                        Recursos de la papeleta
                        @if($papeletaNro !== '')
                            <span class="text-muted d-block" style="font-size:11px;">
                                {{ $papeletaEntidad }} <b class="font-monospace">{{ $papeletaNro }}</b>
                                @if($papeletaPlaca !== '') — {{ $papeletaPlaca }} @endif
                                @if($papeletaMonto !== null) — S/ {{ $papeletaMonto }} @endif
                                — Estado:
                                <b>{{ \App\Models\Papeleta::ESTADOS[$papeletaEstado] ?? $papeletaEstado }}</b>
                            </span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @php
                        $badgeResultados = [
                            'pendiente' => 'bg-warning text-dark',
                            'fundado' => 'bg-success',
                            'infundado' => 'bg-danger',
                            'improcedente' => 'bg-dark',
                            'atendido' => 'bg-info text-dark',
                        ];
                        $hoy = now()->toDateString();
                        $fechaAviso = now()->addDays(\App\Models\PapeletaRecurso::DIAS_AVISO)->toDateString();
                    @endphp

                    {{-- Recursos presentados --}}
                    <div class="small fw-semibold mb-1">
                        <i class="ti ti-list-details"></i> Recursos presentados
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-striped align-middle mb-1" style="font-size: 11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th width="170">Tipo</th>
                                    <th width="150">N° Trámite</th>
                                    <th class="text-center" width="90">Presentado</th>
                                    <th class="text-center" width="90">Vence</th>
                                    <th class="text-center" width="200">Resultado</th>
                                    <th>Nota</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($recursos as $recurso)
                                @php
                                    $vence = $recurso->plazo_vence?->toDateString();
                                    $pendiente = $recurso->resultado === 'pendiente';
                                    // Rojo: vencido y aún pendiente. Naranja: vence dentro de la ventana de aviso.
                                    $estiloVence = '';
                                    if ($pendiente && $vence) {
                                        if ($vence < $hoy) {
                                            $estiloVence = 'color:#dc3545; font-weight:bold;';
                                        } elseif ($vence <= $fechaAviso) {
                                            $estiloVence = 'color:#fd7e14; font-weight:bold;';
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ \App\Models\PapeletaRecurso::TIPOS[$recurso->tipo] ?? $recurso->tipo }}</td>
                                    <td class="font-monospace">{{ $recurso->nro_tramite ?? '—' }}</td>
                                    <td class="text-center">{{ $recurso->fecha_presentacion?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="text-center" style="{{ $estiloVence }}">
                                        {{ $recurso->plazo_vence?->format('d/m/Y') ?? '—' }}
                                        @if($pendiente && $vence && $vence < $hoy)
                                            <i class="ti ti-alarm" title="Plazo vencido con resultado pendiente"></i>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($resolviendoId === $recurso->id)
                                            {{-- Mini-form inline de resolución --}}
                                            <div class="d-flex align-items-center gap-1">
                                                <select class="form-select form-select-sm @error('resolucionResultado') is-invalid @enderror"
                                                        style="font-size:11px;" wire:model="resolucionResultado">
                                                    <option value="">— Resultado —</option>
                                                    @foreach(\App\Models\PapeletaRecurso::RESULTADOS as $clave => $etiqueta)
                                                        @if($clave !== 'pendiente')
                                                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <input type="date" class="form-control form-control-sm @error('resolucionFecha') is-invalid @enderror"
                                                       style="font-size:11px; max-width:120px;"
                                                       wire:model="resolucionFecha" max="{{ $hoy }}">
                                                <button type="button" class="btn btn-xs btn-success"
                                                        style="padding: 2px 6px; font-size: 10px;"
                                                        title="Guardar resultado"
                                                        wire:click="guardarResolucion"
                                                        wire:loading.attr="disabled" wire:target="guardarResolucion">
                                                    <i class="ti ti-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-secondary"
                                                        style="padding: 2px 6px; font-size: 10px;"
                                                        title="Cancelar" wire:click="cancelarResolucion">
                                                    <i class="ti ti-x"></i>
                                                </button>
                                            </div>
                                            @error('resolucionResultado') <div class="text-danger text-start" style="font-size:10px;">{{ $message }}</div> @enderror
                                            @error('resolucionFecha') <div class="text-danger text-start" style="font-size:10px;">{{ $message }}</div> @enderror
                                        @else
                                            <span class="badge {{ $badgeResultados[$recurso->resultado] ?? 'bg-secondary' }}">
                                                {{ \App\Models\PapeletaRecurso::RESULTADOS[$recurso->resultado] ?? $recurso->resultado }}
                                            </span>
                                            @if($recurso->resuelto_at)
                                                <small class="text-muted d-block">{{ $recurso->resuelto_at->format('d/m/Y') }}</small>
                                            @endif
                                            @if($pendiente)
                                                @can('legal.papeletas')
                                                    <button type="button" class="btn btn-xs btn-outline-primary d-block mx-auto mt-1"
                                                            style="padding: 1px 6px; font-size: 10px;"
                                                            wire:click="resolver({{ $recurso->id }})">
                                                        Resolver
                                                    </button>
                                                @endcan
                                            @endif
                                        @endif
                                    </td>
                                    <td>{{ $recurso->nota ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-3 text-muted text-center">
                                        Esta papeleta aún no tiene recursos presentados
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Nuevo recurso --}}
                    @can('legal.papeletas')
                        <div class="border rounded p-2" style="background:#f8f9fa;">
                            <div class="small fw-semibold mb-1">
                                <i class="ti ti-plus"></i> Agregar recurso
                            </div>
                            <form wire:submit.prevent="agregarRecurso">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small"><b>Tipo de recurso</b> <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm @error('tipo') is-invalid @enderror"
                                                wire:model.live="tipo">
                                            @foreach(\App\Models\PapeletaRecurso::TIPOS as $clave => $etiqueta)
                                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </select>
                                        @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small"><b>N° de trámite</b></label>
                                        <input type="text" class="form-control form-control-sm font-monospace @error('nro_tramite') is-invalid @enderror"
                                               wire:model="nro_tramite" maxlength="40" autocomplete="off"
                                               placeholder="Ej. MPD0000426439">
                                        @error('nro_tramite') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small"><b>Fecha de presentación</b> <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm @error('fecha_presentacion') is-invalid @enderror"
                                               wire:model.live="fecha_presentacion" max="{{ $hoy }}">
                                        @error('fecha_presentacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-0 small"><b>Vence el plazo</b> <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control form-control-sm @error('plazo_vence') is-invalid @enderror"
                                               wire:model="plazo_vence">
                                        @error('plazo_vence') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text" style="font-size:10px;">
                                            Sugerido: 10 días acceso a información / 30 días recursos. Editable.
                                        </div>
                                    </div>

                                    <div class="col-md-9">
                                        <label class="form-label mb-0 small"><b>Nota</b></label>
                                        <textarea class="form-control form-control-sm @error('nota') is-invalid @enderror"
                                                  rows="1" wire:model="nota" maxlength="2000"
                                                  placeholder="Observaciones del recurso (opcional)"></textarea>
                                        @error('nota') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-3 d-flex flex-column justify-content-end">
                                        @if($papeletaEstado === 'pendiente')
                                            <div class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" id="recPasarEnRecurso"
                                                       wire:model="pasarEnRecurso">
                                                <label class="form-check-label small" for="recPasarEnRecurso">
                                                    Pasar la papeleta a <b>EN RECURSO</b>
                                                </label>
                                            </div>
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-danger"
                                                wire:loading.attr="disabled" wire:target="agregarRecurso">
                                            <i class="ti ti-device-floppy"></i>
                                            <span wire:loading.remove wire:target="agregarRecurso">Agregar recurso</span>
                                            <span wire:loading wire:target="agregarRecurso">Guardando…</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @endcan
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
