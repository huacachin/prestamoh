{{-- Zona de imágenes (arrastrar o elegir, con miniaturas) compartida por la
     galería de adjuntos y por editar ingreso (26/09). Espera $files en el
     componente (wire:model="files") y un método removeFile($i). --}}
                    <div x-data="{
                            drag: false,
                            uploading: false,
                            openPicker() {
                                if (this.uploading) return;
                                this.$refs.fileInput.click();
                            },
                            onDrop(e) {
                                this.drag = false;
                                if (this.uploading) return;
                                const incoming = Array.from(e.dataTransfer.files || []);
                                if (!incoming.length) return;
                                const valid = incoming.filter(f => f.type.startsWith('image/'));
                                const skipped = incoming.length - valid.length;
                                if (!valid.length) {
                                    avisar('Solo se aceptan imágenes (JPG, PNG, GIF, WebP).');
                                    return;
                                }
                                const dt = new DataTransfer();
                                for (const f of valid) dt.items.add(f);
                                this.$refs.fileInput.files = dt.files;
                                this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                if (skipped > 0) avisar(skipped + ' archivo(s) ignorados (no son imágenes).');
                            }
                         }"
                         x-init="
                            Livewire.hook('commit.prepare', () => uploading = true);
                            Livewire.hook('commit', ({ succeed, fail }) => {
                                const done = () => uploading = false;
                                succeed(done); fail(done);
                            });
                         "
                         @dragover.prevent="drag = true"
                         @dragenter.prevent="drag = true"
                         @dragleave.prevent="drag = false"
                         @drop.prevent="onDrop($event)"
                         :class="drag ? 'huac-drop--active' : ''"
                         class="huac-drop"
                         @click="openPicker()">

                        <input type="file" class="d-none" x-ref="fileInput" multiple
                               wire:model="files"
                               accept="image/jpeg,image/png,image/gif,image/webp">

                        @if(empty($files))
                            <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                <i class="ti ti-cloud-upload" style="font-size:22px; color:#9aa0aa;"></i>
                                <span class="fw-semibold small">Arrastra imágenes aquí</span>
                                <span class="text-muted small">
                                    o <span class="text-primary text-decoration-underline">click para seleccionar</span> · JPG/PNG/GIF/WebP · máx. 10 MB c/u
                                </span>
                            </div>
                        @endif

                        @if(!empty($files))
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <span class="small fw-semibold text-success">
                                        <i class="ti ti-circle-check"></i>
                                        {{ count($files) }} {{ count($files) === 1 ? 'imagen lista' : 'imágenes listas' }}
                                    </span>
                                    <span class="small text-muted">
                                        Suelta más imágenes para añadirlas — o haz clic fuera de las miniaturas para seleccionar.
                                    </span>
                                </div>
                                <div class="row g-2" @click.stop>
                                    @foreach($files as $i => $f)
                                        <div class="col-12 col-xl-6">{{-- 26/09: vista previa al 75% del tamaño real --}}
                                            <div class="position-relative border rounded p-1 bg-white">
                                                @php
                                                    $tmpUrl = null;
                                                    try { $tmpUrl = $f?->temporaryUrl(); } catch (\Throwable $e) {}
                                                @endphp
                                                @if($tmpUrl)
                                                    <img src="{{ $tmpUrl }}" alt="Preview"
                                                         class="rounded"
                                                         style="width:auto; max-width:75%; height:auto; max-height:52vh; display:block; margin:0 auto; background:#fff;">
                                                @else
                                                    <div class="d-flex align-items-center justify-content-center small text-muted bg-light rounded"
                                                         style="height:160px;">
                                                        <i class="ti ti-photo"></i>
                                                    </div>
                                                @endif
                                                <button type="button"
                                                        class="btn btn-danger position-absolute"
                                                        style="top:2px; right:2px; padding:0 6px; font-size:10px; line-height:18px;"
                                                        wire:click="removeFile({{ $i }})"
                                                        title="Quitar">
                                                    <i class="ti ti-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @error('files')   <div class="text-danger small mt-2"><i class="ti ti-alert-circle"></i> {{ $message }}</div> @enderror
                    @error('files.*') <div class="text-danger small mt-2"><i class="ti ti-alert-circle"></i> {{ $message }}</div> @enderror
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
