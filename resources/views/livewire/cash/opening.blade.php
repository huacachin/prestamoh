{{-- 10/10 (Antony): la edición del importe se abre y se cierra en el navegador (editando/valor);
     solo Guardar va al servidor. $movil lo fija Alpine al cargar: se pinta una sola lista. --}}
<div class="container-fluid"
     x-data="{ editando: null, valor: '' }"
     x-init="if (window.matchMedia('(max-width: 767.98px)').matches) $wire.set('movil', true)"
     x-on:apertura-guardada.window="editando = null">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">APERTURA DE CAJA</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="#" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Caja</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <a href="#" class="f-s-14">Apertura</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    {{-- Encabezado: APERTURA DE CAJA FECHA - X Hora - Y --}}
                    <div class="alert alert-light border mb-2" style="background: #fff;">
                        <div class="d-flex flex-wrap align-items-center gap-2" style="color: red; font-weight: bold;">
                            <span>APERTURA DE CAJA FECHA -</span>
                            <input type="text" name="fechaera" autocomplete="off" class="form-control form-control-sm d-inline-block @error('fechaera') is-invalid @enderror dates2"
                                   style="width: 160px;" wire:model="fechaera">
                            <span>Hora - {{ $horaActual }}</span>
                        </div>
                    </div>

                    {{-- Errores --}}
                    @if ($errors->any())
                        <div class="alert alert-danger py-2 px-3 mb-2" style="font-size:12px;">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Banner informativo cuando ya hay apertura del mes --}}
                    @if($currentMonth)
                        <div class="alert alert-info py-2 px-3 mb-3" style="font-size:12px;">
                            <i class="ti ti-info-circle"></i>
                            Ya hay apertura para <strong>{{ \Carbon\Carbon::parse($currentMonth->fecha)->locale('es')->translatedFormat('F Y') }}</strong>
                            · abierta por <strong>{{ $currentMonth->user?->username ?? $currentMonth->user?->name ?? '—' }}</strong>
                            el {{ $currentMonth->fecha?->format('d/m/Y') }}
                            @if($currentMonth->hora) a las {{ $currentMonth->hora }} @endif
                            · S/ <strong>{{ number_format($currentMonth->saldo_inicial, 2) }}</strong>
                            @if($puedeEditar)
                                <span class="text-muted">— como SuperUsuario puedes actualizar el importe abajo.</span>
                            @endif
                        </div>
                    @endif

                    {{-- Form principal --}}
                    <form wire:submit.prevent="save">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-auto" style="width: 70px;">
                                <h5 class="mb-0 fw-bold">S/</h5>
                            </div>
                            <div class="col-md-3">
                                @if($currentMonth && !$puedeEditar)
                                    <h5 class="mb-0">: {{ number_format($currentMonth->saldo_inicial, 2) }}</h5>
                                @else
                                    <input type="number" step="0.01" min="0" name="solesm" autocomplete="off"
                                           class="form-control form-control-sm @error('solesm') is-invalid @enderror"
                                           placeholder="0.00"
                                           value="{{ $currentMonth?->saldo_inicial }}"
                                           wire:model="solesm">
                                @endif
                            </div>
                            <div class="col">
                                @if(!$currentMonth || $puedeEditar)
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="ti ti-device-floppy f-s-12"></i> Guardar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" wire:click="clear">
                                        <i class="ti ti-eraser f-s-12"></i> Limpiar
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($currentMonth)
                            <div class="row g-2 align-items-center mb-2">
                                <div class="col-auto" style="width: 70px;">
                                    <b>Usuario</b>
                                </div>
                                <div class="col-md-3">
                                    : {{ $currentMonth->user?->username ?? $currentMonth->user?->name ?? '-' }}
                                </div>
                            </div>
                        @endif
                    </form>

                    <hr>

                    {{-- Histórico Desktop con sticky header (solo en escritorio: ver $movil) --}}
                    @unless($movil)
                    <div class="d-none d-md-flex justify-content-end mb-1">
                        <x-scroll-bottom-btn scrollable="#tabla-apertura" />
                    </div>
                    <div class="table-responsive d-none d-md-block"
                         id="tabla-apertura" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                                <tr>
                                    <th class="text-center" width="50">Id</th>
                                    <th class="text-center" width="100">Fecha</th>
                                    <th class="text-center" width="80">Hora</th>
                                    <th class="text-center">Usuario</th>
                                    <th class="text-end" width="150">Importe</th>
                                    <th class="text-center" width="80">Moneda</th>
                                    @if($puedeEditar)
                                        <th class="text-center" width="120">Opciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($history as $row)
                                <tr wire:key="apertura-{{ $row->id }}"
                                    onmouseover="this.style.backgroundColor='#CCFF66'"
                                    onmouseout="this.style.backgroundColor=''">
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td class="text-center">{{ $row->fecha?->format('d/m/Y') }}</td>
                                    <td class="text-center">{{ $row->hora ?: '-' }}</td>
                                    <td>{{ $row->user?->username ?? $row->user?->name ?? '-' }}</td>
                                    <td class="text-end">
                                        @if($puedeEditar)
                                            <input type="number" step="0.01" autocomplete="off" class="form-control form-control-sm"
                                                   x-show="editando === {{ $row->id }}" x-cloak x-model="valor" x-ref="importe{{ $row->id }}"
                                                   x-on:keydown.enter.prevent="$wire.updateInline({{ $row->id }}, valor)"
                                                   x-on:keydown.escape="editando = null">
                                            <span class="fw-bold" x-show="editando !== {{ $row->id }}">{{ number_format($row->saldo_inicial, 2) }}</span>
                                        @else
                                            <span class="fw-bold">{{ number_format($row->saldo_inicial, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $row->moneda }}</td>
                                    @if($puedeEditar)
                                        <td class="text-center text-nowrap">
                                            <span x-show="editando === {{ $row->id }}" x-cloak>
                                                <button class="btn btn-xs btn-success" style="padding: 2px 8px; font-size: 10px;"
                                                        x-on:click="$wire.updateInline({{ $row->id }}, valor)"
                                                        wire:loading.attr="disabled" wire:target="updateInline">
                                                    <i class="ti ti-check"></i>
                                                    <span wire:loading.remove wire:target="updateInline">Guardar</span>
                                                    <span wire:loading wire:target="updateInline">Guardando…</span>
                                                </button>
                                                <button class="btn btn-xs btn-secondary" style="padding: 2px 8px; font-size: 10px;"
                                                        x-on:click="editando = null" title="Cancelar (Esc)">
                                                    <i class="ti ti-x"></i>
                                                </button>
                                            </span>
                                            <button class="btn btn-xs btn-primary" style="padding: 2px 8px; font-size: 10px;"
                                                    x-show="editando !== {{ $row->id }}"
                                                    x-on:click="editando = {{ $row->id }}; valor = '{{ $row->saldo_inicial }}'; $nextTick(() => $refs['importe{{ $row->id }}'].focus())"
                                                    title="Editar importe">
                                                <i class="ti ti-edit"></i> Editar
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $puedeEditar ? 7 : 6 }}" class="py-4 text-muted text-center">
                                        No hay aperturas registradas
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                            <tfoot class="bg-primary">
                                <tr>
                                    <td colspan="{{ $puedeEditar ? 7 : 6 }}" class="text-center fw-bold">
                                        Total: {{ $history->count() }} registros
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endunless

                    {{-- Cards Mobile (solo en celular: ver $movil). Antes el Editar de aquí no abría nada. --}}
                    @if($movil)
                    <div class="d-md-none">
                        @forelse($history as $row)
                            <div class="card mb-2 shadow-sm" wire:key="apertura-m-{{ $row->id }}">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">{{ $row->fecha?->format('d/m/Y') }} - {{ $row->hora ?: '-' }}</h6>
                                        <span class="badge bg-primary">{{ $row->moneda }}</span>
                                    </div>
                                    <div class="row g-1" style="font-size: 12px;">
                                        <div class="col-6"><b>Usuario:</b> {{ $row->user?->username ?? '-' }}</div>
                                        <div class="col-6 text-end"><b>S/</b> {{ number_format($row->saldo_inicial, 2) }}</div>
                                    </div>
                                    @if($puedeEditar)
                                        <div class="input-group input-group-sm mt-2" x-show="editando === {{ $row->id }}" x-cloak>
                                            <input type="number" step="0.01" autocomplete="off" class="form-control"
                                                   x-model="valor" x-ref="importe{{ $row->id }}"
                                                   x-on:keydown.enter.prevent="$wire.updateInline({{ $row->id }}, valor)"
                                                   x-on:keydown.escape="editando = null">
                                            <button class="btn btn-success" x-on:click="$wire.updateInline({{ $row->id }}, valor)"
                                                    wire:loading.attr="disabled" wire:target="updateInline">
                                                <i class="ti ti-check"></i>
                                            </button>
                                            <button class="btn btn-secondary" x-on:click="editando = null"><i class="ti ti-x"></i></button>
                                        </div>
                                        <button class="btn btn-xs btn-primary w-100 mt-2" style="font-size: 10px;"
                                                x-show="editando !== {{ $row->id }}"
                                                x-on:click="editando = {{ $row->id }}; valor = '{{ $row->saldo_inicial }}'; $nextTick(() => $refs['importe{{ $row->id }}'].focus())">
                                            <i class="ti ti-edit"></i> Editar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">No hay aperturas registradas</div>
                        @endforelse
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
<span id="final"></span>
</div>
