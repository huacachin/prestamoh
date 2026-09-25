<div class="{{ $embedded ? '' : 'container-fluid' }}">
    @unless($embedded)
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">ADJUNTOS DEL INGRESO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-home-dollar f-s-16"></i>
                    <a href="{{ route('cash.incomes') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Ingresos</span>
                    </a>
                </li>
                <li class="d-flex active">
                    <span class="f-s-14">Adjuntos #{{ $income->id }}</span>
                </li>
            </ul>
        </div>
    </div>
    @endunless

    <div class="card shadow-sm">
        <div class="card-body">

            @if($embedded)
                <h6 class="mb-3"><i class="ti ti-photo"></i> Adjuntos</h6>
            @else
            {{-- Header con datos del ingreso --}}
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h6 class="mb-0" style="color:red;">
                        Ingreso #{{ $income->id }} · {{ $income->reason }}
                    </h6>
                    <small class="text-muted">
                        {{ $income->date?->format('d/m/Y') }} · S/ {{ number_format($income->total, 2) }}
                        @if($income->modo) · <span class="badge bg-secondary">{{ $income->modo }}</span> @endif
                    </small>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('cash.incomes') }}" class="btn btn-sm btn-secondary">
                        <i class="ti ti-arrow-back"></i> Regresar a ingresos
                    </a>
                </div>
            </div>
            @endif

            @unless($puedeEditar)
                <div class="alert alert-info py-1 px-2 mb-2" style="font-size:11px;">
                    <i class="ti ti-info-circle"></i>
                    Solo puedes ver los adjuntos. No tienes permiso para subir o eliminar en este ingreso.
                </div>
            @endunless

            {{-- Embebida en editar ingreso (26/09) no sube nada: ahí las imágenes
                 se eligen en el formulario y se suben con "Guardar cambios". --}}
            @if($puedeEditar && ! $embedded)
                {{-- Form de upload con drag & drop (múltiples archivos) --}}
                <form wire:submit.prevent="save" class="mb-3">
                    @include('livewire.cash.partials.zona-imagenes')

                    @if(!empty($files))
                        <div class="d-flex gap-2 justify-content-center mt-3">
                            <button type="submit" class="btn btn-sm btn-success"
                                    wire:loading.attr="disabled"
                                    wire:target="save,files,removeFile">
                                <i class="ti ti-upload"></i>
                                <span wire:loading.remove wire:target="save">
                                    Subir {{ count($files) }} {{ count($files) === 1 ? 'archivo' : 'archivos' }}
                                </span>
                                <span wire:loading wire:target="save">Subiendo…</span>
                            </button>
                        </div>
                    @endif
                </form>


                <hr class="my-2" style="border-color:#e8e2d5;">
            @endif

            {{-- Galería con lightbox --}}
            @if($attachments->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti ti-photo-off" style="font-size:48px; opacity:.4;"></i>
                    <p class="mt-2 mb-0">No hay adjuntos para este ingreso.</p>
                    @if($puedeEditar)
                        <small>{{ $embedded ? 'Elige las imágenes en el formulario de arriba y pulsa "Guardar cambios".' : 'Sube la primera imagen usando el formulario de arriba.' }}</small>
                    @endif
                </div>
            @else
                @php
                    $lightboxItems = $attachments->map(fn ($a) => [
                        'url'  => $a->url(),
                        'name' => $a->original_name,
                    ])->values();
                @endphp

                <div x-data="{
                        open: false,
                        idx: 0,
                        items: {{ $lightboxItems->toJson() }},
                        show(i) { this.idx = i; this.open = true; },
                        close() { this.open = false; },
                        next()  { this.idx = (this.idx + 1) % this.items.length; },
                        prev()  { this.idx = (this.idx - 1 + this.items.length) % this.items.length; },
                     }"
                     @keydown.escape.window="open && close()"
                     @keydown.arrow-right.window="open && next()"
                     @keydown.arrow-left.window="open && prev()">

                    <div class="row g-2">
                        @foreach($attachments as $i => $att)
                            <div class="col-6 col-sm-4 col-md-4 col-lg-4">
                                <div class="border rounded p-1 h-100 d-flex flex-column"
                                     style="background:#fafafa;">
                                    <button type="button"
                                            class="border-0 bg-transparent p-0"
                                            @click="show({{ $i }})"
                                            title="{{ $att->original_name }}"
                                            style="cursor: zoom-in;">
                                        <img src="{{ $att->thumbUrl() }}" alt=""
                                             class="w-100 rounded"
                                             style="height:280px; object-fit:contain; background:#fff;">
                                    </button>
                                    <div class="d-flex justify-content-between align-items-center mt-1"
                                         style="font-size:10px;">
                                        <span class="text-muted text-truncate" title="{{ $att->original_name }}">
                                            {{ \Illuminate\Support\Str::limit($att->original_name, 18) }}
                                        </span>
                                        @if($puedeEliminar)
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0"
                                                    wire:click="questionDelete({{ $att->id }})"
                                                    title="Eliminar">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Lightbox compartido --}}
                    @include('livewire.cash.partials._lightbox')
                </div>
            @endif
        </div>
    </div>
</div>
