<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">DOCUMENTOS DEL CLIENTE</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Documentos #{{ $client->expediente ?? $client->id }}</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            {{-- Cabecera: cliente + acciones de generación --}}
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0" style="color:red;">{{ $client->fullName() }}</h5>
                    <small class="text-muted">
                        Exp. {{ $client->expediente }} · DNI/RUC {{ $client->documento }}
                    </small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-success" wire:click="abrirModalAnexo1">
                        <i class="ti ti-file-plus"></i> Generar Anexo 1
                    </button>
                    {{-- Fases 2-3: placeholders (tooltip en el span porque un botón disabled no dispara title) --}}
                    <span class="d-inline-block" tabindex="0" title="Próximamente">
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="ti ti-file-plus"></i> Generar Contrato
                        </button>
                    </span>
                    <span class="d-inline-block" tabindex="0" title="Próximamente">
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="ti ti-file-plus"></i> Generar Anexo 2
                        </button>
                    </span>
                    <a href="{{ route('clients.show', $client->id) }}" class="btn btn-sm btn-secondary">
                        <i class="ti ti-arrow-back"></i> Regresar al cliente
                    </a>
                </div>
            </div>

            {{-- Historial de documentos emitidos --}}
            @if($documentos->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-file-off" style="font-size:48px; opacity:.4;"></i>
                    <p class="mt-2 mb-0">Este cliente aún no tiene documentos generados.</p>
                    <small>Genera el primer Anexo 1 con el botón de arriba.</small>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle" style="font-size:11px;">
                        <thead class="bg-primary">
                            <tr>
                                <th>Tipo</th>
                                <th class="text-center">Crédito</th>
                                <th class="text-center">Versión</th>
                                <th class="text-center">Fecha</th>
                                <th class="text-center">Generado por</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Descargas</th>
                                <th class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($documentos as $doc)
                            @php
                                $anulado = $doc->estado === 'anulado';
                                $badgeTipo = match ($doc->tipo) {
                                    'anexo1' => 'bg-primary',
                                    'contrato' => 'bg-dark',
                                    'anexo2' => 'bg-info text-dark',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <tr style="{{ $anulado ? 'opacity:.55;' : '' }}">
                                <td>
                                    <span class="badge {{ $badgeTipo }}"
                                          style="font-size:10px; {{ $anulado ? 'text-decoration: line-through;' : '' }}">
                                        {{ $doc->tipoLabel() }}
                                    </span>
                                </td>
                                <td class="text-center">#{{ $doc->credit_id }}</td>
                                <td class="text-center fw-bold">v{{ $doc->version }}</td>
                                <td class="text-center">{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-center">{{ $doc->generadoPor?->username ?? $doc->generadoPor?->name ?? '—' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $anulado ? 'bg-danger' : 'bg-success' }}" style="font-size:10px;">
                                        {{ \App\Models\DocumentoCliente::ESTADOS[$doc->estado] ?? $doc->estado }}
                                    </span>
                                </td>
                                <td class="text-center text-nowrap">
                                    {{-- Las descargas de un anulado siguen disponibles: son la constancia de lo entregado --}}
                                    @if($doc->pdf_path)
                                        <a href="{{ route('clients.documentos.pdf', $doc->id) }}"
                                           class="btn btn-xs btn-danger" style="padding: 2px 8px; font-size: 10px;"
                                           title="Descargar PDF">
                                            <i class="ti ti-file-type-pdf"></i> PDF
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-xs btn-danger" disabled
                                                style="padding: 2px 8px; font-size: 10px;" title="Sin PDF guardado">
                                            <i class="ti ti-file-type-pdf"></i> PDF
                                        </button>
                                    @endif
                                    <a href="{{ route('clients.documentos.word', $doc->id) }}"
                                       class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;"
                                       title="Descargar Word (editable)">
                                        <i class="ti ti-file-type-doc"></i> Word
                                    </a>
                                </td>
                                <td class="text-center">
                                    @if(! $anulado)
                                        <button type="button" class="btn btn-xs btn-outline-danger"
                                                style="padding: 2px 8px; font-size: 10px;"
                                                wire:click="anular({{ $doc->id }})"
                                                wire:confirm="¿Anular {{ $doc->tipoLabel() }} v{{ $doc->version }} del crédito #{{ $doc->credit_id }}? Quedará tachado, pero sus descargas seguirán disponibles."
                                                title="Anular documento">
                                            <i class="ti ti-ban"></i> Anular
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Modal "Generar Anexo 1" (cronograma) ═══ --}}
    <div class="modal fade" id="anexo1Modal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:anexo1-modal-open.window="modal.show()"
         x-on:anexo1-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-file-plus text-success"></i>
                        Generar Anexo 1 — Cronograma · {{ $client->fullName() }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    @if($creditosActivos->isEmpty())
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="ti ti-alert-triangle"></i>
                            El cliente no tiene créditos activos: no hay cronograma que emitir.
                        </div>
                    @else
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Crédito *</label>
                                <select class="form-select form-select-sm @error('creditoId') is-invalid @enderror"
                                        wire:model.live="creditoId">
                                    <option value="">— Selecciona el crédito —</option>
                                    @foreach($creditosActivos as $c)
                                        <option value="{{ $c->id }}">
                                            #{{ $c->id }} — S/ {{ number_format((float) $c->importe, 2) }} — {{ $c->cuotas }} cuotas ({{ $c->tipoPlanillaLabel() }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('creditoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Fecha del documento *</label>
                                <input type="date" class="form-control form-control-sm @error('fechaDoc') is-invalid @enderror"
                                       wire:model.live="fechaDoc" max="{{ now()->format('Y-m-d') }}">
                                @error('fechaDoc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Vehículo (garantía)</label>
                                <select class="form-select form-select-sm" wire:model.live="vehiculoId">
                                    <option value="">Sin vehículo</option>
                                    @foreach($vehiculos as $v)
                                        <option value="{{ $v->id }}">{{ $v->descripcion() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Valor del vehículo (S/)</label>
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm @error('valorVehiculo') is-invalid @enderror"
                                       wire:model.live="valorVehiculo"
                                       @disabled(! $vehiculoId) placeholder="0.00">
                                @error('valorVehiculo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text" style="font-size:10px;">Opcional — se guardará en la ficha del vehículo.</div>
                            </div>
                        </div>

                        {{-- Vista previa (render 'previa' del snapshot congelado) --}}
                        @if($htmlPreview !== '')
                            <div class="border rounded mt-3 p-1 bg-light">
                                <iframe srcdoc="{{ $htmlPreview }}"
                                        style="width:100%; height:60vh; border:1px solid #ccc; background:#fff;"
                                        title="Vista previa del Anexo 1"></iframe>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-outline-dark"
                            wire:click="previsualizar" wire:loading.attr="disabled" wire:target="previsualizar,generar"
                            @disabled($creditosActivos->isEmpty())>
                        <i class="ti ti-eye"></i>
                        <span wire:loading.remove wire:target="previsualizar">Vista previa</span>
                        <span wire:loading wire:target="previsualizar">Generando previa…</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-success"
                            wire:click="generar" wire:loading.attr="disabled" wire:target="previsualizar,generar"
                            @disabled($creditosActivos->isEmpty())>
                        <i class="ti ti-file-check"></i>
                        <span wire:loading.remove wire:target="generar">Generar</span>
                        <span wire:loading wire:target="generar">Generando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
