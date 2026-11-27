<?php

namespace App\Livewire\Cash;

use App\Livewire\Cash\Concerns\ConMotivosDeIngreso;
use App\Livewire\Cash\Concerns\SavesIncomeAttachments;
use App\Models\Concept;
use App\Models\Income;
use App\Support\Audit;
use App\Support\Auditoria\ConCapturaDeAuditoria;
use App\Support\ConReglasDeEliminacion;
use App\Support\ConSubidaDeArchivos;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class EditIncome extends Component
{
    use ConCapturaDeAuditoria;
    use ConMotivosDeIngreso;
    use ConReglasDeEliminacion;
    use ConSubidaDeArchivos;
    use SavesIncomeAttachments;

    public Income $income;

    #[Locked]
    public int $incomeId;

    public string $date = '';

    public string $reason = '';

    public string $detail = '';

    public string $total = '';

    /**
     * 26/09: imágenes del comprobante elegidas en el formulario (varias, como
     * en CreateIncome); se suben como adjuntos al pulsar "Guardar cambios".
     * Reemplaza al campo suelto `image` (image_path) y al botón "Subir"
     * aparte de la galería.
     */
    public array $files = [];

    /** Imagen antigua (image_path del legacy), solo se muestra. */
    public ?string $current_image = null;

    public bool $canEditDate = false;

    /** Solo para pintar el campo "A" bloqueado; al guardar decide el permiso (puedeCambiarMotivo). */
    public bool $canChangeReason = false;

    public function mount(int $id): void
    {
        $this->income = Income::findOrFail($id);

        // Los asientos del Área Legal (caja=4) se gestionan desde su documento de
        // origen (aviso SIGM / trámite notarial), nunca desde las pantallas de Caja.
        abort_if((int) $this->income->caja === 4, 403, 'Movimiento de la Caja Legal: se gestiona desde el Área Legal.');
        $this->incomeId = $id;

        $this->autorizar();
        $this->canEditDate = auth()->user()?->can('caja.bypass-fecha-anterior') ?? false;
        $this->canChangeReason = $this->puedeCambiarMotivo();

        $this->date = $this->income->date->format('Y-m-d');
        $this->reason = (string) $this->income->reason;
        $this->detail = (string) ($this->income->detail ?? '');
        $this->total = (string) $this->income->total;
        $this->current_image = $this->income->image_path;
    }

    protected $rules = [
        'date' => 'required|date',
        'reason' => 'required|string|max:255',
        'detail' => 'nullable|string|max:500',
        'total' => 'required|numeric|min:0.01',
        'files' => 'nullable|array',
        'files.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240',
    ];

    protected $messages = [
        'reason.required' => 'Indique el motivo (campo "A").',
        'files.*.image' => 'Cada archivo debe ser una imagen.',
        'files.*.mimes' => 'Formatos válidos: JPG, PNG, GIF o WebP.',
        'files.*.max' => 'Cada imagen debe pesar máximo 10 MB.',
    ];

    public function removeFile(int $i): void
    {
        if (isset($this->files[$i])) {
            unset($this->files[$i]);
            $this->files = array_values($this->files);
        }
    }

    /**
     * Regla 04/09 (Antony): el director (caja.editar-historico) edita los
     * registros DE TODOS y de cualquier fecha; administrador hacia abajo,
     * SOLO los hechos por ellos mismos y SOLO del día. caja.ver-todo da
     * visibilidad, ya no edición ajena. Se llama en mount() Y en cada
     * acción (update/destroy): Livewire hidrata sin re-ejecutar mount,
     * así que el guard debe repetirse.
     */
    private function autorizar(): void
    {
        $user = auth()->user();
        $esDeHoy = $this->income->date->format('Y-m-d') === now()->format('Y-m-d');
        $esPropio = $this->income->user_id === $user?->id;
        abort_unless(
            ($user?->can('caja.editar-historico') ?? false) || ($esDeHoy && $esPropio),
            403,
            'Solo puedes editar tus propios movimientos del día.'
        );
    }

    public function update(): void
    {
        $this->autorizar();

        if (! $this->puedeCambiarMotivo()) {
            // Se decide por el PERMISO: las propiedades públicas se pueden manipular desde el navegador.
            $this->reason = (string) $this->income->reason;
        } elseif ($this->motivoLibre()) {
            // "i.d" o " I.D " son el motivo "I.D" que ya está en la lista: se guarda con esa misma escritura.
            $this->reason = $this->motivoComoYaSeUso($this->reason, $this->incomeId);
        }

        try {
            $this->validate();

            $data = [
                // Sin bypass-fecha-anterior la fecha original no se toca
                'date' => (auth()->user()?->can('caja.bypass-fecha-anterior') ?? false) ? $this->date : $this->income->date->format('Y-m-d'),
                'reason' => $this->reason,
                'detail' => $this->detail,
                'total' => $this->total,
            ];

            $this->income->update($data);

            // Las imágenes elegidas se suben en el mismo clic (como adjuntos de la galería).
            $subidas = $this->files !== [] ? $this->storeIncomeAttachments($this->income, $this->files) : 0;
            $this->files = [];

            session()->flash('cash_success', 'Ingreso actualizado correctamente.'.($subidas ? " {$subidas} ".($subidas === 1 ? 'imagen subida.' : 'imágenes subidas.') : ''));
            $this->redirectRoute('cash.incomes');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al actualizar: '.$e->getMessage());
        }
    }

    public function questionDelete(int $id): void
    {
        if ($this->eliminacionBloqueada($this->income)) {
            return;
        }
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        // register_destroy es global: con la galería de adjuntos anidada en esta
        // pantalla, solo aceptamos el id del propio ingreso (el botón Eliminar
        // siempre manda $incomeId; cualquier otro id no es para este componente).
        if ($id !== $this->incomeId) {
            return;
        }

        if (! auth()->user()?->can('caja.eliminar')) {
            abort(403);
        }
        $this->autorizar();
        if ($this->eliminacionBloqueada($this->income)) {
            return;
        }

        // Espejo caja 3 (legacy ingresos-modificar2.php): el borrado elimina ingreso Y ingreso3.
        // (Nota: el legacy NO sincroniza caja3 al EDITAR un ingreso, por eso update() no la toca.)
        Income::where('caja', 3)->where('parent_id', $id)->delete();
        $income = Income::findOrFail($id);
        $income->sinAuditoriaAutomatica(fn () => $income->delete());
        Audit::log("Eliminó el ingreso #{$id}", $income);
        session()->flash('cash_success', 'Ingreso eliminado correctamente.');
        $this->redirectRoute('cash.incomes');
    }

    /**
     * La misma restricción del alta (27/11/2026, Antony: "que tenga la misma restricción"): quien al crear
     * solo puede registrar "Fijos" con motivo "Diario" (asesor, cobranza: sin caja.editar-historico ni
     * caja.ver-todo) tampoco cambia el campo "A" al editar su ingreso del día; el motivo se queda como está.
     */
    private function puedeCambiarMotivo(): bool
    {
        return auth()->user()?->canAny(['caja.editar-historico', 'caja.ver-todo']) ?? false;
    }

    /** Un ingreso "Otros" lleva un motivo libre; el resto, un concepto de Configuración. El tipo no se cambia al editar. */
    private function motivoLibre(): bool
    {
        return $this->income->modo === 'Otros';
    }

    /**
     * Lo que ofrece el campo "A", igual que en el alta (27/11/2026, Antony: "también debe de ir en el
     * editar"): en "Otros", los motivos ya usados (y lo que se escriba nuevo); en "Fijos", los conceptos
     * activos. El motivo que el ingreso ya tiene va siempre en la lista, aunque su concepto se haya
     * desactivado: si no, el cuadro saldría vacío con un valor guardado.
     *
     * @return list<string>
     */
    public function opcionesDeA(): array
    {
        if ($this->motivoLibre()) {
            return $this->motivosDeOtros();
        }

        $conceptos = Concept::where('type', 'ingreso')->where('status', 'active')->orderBy('name')->pluck('name')->all();
        if (trim($this->reason) !== '' && ! in_array($this->reason, $conceptos, true)) {
            array_unshift($conceptos, $this->reason);
        }

        return $conceptos;
    }

    public function render()
    {
        return view('livewire.cash.edit-income', [
            'motivoLibre' => $this->motivoLibre(),
            'opcionesA' => $this->opcionesDeA(),
        ]);
    }
}
