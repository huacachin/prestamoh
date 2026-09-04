{{-- $embebido: dentro de la pestaña de /clients/{id}/edit se omiten cabecera,
     breadcrumb y card (los pone la página padre). --}}
<div @class(['container-fluid' => ! $embebido])>
    {{-- Convención de la casa: lo que llena una consulta (API o ficha) va EN ROJO. --}}
    <style>
        .campo-api {
            color: #c0392b !important;
            font-weight: 600;
            border-color: #e6a6a0 !important;
            background-color: #fff7f6 !important;
        }
    </style>
    @unless($embebido)
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
    @endunless

    <div @class(['card shadow-sm' => ! $embebido])>
        <div @class(['card-body' => ! $embebido])>

            {{-- Cabecera: cliente + acciones de generación --}}
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    @unless($embebido)
                        <h5 class="mb-0" style="color:red;">{{ $client->fullName() }}</h5>
                        <small class="text-muted">
                            Exp. {{ $client->expediente }} · DNI/RUC {{ $client->documento }}
                        </small>
                    @else
                        <h6 class="mb-0" style="color:red;">Documentos</h6>
                    @endunless
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-success" wire:click="abrirModalAnexo1">
                        <i class="ti ti-file-plus"></i> Generar Anexo 1
                    </button>
                    <button type="button" class="btn btn-sm btn-dark" wire:click="abrirModalContrato">
                        <i class="ti ti-file-plus"></i> Generar Contrato
                    </button>
                    <button type="button" class="btn btn-sm btn-info" wire:click="abrirModalAnexo2">
                        <i class="ti ti-file-plus"></i> Generar Anexo 2
                    </button>
                    @unless($embebido)
                        <a href="{{ route('clients.show', $client->id) }}" class="btn btn-sm btn-secondary">
                            <i class="ti ti-arrow-back"></i> Regresar al cliente
                        </a>
                    @endunless
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
                                    @if($doc->modelo)
                                        @php $nombreModelo = $this->nombreModelo($doc->modelo); @endphp
                                        <br>
                                        <small class="text-muted" style="font-size:9px;">
                                            {{ $nombreModelo === $doc->modelo ? $doc->modelo : "{$doc->modelo} — {$nombreModelo}" }}
                                        </small>
                                    @endif
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
                                                data-confirmar="¿Anular {{ $doc->tipoLabel() }} v{{ $doc->version }} del crédito #{{ $doc->credit_id }}? Quedará tachado, pero sus descargas seguirán disponibles."
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
                            {{-- Varios vehículos por anexo (28/08): uno por fila, con su
                                 valor. Sin marcar ninguno el anexo sale sin vehículos. --}}
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">
                                    Vehículos (garantía)
                                    <span class="text-muted fw-normal">— marca los que van en el anexo</span>
                                </label>
                                @if($vehiculos->isEmpty())
                                    <div class="alert alert-light border py-2 small mb-0">
                                        El cliente no tiene vehículos registrados. El anexo se emitirá sin ellos.
                                    </div>
                                @else
                                    <div class="border rounded p-2" style="background:#fcfcfa;">
                                        @foreach($vehiculos as $v)
                                            <div class="row g-2 align-items-center {{ ! $loop->last ? 'mb-2 pb-2 border-bottom' : '' }}"
                                                 wire:key="anx-veh-{{ $v->id }}">
                                                <div class="col-12 col-sm-7">
                                                    <div class="form-check mb-0">
                                                        <input class="form-check-input" type="checkbox"
                                                               id="anx-veh-{{ $v->id }}"
                                                               value="{{ $v->id }}"
                                                               wire:model.live="anexoVehiculos">
                                                        {{-- ps-2: sin él la etiqueta queda pegada al checkbox --}}
                                                        <label class="form-check-label small ps-2" for="anx-veh-{{ $v->id }}">
                                                            {{ $v->descripcion() }}
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-sm-5">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text" style="font-size:11px;">Valor S/</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control form-control-sm @error('anexoValores.'.$v->id) is-invalid @enderror"
                                                               wire:model.live="anexoValores.{{ $v->id }}"
                                                               @disabled(! in_array($v->id, $anexoVehiculos))
                                                               placeholder="0.00">
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="form-text" style="font-size:10px;">
                                        El valor es opcional y se guarda en la ficha de cada vehículo.
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Vista previa (render 'previa' del snapshot congelado) --}}
                        @if($htmlPreview !== '')
                            <div class="border rounded mt-3 p-2 bg-light" style="max-height:65vh; overflow:auto;">
                                {{-- Proporción A4 VERTICAL: el iframe al 100% del modal hacía
                                     ver el documento apaisado aunque el PDF sale parado. --}}
                                <iframe srcdoc="{{ $htmlPreview }}"
                                        style="width:21cm; height:29.7cm; max-width:100%; border:1px solid #ccc;
                                               background:#fff; display:block; margin:0 auto;"
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

    {{-- ═══ Modal "Generar Contrato" (garantía mobiliaria — wizard de modelos) ═══ --}}
    <div class="modal fade" id="contratoModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:contrato-modal-open.window="modal.show()"
         x-on:contrato-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-file-plus text-dark"></i>
                        Generar Contrato — Garantía mobiliaria · {{ $client->fullName() }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    @if($creditosActivos->isEmpty())
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="ti ti-alert-triangle"></i>
                            El cliente no tiene créditos activos: no hay obligación que garantizar.
                        </div>
                    @elseif(empty($modelosAgrupados))
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="ti ti-alert-triangle"></i>
                            El catálogo de modelos del contrato no está disponible.
                        </div>
                    @else

                        {{-- ── 1 · Modelo (RESUELTO desde las decisiones — Guía §5) ──
                             El asesor ya no elige entre 32 nombres: responde
                             garantía y destino, agrega vehículos y marca cuál es
                             bien futuro; el modelo se deduce solo. Sexo de la
                             ficha; personas del codeudor/empresa. --}}
                        <div class="mb-3">
                            <div class="fw-bold small text-uppercase border-bottom pb-1 mb-2">1 · Modelo de contrato</div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1">Garantía *</label>
                                    <select class="form-select form-select-sm" wire:model.live="garantiaContrato"
                                            @if($esEmpresaContrato) disabled title="La empresa solo tiene modelos con GPS" @endif>
                                        <option value="gps">Con GPS</option>
                                        <option value="sin_gps">Sin GPS</option>
                                        <option value="custodia">Custodia</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1">Destino del depósito *</label>
                                    <select class="form-select form-select-sm" wire:model.live="destinoContrato"
                                            @if($garantiaContrato === 'custodia') disabled title="La custodia solo existe con depósito al deudor" @endif>
                                        @if($esEmpresaContrato)
                                            <option value="propio">Cuenta de la empresa</option>
                                            <option value="gerente">Cuenta personal del gerente</option>
                                        @else
                                            <option value="propio">Cuenta {{ ($deudores[0]['sexo'] ?? 'M') === 'F' ? 'de la deudora' : 'del deudor' }}</option>
                                            @if($puedeTerceroContrato)
                                                <option value="tercero">Cuenta de un tercero</option>
                                            @endif
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    @if($modeloContrato !== '')
                                        <div class="small pb-1">
                                            <span class="badge bg-dark" style="font-size:10px;">{{ $modeloContrato }}</span>
                                            <span class="fw-semibold">{{ $this->nombreModelo($modeloContrato) }}</span>
                                        </div>
                                    @else
                                        <div class="alert alert-warning py-1 px-2 mb-1 small">
                                            <i class="ti ti-alert-triangle"></i>
                                            Esta combinación no corresponde a ningún modelo del área
                                            @if(($deudores[0]['sexo'] ?? null) === null) (¿la ficha tiene sexo?)@endif.
                                        </div>
                                    @endif
                                    @error('modeloContrato') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    @if($presetContrato)
                                        <div class="d-flex gap-1 flex-wrap pb-1">
                                            @if($presetContrato['custodia'])
                                                <span class="badge bg-warning text-dark" style="font-size:10px;">Custodia</span>
                                            @elseif($presetContrato['gps'])
                                                <span class="badge bg-success" style="font-size:10px;">Con GPS</span>
                                            @else
                                                <span class="badge bg-secondary" style="font-size:10px;">Sin GPS</span>
                                            @endif
                                            <span class="badge bg-light text-dark border" style="font-size:10px;">
                                                {{ $presetContrato['personas'] === 'empresa' ? 'Persona jurídica' : ($presetContrato['personas'] === 2 ? '2 deudores' : '1 deudor') }}
                                            </span>
                                            <span class="badge bg-light text-dark border" style="font-size:10px;">
                                                {{ count($presetContrato['slots']) }} {{ count($presetContrato['slots']) === 1 ? 'bien' : 'bienes' }}
                                            </span>
                                            @if($presetContrato['destino'] === 'tercero')
                                                <span class="badge bg-info text-dark" style="font-size:10px;">Depósito a tercero</span>
                                            @elseif($presetContrato['destino'] === 'gerente')
                                                <span class="badge bg-info text-dark" style="font-size:10px;">Depósito al gerente</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-muted small pb-1">Completa las decisiones para armar el contrato.</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($presetContrato)
                            {{-- ── 2 · Crédito y vehículo(s) ── --}}
                            <div class="mb-3">
                                <div class="fw-bold small text-uppercase border-bottom pb-1 mb-2">2 · Crédito y vehículo(s)</div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold mb-1">Crédito *</label>
                                        <select class="form-select form-select-sm @error('contratoCreditoId') is-invalid @enderror"
                                                wire:model.live="contratoCreditoId">
                                            <option value="">— Selecciona el crédito —</option>
                                            @foreach($creditosActivos as $c)
                                                <option value="{{ $c->id }}">
                                                    #{{ $c->id }} — S/ {{ number_format((float) $c->importe, 2) }} — {{ $c->cuotas }} cuotas ({{ $c->tipoPlanillaLabel() }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('contratoCreditoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>

                                @if($vehiculos->isEmpty())
                                    <div class="alert alert-warning py-2 small mt-2 mb-0">
                                        <i class="ti ti-alert-triangle"></i>
                                        El cliente no tiene vehículos registrados: registra el vehículo (con placa) en su ficha antes de emitir el contrato.
                                    </div>
                                @else
                                    <div class="row g-2 mt-0">
                                        @foreach($contratoVehiculos as $i => $slot)
                                            <div class="col-md-6" wire:key="contrato-slot-{{ $i }}">
                                                <div class="d-flex justify-content-between align-items-center">
                                                <label class="form-label small fw-semibold mb-1">
                                                    Vehículo {{ $i + 1 }} (garantía) *
                                                    @if($slot['es_futuro'])
                                                        <span class="badge bg-warning text-dark" style="font-size:9px;">Bien futuro</span>
                                                    @endif
                                                </label>
                                                <div class="d-flex align-items-center gap-2">
                                                    @if($garantiaContrato === 'gps' && ! $esEmpresaContrato)
                                                        {{-- ¿Ya está inscrito a nombre del deudor? (Guía §5, decisión 4).
                                                             En el mixto el vehículo 1 pasa a ser el futuro solo. --}}
                                                        <div class="form-check form-switch mb-0">
                                                            <input class="form-check-input" type="checkbox" role="switch"
                                                                   id="futuro-{{ $i }}" wire:model.live="contratoVehiculos.{{ $i }}.es_futuro">
                                                            <label class="form-check-label small text-muted" for="futuro-{{ $i }}">Bien futuro</label>
                                                        </div>
                                                    @endif
                                                    @if(count($contratoVehiculos) > 1)
                                                        <button type="button" class="btn btn-xs btn-outline-danger py-0 px-1"
                                                                wire:click="quitarVehiculoContrato({{ $i }})" title="Quitar este vehículo">
                                                            <i class="ti ti-x"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                                </div>
                                                <select class="form-select form-select-sm @error('contratoVehiculos.'.$i.'.vehiculo_id') is-invalid @enderror"
                                                        wire:model.live="contratoVehiculos.{{ $i }}.vehiculo_id">
                                                    <option value="">— Selecciona el vehículo —</option>
                                                    @foreach($vehiculos as $v)
                                                        <option value="{{ $v->id }}">{{ $v->descripcion() }}</option>
                                                    @endforeach
                                                </select>
                                                @error('contratoVehiculos.'.$i.'.vehiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror

                                                @if($slot['es_futuro'])
                                                    {{-- Bien futuro: los tres datos del acta de transferencia que
                                                         cita la cláusula de declaración jurada. La FECHA es la
                                                         "fecha de transferencia" que exige la guía del área. --}}
                                                    <div class="row g-1 mt-1">
                                                        <div class="col-3">
                                                            <input type="date" class="form-control form-control-sm @error('contratoVehiculos.'.$i.'.fecha_acta') is-invalid @enderror"
                                                                   title="Fecha de la transferencia vehicular"
                                                                   wire:model.blur="contratoVehiculos.{{ $i }}.fecha_acta">
                                                            @error('contratoVehiculos.'.$i.'.fecha_acta') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-3">
                                                            <input type="text" class="form-control form-control-sm" placeholder="Kardex (ej. 0373-2026)"
                                                                   wire:model.blur="contratoVehiculos.{{ $i }}.kardex">
                                                        </div>
                                                        <div class="col-3">
                                                            <input type="text" class="form-control form-control-sm" placeholder="Notario"
                                                                   wire:model.blur="contratoVehiculos.{{ $i }}.notario">
                                                        </div>
                                                        <div class="col-3">
                                                            <input type="text" class="form-control form-control-sm" placeholder="Estado registral"
                                                                   title="Estado registral de la transferencia (ej. EN TRÁMITE DE INSCRIPCIÓN)"
                                                                   wire:model.blur="contratoVehiculos.{{ $i }}.estado_registral">
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @if(count($contratoVehiculos) < 2 && $garantiaContrato !== 'custodia' && ! $esEmpresaContrato)
                                        <button type="button" class="btn btn-sm btn-outline-success mt-2"
                                                wire:click="agregarVehiculoContrato">
                                            <i class="ti ti-plus"></i> Agregar segundo vehículo
                                        </button>
                                    @endif
                                @endif
                            </div>

                            {{-- ── 3 · Deudor(es) / Empresa ── --}}
                            <div class="mb-3">
                                <div class="fw-bold small text-uppercase border-bottom pb-1 mb-2">
                                    3 · {{ $presetContrato['personas'] === 'empresa' ? 'Deudora (persona jurídica)' : 'Deudor(es)' }}
                                </div>

                                @if($presetContrato['personas'] === 'empresa')
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Razón social *</label>
                                            <input type="text" class="form-control form-control-sm @error('empresa.razon_social') is-invalid @enderror"
                                                   wire:model.blur="empresa.razon_social">
                                            @error('empresa.razon_social') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">RUC *</label>
                                            <input type="text" class="form-control form-control-sm @error('empresa.ruc') is-invalid @enderror"
                                                   wire:model.blur="empresa.ruc">
                                            @error('empresa.ruc') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Partida registral</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.blur="empresa.partida">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Oficina registral</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.blur="empresa.oficina_registral">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Correo</label>
                                            <input type="email" class="form-control form-control-sm @error('empresa.correo') is-invalid @enderror"
                                                   wire:model.blur="empresa.correo">
                                            @error('empresa.correo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Domicilio</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.blur="empresa.domicilio">
                                        </div>
                                    </div>

                                    <div class="small fw-semibold mt-2 mb-1">Gerente general (firma en representación)</div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Nombre completo *</label>
                                            <input type="text" class="form-control form-control-sm @error('gerente.nombre') is-invalid @enderror @if(in_array('nombre', $autoGerente)) campo-api @endif"
                                                   wire:model.blur="gerente.nombre">
                                            @error('gerente.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Documento *</label>
                                            <div class="input-group input-group-sm">
                                                <select class="form-select form-select-sm @error('gerente.tipo_documento') is-invalid @enderror"
                                                        style="max-width: 5.5rem;" wire:model.live="gerente.tipo_documento">
                                                    <option value="DNI">DNI</option>
                                                    <option value="CE">CE</option>
                                                </select>
                                                <input type="text" class="form-control form-control-sm @error('gerente.dni') is-invalid @enderror"
                                                       wire:model.blur="gerente.dni">
                                                <button type="button" class="btn btn-danger" wire:click="consultarDocGerente"
                                                        wire:loading.attr="disabled" wire:target="consultarDocGerente"
                                                        title="Consultar: hereda de la ficha si está registrado; si no, RENIEC/Migraciones">
                                                    <span wire:loading.remove wire:target="consultarDocGerente"><i class="ti ti-search"></i></span>
                                                    <span wire:loading wire:target="consultarDocGerente" class="small">…</span>
                                                </button>
                                            </div>
                                            @error('gerente.dni') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Género *</label>
                                            <select class="form-select form-select-sm @error('gerente.sexo') is-invalid @enderror @if(in_array('sexo', $autoGerente)) campo-api @endif"
                                                    wire:model.live="gerente.sexo">
                                                <option value="M">Masculino</option>
                                                <option value="F">Femenino</option>
                                            </select>
                                            @error('gerente.sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Nacionalidad *</label>
                                            {{-- No flexiona (misma regla que el deudor). --}}
                                            <select class="form-select form-select-sm @error('gerente.nacionalidad') is-invalid @enderror @if(in_array('nacionalidad', $autoGerente)) campo-api @endif"
                                                    wire:model.blur="gerente.nacionalidad">
                                                @foreach(\App\Support\Documentos\Nacionalidades::paraValor($gerente['nacionalidad'] ?? null) as $opcion)
                                                    <option value="{{ $opcion }}">{{ $opcion }}</option>
                                                @endforeach
                                            </select>
                                            @error('gerente.nacionalidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Ocupación</label>
                                            <input type="text" class="form-control form-control-sm @if(in_array('ocupacion', $autoGerente)) campo-api @endif" wire:model.blur="gerente.ocupacion">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Estado civil</label>
                                            <select class="form-select form-select-sm @if(in_array('estado_civil', $autoGerente)) campo-api @endif" wire:model.live="gerente.estado_civil">
                                                <option value="">—</option>
                                                @foreach($estadosCiviles as $valor => $etiqueta)
                                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Domicilio</label>
                                            <input type="text" class="form-control form-control-sm @if(in_array('domicilio', $autoGerente)) campo-api @endif" wire:model.blur="gerente.domicilio">
                                        </div>
                                    </div>
                                @else
                                    @foreach($deudores as $i => $d)
                                        <div class="border rounded p-2 mb-2 {{ $i === 1 ? 'bg-light' : '' }}" wire:key="contrato-deudor-{{ $i }}">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-semibold">
                                                    Deudor {{ $i + 1 }}
                                                    @if($i === 0) <span class="text-muted fw-normal">(ficha del cliente — todo editable)</span> @endif
                                                </span>
                                                @if($i === 1 && $codeudorClientId)
                                                    <span class="badge bg-primary d-inline-flex align-items-center gap-1" style="font-size:10px;">
                                                        {{ $codeudorNombre }}
                                                        <button type="button" class="btn btn-link btn-sm text-white p-0 text-decoration-none"
                                                                style="font-size:10px; line-height:1;"
                                                                wire:click="quitarCodeudorContrato" title="Quitar codeudor">
                                                            <i class="ti ti-x"></i> Quitar
                                                        </button>
                                                    </span>
                                                @endif
                                            </div>

                                            @if($i === 1 && ! $codeudorClientId)
                                                <div class="position-relative mb-2">
                                                    <input type="text" class="form-control form-control-sm"
                                                           placeholder="Buscar codeudor por nombre o DNI (mín. 2 caracteres)…"
                                                           wire:model.live.debounce.300ms="buscarCodeudor">
                                                    @if($codeudoresEncontrados->isNotEmpty())
                                                        <div class="list-group position-absolute w-100 shadow-sm"
                                                             style="z-index:1080; max-height:200px; overflow:auto;">
                                                            @foreach($codeudoresEncontrados as $cod)
                                                                <button type="button" class="list-group-item list-group-item-action py-1 small"
                                                                        wire:key="codeudor-{{ $cod->id }}"
                                                                        wire:click="seleccionarCodeudorContrato({{ $cod->id }})">
                                                                    {{ $cod->fullName() }} — {{ $cod->documento }}
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    <div class="form-text" style="font-size:10px;">
                                                        Vincula un cliente registrado o escribe los datos manualmente abajo.
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Nombre completo *</label>
                                                    <input type="text" class="form-control form-control-sm @error('deudores.'.$i.'.nombre') is-invalid @enderror"
                                                           wire:model.blur="deudores.{{ $i }}.nombre">
                                                    @error('deudores.'.$i.'.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">DNI *</label>
                                                    <input type="text" class="form-control form-control-sm @error('deudores.'.$i.'.dni') is-invalid @enderror"
                                                           wire:model.blur="deudores.{{ $i }}.dni">
                                                    @error('deudores.'.$i.'.dni') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Sexo *</label>
                                                    <select class="form-select form-select-sm @error('deudores.'.$i.'.sexo') is-invalid @enderror"
                                                            wire:model.live="deudores.{{ $i }}.sexo">
                                                        <option value="M">Masculino (EL DEUDOR)</option>
                                                        <option value="F">Femenino (LA DEUDORA)</option>
                                                    </select>
                                                    @error('deudores.'.$i.'.sexo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Nacionalidad</label>
                                                    <input type="text" class="form-control form-control-sm" wire:model.blur="deudores.{{ $i }}.nacionalidad">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Ocupación</label>
                                                    <input type="text" class="form-control form-control-sm" wire:model.blur="deudores.{{ $i }}.ocupacion">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Estado civil</label>
                                                    <select class="form-select form-select-sm" wire:model.live="deudores.{{ $i }}.estado_civil">
                                                        <option value="">—</option>
                                                        @foreach($estadosCiviles as $valor => $etiqueta)
                                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Correo</label>
                                                    <input type="email" class="form-control form-control-sm @error('deudores.'.$i.'.correo') is-invalid @enderror"
                                                           wire:model.blur="deudores.{{ $i }}.correo">
                                                    @error('deudores.'.$i.'.correo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small mb-1">Domicilio</label>
                                                    <input type="text" class="form-control form-control-sm" wire:model.blur="deudores.{{ $i }}.domicilio">
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>

                            {{-- ── 4 · Montos y condiciones ── --}}
                            <div class="mb-2">
                                <div class="fw-bold small text-uppercase border-bottom pb-1 mb-2">4 · Montos y condiciones</div>

                                @if($contratoCreditoId && $totalCronograma === null)
                                    <div class="alert alert-warning py-1 small">
                                        <i class="ti ti-alert-triangle"></i>
                                        El crédito seleccionado no tiene cronograma con montos: el contrato no podrá emitirse.
                                    </div>
                                @endif

                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Valor del bien (S/)</label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control form-control-sm @error('valorBien') is-invalid @enderror"
                                               wire:model.live="valorBien" placeholder="0.00">
                                        @error('valorBien') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text" style="font-size:10px;">Default: valor en ficha del (los) vehículo(s).</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Monto máximo (S/)</label>
                                        <input type="number" step="0.01" min="0"
                                               class="form-control form-control-sm @error('montoMaximo') is-invalid @enderror"
                                               wire:model.live="montoMaximo" placeholder="0.00">
                                        @error('montoMaximo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @if($totalCronograma !== null)
                                            <div class="form-text" style="font-size:10px;">
                                                Total real del cronograma: S/ {{ number_format($totalCronograma, 2) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Cuota (S/)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0"
                                                   class="form-control @error('cuotaContrato') is-invalid @enderror"
                                                   wire:model.live="cuotaContrato" @readonly(! $editarCuota)>
                                            <button class="btn btn-outline-secondary" type="button" wire:click="$toggle('editarCuota')"
                                                    title="{{ $editarCuota ? 'Bloquear la cuota' : 'Editar la cuota (default: la del cronograma)' }}">
                                                <i class="ti {{ $editarCuota ? 'ti-lock-open' : 'ti-pencil' }}"></i>
                                            </button>
                                        </div>
                                        @error('cuotaContrato') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        <div class="form-text" style="font-size:10px;">Default: cuota del cronograma (moda).</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Fecha del contrato *</label>
                                        <input type="date" class="form-control form-control-sm @error('fechaContrato') is-invalid @enderror"
                                               wire:model.live="fechaContrato">
                                        @error('fechaContrato') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Banco del desembolso *</label>
                                        <select class="form-select form-select-sm @error('bancoDesembolso') is-invalid @enderror"
                                                wire:model.live="bancoDesembolso">
                                            <option value="">— Selecciona el banco —</option>
                                            @foreach($bancosDesembolso as $clave => $nombreLegal)
                                                <option value="{{ $clave }}">{{ $nombreLegal }}</option>
                                            @endforeach
                                        </select>
                                        @error('bancoDesembolso') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div class="form-text" style="font-size:10px;">
                                            La cláusula de constancia lo menciona; el voucher va aparte, en el Anexo 2.
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        @if($presetContrato['destino'] === 'gerente')
                                            <div class="alert alert-info py-1 small mb-0 w-100">
                                                <i class="ti ti-info-circle"></i>
                                                El modelo elegido consigna el desembolso al gerente general de la empresa deudora.
                                            </div>
                                        @elseif($presetContrato['destino'] === 'propio')
                                            <div class="alert alert-light border py-1 small mb-0 w-100">
                                                <i class="ti ti-info-circle"></i>
                                                El modelo elegido consigna el desembolso a cuenta propia del deudor.
                                            </div>
                                        @endif
                                    </div>

                                    @if($presetContrato['destino'] === 'tercero')
                                        <div class="col-12">
                                            <div class="border rounded p-2">
                                                <div class="small fw-semibold mb-1">Tercero autorizado a recibir el desembolso</div>
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label small mb-1">Nombre completo *</label>
                                                        <input type="text" class="form-control form-control-sm @error('tercero.nombre') is-invalid @enderror @if(in_array('nombre', $autoTercero)) campo-api @endif"
                                                               wire:model.blur="tercero.nombre">
                                                        @error('tercero.nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">DNI *</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" class="form-control form-control-sm @error('tercero.dni') is-invalid @enderror"
                                                                   wire:model.blur="tercero.dni">
                                                            <button type="button" class="btn btn-danger" wire:click="consultarDocTercero"
                                                                    wire:loading.attr="disabled" wire:target="consultarDocTercero" title="Consultar DNI">
                                                                <span wire:loading.remove wire:target="consultarDocTercero"><i class="ti ti-search"></i></span>
                                                                <span wire:loading wire:target="consultarDocTercero" class="small">…</span>
                                                            </button>
                                                        </div>
                                                        @error('tercero.dni') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">Banco *</label>
                                                        <input type="text" class="form-control form-control-sm @error('tercero.banco') is-invalid @enderror"
                                                               wire:model.blur="tercero.banco" placeholder="BCP / Interbank / ...">
                                                        @error('tercero.banco') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small mb-1">N° de cuenta o CCI</label>
                                                        <input type="text" class="form-control form-control-sm" wire:model.blur="tercero.cuenta">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label small mb-1">Motivo del depósito a tercero</label>
                                                        <input type="text" class="form-control form-control-sm" wire:model.blur="tercero.motivo"
                                                               placeholder="Ej.: pago del saldo de precio del vehículo al vendedor">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="col-12">
                                        <label class="form-label small mb-1">Cláusulas adicionales (opcional)</label>
                                        <textarea rows="3" class="form-control form-control-sm @error('clausulasAdicionales') is-invalid @enderror"
                                                  wire:model.blur="clausulasAdicionales"
                                                  placeholder="Texto íntegro de cláusulas extra; se insertan antes del cierre del contrato."></textarea>
                                        @error('clausulasAdicionales') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Vista previa (mismo render 'previa' del snapshot que se emitirá) --}}
                        @if($htmlPreviewContrato !== '')
                            <div class="border rounded mt-3 p-2 bg-light" style="max-height:65vh; overflow:auto;">
                                {{-- Proporción A4 VERTICAL: el iframe al 100% del modal hacía
                                     ver el documento apaisado aunque el PDF sale parado. --}}
                                <iframe srcdoc="{{ $htmlPreviewContrato }}"
                                        style="width:21cm; height:29.7cm; max-width:100%; border:1px solid #ccc;
                                               background:#fff; display:block; margin:0 auto;"
                                        title="Vista previa del contrato"></iframe>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-outline-dark"
                            wire:click="previsualizarContrato" wire:loading.attr="disabled"
                            wire:target="previsualizarContrato,generarContrato"
                            @disabled($creditosActivos->isEmpty() || empty($modelosAgrupados))>
                        <i class="ti ti-eye"></i>
                        <span wire:loading.remove wire:target="previsualizarContrato">Vista previa</span>
                        <span wire:loading wire:target="previsualizarContrato">Generando previa…</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-dark"
                            wire:click="generarContrato" wire:loading.attr="disabled"
                            wire:target="previsualizarContrato,generarContrato"
                            @disabled($creditosActivos->isEmpty() || empty($modelosAgrupados))>
                        <i class="ti ti-file-check"></i>
                        <span wire:loading.remove wire:target="generarContrato">Generar contrato</span>
                        <span wire:loading wire:target="generarContrato">Generando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Modal "Generar Anexo 2" (constancia de entrega del monto — FASE 3) ═══ --}}
    <div class="modal fade" id="anexo2Modal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:anexo2-modal-open.window="modal.show()"
         x-on:anexo2-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-file-plus text-info"></i>
                        Generar Anexo 2 — Constancia de entrega del monto · {{ $client->fullName() }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    @if($creditosActivos->isEmpty())
                        <div class="alert alert-warning py-2 small mb-0">
                            <i class="ti ti-alert-triangle"></i>
                            El cliente no tiene créditos activos: no hay desembolso que constatar.
                        </div>
                    @else
                        {{-- ── Crédito, fecha y combo banco × modalidad ── --}}
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Crédito desembolsado *</label>
                                <select class="form-select form-select-sm @error('anexo2CreditoId') is-invalid @enderror"
                                        wire:model.live="anexo2CreditoId">
                                    <option value="">— Selecciona el crédito —</option>
                                    @foreach($creditosActivos as $c)
                                        <option value="{{ $c->id }}">
                                            #{{ $c->id }} — S/ {{ number_format((float) $c->importe, 2) }} — {{ $c->cuotas }} cuotas ({{ $c->tipoPlanillaLabel() }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('anexo2CreditoId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Fecha del documento *</label>
                                <input type="date" class="form-control form-control-sm @error('fechaAnexo2') is-invalid @enderror"
                                       wire:model.live="fechaAnexo2" max="{{ now()->format('Y-m-d') }}">
                                @error('fechaAnexo2') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Banco del voucher *</label>
                                <select class="form-select form-select-sm @error('anexo2Banco') is-invalid @enderror"
                                        wire:model.live="anexo2Banco">
                                    <option value="">— Selecciona el banco —</option>
                                    @foreach($bancosVoucher as $clave => $nombre)
                                        <option value="{{ $clave }}">{{ $nombre }}</option>
                                    @endforeach
                                </select>
                                @error('anexo2Banco') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1">Modalidad de la operación *</label>
                                <select class="form-select form-select-sm @error('anexo2Modalidad') is-invalid @enderror"
                                        wire:model.live="anexo2Modalidad" @disabled($anexo2Banco === '')>
                                    <option value="">— Selecciona la modalidad —</option>
                                    @foreach($modalidadesAnexo2 as $mod)
                                        <option value="{{ $mod }}">{{ $modalidadesVoucher[$mod] ?? $mod }}</option>
                                    @endforeach
                                </select>
                                @error('anexo2Modalidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @if($anexo2Banco === '')
                                    <div class="form-text" style="font-size:10px;">Elige primero el banco: cada uno tiene sus modalidades.</div>
                                @endif
                            </div>
                        </div>

                        {{-- ── Transcripción del voucher (inputs dinámicos del combo) ── --}}
                        @if(! empty($camposAnexo2))
                            <div class="mt-3" wire:key="anexo2-campos-{{ $anexo2Banco }}-{{ $anexo2Modalidad }}">
                                <div class="fw-bold small text-uppercase border-bottom pb-1 mb-2">
                                    Transcripción del voucher — {{ $tituloVoucherAnexo2 }}
                                </div>
                                <div class="row g-2">
                                    @foreach($camposAnexo2 as $clave => [$label, $requerido])
                                        <div class="col-md-4" wire:key="anexo2-campo-{{ $anexo2Banco }}-{{ $anexo2Modalidad }}-{{ $clave }}">
                                            <label class="form-label small mb-1">
                                                {{ $label }}@if($requerido) *@endif
                                            </label>
                                            <input type="text" class="form-control form-control-sm"
                                                   wire:model.blur="anexo2Campos.{{ $clave }}"
                                                   placeholder="{{ $requerido ? 'Tal como figura en el voucher' : 'Opcional' }}">
                                            @if($clave === 'monto' && $montoDesembolsoAnexo2 !== null)
                                                <div class="form-text" style="font-size:10px;">
                                                    Debe coincidir con el desembolso: S/ {{ number_format($montoDesembolsoAnexo2, 2) }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- ── Foto del comprobante (opcional; se embebe en la constancia) ── --}}
                        <div class="mt-3">
                            <div class="fw-bold small text-uppercase border-bottom pb-1 mb-2">Foto del comprobante</div>
                            <div class="row g-2 align-items-start">
                                <div class="col-md-6">
                                    <input type="file" accept="image/*"
                                           class="form-control form-control-sm @error('comprobante') is-invalid @enderror"
                                           wire:model="comprobante">
                                    @error('comprobante') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text" style="font-size:10px;">
                                        Imagen del voucher (máx. 4 MB). Sin foto, la constancia sale solo con la transcripción.
                                    </div>
                                    <div wire:loading wire:target="comprobante" class="small text-muted">
                                        <i class="ti ti-loader"></i> Subiendo imagen…
                                    </div>
                                </div>
                                @if($comprobante && ! $errors->has('comprobante'))
                                    <div class="col-md-6 text-center">
                                        <img src="{{ $comprobante->temporaryUrl() }}" alt="Comprobante subido"
                                             class="border rounded" style="max-height:160px; max-width:100%;">
                                        <div class="form-text" style="font-size:10px;">Se embeberá al generar (la vista previa no la incluye).</div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Vista previa (render 'previa' del snapshot; la foto sale como recuadro placeholder) --}}
                        @if($htmlPreviewAnexo2 !== '')
                            <div class="border rounded mt-3 p-2 bg-light" style="max-height:65vh; overflow:auto;">
                                {{-- Proporción A4 VERTICAL: el iframe al 100% del modal hacía
                                     ver el documento apaisado aunque el PDF sale parado. --}}
                                <iframe srcdoc="{{ $htmlPreviewAnexo2 }}"
                                        style="width:21cm; height:29.7cm; max-width:100%; border:1px solid #ccc;
                                               background:#fff; display:block; margin:0 auto;"
                                        title="Vista previa del Anexo 2"></iframe>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-outline-dark"
                            wire:click="previsualizarAnexo2" wire:loading.attr="disabled"
                            wire:target="previsualizarAnexo2,generarAnexo2,comprobante"
                            @disabled($creditosActivos->isEmpty())>
                        <i class="ti ti-eye"></i>
                        <span wire:loading.remove wire:target="previsualizarAnexo2">Vista previa</span>
                        <span wire:loading wire:target="previsualizarAnexo2">Generando previa…</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-info"
                            wire:click="generarAnexo2" wire:loading.attr="disabled"
                            wire:target="previsualizarAnexo2,generarAnexo2,comprobante"
                            @disabled($creditosActivos->isEmpty())>
                        <i class="ti ti-file-check"></i>
                        <span wire:loading.remove wire:target="generarAnexo2">Generar</span>
                        <span wire:loading wire:target="generarAnexo2">Generando…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
