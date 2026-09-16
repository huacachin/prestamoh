<div>
    {{-- ═══ Modal de notificaciones WhatsApp (cobranza) ═══ --}}
    <div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);"
         x-on:notif-open.window="modal.show()"
         x-on:notif-close.window="modal.hide()">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-brand-whatsapp text-success"></i>
                        Notificaciones — {{ $clientName }}
                        @if($vencidas > 0)
                            <span class="badge {{ $vencidas >= 3 ? 'bg-danger' : 'bg-warning text-dark' }}" style="font-size:10px;">
                                {{ $vencidas }} cuotas vencidas
                            </span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">

                    {{-- Selector de crédito (multi-crédito): con un solo
                         atrasado se auto-selecciona y esto no aparece --}}
                    @if(count($creditosAtrasados) > 1)
                        <div class="border rounded p-2 mb-3" style="background:#fffdf5;">
                            <div class="small fw-semibold mb-1">
                                <i class="ti ti-alert-triangle text-warning"></i>
                                Este cliente tiene {{ count($creditosAtrasados) }} créditos atrasados — selecciona cuál notificar:
                            </div>
                            <div class="d-flex flex-column gap-1">
                                @foreach($creditosAtrasados as $ca)
                                    <button type="button"
                                            class="btn btn-sm text-start {{ $creditId === $ca['id'] ? 'btn-dark' : 'btn-outline-secondary' }}"
                                            wire:click="seleccionarCredito({{ $ca['id'] }})">
                                        <b>Crédito #{{ $ca['id'] }}</b> — {{ $ca['tipo'] }} —
                                        {{ $ca['vencidas'] }} cuotas vencidas —
                                        S/ {{ number_format($ca['atrasado'], 2) }} atrasado
                                        @if($ca['ultima'])
                                            — última notif. {{ $ca['ultima'] }}
                                        @else
                                            — sin notificaciones
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @elseif($creditId)
                        <div class="small text-muted mb-2">
                            Crédito <b>#{{ $creditId }}</b> — {{ $vencidas }} cuotas vencidas
                        </div>
                    @endif

                    {{-- Editor de nueva notificación --}}
                    @if($editor)
                        <div class="border rounded p-2 mb-3" style="background:#f6fbf7;">
                            <label class="form-label small fw-semibold mb-1">Mensaje a enviar por WhatsApp</label>
                            <textarea class="form-control form-control-sm @error('texto') is-invalid @enderror"
                                      rows="4" wire:model="texto"></textarea>
                            @error('texto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-success"
                                        wire:click="enviarNotif" wire:loading.attr="disabled" wire:target="enviarNotif">
                                    <i class="ti ti-brand-whatsapp"></i>
                                    <span wire:loading.remove wire:target="enviarNotif">Enviar por WhatsApp</span>
                                    <span wire:loading wire:target="enviarNotif">Guardando…</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" wire:click="$set('editor', false)">Cancelar</button>
                            </div>
                            <div class="form-text">Al enviar se guarda en el historial y se abre WhatsApp con el texto listo.</div>
                        </div>
                    @elseif($creditId)
                        <button type="button" class="btn btn-sm btn-success mb-3" wire:click="nuevaNotif">
                            <i class="ti ti-plus"></i> Nueva notificación
                        </button>
                    @endif

                    {{-- Historial --}}
                    @if($notifs->isEmpty())
                        <p class="text-muted small mb-0">Aún no se han enviado notificaciones a este cliente.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle" style="font-size:12px;">
                                <thead class="bg-primary">
                                    <tr>
                                        <th class="text-center" style="width:34px;">#</th>
                                        <th class="text-center" style="width:110px;">Enviada</th>
                                        <th>Mensaje</th>
                                        <th class="text-center" style="width:90px;">Usuario</th>
                                        <th style="width:220px;">Compromiso de pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($notifs as $n)
                                    <tr>
                                        <td class="text-center fw-bold">
                                            {{ $n->numero }}
                                            <span class="badge bg-secondary d-block mx-auto mt-1" style="font-size:9px; width:fit-content;"
                                                  title="Crédito de la notificación">
                                                {{ $n->credit_id ? '#'.$n->credit_id : 'General' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            {{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y H:i') }}
                                            @if($n->cuotas_vencidas !== null)
                                                <span class="badge {{ $n->cuotas_vencidas >= 3 ? 'bg-danger' : 'bg-warning text-dark' }} d-block mx-auto mt-1"
                                                      style="font-size: 9px; width: fit-content;"
                                                      title="Cuotas vencidas al momento del envío">
                                                    {{ $n->cuotas_vencidas }} venc.
                                                </span>
                                            @endif
                                        </td>
                                        <td style="max-width: 320px;">
                                            @if(mb_strlen($n->mensaje) > 50)
                                                <span data-bs-toggle="tooltip" data-bs-placement="top"
                                                      data-bs-custom-class="notif-tooltip"
                                                      data-bs-title="{{ $n->mensaje }}" style="cursor: help;">
                                                    {{ mb_substr($n->mensaje, 0, 50) }}…
                                                </span>
                                            @else
                                                {{ $n->mensaje }}
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $n->usuario ?? $n->usuario_name ?? '—' }}</td>
                                        <td>
                                            {{-- Compromisos MÚLTIPLES por notificación (ej. "25/08
                                                 paga 2 cuotas" + "30/08 paga 3"), todos editables --}}
                                            @foreach($compromisos[$n->id] ?? [] as $comp)
                                                @if($compEditId === $comp->id)
                                                    <div class="d-flex flex-column gap-1 mb-1 border rounded p-1">
                                                        <input type="date" class="form-control form-control-sm @error('compFecha') is-invalid @enderror"
                                                               wire:model="compFecha">
                                                        @error('compFecha') <div class="text-danger" style="font-size:10px;">{{ $message }}</div> @enderror
                                                        <input type="text" class="form-control form-control-sm" placeholder="Detalle (ej. paga 2 cuotas)"
                                                               wire:model="compDetalle" maxlength="5000">
                                                        <div class="d-flex gap-1">
                                                            <button type="button" class="btn btn-xs btn-dark" style="padding:2px 8px; font-size:10px;"
                                                                    wire:click="guardarCompromiso">Guardar</button>
                                                            <button type="button" class="btn btn-xs btn-secondary" style="padding:2px 8px; font-size:10px;"
                                                                    wire:click="$set('compEditId', null)">Cancelar</button>
                                                        </div>
                                                    </div>
                                                @else
                                                    @php
                                                        $cf = \Carbon\Carbon::parse($comp->fecha);
                                                        $dias = now()->startOfDay()->diffInDays($cf->copy()->startOfDay(), false);
                                                        $cfColor = $comp->cumplido_at ? '#6c757d' : ($dias <= 0 ? '#dc3545' : ($dias <= 2 ? '#fd7e14' : '#198754'));
                                                    @endphp
                                                    <div class="d-flex align-items-start gap-1 mb-1" style="line-height:1.2;">
                                                        <div class="flex-grow-1">
                                                            <b style="color: {{ $cfColor }};"><i class="ti ti-calendar-event"></i> {{ $cf->format('d/m/Y') }}</b>
                                                            @if($comp->cumplido_at)
                                                                <span class="badge bg-success" style="font-size:9px;">cumplido</span>
                                                            @endif
                                                            @if($comp->detalle)
                                                                <div class="text-muted" style="font-size:11px;">{{ $comp->detalle }}</div>
                                                            @endif
                                                        </div>
                                                        <a href="#" wire:click.prevent="toggleCumplido({{ $comp->id }})"
                                                           title="{{ $comp->cumplido_at ? 'Volver a pendiente' : 'Marcar cumplido' }}"
                                                           class="{{ $comp->cumplido_at ? 'text-secondary' : 'text-success' }}"><i class="ti ti-check"></i></a>
                                                        <a href="#" wire:click.prevent="editarCompromiso({{ $comp->id }})" title="Editar"><i class="ti ti-pencil"></i></a>
                                                        <a href="#" wire:click.prevent="eliminarCompromiso({{ $comp->id }})"
                                                           data-confirmar="¿Eliminar este compromiso?" title="Eliminar" class="text-danger"><i class="ti ti-trash"></i></a>
                                                    </div>
                                                @endif
                                            @endforeach

                                            @if($compNotifId === $n->id && ! $compEditId)
                                                {{-- Mini-form de compromiso NUEVO --}}
                                                <div class="d-flex flex-column gap-1 border rounded p-1">
                                                    <input type="date" class="form-control form-control-sm @error('compFecha') is-invalid @enderror"
                                                           wire:model="compFecha">
                                                    @error('compFecha') <div class="text-danger" style="font-size:10px;">{{ $message }}</div> @enderror
                                                    <input type="text" class="form-control form-control-sm" placeholder="Detalle (ej. paga 2 cuotas)"
                                                           wire:model="compDetalle" maxlength="5000">
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-xs btn-dark" style="padding:2px 8px; font-size:10px;"
                                                                wire:click="guardarCompromiso">Guardar</button>
                                                        <button type="button" class="btn btn-xs btn-secondary" style="padding:2px 8px; font-size:10px;"
                                                                wire:click="$set('compNotifId', null)">Cancelar</button>
                                                    </div>
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-dark" style="padding:2px 8px; font-size:10px;"
                                                        wire:click="abrirCompromiso({{ $n->id }})">
                                                    <i class="ti ti-calendar-plus"></i> {{ isset($compromisos[$n->id]) ? 'Agregar' : 'Compromiso' }}
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
        </div>
    </div>



    <script>
        document.addEventListener('livewire:init', () => {
            // Al guardar la notificación, el componente pide abrir WhatsApp con el texto.
            Livewire.on('notif-wa', (e) => {
                const url = e?.url ?? e?.[0]?.url;
                if (url) window.open(url, '_blank');
            });

            // Tooltips Bootstrap del modal (mensaje truncado a 50 chars):
            // se re-inicializan tras cada update de Livewire.
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => setTimeout(() => {
                    if (typeof bootstrap === 'undefined') return;
                    document.querySelectorAll('#notifModal [data-bs-toggle="tooltip"]')
                        .forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
                }, 60));
            });
        });
    </script>

    <style>
        /* Tooltip del mensaje de notificación: conserva saltos de línea */
        .notif-tooltip .tooltip-inner {
            white-space: pre-line;
            text-align: left;
            max-width: 340px;
        }
    </style>
</div>
