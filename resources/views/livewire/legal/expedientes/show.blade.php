<div class="container-fluid">
    @php
        $badgesEstado = [
            // Cuaderno principal
            'en_tramite' => 'bg-primary',
            'en_ejecucion' => 'bg-warning text-dark',
            'condonado' => 'bg-info text-dark',
            'cancelado' => 'bg-success',
            'desistido' => 'bg-secondary',
            'archivado' => 'bg-dark',
            // Cuaderno cautelar
            'solicitada' => 'bg-secondary',
            'concedida' => 'bg-info text-dark',
            'oficio_entregado' => 'bg-primary',
            'captura_informado' => 'bg-warning text-dark',
            'capturado' => 'bg-danger',
            'inscrito' => 'bg-success',
            'levantado' => 'bg-dark',
            'rechazada' => 'bg-danger',
        ];

        $badgesTipoActuacion = [
            'resolucion' => 'bg-danger',
            'escrito_demandante' => 'bg-primary',
            'escrito_demandado' => 'bg-secondary',
            'notificacion' => 'bg-info text-dark',
            'oficio' => 'bg-warning text-dark',
            'otro' => 'bg-light text-dark border',
        ];

        $esPrincipal = $expediente->cuaderno === 'principal';
    @endphp

    {{-- ═══ Cabecera ═══ --}}
    <div class="row">
        <div class="col-sm-8 d-flex align-items-center gap-2 flex-wrap">
            <h4 class="main-title title-modules mb-0">EXPEDIENTE {{ $expediente->nro_expediente }}</h4>
            <span class="badge {{ $esPrincipal ? 'bg-dark' : 'bg-danger' }}">
                {{ \App\Models\ExpedienteJudicial::CUADERNOS[$expediente->cuaderno] ?? $expediente->cuaderno }}
            </span>
            <span class="badge {{ $badgesEstado[$expediente->estado] ?? 'bg-secondary' }}">
                {{ $expediente->estadoLabel() }}
            </span>
            @can('legal.judicial')
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-danger dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-exchange f-s-12"></i> Cambiar estado
                    </button>
                    <ul class="dropdown-menu" style="font-size:12px;">
                        @foreach($expediente->estadosDisponibles() as $clave => $etiqueta)
                            <li>
                                <button type="button"
                                        class="dropdown-item {{ $expediente->estado === $clave ? 'active' : '' }}"
                                        wire:click="cambiarEstado('{{ $clave }}')"
                                        @disabled($expediente->estado === $clave)>
                                    {{ $etiqueta }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endcan
        </div>
        <div class="col-sm-4 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-gavel f-s-16"></i>
                    <a href="{{ route('legal.expedientes.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Expedientes</span>
                    </a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Detalle</a></li>
            </ul>
        </div>
    </div>

    @if($expediente->requiere_revision)
        <div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
            <i class="ti ti-alert-triangle"></i>
            <b>Importado con datos por revisar:</b> valida la información del expediente antes de registrar actuaciones.
        </div>
    @endif

    <div class="row g-3">
        {{-- ═══ Datos del expediente ═══ --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2"><b><i class="ti ti-file-description"></i> Datos del expediente</b></div>
                <div class="card-body py-2" style="font-size:12px;">
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Cliente</span>
                        @if($expediente->client)
                            @can('clientes')
                                <a href="{{ route('clients.show', $expediente->client_id) }}" class="fw-bold">
                                    {{ $expediente->client->fullName() }}
                                </a>
                            @else
                                <b>{{ $expediente->client->fullName() }}</b>
                            @endcan
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Exp. interno</span>
                        <b>{{ $expediente->exp_interno ?? '—' }}</b>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Crédito</span>
                        @if($expediente->credit)
                            <span>#{{ $expediente->credit->id }} — S/ {{ number_format($expediente->credit->importe, 2) }}</span>
                        @else
                            <span class="text-muted">Sin crédito vinculado</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Garantía</span>
                        @if($expediente->garantia)
                            @can('legal.garantias')
                                <a href="{{ route('legal.garantias.show', $expediente->garantia_id) }}" class="fw-bold">
                                    Garantía #{{ $expediente->garantia_id }}
                                </a>
                            @else
                                <span>Garantía #{{ $expediente->garantia_id }}</span>
                            @endcan
                        @else
                            <span class="text-muted">Sin garantía vinculada</span>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Vía</span>
                        <span>{{ $expediente->via ? (\App\Models\ExpedienteJudicial::VIAS[$expediente->via] ?? $expediente->via) : '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Materia</span>
                        <span>{{ $expediente->materia ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Proceso</span>
                        <span>{{ $expediente->proceso ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ Juzgado y responsables ═══ --}}
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2"><b><i class="ti ti-building-bank"></i> Juzgado y responsables</b></div>
                <div class="card-body py-2" style="font-size:12px;">
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Juzgado</span>
                        <span class="text-end">{{ $expediente->juzgado ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Distrito judicial</span>
                        <span>{{ $expediente->distrito_judicial ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Juez</span>
                        <span>{{ $expediente->juez ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Secretario</span>
                        <span>{{ $expediente->secretario ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Monto de pretensión</span>
                        <b>{{ $expediente->monto_pretension !== null ? 'S/ '.number_format($expediente->monto_pretension, 2) : '—' }}</b>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Asesor responsable</span>
                        <span>{{ $expediente->asesor?->name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Fecha de inicio</span>
                        <span>{{ $expediente->fecha_inicio?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    @if($expediente->observaciones)
                        <div class="text-muted border-top pt-1 mt-1" style="font-size:11px;">
                            <i class="ti ti-note"></i> {{ $expediente->observaciones }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══ Cuaderno cautelar (solo principal) / vuelta al principal (solo cautelar) ═══ --}}
        @if($esPrincipal)
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <b><i class="ti ti-shield-lock"></i> Cuaderno cautelar</b>
                        @can('legal.judicial')
                            @if($expediente->cautelares->isEmpty())
                                <button type="button" class="btn btn-sm btn-danger"
                                        wire:click="crearCautelar"
                                        data-confirmar="Se creará el cuaderno cautelar {{ \App\Models\ExpedienteJudicial::nroCautelarDesde($expediente->nro_expediente) }} en estado 'Medida solicitada'. ¿Continuar?"
                                        wire:loading.attr="disabled" wire:target="crearCautelar">
                                    <i class="ti ti-plus f-s-12"></i> Crear cautelar
                                </button>
                            @endif
                        @endcan
                    </div>
                    <div class="card-body py-2">
                        @if($expediente->cautelares->isEmpty())
                            <p class="text-muted small mb-0">Este expediente aún no tiene cuaderno cautelar.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle mb-0" style="font-size:11px;">
                                    <thead class="bg-primary">
                                        <tr>
                                            <th class="text-center" width="200">N° Expediente</th>
                                            <th class="text-center" width="180">Forma de medida</th>
                                            <th>Bien afectado</th>
                                            <th class="text-center" width="130">Estado</th>
                                            <th class="text-center" width="70">Ver</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($expediente->cautelares as $cautelar)
                                        <tr>
                                            <td class="text-center fw-bold">{{ $cautelar->nro_expediente }}</td>
                                            <td class="text-center">
                                                {{ $cautelar->forma_medida ? (\App\Models\ExpedienteJudicial::FORMAS_MEDIDA[$cautelar->forma_medida] ?? $cautelar->forma_medida) : '—' }}
                                            </td>
                                            <td>{{ $cautelar->bien_descripcion ?? '—' }}</td>
                                            <td class="text-center">
                                                <span class="badge {{ $badgesEstado[$cautelar->estado] ?? 'bg-secondary' }}">
                                                    {{ $cautelar->estadoLabel() }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('legal.expedientes.show', $cautelar->id) }}"
                                                   class="btn btn-xs btn-outline-danger" style="padding:2px 8px; font-size:10px;"
                                                   title="Ver cuaderno cautelar">
                                                    <i class="ti ti-eye"></i>
                                                </a>
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
        @elseif($expediente->principal)
            <div class="col-12">
                <div class="alert alert-secondary py-2 mb-0" style="font-size:12px;">
                    <i class="ti ti-corner-up-left"></i>
                    Este es el cuaderno cautelar del expediente
                    <a href="{{ route('legal.expedientes.show', $expediente->principal->id) }}" class="fw-bold">
                        {{ $expediente->principal->nro_expediente }}
                    </a>
                    (principal — {{ $expediente->principal->estadoLabel() }}).
                </div>
            </div>
        @endif

        {{-- ═══ Tabs: actuaciones y plazos ═══ --}}
        <div class="col-12" x-data="{ tab: 'actuaciones' }">
            <div class="card shadow-sm">
                <div class="card-header py-2 pb-0 border-bottom-0">
                    <ul class="nav nav-tabs card-header-tabs" style="font-size:12px;">
                        <li class="nav-item">
                            <button type="button" class="nav-link"
                                    :class="{ 'active': tab === 'actuaciones' }"
                                    x-on:click="tab = 'actuaciones'">
                                <i class="ti ti-timeline-event"></i> Actuaciones
                                <span class="badge bg-secondary ms-1">{{ $expediente->actuaciones->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link"
                                    :class="{ 'active': tab === 'plazos' }"
                                    x-on:click="tab = 'plazos'">
                                <i class="ti ti-alarm"></i> Plazos
                                @php $pendientes = $expediente->plazos->whereNull('cumplido_at')->count(); @endphp
                                <span class="badge {{ $pendientes > 0 ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $pendientes }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                {{-- ── Tab actuaciones ── --}}
                <div class="card-body py-2" x-show="tab === 'actuaciones'">
                    <div class="d-flex justify-content-end mb-2">
                        @can('legal.judicial')
                            <button type="button" class="btn btn-sm btn-danger" wire:click="abrirActuacionModal">
                                <i class="ti ti-plus f-s-12"></i> Registrar actuación
                            </button>
                        @endcan
                    </div>
                    @if($expediente->actuaciones->isEmpty())
                        <p class="text-muted small mb-0">Aún no hay actuaciones registradas en este expediente.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0" style="font-size:11px;">
                                <thead class="bg-primary">
                                    <tr>
                                        <th class="text-center" width="90">Fecha</th>
                                        <th class="text-center" width="140">Tipo</th>
                                        <th class="text-center" width="90">Número</th>
                                        <th>Sumilla / Detalle</th>
                                        <th class="text-center" width="100">Registró</th>
                                    </tr>
                                </thead>
                                <tbody>
                                {{-- La relación ya viene ordenada por fecha desc --}}
                                @foreach($expediente->actuaciones as $actuacion)
                                    <tr>
                                        <td class="text-center">{{ $actuacion->fecha?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $badgesTipoActuacion[$actuacion->tipo] ?? 'bg-secondary' }}">
                                                {{ \App\Models\ActuacionJudicial::TIPOS[$actuacion->tipo] ?? $actuacion->tipo }}
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold">{{ $actuacion->numero ?? '—' }}</td>
                                        <td x-data="{ abierto: false }">
                                            {{ $actuacion->sumilla }}
                                            @if($actuacion->detalle)
                                                <button type="button" class="btn btn-link p-0 ms-1 align-baseline"
                                                        style="font-size:10px;" x-on:click="abierto = !abierto">
                                                    <span x-show="!abierto">Ver detalle <i class="ti ti-chevron-down"></i></span>
                                                    <span x-show="abierto" x-cloak>Ocultar <i class="ti ti-chevron-up"></i></span>
                                                </button>
                                                <div x-show="abierto" x-cloak
                                                     class="text-muted border-start ps-2 mt-1"
                                                     style="white-space:pre-line; font-size:10.5px;">{{ $actuacion->detalle }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $actuacion->registradoPor?->username ?? $actuacion->registradoPor?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ── Tab plazos ── --}}
                <div class="card-body py-2" x-show="tab === 'plazos'" x-cloak>
                    <div class="d-flex justify-content-end mb-2">
                        @can('legal.judicial')
                            <button type="button" class="btn btn-sm btn-danger" wire:click="abrirPlazoModal">
                                <i class="ti ti-plus f-s-12"></i> Agregar plazo
                            </button>
                        @endcan
                    </div>
                    @if($expediente->plazos->isEmpty())
                        <p class="text-muted small mb-0">Aún no hay plazos registrados en este expediente.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0" style="font-size:11px;">
                                <thead class="bg-primary">
                                    <tr>
                                        <th>Descripción</th>
                                        <th class="text-center" width="100">Vence</th>
                                        <th class="text-center" width="110">Responsable</th>
                                        <th class="text-center" width="100">Estado</th>
                                        <th class="text-center" width="70">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                {{-- La relación ya viene ordenada por fecha_vencimiento asc --}}
                                @foreach($expediente->plazos as $plazo)
                                    @php
                                        $pendiente = $plazo->cumplido_at === null;
                                        $dias = (int) now()->startOfDay()
                                            ->diffInDays($plazo->fecha_vencimiento->copy()->startOfDay(), false);
                                    @endphp
                                    <tr>
                                        <td>
                                            {{ $plazo->descripcion }}
                                            @if($plazo->actuacion_id)
                                                <span class="text-muted" style="font-size:10px;">
                                                    <i class="ti ti-link"></i> generado por actuación
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($pendiente && $dias < 0)
                                                <span class="badge bg-danger" title="Vencido hace {{ abs($dias) }} día(s)">
                                                    {{ $plazo->fecha_vencimiento->format('d/m/Y') }}
                                                </span>
                                            @elseif($pendiente && $dias <= \App\Models\PlazoJudicial::DIAS_AVISO)
                                                <span class="badge text-white" style="background:#fd7e14;"
                                                      title="Vence en {{ $dias }} día(s)">
                                                    {{ $plazo->fecha_vencimiento->format('d/m/Y') }}
                                                </span>
                                            @else
                                                {{ $plazo->fecha_vencimiento->format('d/m/Y') }}
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $plazo->responsable?->username ?? $plazo->responsable?->name ?? '—' }}</td>
                                        <td class="text-center">
                                            @if($pendiente)
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                            @else
                                                <span class="badge bg-success" title="Cumplido el {{ $plazo->cumplido_at->format('d/m/Y H:i') }}">
                                                    Cumplido
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @can('legal.judicial')
                                                @if($pendiente)
                                                    <button type="button" class="btn btn-xs btn-outline-success"
                                                            style="padding:2px 8px; font-size:10px;"
                                                            title="Marcar cumplido"
                                                            wire:click="marcarCumplido({{ $plazo->id }})"
                                                            data-confirmar="¿Marcar este plazo como cumplido?"
                                                            wire:loading.attr="disabled" wire:target="marcarCumplido">
                                                        <i class="ti ti-check"></i>
                                                    </button>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            @endcan
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

    {{-- Modales hijos de registro --}}
    <livewire:legal.expedientes.actuacion-modal />
    <livewire:legal.expedientes.plazo-modal />
</div>
