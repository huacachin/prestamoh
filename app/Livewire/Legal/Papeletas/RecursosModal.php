<?php

namespace App\Livewire\Legal\Papeletas;

use App\Models\Papeleta;
use App\Models\PapeletaRecurso;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de recursos de una papeleta (hijo de Legal\Papeletas\Index).
 *
 * Lista los recursos presentados (descargo, apelación, prescripción...),
 * permite resolverlos inline (resultado + fecha) y registrar uno nuevo con
 * el plazo legal sugerido (10 días el acceso a la información, 30 el resto
 * — PapeletaRecurso::plazoDias) recalculado en vivo al cambiar tipo o fecha.
 * El padre lo abre con dispatch('abrir-recursos-modal', papeletaId: ...) y
 * escucha 'recurso-guardado' para refrescar contadores.
 */
class RecursosModal extends Component
{
    public ?int $papeletaId = null;

    // ── Cabecera (solo display) ──

    public string $papeletaEntidad = '';

    public string $papeletaNro = '';

    public string $papeletaPlaca = '';

    public ?string $papeletaMonto = null;

    public string $papeletaEstado = '';

    // ── Formulario de nuevo recurso ──

    public string $tipo = 'descargo';

    public string $nro_tramite = '';

    public string $fecha_presentacion = '';

    public string $plazo_vence = '';

    public string $nota = '';

    /** Al agregar a una papeleta 'pendiente': pasarla a EN RECURSO. */
    public bool $pasarEnRecurso = true;

    // ── Resolución inline de un recurso pendiente ──

    /** id del recurso cuya fila muestra el mini-form de resolución. */
    public ?int $resolviendoId = null;

    public string $resolucionResultado = '';

    public string $resolucionFecha = '';

