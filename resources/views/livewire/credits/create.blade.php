<div class="container-fluid">
    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules" style="color:red;">NUEVO PRÉSTAMO</h4>
        </div>
        <div class="col-sm-6 mt-sm-2">
            <ul class="breadcrumb breadcrumb-start float-sm-end">
                <li class="d-flex">
                    <i class="ti ti-credit-card f-s-16"></i>
                    <a href="{{ route('credits.index') }}" class="f-s-14 d-flex gap-2">
                        <span class="d-none d-md-block">Créditos</span>
                    </a>
                </li>
                <li class="breadcrumb-item active"><span>Nuevo</span></li>
            </ul>
        </div>
    </div>

    <form wire:submit.prevent="save">

        @if ($errors->any())
            <div class="alert alert-danger py-2 px-3 mb-2" style="font-size:12px;">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row g-2">

                    {{-- ─── Cliente ─── --}}
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">DNI</label>
                        <input type="text" name="codigoc" autocomplete="off" class="form-control form-control-sm @error('codigoc') is-invalid @enderror @error('codigod') is-invalid @enderror"
                               wire:model.live.debounce.500ms="codigoc"
                               style="background-color:yellow;" maxlength="11" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-0 small fw-semibold">Nombre del Cliente</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ $nombreb }}" readonly>
                        @if($dniMsg)
                            <span class="small {{ $dniMsgType === 'ok' ? 'text-success' : 'text-danger' }}"
                                  style="font-size:11px;">
                                <i class="ti {{ $dniMsgType === 'ok' ? 'ti-circle-check' : 'ti-alert-circle' }}"></i>
                                {{ $dniMsg }}
                            </span>
                        @endif
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Código Préstamo</label>
                        <input type="number" name="codpre_" autocomplete="off" class="form-control form-control-sm @error('codpre_') is-invalid @enderror"
                               wire:model.defer="codpre_"
                               style="background-color:yellow;" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Moneda</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="Soles" readonly>
                    </div>

                    {{-- ─── Capital y Periodo ─── --}}
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Capital</label>
                        <input type="number" name="impopres" autocomplete="off" class="form-control form-control-sm @error('impopres') is-invalid @enderror"
                               wire:model.live.debounce.500ms="impopres"
                               style="background-color:yellow;" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Periodo</label>
                        <input type="text" name="selecano" autocomplete="off" class="form-control form-control-sm bg-light" wire:model="selecano" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Mes</label>
                        <select class="form-select form-select-sm bg-light" wire:model="selecmes">
                            @foreach(['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'] as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Tipo</label>
                        <select class="form-select form-select-sm @error('seletipl') is-invalid @enderror"
                                wire:model.live="seletipl"
                                style="background-color:yellow;" required>
                            <option value="">Seleccione</option>
                            <option value="1">Semanal</option>
                            <option value="3">Mensual</option>
                            <option value="4">Diario</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Cuotas</label>
                        <input type="number" name="cuot" autocomplete="off" class="form-control form-control-sm @error('cuot') is-invalid @enderror"
                               wire:model.live.debounce.500ms="cuot"
                               style="background-color:yellow;" min="1" required
                               @if($seletipl === '4') readonly @endif>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Interés</label>
                        <input type="number" name="inte" autocomplete="off" class="form-control form-control-sm @error('inte') is-invalid @enderror"
                               wire:model.live.debounce.500ms="inte"
                               style="background-color:yellow;" min="0" step="0.01" required>
                    </div>

                    {{-- ─── Mora calculada y fechas ─── --}}
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Mora Capital</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($moracc, 2) }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Mora Interés</label>
                        <input type="text" class="form-control form-control-sm bg-light"
                               value="{{ number_format($moraii, 2) }}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Fecha Registro</label>
                        <input type="text" name="fechar" autocomplete="off" class="form-control form-control-sm bg-light dates" wire:model="fechar" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0 small fw-semibold">Fecha Préstamo</label>
                        <input type="text" name="fechad" autocomplete="off" class="form-control form-control-sm bg-light dates" wire:model="fechad" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-0 small fw-semibold">Asesor</label>
                        <select class="form-select form-select-sm @error('nomasesores') is-invalid @enderror"
                                wire:model="nomasesores"
                                style="background-color:yellow;" required>
                            <option value="">Seleccione</option>
                            @foreach($asesores as $a)
                                <option value="{{ $a->username }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="d-flex gap-2 mt-3 justify-content-center">
                    <button type="submit" class="btn btn-sm btn-dark"
                            wire:loading.attr="disabled" wire:target="save">
                        <i class="ti ti-check"></i>
                        <span wire:loading.remove wire:target="save">Aceptar</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                    <a href="{{ route('credits.index') }}" class="btn btn-sm btn-secondary">
                        <i class="ti ti-x"></i> Cancelar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
