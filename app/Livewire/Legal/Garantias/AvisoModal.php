<?php

namespace App\Livewire\Legal\Garantias;

use App\Models\Garantia;
use App\Models\SigmAviso;
use App\Services\Legal\CajaLegal;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de registro de avisos SIGM (hijo de Legal\Garantias\Show).
 *
 * Es componente aparte a propósito (mismo patrón que Clients\NotificationsModal):
 * sus interacciones solo re-renderizan el modal y no la ficha completa de la
 * garantía. El padre lo abre con dispatch('abrir-aviso-modal', garantiaId: ...).
 */
class AvisoModal extends Component
{
    public ?int $garantiaId = null;

    /** Rótulo del encabezado: "Cliente — Crédito #N" */
    public string $garantiaLabel = '';

    // ── Formulario ──
    public string $tipo = 'constitucion';

    public ?string $nroFormulario = null;

    public ?string $folio = null;

    public string $fechaPresentacion = '';

    public ?string $vigenciaHasta = null;

    public ?string $modalidadEjecucion = null;

    public ?string $fechaInicioEjecucion = null;

    public ?string $fechaTerminoEjecucion = null;

    public string $nota = '';

    protected function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(SigmAviso::TIPOS))],
            // Formato SIGM sugerido: AAAA-NNNNNN (año + correlativo)
            'nroFormulario' => ['nullable', 'regex:/^\d{4}-\d{1,7}$/', Rule::unique('sigm_avisos', 'nro_formulario')],
            'folio' => ['nullable', 'regex:/^\d{1,25}$/', Rule::unique('sigm_avisos', 'folio')],
            'fechaPresentacion' => ['required', 'date'],
            'vigenciaHasta' => ['nullable', 'date', 'after_or_equal:fechaPresentacion'],
            'modalidadEjecucion' => [
                Rule::requiredIf($this->tipo === 'ejecucion'),
                'nullable',
                Rule::in(array_keys(SigmAviso::MODALIDADES_EJECUCION)),
            ],
            'fechaInicioEjecucion' => ['nullable', 'date'],
            'fechaTerminoEjecucion' => ['nullable', 'date', 'after_or_equal:fechaInicioEjecucion'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'tipo.required' => 'Selecciona el tipo de aviso.',
            'tipo.in' => 'El tipo de aviso no es válido.',
            'nroFormulario.regex' => 'El N° de formulario debe tener el formato AAAA-NNNNNN (ej. 2026-123456).',
            'nroFormulario.unique' => 'Ese N° de formulario ya está registrado en otro aviso.',
            'folio.regex' => 'El folio debe ser numérico (hasta 25 dígitos).',
            'folio.unique' => 'Ese folio ya está registrado en otro aviso.',
            'fechaPresentacion.required' => 'Indica la fecha de presentación del aviso.',
            'fechaPresentacion.date' => 'La fecha de presentación no es válida.',
            'vigenciaHasta.date' => 'La fecha de vigencia no es válida.',
            'vigenciaHasta.after_or_equal' => 'La vigencia no puede ser anterior a la fecha de presentación.',
            'modalidadEjecucion.required' => 'Indica la modalidad de ejecución.',
            'modalidadEjecucion.in' => 'La modalidad de ejecución no es válida.',
            'fechaInicioEjecucion.date' => 'La fecha de inicio de ejecución no es válida.',
            'fechaTerminoEjecucion.date' => 'La fecha de término de ejecución no es válida.',
            'fechaTerminoEjecucion.after_or_equal' => 'El término de la ejecución no puede ser anterior a su inicio.',
            'nota.max' => 'La nota no puede exceder los 2000 caracteres.',
        ];
    }

    #[On('abrir-aviso-modal')]
    public function abrir(int $garantiaId): void
    {
        // El evento es invocable desde el navegador con cualquier id: se
        // vuelve a verificar el permiso del módulo antes de abrir.
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }

        $garantia = Garantia::with(['client', 'credit'])->findOrFail($garantiaId);

        $this->garantiaId = $garantia->id;
        $this->garantiaLabel = trim(
            ($garantia->client?->fullName() ?? 'Garantía #'.$garantia->id)
            .($garantia->credit_id ? " — Crédito #{$garantia->credit_id}" : '')
        );

        $this->limpiarFormulario();
        $this->dispatch('aviso-modal-open');
    }

    public function guardar(): void
    {
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }
        if (! $this->garantiaId) {
            return;
        }

        // Normaliza vacíos a null ANTES de validar (para nullable/unique/regex)
        $this->nroFormulario = trim((string) $this->nroFormulario) ?: null;
        $this->folio = trim((string) $this->folio) ?: null;
        $this->vigenciaHasta = $this->vigenciaHasta ?: null;
        $this->modalidadEjecucion = $this->modalidadEjecucion ?: null;
        $this->fechaInicioEjecucion = $this->fechaInicioEjecucion ?: null;
        $this->fechaTerminoEjecucion = $this->fechaTerminoEjecucion ?: null;

        $this->validate();

        $garantia = Garantia::findOrFail($this->garantiaId);

        // Vigencia por defecto (D. Leg. 1400): constitución/renovación sin
        // fecha explícita = presentación + 5 años.
        $vigencia = $this->vigenciaHasta;
        if (! $vigencia && in_array($this->tipo, ['constitucion', 'renovacion'], true)) {
            $vigencia = Carbon::parse($this->fechaPresentacion)
                ->addYears(SigmAviso::VIGENCIA_ANIOS_DEFAULT)
                ->toDateString();
        }

        // Los campos de ejecución solo aplican a avisos de ese tipo
        $esEjecucion = $this->tipo === 'ejecucion';

        // El aviso y su egreso en la caja legal (caja=4) se graban juntos:
        // si la tasa no puede asentarse, el aviso tampoco queda registrado.
        $aviso = DB::transaction(function () use ($garantia, $vigencia, $esEjecucion): SigmAviso {
            $aviso = SigmAviso::create([
                'garantia_id' => $garantia->id,
                'tipo' => $this->tipo,
                'nro_formulario' => $this->nroFormulario,
                'folio' => $this->folio,
                'fecha_presentacion' => $this->fechaPresentacion,
                'vigencia_hasta' => $vigencia,
                'modalidad_ejecucion' => $esEjecucion ? $this->modalidadEjecucion : null,
                'fecha_inicio_ejecucion' => $esEjecucion ? $this->fechaInicioEjecucion : null,
                'fecha_termino_ejecucion' => $esEjecucion ? $this->fechaTerminoEjecucion : null,
                'tasa' => SigmAviso::TASA_SIGM,
                'estado' => 'registrado',
                'nota' => trim($this->nota) !== '' ? trim($this->nota) : null,
                'registrado_por' => auth()->id(),
            ]);

            // Egreso automático por la tasa registral SIGM en la caja legal.
            if ((float) $aviso->tasa > 0) {
                $tipoLabel = SigmAviso::TIPOS[$aviso->tipo] ?? $aviso->tipo;
                $egreso = CajaLegal::egreso(
                    'Tasa SIGM',
                    (float) $aviso->tasa,
                    "Aviso {$tipoLabel} ".($aviso->nro_formulario ?? 's/n')." — garantía #{$garantia->id}",
                    $this->fechaPresentacion,
                );
                $aviso->update(['expense_id' => $egreso->id]);
            }

            return $aviso;
        });

        // La garantía recalcula su estado y vigencia a partir del historial
        $garantia->sincronizarConAvisos();

        $tipoLabel = SigmAviso::TIPOS[$aviso->tipo] ?? $aviso->tipo;
        Audit::log(
            "Registró aviso SIGM de {$tipoLabel} — formulario ".($aviso->nro_formulario ?? 's/n')
            ." (garantía #{$garantia->id})",
            $aviso,
            ['garantia_id' => $garantia->id, 'tipo' => $aviso->tipo, 'folio' => $aviso->folio, 'expense_id' => $aviso->expense_id]
        );

        $this->dispatch('aviso-registrado');
        $this->dispatch('successAlert', ['message' => 'Aviso SIGM registrado correctamente.']);
        $this->dispatch('aviso-modal-close');
        $this->limpiarFormulario();
    }

    /** Deja el formulario listo para un nuevo registro. */
    private function limpiarFormulario(): void
    {
        $this->tipo = 'constitucion';
        $this->nroFormulario = null;
        $this->folio = null;
        $this->fechaPresentacion = now()->toDateString();
        $this->vigenciaHasta = null;
        $this->modalidadEjecucion = null;
        $this->fechaInicioEjecucion = null;
        $this->fechaTerminoEjecucion = null;
        $this->nota = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.legal.garantias.aviso-modal');
    }
}
