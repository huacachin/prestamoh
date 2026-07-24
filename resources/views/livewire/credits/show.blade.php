<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">DETALLE DE CRÉDITO #{{ $credit->id }}</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('credits.index') }}" class="f-s-14 d-flex gap-2"><span class="d-none d-md-block">Créditos</span></a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Detalle</span></li>
            </ul>
        </div>
    </div>

    @if(session('credit_success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-2" role="alert">
            {{ session('credit_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Info crédito --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <h6>CLIENTE</h6>
                    <p class="mb-1"><strong>{{ $credit->client?->fullName() }}</strong></p>
                    <small class="text-muted">{{ $credit->client?->tipo_documento }} {{ $credit->client?->documento }}</small>
                </div>
                <div class="col-12 col-md-6">
                    <div class="row g-2">
                        <div class="col-auto">
                            <small class="text-muted">Importe:</small><br>
                            <strong>{{ $credit->moneda === 'USD' ? '$' : 'S/.' }} {{ number_format($credit->importe, 2) }}</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Cuotas:</small><br>
                            <strong>{{ $credit->cuotas }} ({{ $credit->tipoPlanillaLabel() }})</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Interés:</small><br>
                            <strong>{{ $credit->interes }}%</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Situación:</small><br>
                            @php
                                $bc = match($credit->situacion) {
                                    'Activo' => 'bg-success', 'Cancelado' => 'bg-secondary',
                                    'Refinanciado' => 'bg-warning', 'Eliminado' => 'bg-danger', default => 'bg-dark',
                                };
                            @endphp
                            <span class="badge {{ $bc }}">{{ $credit->situacion }}</span>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Fecha:</small><br>
                            <strong>{{ $credit->fecha_prestamo?->format('d/m/Y') }}</strong>
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Vencimiento:</small><br>
                            <strong>{{ $credit->fecha_vencimiento?->format('d/m/Y') }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Resumen financiero --}}
            <div class="row g-3 mt-2">
                <div class="col-auto">
                    <div class="p-2 rounded" style="background:#e8f5e9;">
                        <small class="text-muted">Total Deuda</small><br>
                        <strong>{{ number_format($totalDeuda, 2) }}</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="p-2 rounded" style="background:#e3f2fd;">
                        <small class="text-muted">Total Pagado</small><br>
                        <strong>{{ number_format($totalPagado, 2) }}</strong>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="p-2 rounded" style="background:#fff3e0;">
                        <small class="text-muted">Saldo Pendiente</small><br>
                        <strong>{{ number_format($saldoPendiente, 2) }}</strong>
                    </div>
                </div>
            </div>

            <div class="mt-2 d-flex gap-2">
                <a href="{{ route('credits.schedule', $credit->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-calendar"></i> Cronograma
                </a>
                <a href="{{ route('payments.create', $credit->id) }}" class="btn btn-sm btn-outline-success">
                    <i class="ti ti-currency-dollar"></i> Registrar Pago
                </a>
                <a href="{{ route('credits.edit', $credit->id) }}" class="btn btn-sm btn-outline-warning">
                    <i class="ti ti-edit"></i> Editar
                </a>
                <a href="{{ route('credits.index') }}" class="btn btn-sm btn-secondary">Volver</a>
            </div>
        </div>
    </div>

    {{-- Cronograma --}}
    <div class="card shadow-sm mt-3">
        <div class="card-body pb-2">
            <h6>CRONOGRAMA DE CUOTAS</h6>
            @php $tieneExc = $credit->installments->sum('importe_excedente') > 0; @endphp
            <div class="table-responsive tableFixHead">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                    <tr>
                        <th>Cuota</th>
                        <th>Fecha Venc.</th>
                        <th>Capital</th>
                        <th>Interés</th>
                        @if($tieneExc)
                            <th>Excedente</th>
                        @endif
                        <th>Pagado Cap.</th>
                        <th>Pagado Int.</th>
                        <th>Saldo</th>
                        <th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($credit->installments as $inst)
                        @php
                            $saldo = $inst->saldoPendiente();
                            $vencida = !$inst->pagado && $inst->fecha_vencimiento?->isPast();
                        @endphp
                        <tr class="{{ $vencida ? 'table-danger' : '' }}">
                            <td>{{ $inst->num_cuota }}</td>
                            <td>{{ $inst->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format($inst->importe_cuota, 2) }}</td>
                            <td class="text-end">{{ number_format($inst->importe_interes, 2) }}</td>
                            @if($tieneExc)
                                <td class="text-end">{{ number_format($inst->importe_excedente, 2) }}</td>
                            @endif
                            <td class="text-end">{{ number_format($inst->importe_aplicado, 2) }}</td>
                            <td class="text-end">{{ number_format($inst->interes_aplicado, 2) }}</td>
                            <td class="text-end">{{ number_format($saldo, 2) }}</td>
                            <td>
                                @if($inst->pagado)
                                    <span class="badge bg-success">Pagado</span>
                                @elseif($vencida)
                                    <span class="badge bg-danger">Vencida</span>
                                @else
                                    <span class="badge bg-warning">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    @php
                        $tCap    = $credit->installments->sum('importe_cuota');
                        $tInt    = $credit->installments->sum('importe_interes');
                        $tExc    = $credit->installments->sum('importe_excedente');
                        $tPagCap = $credit->installments->sum('importe_aplicado');
                        $tPagInt = $credit->installments->sum('interes_aplicado');
                        $tSaldo  = $credit->installments->sum(fn ($i) => $i->saldoPendiente());
                    @endphp
                    <tfoot>
                        <tr class="fw-bold" style="background:#f0f0f0;">
                            <td colspan="2" class="text-end">Totales</td>
                            <td class="text-end">{{ number_format($tCap, 2) }}</td>
                            <td class="text-end">{{ number_format($tInt, 2) }}</td>
                            @if($tieneExc)
                                <td class="text-end">{{ number_format($tExc, 2) }}</td>
                            @endif
                            <td class="text-end">{{ number_format($tPagCap, 2) }}</td>
                            <td class="text-end">{{ number_format($tPagInt, 2) }}</td>
                            <td class="text-end">{{ number_format($tSaldo, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagos realizados (agrupados por cobro / mass_deletion) --}}
    <div class="card shadow-sm mt-3">
        <div class="card-body pb-2">
            <h6>PAGOS REALIZADOS</h6>
            <div class="table-responsive tableFixHead">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="bg-primary">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Cuotas</th>
                        <th class="text-end">Capital</th>
                        <th class="text-end">Interés</th>
                        @if($tieneExc)
                            <th class="text-end">Excedente</th>
                        @endif
                        <th class="text-end">Mora</th>
                        <th class="text-end">Total</th>
                        <th>Cobrador</th>
                        <th class="text-center">Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($credit->massDeletions as $masivo)
                        @php
                            $totales = ['C' => 0, 'I' => 0, 'E' => 0, 'M' => 0];
                            $cuotas = [];
                            foreach ($masivo->details as $d) {
                                if (isset($totales[$d->tipo])) $totales[$d->tipo] += (float) $d->amount;
                                if ($d->tipo === 'C' && $d->installment?->num_cuota !== null) {
                                    $cuotas[] = $d->installment->num_cuota;
                                }
                            }
                            $cuotas = array_values(array_unique($cuotas));
                            sort($cuotas);
                        @endphp
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $masivo->date?->format('d/m/Y') }}
                                <small class="text-muted">{{ $masivo->time }}</small>
                            </td>
                            <td>{{ $cuotas ? implode(',', $cuotas) : '—' }}</td>
                            <td class="text-end">{{ number_format($totales['C'], 2) }}</td>
                            <td class="text-end">{{ number_format($totales['I'], 2) }}</td>
                            @if($tieneExc)
                                <td class="text-end">{{ number_format($totales['E'], 2) }}</td>
                            @endif
                            <td class="text-end">{{ number_format($totales['M'], 2) }}</td>
                            <td class="text-end fw-bold">{{ number_format($masivo->amount, 2) }}</td>
                            <td>{{ $masivo->user ?: '—' }}</td>
                            <td class="text-center" style="white-space:nowrap;">
                                <button type="button"
                                        wire:click="printPayment({{ $masivo->id }})"
                                        class="btn btn-sm btn-primary"
                                        title="Imprimir ticket #{{ $masivo->id }}">
                                    <i class="ti ti-printer"></i>
                                </button>
                                @php
                                    // Links firmados del recibo (público + PDF) — ver PaymentController.
                                    $verRecibo = \Illuminate\Support\Facades\URL::signedRoute('recibo.publico', ['massDeletionId' => $masivo->id]);
                                    $telRecibo = preg_replace('/\D/', '', (string) $credit->client?->celular1);
                                    $msgRecibo = config('printer.company_name', 'PRESTAMOS HUACACHIN')
                                        .': su recibo de pago #'.str_pad((string) $masivo->id, 6, '0', STR_PAD_LEFT)
                                        .' del '.($masivo->date?->format('d/m/Y') ?? '')
                                        .' por S/ '.number_format((float) $masivo->amount, 2)
                                        .'. Vealo aqui: '.$verRecibo;
                                @endphp
                                @if($telRecibo !== '')
                                    <a href="https://api.whatsapp.com/send?phone=51{{ $telRecibo }}&text={{ rawurlencode($msgRecibo) }}"
                                       target="_blank" rel="noopener" class="btn btn-sm btn-success"
                                       title="Enviar recibo por WhatsApp al cliente">
                                        <i class="ti ti-brand-whatsapp"></i>
                                    </a>
                                @endif
                                <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('recibo.pdf', ['massDeletionId' => $masivo->id]) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Descargar recibo en PDF">
                                    <i class="ti ti-file-download"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $tieneExc ? 10 : 9 }}" class="py-4 text-muted text-center">Sin pagos registrados</td>
                        </tr>
                    @endforelse
                    </tbody>
                    @if($credit->massDeletions->count())
                        @php
                            $pC = $pI = $pE = $pM = $pTot = 0;
                            foreach ($credit->massDeletions as $m) {
                                foreach ($m->details as $d) {
                                    if ($d->tipo === 'C') $pC += (float) $d->amount;
                                    elseif ($d->tipo === 'I') $pI += (float) $d->amount;
                                    elseif ($d->tipo === 'E') $pE += (float) $d->amount;
                                    elseif ($d->tipo === 'M') $pM += (float) $d->amount;
                                }
                                $pTot += (float) $m->amount;
                            }
                        @endphp
                        <tfoot>
                            <tr class="fw-bold" style="background:#f0f0f0;">
                                <td colspan="3" class="text-end">Totales</td>
                                <td class="text-end">{{ number_format($pC, 2) }}</td>
                                <td class="text-end">{{ number_format($pI, 2) }}</td>
                                @if($tieneExc)
                                    <td class="text-end">{{ number_format($pE, 2) }}</td>
                                @endif
                                <td class="text-end">{{ number_format($pM, 2) }}</td>
                                <td class="text-end">{{ number_format($pTot, 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