    protected function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(PapeletaRecurso::TIPOS))],
            'nro_tramite' => ['nullable', 'string', 'max:40'],
            'fecha_presentacion' => ['required', 'date', 'before_or_equal:'.now()->toDateString()],
            'plazo_vence' => ['required', 'date', 'after_or_equal:fecha_presentacion'],
            'nota' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'tipo.required' => 'Selecciona el tipo de recurso.',
            'tipo.in' => 'El tipo de recurso no es válido.',
            'nro_tramite.max' => 'El N° de trámite no debe exceder 40 caracteres.',
            'fecha_presentacion.required' => 'Indica la fecha de presentación.',
            'fecha_presentacion.date' => 'La fecha de presentación no es válida.',
            'fecha_presentacion.before_or_equal' => 'La fecha de presentación no puede ser futura.',
            'plazo_vence.required' => 'Indica la fecha en que vence el plazo.',
            'plazo_vence.date' => 'La fecha de vencimiento del plazo no es válida.',
            'plazo_vence.after_or_equal' => 'El plazo no puede vencer antes de la presentación.',
            'nota.max' => 'La nota no puede exceder los 2000 caracteres.',
        ];
    }

    /** Abre el modal con la papeleta y su historial de recursos. */
    #[On('abrir-recursos-modal')]
    public function abrir(int $papeletaId): void
    {
        // El evento es invocable desde el navegador con cualquier id: se
        // vuelve a verificar el permiso del módulo antes de abrir.
        if (! auth()->user()?->can('legal.papeletas')) {
            abort(403);
        }

        $papeleta = Papeleta::with('vehiculo')->findOrFail($papeletaId);

        $this->papeletaId = $papeleta->id;
        $this->cargarCabecera($papeleta);

        $this->limpiarFormulario();
        $this->dispatch('recursos-modal-open');
    }

    /** Sugerencia en vivo: plazo = presentación + plazo legal del tipo. */
    public function updatedTipo(): void
    {
        $this->sugerirPlazo();
    }

    public function updatedFechaPresentacion(): void
    {
        $this->sugerirPlazo();
    }

    /** Registra el recurso (y de paso pasa la papeleta a EN RECURSO si aplica). */
    public function agregarRecurso(): void
    {
        if (! auth()->user()?->can('legal.papeletas')) {
            abort(403);
        }
        if (! $this->papeletaId) {
            return;
        }

        // Normaliza vacíos ANTES de validar (para nullable/max)
        $this->nro_tramite = trim($this->nro_tramite);
        $this->nota = trim($this->nota);

        $this->validate();

        $papeleta = Papeleta::findOrFail($this->papeletaId);

        $recurso = PapeletaRecurso::create([
            'papeleta_id' => $papeleta->id,
            'tipo' => $this->tipo,
            'nro_tramite' => $this->nro_tramite !== '' ? $this->nro_tramite : null,
            'fecha_presentacion' => $this->fecha_presentacion,
            'plazo_vence' => $this->plazo_vence,
            'nota' => $this->nota !== '' ? $this->nota : null,
            'registrado_por' => auth()->id(),
        ]);

        // Una papeleta pendiente con recurso presentado pasa a EN RECURSO
        // (checkbox marcado por defecto; el usuario puede desmarcarlo).
        if ($this->pasarEnRecurso && $papeleta->estado === 'pendiente') {
            $papeleta->update(['estado' => 'en_recurso']);
        }

        $tipoLabel = PapeletaRecurso::TIPOS[$recurso->tipo] ?? $recurso->tipo;
        $entidadLabel = Papeleta::ENTIDADES[$papeleta->entidad] ?? $papeleta->entidad;
        Audit::log(
            "Registró recurso {$tipoLabel} de la papeleta {$entidadLabel} {$papeleta->nro_papeleta}",
            $recurso,
            [
                'papeleta_id' => $papeleta->id,
                'plazo_vence' => $recurso->plazo_vence?->toDateString(),
                'estado_papeleta' => $papeleta->estado,
            ],
        );

        $this->cargarCabecera($papeleta);
        $this->limpiarFormulario();

        $this->dispatch('recurso-guardado');
        $this->dispatch('successAlert', ['message' => 'Recurso registrado correctamente.']);
    }

    /** Muestra el mini-form de resolución en la fila del recurso. */
    public function resolver(int $recursoId): void
    {
        if (! auth()->user()?->can('legal.papeletas')) {
            abort(403);
        }

        $this->resolviendoId = $recursoId;
        $this->resolucionResultado = '';
        $this->resolucionFecha = now()->toDateString();
        $this->resetErrorBag(['resolucionResultado', 'resolucionFecha']);
    }

    /** Oculta el mini-form de resolución sin guardar. */
    public function cancelarResolucion(): void
    {
        $this->resolviendoId = null;
        $this->resolucionResultado = '';
        $this->resetErrorBag(['resolucionResultado', 'resolucionFecha']);
    }

    /** Marca el resultado del recurso pendiente (mini-form inline). */
    public function guardarResolucion(): void
    {
        if (! auth()->user()?->can('legal.papeletas')) {
            abort(403);
        }
        if (! $this->resolviendoId) {
            return;
        }

        // 'pendiente' no es un desenlace: solo se aceptan resultados finales.
        $finales = array_diff(array_keys(PapeletaRecurso::RESULTADOS), ['pendiente']);
        $this->validate(
            [
                'resolucionResultado' => ['required', Rule::in($finales)],
                'resolucionFecha' => ['required', 'date', 'before_or_equal:'.now()->toDateString()],
            ],
            [
                'resolucionResultado.required' => 'Selecciona el resultado del recurso.',
                'resolucionResultado.in' => 'El resultado no es válido.',
                'resolucionFecha.required' => 'Indica la fecha de resolución.',
                'resolucionFecha.date' => 'La fecha de resolución no es válida.',
                'resolucionFecha.before_or_equal' => 'La fecha de resolución no puede ser futura.',
            ],
        );

        $recurso = PapeletaRecurso::with('papeleta')->findOrFail($this->resolviendoId);

        if ($recurso->papeleta_id !== $this->papeletaId || $recurso->resultado !== 'pendiente') {
            $this->dispatch('errorAlert', ['message' => 'El recurso ya no está pendiente.']);
            $this->cancelarResolucion();

            return;
        }

        $recurso->update([
            'resultado' => $this->resolucionResultado,
            'resuelto_at' => $this->resolucionFecha,
        ]);

        $tipoLabel = PapeletaRecurso::TIPOS[$recurso->tipo] ?? $recurso->tipo;
        $resultadoLabel = PapeletaRecurso::RESULTADOS[$this->resolucionResultado] ?? $this->resolucionResultado;
        $papeleta = $recurso->papeleta;
        $entidadLabel = Papeleta::ENTIDADES[$papeleta->entidad] ?? $papeleta->entidad;
        Audit::log(
            "Marcó {$resultadoLabel} el recurso {$tipoLabel} de la papeleta {$entidadLabel} {$papeleta->nro_papeleta}",
            $recurso,
            ['papeleta_id' => $papeleta->id, 'resuelto_at' => $this->resolucionFecha],
        );

        $this->cancelarResolucion();
        $this->dispatch('recurso-guardado');
        $this->dispatch('successAlert', ['message' => "Recurso marcado como «{$resultadoLabel}»."]);
    }

    /** Copia los datos de cabecera de la papeleta (solo display). */
    private function cargarCabecera(Papeleta $papeleta): void
    {
        $this->papeletaEntidad = Papeleta::ENTIDADES[$papeleta->entidad] ?? $papeleta->entidad;
        $this->papeletaNro = $papeleta->nro_papeleta;
        $this->papeletaPlaca = $papeleta->vehiculo?->descripcion() ?? '';
        $this->papeletaMonto = $papeleta->monto !== null ? number_format((float) $papeleta->monto, 2) : null;
        $this->papeletaEstado = $papeleta->estado;
    }

    /** Deja el formulario de nuevo recurso listo para otro registro. */
    private function limpiarFormulario(): void
    {
        $this->tipo = 'descargo';
        $this->nro_tramite = '';
        $this->fecha_presentacion = now()->toDateString();
        $this->nota = '';
        $this->pasarEnRecurso = true;
        $this->sugerirPlazo();
        $this->cancelarResolucion();
        $this->resetErrorBag();
    }

    /** plazo_vence sugerido: presentación + plazoDias(tipo) (editable). */
    private function sugerirPlazo(): void
    {
        try {
            $base = $this->fecha_presentacion !== ''
                ? Carbon::parse($this->fecha_presentacion)
                : now();
        } catch (\Throwable) {
            return; // fecha a medio escribir: no pisar el plazo
        }

        $this->plazo_vence = $base->copy()
            ->addDays(PapeletaRecurso::plazoDias($this->tipo))
            ->toDateString();
    }

    public function render()
    {
        $recursos = $this->papeletaId
            ? PapeletaRecurso::where('papeleta_id', $this->papeletaId)
                ->orderByDesc('fecha_presentacion')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('livewire.legal.papeletas.recursos-modal', compact('recursos'));
    }
}
