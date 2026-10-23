<div class="container-fluid">    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">FICHA DE CLIENTE</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-users f-s-16"></i>
                    <a href="{{ route('clients.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Clientes</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Detalle</span></li>
            </ul>
        </div>
    </div>

    {{-- Acciones --}}
    <div class="row my-2">
        <div class="col-12">
            <div class="d-flex gap-2 py-1">
                <a href="{{ route('clients.edit', $client->id) }}" class="btn btn-sm btn-outline-success">
                    <i class="ti ti-edit"></i> Editar
                </a>
                <a href="{{ route('credits.create', $client->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-credit-card"></i> Nuevo Crédito
                </a>
                <a href="{{ route('exports.clients.history', $client->id) }}" target="_blank" class="btn btn-sm btn-success">
                    <i class="ti ti-file-spreadsheet"></i> Historial Excel
                </a>
                <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">
                    <i class="ti ti-printer"></i> Imprimir
                </button>
                {{-- 26/09: lo mismo que el enlace del número de expediente, pero a la vista --}}
                <a href="{{ route('clients.gallery', $client->id) }}" class="btn btn-sm btn-outline-secondary" title="Ver adjuntos del cliente">
                    <i class="ti ti-photo"></i> Imágenes
                </a>
                <a href="{{ route('clients.index') }}" class="btn btn-sm btn-secondary ms-auto">Volver</a>
            </div>
        </div>
    </div>

    {{-- 10/10 (Antony): la persona relacionada queda referenciada al préstamo en el que
         es copropietaria (Client::copropiedades: exacto por el contrato, o por el vehículo). --}}
    @php $copropiedades = $client->es_relacionado || $client->vehiculosCompartidos()->exists() ? $client->copropiedades() : collect(); @endphp
    @if($client->es_relacionado || $copropiedades->isNotEmpty())
        <div class="alert alert-info py-2 mb-2 small copropietario-en">
            <div class="fw-semibold"><i class="ti ti-users"></i> {{ $client->es_relacionado ? 'Persona relacionada · copropietaria en:' : 'Copropietaria en:' }}</div>
            @forelse($copropiedades as $c)
                <div>
                    @if($c['placas']) vehículo {{ implode(', ', $c['placas']) }} de @else codeudora de @endif
                    <a href="{{ route('clients.show', $c['titular']->id) }}">{{ $c['titular']->fullName() }}</a>
                    (exp. {{ $c['titular']->expediente ?: '—' }})
                    @if($c['credit'])
                        · <a href="{{ route('credits.show', $c['credit']->id) }}">crédito #{{ $c['credit']->id }}</a>
                        @if($c['documento']) · contrato v{{ $c['documento']->version }} del {{ $c['documento']->created_at?->format('d/m/Y') }} @endif
                        @unless($c['exacto']) <span class="text-muted">(último crédito con contrato del titular)</span> @endunless
                    @else
                        · <span class="text-muted">el titular aún no tiene contrato emitido</span>
                    @endif
                </div>
            @empty
                <div class="text-muted">Sin vehículos ni contratos vinculados todavía.</div>
            @endforelse
        </div>
    @endif

    {{-- Ficha personal --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            {{-- Responsive: en pantallas angostas la tabla scrollea dentro de su
                 contenedor en vez de estrujar las celdas hasta hacerlas ilegibles. --}}
            <div class="table-responsive">
            <table class="table table-bordered mb-0" style="font-size: 13px; min-width: 640px;">
                <tr>
                    <td colspan="4" class="bg-primary text-white" style="font-weight:500; padding:6px 12px;">Ficha Personal</td>
                </tr>
                <tr>
                    <td colspan="4" style="background-color:#e9ecef; font-weight:500; padding:6px 12px;">Datos Personales</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0; width:15%;">Nombres :</td>
                    <td style="width:35%;">{{ $client->fullName() }}</td>
                    <td style="background-color:#f0f0f0; width:15%;">Fecha Nacimiento</td>
                    <td>{{ $client->fecha_nacimiento ? $client->fecha_nacimiento->format('d/m/Y') : '' }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">N° Expediente</td>
                    {{-- 26/09: ya no enlaza a los adjuntos; para eso está el botón "Imágenes" --}}
                    <td>{{ $client->expediente }}</td>
                    <td style="background-color:#f0f0f0;">Nacionalidad</td>
                    <td>Peruano</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">Dirección</td>
                    <td>{{ $client->direccion }}</td>
                    <td style="background-color:#f0f0f0;">Móvil</td>
                    <td>{{ $client->celular1 }} {{ $client->celular2 }}</td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;">Registrado el</td>
                    <td>{{ $client->fecha_registro ? $client->fecha_registro->format('d/m/Y') : '' }}</td>
                    <td style="background-color:#f0f0f0;">DNI</td>
                    <td>
                        <a href="{{ route('credits.create', $client->id) }}"
                           title="Crear nuevo crédito"
                           style="color:#0d6efd; text-decoration:underline;">
                            {{ $client->documento }}
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f0f0f0;"><b>Capital</b></td>
                    <td style="background-color:#fff9db; font-weight:600;">{{ $client->capital !== null ? number_format($client->capital, 2) : '—' }}</td>
                    <td style="background-color:#f0f0f0;"><b>Crédito ({{ \App\Models\Client::LINEA_CREDITO_PCT }}%)</b></td>
                    <td style="background-color:#fff9db; font-weight:600;">{{ $lineaCredito !== null ? number_format($lineaCredito, 2) : '—' }}</td>
                </tr>
                <tr>
                    <td colspan="4" style="background-color:#e9ecef; font-weight:500; padding:6px 12px;">Historial de Pagos</td>
                </tr>
            </table>
            </div>
        </div>
    </div>

    {{-- Tabla de créditos del cliente --}}
    <div class="card shadow-sm mt-2">
        <div class="card-body pb-2">
            <div class="d-flex justify-content-end mb-1"><x-scroll-bottom-btn scrollable="#tabla-cliente" /></div>
                    <div id="tabla-cliente" class="table-responsive" style="max-height: 650px; overflow:auto;">
                {{-- min-width: con 25 columnas y width:100% (table-autofit), sin un piso
                     el navegador estruja las celdas en vez de activar el scroll del
                     table-responsive. Bajo 1300px la tabla desborda y scrollea. --}}
                <table class="table table-bordered table-striped table-hover table-autofit" style="font-size: 11px; min-width: 1300px;">
                    <thead class="bg-primary" style="position: sticky; top: 0; z-index: 2;">
                        <tr>
                            <th class="text-center">N°</th>
                            <th class="text-center">Usuario</th>
                            <th class="text-center">Codigo</th>
                            <th class="text-center">Cod. Ant.</th>
                            <th class="text-center">F. Crédito</th>
                            <th class="text-center">F. Vcto</th>
                            <th class="text-center">F. Pago</th>
                            <th class="text-center">F. Cancelado</th>
                            <th class="text-center">Capital</th>
                            <th class="text-center">%</th>
                            <th class="text-center">Interés</th>
                            <th class="text-center">C.</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Capital R.</th>
                            <th class="text-center">Interés G.</th>
                            <th class="text-center">Mora</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Saldo Capital</th>
                            <th class="text-center" style="color:red;">S/</th>
                            <th class="text-center" style="color:red;">MxD</th>
                            <th class="text-center" style="color:red;">Días</th>
                            <th class="text-center" style="color:red;">Gat.</th>
                            <th class="text-center">Asesor</th>
                            <th class="text-center">Op.</th>
                            <th class="text-center">Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $tdStyle = ($row['bg'] ? '--bs-table-bg:'.$row['bg'].'; --bs-table-striped-bg:'.$row['bg'].'; --bs-table-hover-bg:'.$row['bg'].'; background-color:'.$row['bg'].' !important;' : '') . ' color:'.$row['color'].' !important;';
                            @endphp
                            <tr style="{{ $tdStyle }}">
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['n'] }}</td>
                                <td style="{{ $tdStyle }}">{{ $row['usuario'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">
                                    @if($row['estado_activado'])
                                        <span class="badge bg-success" style="font-size:9px;">A</span>
                                    @else
                                        <span class="badge bg-secondary" style="font-size:9px;">D</span>
                                    @endif
                                    {{-- 23/10: clic en el código → resumen de cobranza para copiar --}}
                                    <a href="#" wire:click.prevent="verResumen({{ $row['codigo'] }})" title="Ver resumen del crédito"
                                       style="color:inherit; font-weight:600; text-decoration:underline dotted;">{{ $row['codigo'] }}</a>
                                </td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['cod_ant'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_credito'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_vcto'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_pago'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['f_cancelado'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['capital'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['interes_pct'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['interes'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['cuotas'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['total'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['capital_r'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['interes_g'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['mora'], 2) }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ number_format($row['total_pag'], 2) }}</td>
                                {{-- Se muestra siempre, tambien cuando es 0: un blanco no
                                     distingue "sin deuda" de "sin dato". El export a Excel
                                     ya escribia el 0, asi que ademas quedan consistentes. --}}
                                <td style="{{ $tdStyle }}" class="text-end">
                                    {{ number_format($row['saldo_capital'], 2) }}
                                </td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ $row['mora_s'] > 0 ? $row['mora_s'] : '' }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ $row['mxd'] > 0 ? $row['mxd'] : '' }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">{{ $row['dias'] != 0 ? $row['dias'] : '' }}</td>
                                <td style="{{ $tdStyle }}" class="text-end">{{ $row['gat'] > 0 ? number_format($row['gat'], 2) : '' }}</td>
                                <td style="{{ $tdStyle }}">{{ $row['asesor'] }}</td>
                                <td style="{{ $tdStyle }}" class="text-center">
                                    <a href="{{ route('credits.schedule', $row['codigo']) }}" target="_blank" title="Ver cronograma">
                                        <i class="ti ti-edit f-s-14"></i>
                                    </a>
                                </td>
                                <td style="{{ $tdStyle }}" class="text-center">
                                    @if(!$row['estado_activado'])
                                        <a href="{{ route('payments.create', $row['codigo']) }}" target="_blank" title="Registrar pago">
                                            <i class="ti ti-edit f-s-14"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="25" class="text-center text-muted" style="padding:8px;">Sin créditos registrados</td>
                            </tr>
                        @endforelse

                        @if(count($rows) > 0)
                            <tr style="background-color:#f0f0f0; font-weight:500;">
                                <td colspan="3" class="text-center">Total</td>
                                <td colspan="5"></td>
                                <td class="text-end">{{ number_format($totals['capital'], 2) }}</td>
                                <td></td>
                                <td class="text-end">{{ number_format($totals['interes_t'], 2) }}</td>
                                <td></td>
                                <td class="text-end">{{ number_format($totals['total_a_pag'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['capital_r'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['interes_g'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['mora'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['total_pag'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['saldo_capital'], 2) }}</td>
                                <td class="text-end">{{ number_format($totals['mora_x_dia'], 2) }}</td>
                                <td colspan="6"></td>
                            </tr>
                            {{-- Línea de crédito (informativa): 25% del capital declarado.
                                 Usado = capital de créditos Activos. Rojo si ya excedió. --}}
                            <tr style="background-color:#fff9db; font-weight:600; font-size:12px;">
                                @if($lineaCredito !== null)
                                    <td colspan="25" class="text-end" style="padding:8px 12px;">
                                        Crédito ({{ \App\Models\Client::LINEA_CREDITO_PCT }}% del capital):
                                        <b>{{ number_format($lineaCredito, 2) }}</b>
                                        &nbsp;|&nbsp; Usado (créditos Activos): <b>{{ number_format($usadoCredito, 2) }}</b>
                                        &nbsp;|&nbsp; Disponible:
                                        <b style="{{ $disponibleCredito < 0 ? 'color:red;' : 'color:green;' }}">
                                            {{ number_format($disponibleCredito, 2) }}
                                        </b>
                                        @if($disponibleCredito < 0)
                                            <span style="color:red;"><b>(EXCEDIDO)</b></span>
                                        @endif
                                    </td>
                                @else
                                    <td colspan="25" class="text-end text-muted" style="padding:8px 12px;">
                                        Sin capital registrado — edita el cliente para asignar su capital y línea de crédito ({{ \App\Models\Client::LINEA_CREDITO_PCT }}%).
                                    </td>
                                @endif
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<span id="final"></span>

    {{-- Resumen de cobranza del crédito (23/10, Antony): se abre con el clic en el código y se copia
         tal cual (App\Support\ResumenDeudaCredito). wire:ignore.self: Bootstrap pone clases en el modal. --}}
    {{-- 23/10 (Antony): ventana movible, la misma de "Confirmar cobro" (modalFlotante). wire:ignore.self también en
         el diálogo: el morph de Livewire no debe reponer su style y devolverla a la esquina (ver ventanas-flotantes). --}}
    <div class="modal fade" id="resumenCreditoModal" tabindex="-1" aria-hidden="true" wire:ignore.self
         x-data="Object.assign(modalFlotante('resumen'), resumenCredito())" x-on:resumen-credito-listo.window="abrir()">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;" x-ref="dialogo" wire:ignore.self>
            <div class="modal-content">
                <div class="modal-header py-2"
                     x-on:pointerdown="iniciarArrastre($event)" x-on:pointermove="arrastrar($event)"
                     x-on:pointerup="soltar($event)" x-on:pointercancel="soltar($event)">
                    <h6 class="modal-title mb-0">Resumen del crédito{{ $resumen ? ' #'.$resumen['codigo'] : '' }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body py-3">
                    @if($resumen)
                        {{-- data-copia: lo que se copia (sin líneas en blanco, pedido de Antony); en pantalla se ve con aire --}}
                        <pre x-ref="texto" class="resumen-credito mb-0" data-copia="{{ $resumen['texto_copia'] }}">{{ $resumen['texto'] }}</pre>
                    @endif
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-sm btn-primary" x-on:click="copiar()" title="Copiar el texto para pegarlo en WhatsApp">
                        <i class="ti" :class="copiado ? 'ti-check' : 'ti-copy'"></i> <span x-text="copiado ? 'Copiado' : 'Copiar'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <style>
        .resumen-credito { font-family: 'Poppins', sans-serif; font-size: 13px; line-height: 1.5; white-space: pre-wrap; color: #15264b; background: #f5f6fa; border-radius: 10px; padding: 12px 14px; }
    </style>
    <script>
        function resumenCredito() {
            return {
                copiado: false,
                abrir() { this.modal.show(); this.copiado = false; }, // instancia de modalFlotante (sin fondo oscuro en escritorio)
                copiar() {
                    const texto = this.$refs.texto ? (this.$refs.texto.dataset.copia || this.$refs.texto.innerText) : '';
                    const listo = () => { this.copiado = true; setTimeout(() => { this.copiado = false; }, 1800); };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(texto).then(listo, () => this.copiarViejo(texto, listo));
                    } else {
                        this.copiarViejo(texto, listo);
                    }
                },
                // Navegadores sin API de portapapeles (o sin https): el truco del textarea.
                copiarViejo(texto, listo) {
                    const t = document.createElement('textarea');
                    t.value = texto; t.style.position = 'fixed'; t.style.opacity = '0';
                    document.body.appendChild(t); t.select();
                    try { document.execCommand('copy'); listo(); } catch (e) {}
                    document.body.removeChild(t);
                },
            };
        }
    </script>
</div>
