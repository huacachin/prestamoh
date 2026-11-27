{{-- El alta vive oculta desde que se abre la pestaña; "Nuevo préstamo" solo la
     muestra (Alpine, fade in), sin pedir nada al servidor. Se cierra con
     Cancelar (evento del botón) o al guardar (evento Livewire `prestamo-creado`). --}}
<div x-data="{ abierto: false }"
     x-on:prestamo-creado.window="abierto = false"
     x-on:prestamo-cancelado="abierto = false">
    <style>
        .alta-prestamo { background: #fffdf5; }
        /* 20/11 (Antony): el préstamo cuyos pasos muestra la ruta va sombreado en amarillo Huacachin (el mismo de
           la fila con recibos abiertos del reporte de pagos y de las cuotas vencidas en Morosos). Bootstrap pinta
           la cebra y el hover con un box-shadow interior sobre la celda, que teñiría el amarillo; se apaga igual. */
        .tabla-prestamos tbody tr.fila-en-ruta > td {
            background-color: #ffff00 !important;
            --bs-table-bg-type: transparent; --bs-table-accent-bg: transparent; --bs-table-bg-state: transparent; box-shadow: none;
        }
    </style>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="mb-0" style="color:red;">
            Préstamos <span class="text-muted small fw-normal">({{ $conteo }})</span>
        </h6>
        @if($puedeCrear && $bloqueoPrestamo)
            {{-- 06/11 (Antony): a un cliente cesado no se le presta; primero hay que darlo de alta. --}}
            <div class="small rounded px-2 py-1" style="background:#fdecea; color:#a1271b;">
                <i class="ti ti-ban"></i> {{ $bloqueoPrestamo }}
                @can('registro.cesados')
                    <a href="{{ route('clients.ceased', ['documento' => $client->documento]) }}" class="ms-1">Ir a Cliente Cesados</a>
                @endcan
            </div>
        @elseif($puedeCrear)
            <button type="button" class="btn btn-sm btn-success" x-show="! abierto"
                    x-on:click="abierto = true; setTimeout(() => $refs.alta.querySelector('input[name=impopres]')?.focus(), 320)">
                <i class="ti ti-plus"></i> Nuevo préstamo
            </button>
        @endif
    </div>

    {{-- ════════ Alta de crédito, aquí mismo (el formulario de /credits/create/{id}) ════════ --}}
    @if($puedeCrear && ! $bloqueoPrestamo)
        <div class="border rounded p-2 mb-3 alta-prestamo" x-ref="alta" style="display:none;"
             x-show="abierto" x-transition.opacity.duration.300ms>
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold small" style="color:red;"><i class="ti ti-plus"></i> Nuevo préstamo para {{ $client->fullName() }}</span>
            </div>
            <livewire:credits.create :clientId="$clientId" :embebido="true" :key="'alta-'.$clientId" />
        </div>
    @endif

    {{-- ════════ Tabla ════════ --}}
    @if($credits->isEmpty())
        <p class="text-muted small mb-0">Este cliente aún no tiene préstamos.</p>
    @else
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
            <table class="table table-bordered table-striped table-hover table-sm mb-0 tabla-prestamos" style="font-size: 11px;">
                <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                    <tr>
                        <th class="text-center">N°</th>
                        <th class="text-center">Código</th>
                        <th class="text-center">Fecha</th>
                        <th class="text-center">Vence</th>
                        <th class="text-end">Capital</th>
                        <th class="text-center">%</th>
                        <th class="text-center">C.</th>
                        <th class="text-center">T.C.</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Pagado</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-center">Situación</th>
                        <th class="text-center">Asesor</th>
                        <th class="text-center">Usuario</th>
                        <th class="text-center">Op.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($credits as $credit)
                        @php
                            $interes = round((float) $credit->importe * (float) $credit->interes / 100, 2);
                            if ((int) $credit->tipo_planilla === 3) {
                                $interes = round($interes * max(1, (int) $credit->cuotas), 2);
                            }
                            $total = (float) $credit->importe + $interes;
                            $pagado = $resumen[$credit->id]['pagado'] ?? 0.0;
                            // Solo un crédito activo debe algo: el cancelado o refinanciado
                            // puede dejar cuotas sin aplicar en el cronograma (refi legacy), pero su deuda ya no está aquí.
                            $saldo = $credit->situacion === 'Activo' ? ($resumen[$credit->id]['saldo'] ?? max(0.0, $total - $pagado)) : 0.0;
                            $badge = match ($credit->situacion) {
                                'Activo' => 'bg-success',
                                'Refinanciado' => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                            $esNuevo = $recienCreado === $credit->id;
                            // 20/11: el préstamo cuyos pasos muestra la ruta de arriba (fila en amarillo).
                            $enRuta = $prestamoDeLaRuta === $credit->id;
                        @endphp
                        <tr @class(['table-success fila-nueva' => $esNuevo, 'fila-en-ruta' => $enRuta]) wire:key="prestamo-{{ $credit->id }}"
                            @if($enRuta) aria-current="true" title="La ruta de arriba muestra los pasos de este préstamo" @endif>
                            <td class="text-center">{{ $loop->iteration }}</td>
                            <td class="text-center fw-bold">
                                @if($puedeVerCreditos)
                                    <a href="{{ route('credits.show', $credit->id) }}" style="color: black;">{{ $credit->id }}</a>
                                @else
                                    {{ $credit->id }}
                                @endif
                                @if($esNuevo)<span class="badge bg-success ms-1" style="font-size:9px;">nuevo</span>@endif
                            </td>
                            <td class="text-center">{{ $credit->fecha_prestamo?->format('d/m/Y') }}</td>
                            <td class="text-center">{{ $credit->fecha_vencimiento?->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format((float) $credit->importe, 2) }}</td>
                            <td class="text-center">{{ (int) $credit->interes == (float) $credit->interes ? (int) $credit->interes : number_format((float) $credit->interes, 2) }}</td>
                            <td class="text-center">{{ $credit->cuotas }}</td>
                            <td class="text-center">{{ $credit->tipoPlanillaLabel() }}</td>
                            <td class="text-end">{{ number_format($total, 2) }}</td>
                            <td class="text-end">{{ number_format($pagado, 2) }}</td>
                            <td class="text-end {{ $saldo > 0 ? 'fw-bold text-danger' : '' }}">{{ number_format($saldo, 2) }}</td>
                            <td class="text-center"><span class="badge {{ $badge }}" style="font-size:9px;">{{ $credit->situacion }}</span></td>
                            <td class="text-center">{{ $credit->asesor ?: '-' }}</td>
                            <td class="text-center">{{ $credit->user?->username ?? $credit->usuario ?? '-' }}</td>
                            <td class="text-center text-nowrap">
                                {{-- 27/11 (Antony, ficha 1493): el cronograma y el cobro llevan de dónde se vino, para que su
                                     "Regresar" vuelva a esta pestaña (con el préstamo abierto en la ruta, si lo había). --}}
                                @php $regreso = ['desde' => 'expediente'] + ($prestamo ? ['prestamo' => $prestamo] : []); @endphp
                                @if($puedeVerCreditos)
                                    <a href="{{ route('credits.schedule', ['id' => $credit->id] + $regreso) }}" class="btn btn-xs btn-outline-primary" style="padding: 1px 6px; font-size: 10px;" title="Cronograma">
                                        <i class="ti ti-calendar"></i>
                                    </a>
                                @endif
                                @if($credit->situacion === 'Activo')
                                    @can('pagos')
                                        <a href="{{ route('payments.create', ['creditId' => $credit->id] + $regreso) }}" class="btn btn-xs btn-outline-success" style="padding: 1px 6px; font-size: 10px;" title="Cobrar">
                                            <i class="ti ti-currency-dollar"></i>
                                        </a>
                                    @endcan
                                    {{-- 20/11: ver en la ruta los pasos de este préstamo. --}}
                                    @if(! $enRuta)
                                        <button type="button" class="btn btn-xs btn-outline-primary" style="padding: 1px 6px; font-size: 10px;"
                                                wire:click="$parent.$set('prestamo', '{{ $credit->id }}')" title="Ver en la ruta los pasos de este préstamo">
                                            <i class="ti ti-route"></i> Pasos
                                        </button>
                                    @endif
                                    {{-- 20/11: se edita mientras no tenga el contrato emitido (y, como siempre, el día del registro). --}}
                                    @if($puedeVerCreditos && ($edicion[$credit->id]['editable'] ?? false))
                                        <a href="{{ route('credits.edit', ['id' => $credit->id, 'desde' => 'expediente']) }}" class="btn btn-xs btn-outline-warning"
                                           style="padding: 1px 6px; font-size: 10px;" title="Editar el préstamo">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                    @elseif($puedeVerCreditos && ($edicion[$credit->id]['contrato'] ?? false))
                                        <span class="text-muted" style="padding: 1px 4px; font-size: 12px;" title="{{ \App\Support\EdicionCredito::MOTIVO_CONTRATO }}">
                                            <i class="ti ti-lock" aria-hidden="true"></i><span class="visually-hidden">Edición bloqueada: contrato emitido</span>
                                        </span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
