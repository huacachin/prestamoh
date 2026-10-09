{{-- Raíz con el estado del visor de fotos (galería tipo lightbox, 26/09): mismo
     contrato que el de caja (open/idx/items + close/next/prev) para reutilizar el
     partial livewire.cash.partials._lightbox. openLightbox(items, i) abre en la foto i. --}}
<div class="mt-3"
     x-data="{
        open: false, idx: 0, items: [],
        openLightbox(items, i) {
            if (! items || ! items.length) return;
            this.items = items;
            this.idx = Math.min(Math.max(i || 0, 0), items.length - 1);
            this.open = true;
        },
        close() { this.open = false; },
        next() { this.idx = (this.idx + 1) % this.items.length; },
        prev() { this.idx = (this.idx - 1 + this.items.length) % this.items.length; },
     }"
     @keydown.escape.window="open && close()"
     @keydown.arrow-right.window="open && next()"
     @keydown.arrow-left.window="open && prev()">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="mb-0" style="color:red;">
            Reportes de GPS de los vehículos
            <span class="text-muted small fw-normal d-block d-sm-inline">(dónde está cada vehículo en garantía, sus horarios y fotos)</span>
        </h6>
        @if($puedeEditar && ! $mostrarForm)
            <button type="button" class="btn btn-sm btn-dark" wire:click="nuevo" @if($vehiculos->isEmpty()) disabled title="El cliente no tiene vehículos" @endif>
                <i class="ti ti-plus"></i> Nuevo reporte
            </button>
        @endif
    </div>

    @if($msg)
        @php $cls = $msgType === 'ok' ? 'alert-success' : 'alert-danger'; @endphp
        <div class="alert {{ $cls }} py-2 mb-2 small">{{ $msg }}</div>
    @endif

    {{-- ═══ Formulario por pasos. Solo se digita lo del recorrido: cliente,
         expediente, placas y domicilio ya se saben. A la derecha, la vista
         previa del mensaje se arma mientras se escribe. ═══ --}}
    @if($mostrarForm)
        <div class="border rounded p-3 mb-3" style="background:#fcfcfa;">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <div class="fw-semibold"><i class="ti ti-route"></i> Nuevo reporte de GPS</div>
                    <div class="small text-muted">
                        {{ $client->fullName() }} · Expediente {{ $client->expediente }}.
                        Uno o varios puntos del recorrido, cada uno con su horario de estadía.
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelar"><i class="ti ti-x"></i> Cancelar</button>
            </div>

            <div class="row g-3">
                {{-- ─── Columna del formulario ─── --}}
                <div class="col-12 col-xl-7">

                    {{-- Paso 1 --}}
                    <div class="mb-3">
                        <div class="small fw-semibold text-uppercase text-muted mb-1"><span class="badge bg-dark rounded-pill me-1">1</span> Vehículo y fecha</div>
                        <div class="row g-2">
                            <div class="col-7 col-md-5">
                                <label class="form-label mb-0 small">Placa *</label>
                                <select class="form-select form-select-sm @error('form.vehiculo_id') is-invalid @enderror" wire:model.live="form.vehiculo_id">
                                    @if($vehiculos->count() !== 1)<option value="">-- Elegir --</option>@endif
                                    @foreach($vehiculos as $v)
                                        <option value="{{ $v->id }}">{{ $v->placa }} — {{ trim($v->marca.' '.$v->modelo) }}</option>
                                    @endforeach
                                </select>
                                @error('form.vehiculo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-5 col-md-5">
                                <label class="form-label mb-0 small">Fecha y hora del reporte *</label>
                                <input type="datetime-local" class="form-control form-control-sm @error('form.fecha') is-invalid @enderror" wire:model.live="form.fecha">
                                @error('form.fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Paso 2 --}}
                    <div class="mb-3">
                        <div class="small fw-semibold text-uppercase text-muted mb-1"><span class="badge bg-dark rounded-pill me-1">2</span> Horarios de la ruta <span class="fw-normal text-lowercase">(opcional)</span></div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <div class="border rounded p-2 bg-white">
                                    <label class="form-label mb-1 small fw-semibold"><i class="ti ti-clock-play"></i> Inicio de ruta</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="time" class="form-control form-control-sm" wire:model.live="form.inicio_desde" title="Desde">
                                        <span class="text-muted">a</span>
                                        <input type="time" class="form-control form-control-sm" wire:model.live="form.inicio_hasta" title="Hasta">
                                    </div>
                                    <div class="small text-muted mt-1">Ej.: 6:30 a.m. a 7:40 a.m.</div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border rounded p-2 bg-white">
                                    <label class="form-label mb-1 small fw-semibold"><i class="ti ti-clock-stop"></i> Fin de ruta</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="time" class="form-control form-control-sm" wire:model.live="form.fin_desde" title="Desde">
                                        <span class="text-muted">a</span>
                                        <input type="time" class="form-control form-control-sm" wire:model.live="form.fin_hasta" title="Hasta">
                                    </div>
                                    <div class="small text-muted mt-1">Ej.: 11:00 p.m. a 1:30 a.m.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Paso 3 --}}
                    <div class="mb-3">
                        <div class="small fw-semibold text-uppercase text-muted mb-1"><span class="badge bg-dark rounded-pill me-1">3</span> Puntos del recorrido</div>
                        @foreach($form['puntos'] as $i => $p)
                            <div class="border rounded p-2 mb-2 bg-white" wire:key="punto-{{ $i }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-semibold"><i class="ti ti-map-pin"></i> Punto {{ $i + 1 }}</span>
                                    @if(count($form['puntos']) > 1)
                                        <a href="#" class="small text-danger text-decoration-none" wire:click.prevent="quitarPunto({{ $i }})"><i class="ti ti-x"></i> quitar</a>
                                    @endif
                                </div>
                                <div class="row g-2 mb-2">
                                    {{-- Título del punto (02/10): sale en el mensaje como "📍 *Título (etiqueta):*". --}}
                                    <div class="col-12 col-md-4">
                                        <label class="form-label mb-0 small">Título del punto</label>
                                        <input type="text" class="form-control form-control-sm" maxlength="60"
                                               wire:model.live.debounce.500ms="form.puntos.{{ $i }}.titulo"
                                               placeholder="{{ \App\Models\VehiculoGpsReporte::TITULO_PUNTO }}">
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label mb-0 small">¿Qué punto es?</label>
                                        <select class="form-select form-select-sm" wire:model.live="form.puntos.{{ $i }}.etiqueta">
                                            <option value="">Sin etiqueta (formato corto)</option>
                                            @foreach(\App\Models\VehiculoGpsReporte::ETIQUETAS as $e)<option value="{{ $e }}">{{ $e }}</option>@endforeach
                                            <option value="Otra">Otra…</option>
                                        </select>
                                    </div>
                                    @if(($p['etiqueta'] ?? '') === 'Otra')
                                        <div class="col-6 col-md-3">
                                            <label class="form-label mb-0 small">Nombre del punto</label>
                                            <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="form.puntos.{{ $i }}.etiqueta_otra" placeholder="Ej.: Taller">
                                        </div>
                                    @endif
                                    <div class="col-12 col-md-5">
                                        <label class="form-label mb-0 small">Horario aproximado de estadía</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="time" class="form-control form-control-sm" wire:model.live="form.puntos.{{ $i }}.estadia_desde">
                                            <span class="text-muted">a</span>
                                            <input type="time" class="form-control form-control-sm" wire:model.live="form.puntos.{{ $i }}.estadia_hasta">
                                        </div>
                                    </div>
                                </div>
                                <label class="form-label mb-0 small">Dirección o referencia del lugar *</label>
                                <input type="text" class="form-control form-control-sm @error("form.puntos.$i.direccion") is-invalid @enderror"
                                       wire:model.live.debounce.500ms="form.puntos.{{ $i }}.direccion" placeholder="Ej.: Las Lúcumas, Carabayllo 15319">
                                @error("form.puntos.$i.direccion") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <label class="form-label mb-0 small mt-2">Enlace de Google Maps <span class="text-muted">(el que comparte la app de Maps)</span></label>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="form.puntos.{{ $i }}.link" placeholder="https://maps.app.goo.gl/…">
                                    @php $mapsPunto = \App\Models\VehiculoGpsReporte::maps($p['link'] ?? null, $p['direccion'] ?? null); @endphp
                                    <a href="{{ $mapsPunto ?? '#' }}" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-outline-success flex-shrink-0 {{ $mapsPunto ? '' : 'disabled' }}"
                                       title="{{ ($p['link'] ?? '') !== '' ? 'Abrir el enlace en Google Maps' : 'Buscar la dirección en Google Maps' }}">
                                        <i class="ti ti-map-pin"></i> Maps
                                    </a>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-dark" wire:click="agregarPunto">
                            <i class="ti ti-plus"></i> Agregar otro punto
                        </button>
                    </div>

                    {{-- Paso 4: domicilio, tomado de la ficha y de Casa --}}
                    <div class="mb-3">
                        <div class="small fw-semibold text-uppercase text-muted mb-1"><span class="badge bg-dark rounded-pill me-1">4</span> Domicilio del cliente</div>
                        @php $casa = $this->enlaceCasa(); $dom = $this->domicilioFicha(); @endphp
                        <div class="border rounded p-2 bg-white">
                            @unless($form['domicilio_personalizado'])
                                <div class="d-flex align-items-start gap-2">
                                    <i class="ti ti-home f-s-18 text-muted"></i>
                                    <div class="flex-grow-1">
                                        <div class="{{ $dom !== '' ? '' : 'text-danger' }}">{{ $dom !== '' ? $dom : 'La ficha del cliente no tiene dirección.' }}</div>
                                        <div class="small mt-1">
                                            @if($casa !== '')
                                                <span class="badge bg-success"><i class="ti ti-map-pin"></i> Enlace de Casa registrado</span>
                                                <a href="{{ $casa }}" target="_blank" rel="noopener" class="ms-1">ver en el mapa</a>
                                            @else
                                                <span class="badge bg-warning text-dark"><i class="ti ti-map-pin-off"></i> Sin ubicación de Casa</span>
                                                <span class="text-muted ms-1">regístrala arriba y saldrá el enlace, o escribe uno para este reporte.</span>
                                                @if($dom !== '')
                                                    <a href="{{ \App\Models\VehiculoGpsReporte::maps(null, $dom) }}" target="_blank" rel="noopener" class="ms-1">buscar la dirección en Maps</a>
                                                @endif
                                            @endif
                                        </div>
                                        <div class="small text-muted mt-1">Se toma de la ficha: no hace falta escribirlo.</div>
                                    </div>
                                </div>
                            @endunless
                            <div class="form-check form-switch mt-2 small">
                                <input class="form-check-input" type="checkbox" id="dom-personalizado" wire:model.live="form.domicilio_personalizado">
                                <label class="form-check-label" for="dom-personalizado">Usar otro domicilio o enlace solo para este reporte</label>
                            </div>
                            @if($form['domicilio_personalizado'])
                                <div class="row g-2 mt-1">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label mb-0 small">Dirección del domicilio</label>
                                        <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="form.domicilio_direccion">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label mb-0 small">Enlace de Google Maps del domicilio</label>
                                        <div class="d-flex gap-2">
                                            <input type="text" class="form-control form-control-sm" wire:model.live.debounce.500ms="form.domicilio_link" placeholder="https://maps.app.goo.gl/…">
                                            @php $mapsDom = \App\Models\VehiculoGpsReporte::maps($form['domicilio_link'] ?? null, $form['domicilio_direccion'] ?? null); @endphp
                                            <a href="{{ $mapsDom ?? '#' }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success flex-shrink-0 {{ $mapsDom ? '' : 'disabled' }}" title="Abrir en Google Maps">
                                                <i class="ti ti-map-pin"></i> Maps
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Paso 5: fotos (se suben al guardar) --}}
                    <div class="mb-3">
                        <div class="small fw-semibold text-uppercase text-muted mb-1"><span class="badge bg-dark rounded-pill me-1">5</span> Fotos <span class="fw-normal text-lowercase">(opcional)</span></div>
                        <div class="bg-white rounded">
                            @include('livewire.cash.partials.zona-imagenes')
                        </div>
                        {{-- O elegirlas de la pestaña Adjuntos del cliente (02/10): se copian al guardar. --}}
                        @php $adjuntosForm = collect($form['adjuntos'] ?? [])->map(fn ($id) => (int) $id); $adjuntosElegidos = $adjuntosCliente->whereIn('id', $adjuntosForm->all()); @endphp
                        <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="abrirAdjuntos" @disabled($adjuntosCliente->isEmpty())
                                    title="{{ $adjuntosCliente->isEmpty() ? 'El cliente no tiene fotos en Adjuntos' : 'Elegir fotos ya guardadas en la pestaña Adjuntos' }}">
                                <i class="ti ti-photo-search"></i> Elegir de Adjuntos ({{ $adjuntosCliente->count() }})
                            </button>
                            @foreach($adjuntosElegidos as $att)
                                <div class="position-relative" wire:key="adj-form-{{ $att->id }}" title="{{ $att->original_name }}">
                                    <img src="{{ $att->thumbUrl() }}" alt="" class="rounded border" style="width:56px; height:56px; object-fit:cover; background:#fff;">
                                    <button type="button" class="btn btn-danger position-absolute" style="top:-6px; right:-6px; padding:0 5px; font-size:10px; line-height:1.4;"
                                            wire:click="quitarAdjuntoForm({{ $att->id }})" title="Quitar">
                                        <i class="ti ti-x"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <div class="small text-muted mt-1">Se suben al guardar el reporte. Después también puedes arrastrar más fotos sobre el reporte guardado o elegirlas de Adjuntos.</div>
                    </div>

                    @php $nFotosForm = count($files) + $adjuntosElegidos->count(); @endphp
                    <div class="d-grid d-sm-flex gap-2">
                        <button type="button" class="btn btn-dark" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar,files,removeFile">
                            <i class="ti ti-device-floppy"></i>
                            <span wire:loading.remove wire:target="guardar">Guardar y generar el mensaje{{ $nFotosForm > 0 ? ' con '.$nFotosForm.($nFotosForm === 1 ? ' foto' : ' fotos') : '' }}</span>
                            <span wire:loading wire:target="guardar">Guardando…</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" wire:click="cancelar">Cancelar</button>
                    </div>
                </div>

                {{-- ─── Vista previa del mensaje ─── --}}
                <div class="col-12 col-xl-5">
                    <div class="border rounded p-2 h-100" style="background:#f4faf6; position:sticky; top:90px;">
                        <div class="small fw-semibold mb-1"><i class="ti ti-message-2"></i> Así saldrá el mensaje</div>
                        {{-- Negritas como en WhatsApp (los *…* del texto se ven en negrita aquí). --}}
                        <pre class="mb-0 small bg-white border rounded p-2" style="white-space:pre-wrap; font-family:inherit; min-height:200px;">{!! preg_replace('/\*([^*\n]+)\*/u', '<b>$1</b>', e($this->vistaPrevia())) !!}</pre>
                        <div class="small text-muted mt-1">Se actualiza mientras escribes. Al guardar tendrás el botón "Copiar texto".</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Detalle del reporte: texto para copiar, enlaces a Maps y fotos ═══ --}}
    @if($reporteVer)
        {{-- Para WhatsApp (02/10, Antony): el portapapeles solo lleva UNA imagen por copia y
             WhatsApp Web al pegar toma la imagen o el texto, nunca ambos. Por eso:
             · "Copiar" en cada foto → Ctrl+V en el chat (la 2.ª se pega sobre la vista previa)
               y luego "Copiar texto" → Ctrl+V en el pie de foto;
             · "Descargar fotos" como respaldo (se arrastran juntas desde Descargas);
             · "Compartir" (Web Share: celular / WhatsApp de Windows) manda texto + fotos de una.
             La clave incluye las fotos: si cambian, el bloque se recrea y Alpine parte fresco. --}}
        @php $descargas = \Illuminate\Support\Js::from($reporteVer->descargas()); @endphp
        <div class="border rounded p-2 mb-3" style="background:#f4faf6;"
             wire:key="detalle-{{ $reporteVer->id }}-{{ $reporteVer->fotos->pluck('id')->implode('-') }}"
             x-data="{
                copiado: false,
                estado: {},
                compartible: false,
                aviso: '',
                fotos: {{ $descargas }},
                cache: {},
                init() {
                    try {
                        const prueba = new File([new Blob(['x'])], 'x.jpg', { type: 'image/jpeg' });
                        this.compartible = !! (navigator.share && (this.fotos.length === 0 || (navigator.canShare && navigator.canShare({ files: [prueba] }))));
                    } catch (e) { this.compartible = false; }
                    // Donde se puede compartir (celular), las fotos se precargan para que el
                    // gesto del usuario no caduque esperando la descarga.
                    if (this.compartible) { this.fotos.forEach(f => this.blob(f.url).catch(() => null)); }
                },
                avisar(texto) { this.aviso = texto; setTimeout(() => this.aviso = '', 6000); },
                blob(url) {
                    return this.cache[url] ??= fetch(url, { credentials: 'same-origin' }).then(r => { if (! r.ok) throw new Error(r.status); return r.blob(); });
                },
                // La imagen como PNG (lo único que el portapapeles acepta), tope 1920 px: WhatsApp igual la comprime.
                async png(url) {
                    const img = await new Promise((ok, ko) => { const i = new Image(); i.onload = () => ok(i); i.onerror = ko; i.src = url; });
                    const escala = Math.min(1, 1920 / Math.max(img.naturalWidth, img.naturalHeight));
                    const c = document.createElement('canvas');
                    c.width = Math.round(img.naturalWidth * escala); c.height = Math.round(img.naturalHeight * escala);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    return await new Promise((ok, ko) => c.toBlob(b => b ? ok(b) : ko(new Error('png')), 'image/png'));
                },
                marcar(id, valor) { this.estado = { ...this.estado, [id]: valor }; setTimeout(() => { this.estado = { ...this.estado, [id]: null }; }, 2500); },
                async copiarFoto(id, url) {
                    if (! navigator.clipboard?.write || ! window.ClipboardItem) {
                        this.marcar(id, 'error'); this.avisar('Este navegador no copia imágenes: usa Descargar fotos.'); return;
                    }
                    try {
                        await navigator.clipboard.write([new ClipboardItem({ 'image/png': this.png(url) })]);
                        this.marcar(id, 'ok');
                    } catch (e) { this.marcar(id, 'error'); this.avisar('No se pudo copiar la foto: usa Descargar fotos.'); }
                },
                descargarFotos() {
                    // Un <a download> por foto, espaciados: el navegador pide permiso una sola vez para varias descargas.
                    this.fotos.forEach((f, i) => setTimeout(() => {
                        const a = document.createElement('a'); a.href = f.url; a.download = f.nombre; a.rel = 'noopener';
                        document.body.appendChild(a); a.click(); a.remove();
                    }, i * 400));
                },
                async compartir() {
                    try {
                        const files = await Promise.all(this.fotos.map(async f => new File([await this.blob(f.url)], f.nombre, { type: f.mime })));
                        const datos = { text: this.$refs.texto.textContent };
                        if (files.length) { datos.files = files; }
                        await navigator.share(datos);
                    } catch (e) {
                        if (e && e.name === 'AbortError') return;
                        this.avisar('No se pudo compartir desde este equipo: usa Copiar o Descargar fotos.');
                    }
                },
             }">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-1 flex-wrap">
                <span class="fw-semibold small"><i class="ti ti-message-2"></i> Reporte del {{ $reporteVer->fecha->format('d/m/Y H:i') }} · {{ $reporteVer->placa }}</span>
                <div class="d-flex gap-2 flex-wrap">
                    {{-- Mismo enlace que los comprobantes pero SIN número (02/10, Antony): WhatsApp
                         no deja apuntar a un grupo, así que se abre con el texto puesto y pide
                         elegir el chat (grupos incluidos). Las fotos van aparte (Copiar / Compartir). --}}
                    <a href="https://api.whatsapp.com/send?text={{ rawurlencode($reporteVer->texto()) }}" target="_blank" rel="noopener"
                       class="btn btn-sm btn-success" title="Abre WhatsApp con el texto listo; eliges el grupo o contacto y lo mandas">
                        <i class="ti ti-brand-whatsapp"></i> Enviar por WhatsApp
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-success"
                            x-on:click="navigator.clipboard.writeText($refs.texto.textContent).then(() => { copiado = true; setTimeout(() => copiado = false, 2000) })">
                        <i class="ti ti-copy"></i> <span x-text="copiado ? '¡Copiado!' : 'Copiar texto'">Copiar texto</span>
                    </button>
                    @if($reporteVer->fotos->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-outline-success" x-on:click="descargarFotos()"
                                title="Baja las {{ $reporteVer->fotos->count() }} fotos a Descargas para arrastrarlas juntas al chat (el navegador pide permiso una vez)">
                            <i class="ti ti-download"></i> Descargar fotos ({{ $reporteVer->fotos->count() }})
                        </button>
                    @endif
                    {{-- Solo donde el navegador sabe compartir archivos (celular, WhatsApp de Windows). --}}
                    <button type="button" class="btn btn-sm btn-success" x-show="compartible" x-cloak x-on:click="compartir()"
                            title="Abre el menú de compartir del equipo con el texto y las fotos: elige WhatsApp">
                        <i class="ti ti-share"></i> Compartir
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="ver({{ $reporteVer->id }})">Cerrar</button>
                </div>
            </div>
            <div class="alert alert-warning py-1 px-2 small mb-1" x-show="aviso" x-cloak x-text="aviso"></div>
            <div class="row g-2">
                <div class="col-12 col-xl-7">
                    {{-- En pantalla, las negritas como en WhatsApp; lo que se COPIA es el texto
                         con asteriscos (x-ref="texto", oculto), que WhatsApp convierte en negrita. --}}
                    <pre class="mb-0 small bg-white border rounded p-2" style="white-space:pre-wrap; font-family:inherit;">{!! $reporteVer->textoHtml() !!}</pre>
                    <pre x-ref="texto" class="d-none">{{ $reporteVer->texto() }}</pre>
                    {{-- Abrir cada ubicación en Google Maps (pestaña nueva) --}}
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach($reporteVer->puntos ?? [] as $n => $p)
                            @php $m = \App\Models\VehiculoGpsReporte::maps($p['link'] ?? null, $p['direccion'] ?? null); @endphp
                            @if($m)
                                <a href="{{ $m }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success">
                                    <i class="ti ti-car"></i> {{ ($p['etiqueta'] ?? '') !== '' ? $p['etiqueta'] : 'Punto '.($n + 1) }} en Maps
                                </a>
                            @endif
                        @endforeach
                        @php $md = \App\Models\VehiculoGpsReporte::maps($reporteVer->domicilio_link, $reporteVer->domicilio_direccion); @endphp
                        @if($md)
                            <a href="{{ $md }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="ti ti-home"></i> Domicilio en Maps</a>
                        @endif
                    </div>
                </div>
                <div class="col-12 col-xl-5">
                    <div class="small fw-semibold mb-1"><i class="ti ti-photo"></i> Fotos ({{ $reporteVer->fotos->count() }})</div>
                    @if($reporteVer->fotos->isNotEmpty())
                        @php $galeriaVer = \Illuminate\Support\Js::from($reporteVer->galeria()); @endphp
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach($reporteVer->fotos as $foto)
                                <div class="position-relative" wire:key="foto-{{ $foto->id }}">
                                    {{-- Clic: galería tipo lightbox empezando en esta foto (← → Esc) --}}
                                    <a href="{{ $foto->url() }}" @click.prevent="openLightbox({{ $galeriaVer }}, {{ $loop->index }})"
                                       title="{{ $foto->original_name }} · ver en grande" style="cursor: zoom-in;">
                                        <img src="{{ $foto->thumbUrl() }}" alt="" class="rounded border" style="width:110px; height:110px; object-fit:cover; background:#fff;">
                                    </a>
                                    {{-- Copia ESTA foto al portapapeles para pegarla en WhatsApp Web (Ctrl+V). --}}
                                    <button type="button" class="btn btn-light border position-absolute shadow-sm" style="bottom:2px; left:2px; padding:0 6px; font-size:10px; line-height:18px;"
                                            x-on:click="copiarFoto({{ $foto->id }}, {{ Js::from($foto->url()) }})"
                                            :class="{ 'btn-success text-white': estado[{{ $foto->id }}] === 'ok', 'btn-danger text-white': estado[{{ $foto->id }}] === 'error' }"
                                            title="Copiar la foto para pegarla en WhatsApp (Ctrl+V)">
                                        <i class="ti ti-copy"></i> <span x-text="estado[{{ $foto->id }}] === 'ok' ? '¡Copiada!' : (estado[{{ $foto->id }}] === 'error' ? 'No se pudo' : 'Copiar')">Copiar</span>
                                    </button>
                                    @if($puedeEditar)
                                        <button type="button" class="btn btn-danger position-absolute" style="top:2px; right:2px; padding:0 6px; font-size:10px; line-height:18px;"
                                                wire:click="eliminarFoto({{ $foto->id }})" @creadoEl($foto) data-confirmar="¿Quitar esta foto del reporte?" title="Quitar foto">
                                            <i class="ti ti-x"></i>
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="small text-muted mb-2" style="line-height:1.35;">
                            <i class="ti ti-brand-whatsapp"></i> <b>En la PC:</b> «Enviar por WhatsApp» → eliges el grupo (el texto queda escrito) → «Copiar» en una foto → Ctrl+V en el chat
                            (el texto pasa a ser el pie) → «Copiar» en la otra → Ctrl+V sobre la vista previa → Enviar. En el celular, «Compartir» lo manda todo de una.
                        </div>
                    @endif
                    @if($puedeEditar)
                        {{-- Arrastrar aquí sube al instante (updatedFotosExtra) --}}
                        <div class="bg-white rounded">
                            @include('livewire.cash.partials.zona-imagenes', ['modelo' => 'fotosExtra', 'quitar' => 'removeFotoExtra'])
                        </div>
                        {{-- O copiar fotos de la pestaña Adjuntos (02/10): se anexan al instante. --}}
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" wire:click="abrirAdjuntos" @disabled($adjuntosCliente->isEmpty())
                                title="{{ $adjuntosCliente->isEmpty() ? 'El cliente no tiene fotos en Adjuntos' : 'Elegir fotos ya guardadas en la pestaña Adjuntos' }}">
                            <i class="ti ti-photo-search"></i> Elegir de Adjuntos ({{ $adjuntosCliente->count() }})
                        </button>
                        <div class="small text-muted mt-1"><span wire:loading wire:target="fotosExtra">Subiendo fotos…</span><span wire:loading.remove wire:target="fotosExtra">Las fotos que arrastres aquí se guardan al instante.</span></div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Modal "Elegir de Adjuntos" (02/10): las fotos de la pestaña Adjuntos del
         cliente, con casilla; se copian al reporte (al guardar, o al instante si el
         reporte ya existe). Las ya anexadas se muestran marcadas y sin casilla. ═══ --}}
    @if($puedeEditar)
        <div class="modal fade" id="gpsAdjuntosModal" tabindex="-1" aria-hidden="true" wire:ignore.self
             x-data="{ modal: null }"
             x-init="modal = bootstrap.Modal.getOrCreateInstance($el);
                     $el.addEventListener('hidden.bs.modal', () => { if ($wire.mostrarAdjuntos) { $wire.cerrarAdjuntos(); } });"
             x-on:adjuntos-modal-open.window="modal.show()"
             x-on:adjuntos-modal-close.window="modal.hide()">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0"><i class="ti ti-photo-search"></i> Elegir fotos de Adjuntos</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        @php $yaAnexados = $this->adjuntosYaAnexados(); @endphp
                        @if($adjuntosCliente->isEmpty())
                            <div class="text-muted small">El cliente no tiene fotos en la pestaña Adjuntos.</div>
                        @else
                            <div class="small text-muted mb-2">
                                Marca las fotos que van en este reporte. Se copian al reporte: borrarlas después de Adjuntos no lo afecta.
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($adjuntosCliente as $att)
                                    @php $anexada = in_array((int) $att->id, $yaAnexados, true); @endphp
                                    <label class="position-relative border rounded p-1 {{ $anexada ? 'opacity-50' : '' }}" style="cursor:{{ $anexada ? 'default' : 'pointer' }};"
                                           wire:key="adj-modal-{{ $att->id }}" title="{{ $att->original_name }}{{ $anexada ? ' · ya anexada' : '' }}">
                                        <img src="{{ $att->thumbUrl() }}" alt="" class="rounded" style="width:120px; height:120px; object-fit:cover; background:#fff; display:block;">
                                        @if($anexada)
                                            <span class="badge bg-success position-absolute" style="top:6px; left:6px; font-size:9px;"><i class="ti ti-check"></i> ya anexada</span>
                                        @else
                                            <input class="form-check-input position-absolute" type="checkbox" value="{{ $att->id }}" wire:model="adjuntosSel"
                                                   style="top:6px; left:6px; width:1.2rem; height:1.2rem;">
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="confirmarAdjuntos" wire:loading.attr="disabled" wire:target="confirmarAdjuntos"
                                @disabled($adjuntosCliente->isEmpty())>
                            <i class="ti ti-check"></i> Anexar seleccionadas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ Tabla de reportes: del último registrado hacia abajo, filtrable por placa ═══ --}}
    @if($placas->count() > 1)
        <div class="d-flex align-items-center gap-2 mb-2 small">
            <label class="mb-0 text-muted" for="filtro-placa"><i class="ti ti-filter"></i> Placa:</label>
            <select id="filtro-placa" class="form-select form-select-sm w-auto" wire:model.live="filtroPlaca">
                <option value="">Todas ({{ $placas->count() }})</option>
                @foreach($placas as $pl)
                    <option value="{{ $pl }}">{{ $pl }}</option>
                @endforeach
            </select>
            @if($filtroPlaca !== '')
                <a href="#" class="text-decoration-none" wire:click.prevent="$set('filtroPlaca', '')">quitar filtro</a>
            @endif
        </div>
    @endif
    @if($reportes->isEmpty())
        <div class="text-muted small fst-italic">
            {{ $filtroPlaca !== '' ? "No hay reportes de la placa {$filtroPlaca}." : 'Aún no hay reportes de GPS para los vehículos de este cliente.' }}
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:12px;">
                <thead class="table-secondary">
                    <tr>
                        <th class="text-center" width="120">Fecha y hora</th>
                        <th class="text-center" width="90">Placa</th>
                        <th>Ubicación del vehículo</th>
                        <th class="text-center" width="120">Fotos</th>
                        <th class="text-center" width="110">Registró</th>
                        <th class="text-center" width="230"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($reportes as $r)
                    <tr wire:key="rep-{{ $r->id }}" class="{{ $verId === $r->id ? 'table-success' : '' }}">
                        <td class="text-center" style="white-space:nowrap;">{{ $r->fecha->format('d/m/Y') }} <span class="text-muted">{{ $r->fecha->format('H:i') }}</span></td>
                        <td class="text-center fw-bold">{{ $r->placa }}</td>
                        <td>
                            {{-- Todos los puntos a la vista, cada uno con su Maps --}}
                            @foreach($r->puntos ?? [] as $n => $p)
                                @php $mp = \App\Models\VehiculoGpsReporte::maps($p['link'] ?? null, $p['direccion'] ?? null); @endphp
                                <div class="d-flex align-items-start gap-1 {{ $n > 0 ? 'mt-1' : '' }}">
                                    @if(($p['etiqueta'] ?? '') !== '' || count($r->puntos) > 1)
                                        <span class="badge bg-secondary flex-shrink-0" title="Punto {{ $n + 1 }}">{{ ($p['etiqueta'] ?? '') !== '' ? $p['etiqueta'] : 'Punto '.($n + 1) }}</span>
                                    @endif
                                    <span class="flex-grow-1">
                                        {{ $p['direccion'] ?? '—' }}
                                        @if(($p['estadia_desde'] ?? '') || ($p['estadia_hasta'] ?? ''))
                                            <span class="text-muted">· {{ \App\Models\VehiculoGpsReporte::rango($p['estadia_desde'] ?? null, $p['estadia_hasta'] ?? null) }}</span>
                                        @endif
                                    </span>
                                    @if($mp)
                                        <a href="{{ $mp }}" target="_blank" rel="noopener" class="text-success flex-shrink-0" title="{{ ($p['link'] ?? '') !== '' ? 'Abrir en Google Maps' : 'Buscar la dirección en Google Maps' }}"><i class="ti ti-map-pin"></i></a>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td class="text-center">
                            {{-- Como en ingresos/egresos (02/10): cámara + contador que abre la galería;
                                 sin fotos, la cámara apagada abre el reporte para subirlas. --}}
                            @if($r->fotos->isNotEmpty())
                                <a href="#" @click.prevent="openLightbox({{ \Illuminate\Support\Js::from($r->galeria()) }}, 0)"
                                   title="Ver fotos ({{ $r->fotos->count() }})" style="cursor: zoom-in;">
                                    <i class="ti ti-camera f-s-16 text-info"></i>
                                    <span class="badge bg-info" style="font-size:9px; padding:1px 4px;">{{ $r->fotos->count() }}</span>
                                </a>
                            @elseif($puedeEditar)
                                <a href="#" wire:click.prevent="ver({{ $r->id }})" title="Sin fotos · abrir el reporte para subirlas">
                                    <i class="ti ti-camera f-s-16 text-muted"></i>
                                </a>
                            @else
                                <i class="ti ti-camera f-s-16 text-muted" title="Sin fotos"></i>
                            @endif
                        </td>
                        <td class="text-center">{{ $r->registradoPor?->username ?? $r->registradoPor?->name ?? '—' }}</td>
                        <td class="text-center" style="white-space:nowrap;">
                            @php $md = \App\Models\VehiculoGpsReporte::maps($r->domicilio_link, $r->domicilio_direccion); @endphp
                            @if($md)
                                <a href="{{ $md }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary py-0" title="Domicilio en Google Maps (pestaña nueva)"><i class="ti ti-home"></i> Domicilio</a>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-success py-0" wire:click="ver({{ $r->id }})" title="Ver el texto, los enlaces y las fotos">
                                <i class="ti ti-message-2"></i> {{ $verId === $r->id ? 'Cerrar' : 'Ver' }}
                            </button>
                            @if($puedeEditar)
                                <button type="button" class="btn btn-sm btn-outline-danger py-0" wire:click="eliminar({{ $r->id }})" @creadoEl($r)
                                        data-confirmar="¿Eliminar el reporte del {{ $r->fecha->format('d/m/Y H:i') }} del vehículo {{ $r->placa }}?">
                                    <i class="ti ti-trash"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Visor de fotos (galería tipo lightbox compartida con caja) --}}
    @include('livewire.cash.partials._lightbox')
</div>
