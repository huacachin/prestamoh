{{-- =========================================================
     DASHBOARD · Corazón del sistema
     Capital prestado + interés y mora cobrados, por mes o día.
     Definiciones en App\Livewire\Dashboard\Index (confirmadas
     con el negocio — no cambiarlas sin revisar allí).
     ========================================================= --}}
<div class="container-fluid">

    <div class="row">
        <div class="col-sm-6">
            <h4 class="main-title title-modules">PANEL DE CONTROL</h4>
        </div>
    </div>

    @php
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                  7=>'Julio',8=>'Agosto',9=>'Setiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $fmt = fn ($n) => number_format((float) $n, 2);
    @endphp

    {{-- ── Filtros ─────────────────────────────────────────── --}}
    <div class="card shadow-sm">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Año</label>
                    <select class="form-select form-select-sm" wire:model.live="year">
                        @foreach($anios as $a)
                            <option value="{{ $a }}">{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Mes</label>
                    <select class="form-select form-select-sm" wire:model.live="month">
                        @foreach($meses as $num => $nombre)
                            <option value="{{ $num }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label mb-0 small fw-semibold">Día</label>
                    <select class="form-select form-select-sm" wire:model.live="day">
                        <option value="">Todo el mes</option>
                        @for($d = 1; $d <= $diasDelMes; $d++)
                            <option value="{{ $d }}">{{ $d }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <span class="badge bg-dark f-s-13 px-3 py-2">
                        <i class="ti ti-calendar"></i> {{ $etiqueta }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CAPITAL PRESTADO (protagonista) ─────────────────── --}}
    <div class="card shadow-sm mt-3" wire:loading.class="opacity-50">
        <div class="card-body text-center py-4">
            <div class="text-uppercase fw-semibold text-muted" style="letter-spacing: 2px;">
                Capital prestado {{ $esDia ? 'en el día' : 'en el mes' }}
            </div>
            <div class="fw-bold my-1" style="font-size: clamp(2.2rem, 6vw, 4rem); line-height: 1.1; color: #1d4ed8;">
                S/ {{ $fmt($capitalPrestado) }}
            </div>
            <div class="text-muted">
                {{ $nCreditos }} {{ $nCreditos === 1 ? 'crédito activado' : 'créditos activados' }}
                <span class="mx-1">·</span>
                <span title="Créditos activados en el período (no incluye refinanciamientos)">
                    <i class="ti ti-info-circle"></i> no incluye refinanciados
                </span>
            </div>
        </div>
    </div>

    {{-- ── Interés y Mora cobrados ─────────────────────────── --}}
    <div class="row g-3 mt-0">
        <div class="col-12 col-md-6">
            <div class="card shadow-sm h-100" wire:loading.class="opacity-50">
                <div class="card-body text-center py-3" style="border-top: 4px solid #16a34a; border-radius: inherit;">
                    <div class="text-uppercase fw-semibold text-muted small" style="letter-spacing: 1.5px;">
                        Interés cobrado
                    </div>
                    <div class="fw-bold my-1" style="font-size: clamp(1.5rem, 4vw, 2.4rem); color: #16a34a;">
                        S/ {{ $fmt($interesCobrado) }}
                    </div>
                    <div class="text-muted small">{{ $nInteres }} {{ $nInteres === 1 ? 'pago' : 'pagos' }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow-sm h-100" wire:loading.class="opacity-50">
                <div class="card-body text-center py-3" style="border-top: 4px solid #dc2626; border-radius: inherit;">
                    <div class="text-uppercase fw-semibold text-muted small" style="letter-spacing: 1.5px;">
                        Mora cobrada
                    </div>
                    <div class="fw-bold my-1" style="font-size: clamp(1.5rem, 4vw, 2.4rem); color: #dc2626;">
                        S/ {{ $fmt($moraCobrada) }}
                    </div>
                    <div class="text-muted small">{{ $nMora }} {{ $nMora === 1 ? 'pago' : 'pagos' }}</div>
                </div>
            </div>
        </div>
    </div>

</div>
