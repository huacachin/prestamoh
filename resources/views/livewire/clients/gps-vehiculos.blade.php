<div class="mt-3">
    <style>
        .placa-gps { border: 1px solid #e3e6ea; border-radius: 6px; margin-bottom: 10px; background: #fff; }
        .placa-gps .placa-cabecera { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;
                                     padding: 6px 10px; background: #f6f7f9; border-bottom: 1px solid #e3e6ea; border-radius: 6px 6px 0 0; }
        .placa-gps .placa-nombre { font-weight: 700; font-size: 14px; letter-spacing: .5px; }
        .placa-gps .placa-cuerpo { padding: 8px 10px; }
        .placa-gps .alta-ubicacion { border: 1px dashed #c9cdd2; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; background: #fcfcfa; }
        .placa-gps table { font-size: 12px; margin-bottom: 0; }
    </style>

    <h6 class="mb-2" style="color:red;">
        Ubicaciones GPS de los vehículos
        <span class="text-muted small fw-normal d-block d-sm-inline">(dónde estaba cada vehículo en garantía y cuándo)</span>
    </h6>

    @if($msg)
        <div class="alert {{ $msgType === 'ok' ? 'alert-success' : 'alert-danger' }} py-2 mb-2 small">{{ $msg }}</div>
    @endif

    @if($vehiculos->isEmpty() && $huerfanas->isEmpty())
        <div class="text-muted small fst-italic">
            Aún no se han agregado vehículos a este cliente.
            @if($puedeEditar)
                <a href="{{ route('clients.edit', ['id' => $clientId, 'tab' => 'vehiculos']) }}">Agregar desde la pestaña Vehículos</a>
            @endif
        </div>
    @endif

    {{-- ═══ Una tarjeta por placa: botón, formulario y sus ubicaciones ═══ --}}
    @foreach($vehiculos as $v)
        @php $ubicaciones = $porVehiculo->get($v->id, collect()); @endphp
        <div class="placa-gps" wire:key="placa-{{ $v->id }}">
            <div class="placa-cabecera">
                <div>
                    <span class="placa-nombre"><i class="ti ti-car"></i> {{ $v->placa }}</span>
                    <span class="text-muted small ms-1">{{ trim($v->marca.' '.$v->modelo) }}</span>
                    <span class="text-muted small ms-1">· {{ $ubicaciones->count() }} {{ $ubicaciones->count() === 1 ? 'ubicación' : 'ubicaciones' }}</span>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    {{-- 06/11 (Trello): la cuenta del GPS de la placa, en un modal. Verde si ya está registrada. --}}
                    @php $cuentaV = $cuentas->get($v->id); @endphp
                    @if($cuentaV || $puedeEditar)
                        <button type="button" class="btn btn-sm py-0 {{ $cuentaV ? 'btn-success' : 'btn-outline-dark' }}"
                                wire:click="abrirCuenta({{ $v->id }})" wire:loading.attr="disabled" wire:target="abrirCuenta({{ $v->id }})"
                                title="{{ $cuentaV ? 'Cuenta GPS: '.$cuentaV->aplicativo : 'Registrar la cuenta del GPS de esta placa' }}">
                            <i class="ti ti-key"></i> Cuenta GPS
                        </button>
                    @endif
                    @if($puedeEditar && $formVehiculoId !== $v->id)
                        <button type="button" class="btn btn-sm btn-dark py-0" wire:click="nuevo({{ $v->id }})">
                            <i class="ti ti-plus"></i> Agregar ubicación
                        </button>
                    @endif
                </div>
            </div>
            <div class="placa-cuerpo">
                @if($formVehiculoId === $v->id)
                    <div class="alta-ubicacion">
                        @if($editId)
                            <div class="small fw-semibold mb-1"><i class="ti ti-pencil"></i> Editando la ubicación · la fecha de registro no cambia</div>
                        @endif
                        <div class="row g-2">
                            <div class="col-12 col-md-5">
                                <label class="form-label mb-0 small">Coordenadas * <span class="text-muted">(o el enlace de Google Maps)</span></label>
                                <input type="text" class="form-control form-control-sm @error('form.coordenadas') is-invalid @enderror"
                                       wire:model="form.coordenadas" wire:keydown.enter="guardar" autocomplete="off" autofocus
                                       placeholder="-12.014431, -76.824936 o https://maps.app.goo.gl/…">
                                @error('form.coordenadas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label mb-0 small">Descripción</label>
                                <input type="text" class="form-control form-control-sm @error('form.descripcion') is-invalid @enderror" maxlength="1000"
                                       wire:model="form.descripcion" wire:keydown.enter="guardar" autocomplete="off"
                                       placeholder="Ej.: parado frente al mercado de Huaycán, el cliente dice que trabaja ahí">
                                @error('form.descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-dark" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                                <i class="ti ti-device-floppy"></i>
                                <span wire:loading.remove wire:target="guardar">{{ $editId ? 'Guardar cambios' : 'Guardar' }}</span>
                                <span wire:loading wire:target="guardar">Guardando…</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelar"><i class="ti ti-x"></i> Cancelar</button>
                            @if($editId && ($editando = $ubicaciones->firstWhere('id', $editId)))
                                {{-- 13/10 (Antony): Eliminar solo aparece mientras se edita; misma regla del mismo día (@creadoEl + ConReglasDeEliminacion). --}}
                                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" wire:click="eliminar({{ $editando->id }})" @creadoEl($editando)
                                        data-confirmar="¿Eliminar la ubicación del {{ $editando->fecha->format('d/m/Y H:i') }} del vehículo {{ $v->placa }}?" title="Eliminar esta ubicación">
                                    <i class="ti ti-trash"></i> Eliminar
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                @include('livewire.clients.partials._ubicaciones-vehiculo', ['ubicaciones' => $ubicaciones, 'placa' => $v->placa])
            </div>
        </div>
    @endforeach

    {{-- ═══ Ubicaciones de placas que ya no están entre los vehículos del cliente ═══ --}}
    @foreach($huerfanas as $placa => $ubicaciones)
        <div class="placa-gps" wire:key="placa-huerfana-{{ $placa }}">
            <div class="placa-cabecera">
                <div>
                    <span class="placa-nombre"><i class="ti ti-car-off"></i> {{ $placa }}</span>
                    <span class="text-muted small ms-1">vehículo ya no registrado en la ficha</span>
                    <span class="text-muted small ms-1">· {{ $ubicaciones->count() }} {{ $ubicaciones->count() === 1 ? 'ubicación' : 'ubicaciones' }}</span>
                </div>
            </div>
            <div class="placa-cuerpo">
                @include('livewire.clients.partials._ubicaciones-vehiculo', ['ubicaciones' => $ubicaciones, 'placa' => $placa])
            </div>
        </div>
    @endforeach

    {{-- ═══ Modal "Cuenta GPS" de una placa (06/11/2026, Trello) ═══
         Aplicativo, enlace, usuario y contraseña de la plataforma del GPS. Al cerrarse, la contraseña
         sale del estado del componente (cerrarCuenta). --}}
    <div class="modal fade" id="cuentaGpsModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="{ modal: null, ver: false, copiado: null,
                   copiar(texto, clave) { navigator.clipboard.writeText(texto || '').then(() => { this.copiado = clave; setTimeout(() => this.copiado = null, 1500); }); } }"
         x-init="modal = bootstrap.Modal.getOrCreateInstance($el);
                 $el.addEventListener('hidden.bs.modal', () => { ver = false; if ($wire.cuentaVehiculoId) $wire.cerrarCuenta(); });"
         x-on:cuenta-gps-modal-open.window="ver = false; modal.show()"
         x-on:cuenta-gps-modal-close.window="modal.hide()">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0">
                        <i class="ti ti-key text-secondary"></i> Cuenta GPS
                        @if($vehiculoCuenta)
                            · <span class="fw-bold">{{ $vehiculoCuenta->placa }}</span>
                            <span class="text-muted small fw-normal">{{ trim($vehiculoCuenta->marca.' '.$vehiculoCuenta->modelo) }}</span>
                        @endif
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @php $guardada = $vehiculoCuenta ? $cuentas->get($vehiculoCuenta->id) : null; @endphp
                    <div class="mb-2">
                        <label class="form-label small mb-1">Aplicativo *</label>
                        <input type="text" class="form-control form-control-sm {{ $puedeEditar ? 'campo-obligatorio' : 'bg-light' }} @error('cuenta.aplicativo') is-invalid @enderror"
                               wire:model="cuenta.aplicativo" list="aplicativos-gps" maxlength="100" autocomplete="off" @readonly(! $puedeEditar)
                               placeholder="Ej.: GPS Perú, Protrack, Wialon">
                        <datalist id="aplicativos-gps">
                            @foreach($aplicativosPrevios as $app)
                                <option value="{{ $app }}"></option>
                            @endforeach
                        </datalist>
                        @error('cuenta.aplicativo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Enlace <span class="text-muted">(página para entrar)</span></label>
                        <div class="input-group input-group-sm has-validation">
                            <input type="text" class="form-control {{ $puedeEditar ? '' : 'bg-light' }} @error('cuenta.enlace') is-invalid @enderror"
                                   wire:model="cuenta.enlace" maxlength="500" autocomplete="off" @readonly(! $puedeEditar)
                                   placeholder="https://…">
                            {{-- 06/11 (Antony): el enlace también se copia, como el usuario y la contraseña. --}}
                            <button type="button" class="btn btn-outline-secondary" x-on:click="copiar($wire.cuenta.enlace, 'enlace')" title="Copiar el enlace">
                                <i class="ti" :class="copiado === 'enlace' ? 'ti-check' : 'ti-copy'"></i>
                            </button>
                            @if($guardada?->enlace)
                                <a class="btn btn-outline-secondary" href="{{ $guardada->enlace }}" target="_blank" rel="noopener noreferrer" title="Abrir el enlace en otra pestaña">
                                    <i class="ti ti-external-link"></i> Abrir
                                </a>
                            @endif
                            @error('cuenta.enlace') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Usuario *</label>
                        <div class="input-group input-group-sm has-validation">
                            <input type="text" class="form-control {{ $puedeEditar ? 'campo-obligatorio' : 'bg-light' }} @error('cuenta.usuario') is-invalid @enderror"
                                   wire:model="cuenta.usuario" maxlength="150" autocomplete="off" @readonly(! $puedeEditar)>
                            <button type="button" class="btn btn-outline-secondary" x-on:click="copiar($wire.cuenta.usuario, 'usuario')" title="Copiar el usuario">
                                <i class="ti" :class="copiado === 'usuario' ? 'ti-check' : 'ti-copy'"></i>
                            </button>
                            @error('cuenta.usuario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small mb-1">Contraseña *</label>
                        <div class="input-group input-group-sm has-validation">
                            {{-- autocomplete="new-password": que el navegador no la guarde ni rellene con la clave del sistema. --}}
                            <input :type="ver ? 'text' : 'password'" type="password"
                                   class="form-control {{ $puedeEditar ? 'campo-obligatorio' : 'bg-light' }} @error('cuenta.contrasena') is-invalid @enderror"
                                   wire:model="cuenta.contrasena" maxlength="150" autocomplete="new-password" @readonly(! $puedeEditar)>
                            <button type="button" class="btn btn-outline-secondary" x-on:click="ver = ! ver" :title="ver ? 'Ocultar' : 'Ver la contraseña'">
                                <i class="ti" :class="ver ? 'ti-eye-off' : 'ti-eye'"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" x-on:click="copiar($wire.cuenta.contrasena, 'contrasena')" title="Copiar la contraseña">
                                <i class="ti" :class="copiado === 'contrasena' ? 'ti-check' : 'ti-copy'"></i>
                            </button>
                            @error('cuenta.contrasena') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    @if($guardada)
                        <div class="text-muted small mt-2">
                            Registrada por {{ $guardada->registradoPor?->name ?? '—' }} el {{ $guardada->created_at->format('d/m/Y H:i') }}
                            @if($guardada->actualizadoPor)
                                · actualizada por {{ $guardada->actualizadoPor->name }} el {{ $guardada->updated_at->format('d/m/Y H:i') }}
                            @endif
                        </div>
                    @endif
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal"><i class="ti ti-x"></i> {{ $puedeEditar ? 'Cancelar' : 'Cerrar' }}</button>
                    @if($puedeEditar)
                        <button type="button" class="btn btn-sm btn-dark" wire:click="guardarCuenta" wire:loading.attr="disabled" wire:target="guardarCuenta">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="guardarCuenta">Guardar</span>
                            <span wire:loading wire:target="guardarCuenta">Guardando…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
