<?php

namespace App\Livewire\Legal\Caja;

use App\Services\Legal\CajaLegal;
use App\Support\Audit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de asiento MANUAL de la Caja Legal (hijo de Legal\Caja\Index):
 * tarifas cobradas por trámites del área e ingresos/gastos sueltos que no
 * nacen de un documento (esos se generan solos desde SIGM/notaría con su
 * expense_id de gancho).
 *
 * El padre lo abre con dispatch('abrir-movimiento-caja'); al guardar delega
 * en el servicio CajaLegal (que fija caja=4 SIEMPRE: el default de la columna
 * es 1 y un create() suelto caería en la caja operativa) y emite
 * 'movimiento-guardado' para que el tablero se refresque.
 */
class MovimientoModal extends Component
{
    /** Motivos frecuentes del área (datalist: el campo sigue siendo libre). */
    public const MOTIVOS_SUGERIDOS = [
        'Tarifa constitución SIGM',
        'Tarifa cancelación SIGM',
        'Tarifa trámite ATU',
        'Tarifa trámite SAT',
        'Redacción de contrato',
        'Diligencia',
        'Otro',
    ];

    public string $tipo = 'ingreso';   // 'ingreso' | 'egreso'

    public string $fecha = '';

    public string $motivo = '';

    public string $detalle = '';

    public $monto = '';

    protected function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['ingreso', 'egreso'])],
            'fecha' => ['required', 'date', 'before_or_equal:'.now()->toDateString()],
            'motivo' => ['required', 'string', 'max:255'],
            'detalle' => ['nullable', 'string', 'max:500'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
        ];
    }

    protected function messages(): array
    {
        return [
            'tipo.required' => 'Indica si es un ingreso o un egreso.',
            'tipo.in' => 'El tipo de movimiento no es válido.',
            'fecha.required' => 'Indica la fecha del movimiento.',
            'fecha.date' => 'La fecha no es válida.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
            'motivo.required' => 'Indica el motivo del movimiento.',
            'motivo.max' => 'El motivo no debe exceder 255 caracteres.',
            'detalle.max' => 'El detalle no debe exceder 500 caracteres.',
            'monto.required' => 'Ingresa el monto.',
            'monto.numeric' => 'El monto debe ser numérico.',
            'monto.gt' => 'El monto debe ser mayor a 0.',
            'monto.max' => 'El monto excede el máximo permitido.',
        ];
    }

    /** Deja el formulario como un alta en blanco (fecha = hoy). */
    private function limpiarFormulario(): void
    {
        $this->reset(['tipo', 'fecha', 'motivo', 'detalle', 'monto']);
        $this->tipo = 'ingreso';
        $this->fecha = now()->toDateString();
        $this->resetErrorBag();
    }

    /** El tablero pide registrar un movimiento manual. */
    #[On('abrir-movimiento-caja')]
    public function abrir(): void
    {
        if (! auth()->user()?->can('legal.caja')) {
            abort(403);
        }

        $this->limpiarFormulario();
        $this->dispatch('movimiento-modal-open');
    }

    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.caja')) {
            abort(403);
        }

        $this->motivo = trim($this->motivo);
        $this->detalle = trim($this->detalle);

        $this->validate();

        $monto = round((float) $this->monto, 2);

        try {
            // CajaLegal fija caja=4, user y sede; NUNCA un create() directo aquí.
            $movimiento = $this->tipo === 'ingreso'
                ? CajaLegal::ingreso($this->motivo, $monto, $this->detalle, $this->fecha)
                : CajaLegal::egreso($this->motivo, $monto, $this->detalle, $this->fecha);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('errorAlert', ['message' => 'No se pudo registrar el movimiento: '.$e->getMessage()]);

            return;
        }

        Audit::log(
            'Registró '.$this->tipo.' de la Caja Legal: '.$this->motivo.' S/ '.number_format($monto, 2),
            $movimiento,
            ['tipo' => $this->tipo, 'fecha' => $this->fecha, 'monto' => $monto],
        );

        $etiqueta = $this->tipo === 'ingreso' ? 'Ingreso' : 'Egreso';
        $this->dispatch('successAlert', ['message' => "{$etiqueta} de la Caja Legal registrado: S/ ".number_format($monto, 2).'.']);
        $this->dispatch('movimiento-guardado');
        $this->dispatch('movimiento-modal-close');
        $this->limpiarFormulario();
    }

    public function render()
    {
        return view('livewire.legal.caja.movimiento-modal');
    }
}
