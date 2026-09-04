<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">EGRESO GENERALES : NUEVO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="{{ route('cash.expenses') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Egresos</span>
                    </a>
                </li>
                <li class="d-flex active"><span class="f-s-14">Nuevo</span></li>
            </ul>
        </div>
    </div>

    <div class="row table-section">
        <div class="col-xl-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger py-2 px-3 mb-2" style="font-size:12px;">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </div>
                    @endif

                    @unless($canEditDate)
                        <div class="alert alert-info py-1 px-2 mb-2" style="font-size:11px;">
                            <i class="ti ti-info-circle"></i>
                            La fecha queda fija en hoy. Solo SuperUsuario / Administrador / Director pueden modificarla.
                        </div>
                    @endunless

                    @unless($canChooseOtros)
                        <div class="alert alert-info py-1 px-2 mb-2" style="font-size:11px;">
                            <i class="ti ti-info-circle"></i>
                            Como Asesor / Cobranza solo puedes registrar egresos tipo <strong>Fijos</strong> con motivo <strong>Diario</strong>.
                        </div>
                    @endunless

                    {{-- Paso 1: selector --}}
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label mb-0 small fw-semibold">Tipo de Egreso</label>
                            <select class="form-select form-select-sm @error('modo') is-invalid @enderror"
                                    wire:model.live="modo"
                                    {{ $canChooseOtros ? '' : 'disabled' }}>
                                <option value="">— Seleccione —</option>
                                <option value="Fijos">Fijos</option>
                                @if($canChooseOtros)
                                    <option value="Otros">Otros</option>
                                @endif
                            </select>
                        </div>
                    </div>

                    {{-- Paso 2: form principal --}}
                    @if($modo)
                        <hr class="my-2" style="border-color:#e8e2d5;">

                        <div class="row g-2">
                            <div class="col-md-1">
                                <label class="form-label mb-0 small fw-semibold">Fecha (*)</label>
                                <input type="text" autocomplete="off"
                                       class="form-control form-control-sm dates @error('date') is-invalid @enderror @unless($canEditDate) bg-light @endunless"
                                       wire:model.defer="date"
                                       @unless($canEditDate) readonly @endunless>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">A (*)</label>
                                @if($modo === 'Fijos')
                                    <select class="form-select form-select-sm @error('reason') is-invalid @enderror"
                                            wire:model.live="reason"
                                            {{ !$canChooseOtros ? 'disabled' : '' }}>
                                        <option value="">— Seleccione —</option>
                                        @foreach($concepts as $c)
                                            <option value="{{ $c->name }}">{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" name="reason" autocomplete="on"
                                           class="form-control form-control-sm @error('reason') is-invalid @enderror"
                                           wire:model.defer="reason"
                                           placeholder="Motivo libre (proveedor, ej. 'Recarga teléfono')"
                                           maxlength="255">
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label class="form-label mb-0 small fw-semibold">Detalle (*)</label>
                                <input type="text" name="detail" autocomplete="on"
                                       class="form-control form-control-sm @error('detail') is-invalid @enderror"
                                       wire:model.defer="detail"
                                       placeholder="Descripción del egreso"
                                       maxlength="500">
                            </div>

                            <div class="col-md-1">
                                <label class="form-label mb-0 small fw-semibold">Monto (*)</label>
                                <input type="number" step="0.01" min="0.01" name="total" autocomplete="off"
                                       class="form-control form-control-sm @error('total') is-invalid @enderror"
                                       wire:model.defer="total"
                                       placeholder="0.00"
                                       style="background:yellow;">
                            </div>

                            {{-- Campos exclusivos del legacy de gastos --}}
                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">T.Comp.</label>
                                <input type="text" name="document_type" autocomplete="on"
                                       class="form-control form-control-sm @error('document_type') is-invalid @enderror"
                                       wire:model.defer="document_type"
                                       placeholder="Boleta, Factura, Recibo, etc."
                                       maxlength="100">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label mb-0 small fw-semibold">Respons.</label>
                                <input type="text" name="in_charge" autocomplete="name"
                                       class="form-control form-control-sm @error('in_charge') is-invalid @enderror"
                                       wire:model.defer="in_charge"
                                       placeholder="Nombre del responsable"
                                       maxlength="255">
                            </div>
                            {{-- Adjuntos (imágenes) — mismo paso --}}
                            <div class="col-12 mt-2">
                                <label class="form-label mb-0 small fw-semibold">
                                    Adjuntos (imágenes) <span class="text-muted" style="font-weight:400;">— opcional, podés arrastrar varias · JPG/PNG/GIF/WebP · máx 10 MB c/u</span>
                                </label>

                                <div x-data="{
                                        drag: false,
                                        uploading: false,
                                        openPicker() { if (this.uploading) return; this.$refs.fileInput.click(); },
                                        onDrop(e) {
                                            this.drag = false;
                                            if (this.uploading) return;
                                            const incoming = Array.from(e.dataTransfer.files || []);
                                            if (!incoming.length) return;
                                            const valid = incoming.filter(f => f.type.startsWith('image/'));
                                            const skipped = incoming.length - valid.length;
                                            if (!valid.length) { avisar('Solo se aceptan imágenes (JPG, PNG, GIF, WebP).'); return; }
                                            const dt = new DataTransfer();
                                            for (const f of valid) dt.items.add(f);
                                            this.$refs.fileInput.files = dt.files;
                                            this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                            if (skipped > 0) avisar(skipped + ' archivo(s) ignorados (no son imágenes).');
                                        }
                                     }"
                                     x-init="
                                        Livewire.hook('commit.prepare', () => uploading = true);
                                        Livewire.hook('commit', ({ succeed, fail }) => { const done = () => uploading = false; succeed(done); fail(done); });
                                     "
                                     @dragover.prevent="drag = true"
                                     @dragenter.prevent="drag = true"
                                     @dragleave.prevent="drag = false"
                                     @drop.prevent="onDrop($event)"
                                     :class="drag ? 'huac-drop--active' : ''"
                                     class="huac-drop mt-1"
                                     @click="openPicker()">

                                    <input type="file" class="d-none" x-ref="fileInput" multiple
                                           wire:model="files"
                                           accept="image/jpeg,image/png,image/gif,image/webp">

                                    @if(empty($files))
                                        <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                            <i class="ti ti-cloud-upload" style="font-size:22px; color:#9aa0aa;"></i>
                                            <span class="fw-semibold small">Arrastrá imágenes aquí</span>
                                            <span class="text-muted small">
                                                o <span class="text-primary text-decoration-underline">click para seleccionar</span>
                                            </span>
                                        </div>
                                    @else
                                        <div>
                                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                                <span class="small fw-semibold text-success">
                                                    <i class="ti ti-circle-check"></i>
                                                    {{ count($files) }} {{ count($files) === 1 ? 'imagen lista' : 'imágenes listas' }}
                                                </span>
                                                <span class="small text-muted">Soltá más imágenes para añadirlas.</span>
                                            </div>
                                            <div class="row g-2" @click.stop>
                                                @foreach($files as $i => $f)
                                                    <div class="col-6 col-sm-3 col-md-2">
                                                        <div class="position-relative border rounded p-1 bg-white">
                                                            @php
                                                                $tmpUrl = null;
                                                                try { $tmpUrl = $f?->temporaryUrl(); } catch (\Throwable $e) {}
                                                            @endphp
                                                            @if($tmpUrl)
                                                                <img src="{{ $tmpUrl }}" alt="Preview" class="w-100 rounded"
                                                                     style="height:90px; object-fit:contain; background:#fff;">
                                                            @else
                                                                <div class="d-flex align-items-center justify-content-center small text-muted bg-light rounded"
                                                                     style="height:90px;"><i class="ti ti-photo"></i></div>
                                                            @endif
                                                            <button type="button" class="btn btn-danger position-absolute"
                                                                    style="top:2px; right:2px; padding:0 6px; font-size:10px; line-height:18px;"
                                                                    wire:click="removeFile({{ $i }})" title="Quitar">
                                                                <i class="ti ti-x"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div wire:loading wire:target="files" class="text-muted small mt-1">
                                    <i class="ti ti-loader"></i> Cargando imágenes…
                                </div>
                                @error('files.*') <div class="text-danger small mt-1"><i class="ti ti-alert-circle"></i> {{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Botones + hint discreto, todo centrado --}}
                        <div class="d-flex flex-column align-items-center gap-1 mt-3">
                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                <button type="button" class="btn btn-sm btn-dark"
                                        wire:click="save"
                                        wire:loading.attr="disabled" wire:target="save,files">
                                    <i class="ti ti-device-floppy"></i>
                                    <span wire:loading.remove wire:target="save">Guardar</span>
                                    <span wire:loading wire:target="save">Guardando…</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" wire:click="clear">
                                    <i class="ti ti-eraser"></i> Limpiar
                                </button>
                                <a href="{{ route('cash.expenses') }}" class="btn btn-sm btn-danger">
                                    <i class="ti ti-arrow-back"></i> Regresar
                                </a>
                            </div>
                            <small class="text-muted" style="font-size:11px;">
                                <i class="ti ti-info-circle"></i>
                                El egreso y sus imágenes se guardan en un solo paso.
                            </small>
                        </div>
                    @else
                        <div class="text-center text-muted py-4">
                            <i class="ti ti-arrow-up" style="font-size:24px; opacity:.5;"></i>
                            <p class="mt-2 mb-0 small">Seleccione el Tipo de Egreso para continuar.</p>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    <style>
        .huac-drop {
            border: 2px dashed #cfd5e0; border-radius: 10px;
            padding: 12px 16px; background: #fafbfc;
            cursor: pointer; transition: all .15s ease;
        }
        .huac-drop:hover { border-color: #6c7a91; background: #f4f6f9; }
        .huac-drop--active {
            border-color: #009BDC !important;
            background: #e8f5fc !important;
            transform: scale(1.005);
        }
    </style>

</div>
