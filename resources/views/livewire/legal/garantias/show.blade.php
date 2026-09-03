<div class="container-fluid">
    @php
        $badgeEstado = [
            'en_constitucion' => 'bg-secondary',
            'vigente' => 'bg-success',
            'cancelada' => 'bg-dark',
            'en_ejecucion' => 'bg-warning text-dark',
            'ejecutada' => 'bg-danger',
        ][$garantia->estado] ?? 'bg-secondary';

        $diasVigencia = $garantia->vigencia_hasta
            ? (int) now()->startOfDay()->diffInDays($garantia->vigencia_hasta->copy()->startOfDay(), false)
            : null;
    @endphp

    <div class="row">
        <div class="col-sm-6 d-flex align-items-center gap-2 flex-wrap">
            <h4 class="main-title title-modules mb-0">GARANTÍA #{{ $garantia->id }}</h4>
            @can('legal.garantias')
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="abrirEditarModal">
                    <i class="ti ti-pencil f-s-12"></i> Editar
                </button>
            @endcan
            @can('legal.contratos')
                <a href="{{ route('legal.contratos.form', $garantia->id) }}" class="btn btn-sm btn-danger">
                    <i class="ti ti-file-text f-s-12"></i> Generar contrato
                </a>
            @endcan
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-scale f-s-16"></i>
                    <a href="{{ route('legal.garantias.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Garantías</span>
                    </a>
                </li>
                <li class="d-flex active"><a href="#" class="f-s-14">Detalle</a></li>
            </ul>
        </div>
    </div>

    @if($garantia->requiere_revision)
        <div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
            <i class="ti ti-alert-triangle"></i>
            <b>Importada con datos por revisar:</b> valida la información antes de emitir contratos o avisos.
        </div>
    @endif

    @php $gemelasVigentes = $otrasGarantias->where('estado', 'vigente'); @endphp
    @if($garantia->estado !== 'vigente' && $gemelasVigentes->isNotEmpty())
        <div class="alert alert-info py-2 mb-3" style="font-size:12px;">
            <i class="ti ti-info-circle"></i>
            <b>Este cliente tiene {{ $gemelasVigentes->count() > 1 ? 'garantías vigentes' : 'una garantía vigente' }}:</b>
            @foreach($gemelasVigentes as $otra)
                <a href="{{ route('legal.garantias.show', $otra->id) }}" class="fw-bold">garantía #{{ $otra->id }} (crédito #{{ $otra->credit_id }})</a>@if(! $loop->last), @endif
            @endforeach
            — los contratos y avisos nuevos se gestionan sobre la vigente; esta ({{ \App\Models\Garantia::ESTADOS[$garantia->estado] ?? $garantia->estado }}) queda como historial.
        </div>
    @elseif($otrasGarantias->isNotEmpty())
        <p class="text-muted mb-3" style="font-size:11px;">
            <i class="ti ti-versions"></i> Otras garantías del cliente:
            @foreach($otrasGarantias as $otra)
                <a href="{{ route('legal.garantias.show', $otra->id) }}">#{{ $otra->id }}</a>
                <span class="text-lowercase">({{ \App\Models\Garantia::ESTADOS[$otra->estado] ?? $otra->estado }})</span>@if(! $loop->last) · @endif
            @endforeach
        </p>
    @endif

    <div class="row g-3">
        {{-- ═══ Datos del crédito ═══ --}}
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2"><b><i class="ti ti-cash"></i> Crédito</b></div>
                <div class="card-body py-2" style="font-size:12px;">
                    @if($garantia->credit)
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">N° crédito</span>
                            <b>#{{ $garantia->credit->id }}</b>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">Importe</span>
                            <b>S/ {{ number_format($garantia->credit->importe, 2) }}</b>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">Cuotas</span>
                            <span>{{ $garantia->credit->cuotas }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-1">
                            <span class="text-muted">Planilla</span>
                            <span>{{ $garantia->credit->tipoPlanillaLabel() }}</span>
                        </div>
                    @else
                        <span class="text-muted">Sin crédito vinculado</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══ Deudor(es) ═══ --}}
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2"><b><i class="ti ti-users"></i> Deudor(es)</b></div>
                <div class="card-body py-2" style="font-size:12px;">
                    <div class="border-bottom pb-1 mb-1">
                        <span class="badge bg-primary" style="font-size:9px;">Deudor</span>
                        <b class="d-block">{{ $garantia->client?->fullName() ?? '—' }}</b>
                        <span class="text-muted">
                            {{ $garantia->client?->tipo_documento ?? 'Doc.' }}: {{ $garantia->client?->documento ?? '—' }}
                            @if($garantia->client?->celular1) — Cel. {{ $garantia->client->celular1 }} @endif
                        </span>
                    </div>
                    @if($garantia->codeudor)
                        <div>
                            <span class="badge bg-secondary" style="font-size:9px;">Codeudor</span>
                            <b class="d-block">{{ $garantia->codeudor->fullName() }}</b>
                            <span class="text-muted">
                                {{ $garantia->codeudor->tipo_documento ?? 'Doc.' }}: {{ $garantia->codeudor->documento ?? '—' }}
                                @if($garantia->codeudor->celular1) — Cel. {{ $garantia->codeudor->celular1 }} @endif
                            </span>
                        </div>
                    @else
                        <span class="text-muted">Sin codeudor</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══ Parámetros / estado ═══ --}}
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2"><b><i class="ti ti-adjustments"></i> Parámetros y vigencia</b></div>
                <div class="card-body py-2" style="font-size:12px;">
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Tipo</span>
                        <span>{{ \App\Models\Garantia::TIPOS[$garantia->tipo] ?? $garantia->tipo }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Tipo de persona</span>
                        <span>{{ \App\Models\Garantia::TIPO_PERSONAS[$garantia->tipo_persona] ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Monto de gravamen</span>
                        <b>S/ {{ number_format($garantia->monto_gravamen, 2) }}</b>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">GPS / Custodia</span>
                        <span>
                            <i class="ti ti-gps f-s-16 {{ $garantia->gps ? 'text-success' : 'text-muted opacity-25' }}"
                               title="GPS: {{ $garantia->gps ? 'Sí' : 'No' }}"></i>
                            <i class="ti ti-shield-lock f-s-16 {{ $garantia->custodia ? 'text-success' : 'text-muted opacity-25' }}"
                               title="Custodia: {{ $garantia->custodia ? 'Sí' : 'No' }}"></i>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Estado</span>
                        <span class="badge {{ $badgeEstado }}">
                            {{ \App\Models\Garantia::ESTADOS[$garantia->estado] ?? $garantia->estado }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <span class="text-muted">Constitución</span>
                        <span>{{ $garantia->fecha_constitucion?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">Vigencia hasta</span>
                        @if($garantia->vigencia_hasta)
                            @if($diasVigencia < 0)
                                <span class="badge bg-danger" title="Vigencia vencida">
                                    {{ $garantia->vigencia_hasta->format('d/m/Y') }}
                                </span>
                            @elseif($diasVigencia <= \App\Models\Garantia::DIAS_AVISO_RENOVACION)
                                <span class="badge text-white" style="background:#fd7e14;"
                                      title="Vence en {{ $diasVigencia }} día(s) — por renovar">
                                    {{ $garantia->vigencia_hasta->format('d/m/Y') }}
                                </span>
                            @else
                                <b>{{ $garantia->vigencia_hasta->format('d/m/Y') }}</b>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                    @if($garantia->observaciones)
                        <div class="text-muted border-top pt-1 mt-1" style="font-size:11px;">
                            <i class="ti ti-note"></i> {{ $garantia->observaciones }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══ Vehículos en garantía ═══ --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2"><b><i class="ti ti-car"></i> Vehículos en garantía</b></div>
                <div class="card-body py-2">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0" style="font-size:11px;">
                            <thead class="bg-primary">
                                <tr>
                                    <th class="text-center" width="30">#</th>
                                    <th class="text-center" width="80">Placa</th>
                                    <th>Marca / Modelo</th>
                                    <th class="text-center" width="50">Año</th>
                                    <th class="text-center" width="110">N° Serie</th>
                                    <th class="text-center" width="110">N° Motor</th>
                                    <th class="text-end" width="90">Valor S/</th>
                                    <th class="text-center" width="90">Bien futuro</th>
                                    <th>Acta / Kardex / Notario</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($garantia->vehiculos as $v)
                                <tr>
                                    <td class="text-center">{{ $v->pivot->orden ?? $loop->iteration }}</td>
                                    <td class="text-center fw-bold">{{ $v->placa ?: '—' }}</td>
                                    <td>{{ trim("{$v->marca} {$v->modelo}") ?: '—' }} @if($v->color)<span class="text-muted">({{ $v->color }})</span>@endif</td>
                                    <td class="text-center">{{ $v->anio ?? '—' }}</td>
                                    <td class="text-center">{{ $v->nro_serie ?? '—' }}</td>
                                    <td class="text-center">{{ $v->nro_motor ?? '—' }}</td>
                                    <td class="text-end">{{ $v->valor !== null ? number_format($v->valor, 2) : '—' }}</td>
                                    <td class="text-center">
                                        @if($v->pivot->es_bien_futuro)
                                            <span class="badge bg-warning text-dark">Sí</span>
                                        @else
                                            <span class="badge bg-light text-dark border">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($v->pivot->es_bien_futuro)
                                            <div style="line-height:1.4;">
                                                <b>Acta:</b> {{ $v->pivot->acta_notarial ?? '—' }}
                                                — <b>Kardex:</b> {{ $v->pivot->kardex ?? '—' }}<br>
                                                <b>Notario:</b> {{ $v->pivot->notario ?? '—' }}
                                                — <b>Fecha acta:</b>
                                                {{ $v->pivot->fecha_acta ? \Carbon\Carbon::parse($v->pivot->fecha_acta)->format('d/m/Y') : '—' }}
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-3 text-muted text-center">Sin vehículos vinculados</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ Timeline de avisos SIGM ═══ --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <b><i class="ti ti-timeline-event"></i> Avisos SIGM</b>
                    @can('legal.garantias')
                        <button type="button" class="btn btn-sm btn-danger" wire:click="abrirAvisoModal">
                            <i class="ti ti-plus f-s-12"></i> Registrar aviso
                        </button>
                    @endcan
                </div>
                <div class="card-body py-2">
                    @if($garantia->avisos->isEmpty())
                        <p class="text-muted small mb-0">Aún no hay avisos SIGM registrados para esta garantía.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0" style="font-size:11px;">
                                <thead class="bg-primary">
                                    <tr>
                                        <th class="text-center" width="100">Tipo</th>
                                        <th class="text-center" width="100">N° Formulario</th>
                                        <th class="text-center" width="110">Folio</th>
                                        <th class="text-center" width="90">Presentación</th>
                                        <th class="text-center" width="90">Vigencia hasta</th>
                                        <th class="text-center" width="80">Estado</th>
                                        <th>Nota</th>
                                        <th class="text-center" width="90">Registró</th>
                                    </tr>
                                </thead>
                                <tbody>
                                {{-- La relación ya viene ordenada por fecha_presentacion --}}
                                @foreach($garantia->avisos as $aviso)
                                    @php
                                        $badgeAviso = [
                                            'registrado' => 'bg-success',
                                            'observado' => 'bg-warning text-dark',
                                            'anulado' => 'bg-secondary',
                                        ][$aviso->estado] ?? 'bg-secondary';
                                    @endphp
                                    <tr class="{{ $aviso->estado === 'anulado' ? 'text-decoration-line-through text-muted' : '' }}">
                                        <td class="text-center fw-bold">
                                            {{ \App\Models\SigmAviso::TIPOS[$aviso->tipo] ?? $aviso->tipo }}
                                        </td>
                                        <td class="text-center">{{ $aviso->nro_formulario ?? '—' }}</td>
                                        <td class="text-center">{{ $aviso->folio ?? '—' }}</td>
                                        <td class="text-center">{{ $aviso->fecha_presentacion?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="text-center">{{ $aviso->vigencia_hasta?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $badgeAviso }}">
                                                {{ \App\Models\SigmAviso::ESTADOS[$aviso->estado] ?? $aviso->estado }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($aviso->tipo === 'ejecucion')
                                                <div style="line-height:1.4;">
                                                    <b>{{ \App\Models\SigmAviso::MODALIDADES_EJECUCION[$aviso->modalidad_ejecucion] ?? '—' }}</b>
                                                    ({{ $aviso->fecha_inicio_ejecucion?->format('d/m/Y') ?? '—' }}
                                                    al {{ $aviso->fecha_termino_ejecucion?->format('d/m/Y') ?? '—' }})
                                                </div>
                                            @endif
                                            {{ $aviso->nota ?? '' }}
                                        </td>
                                        <td class="text-center">{{ $aviso->registradoPor?->username ?? $aviso->registradoPor?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══ Contratos generados ═══ --}}
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header py-2"><b><i class="ti ti-file-text"></i> Contratos</b></div>
                <div class="card-body py-2">
                    @if($garantia->contratos->isEmpty())
                        <p class="text-muted small mb-0">Aún no se han generado contratos para esta garantía.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0" style="font-size:11px;">
                                <thead class="bg-primary">
                                    <tr>
                                        <th class="text-center" width="90">Número</th>
                                        <th class="text-center" width="120">Tipo</th>
                                        <th class="text-center" width="60">Versión</th>
                                        <th class="text-center" width="90">Estado</th>
                                        <th class="text-center" width="120">Generado</th>
                                        <th class="text-center" width="100">Por</th>
                                        <th class="text-center" width="70">PDF</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($garantia->contratos as $contrato)
                                    <tr>
                                        <td class="text-center fw-bold">{{ $contrato->numero ?? '—' }}</td>
                                        <td class="text-center">{{ $contrato->tipo ?? '—' }}</td>
                                        <td class="text-center">v{{ $contrato->version }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ ['borrador' => 'bg-secondary', 'emitido' => 'bg-success', 'anulado' => 'bg-dark'][$contrato->estado] ?? 'bg-secondary' }}">
                                                {{ \App\Models\Contrato::ESTADOS[$contrato->estado] ?? $contrato->estado }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ $contrato->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        <td class="text-center">{{ $contrato->generadoPor?->username ?? $contrato->generadoPor?->name ?? '—' }}</td>
                                        <td class="text-center">
                                            @can('legal.contratos')
                                                @if($contrato->pdf_path)
                                                    <a href="{{ route('legal.contratos.pdf', $contrato->id) }}"
                                                       class="btn btn-xs btn-outline-danger" style="padding:2px 8px; font-size:10px;"
                                                       title="Descargar PDF">
                                                        <i class="ti ti-download"></i> PDF
                                                    </a>
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

    {{-- Modal hijo de registro de avisos SIGM --}}
    <livewire:legal.garantias.aviso-modal />

    {{-- Modal hijo de edición de la garantía --}}
    <livewire:legal.garantias.editar-modal />
</div>
