<?php

namespace App\Livewire\Cash;

use App\Livewire\Cash\Concerns\SavesExpenseAttachments;
use App\Models\Concept;
use App\Models\Expense;
use App\Support\ConSubidaDeArchivos;
use Livewire\Component;

class CreateExpense extends Component
{
    use ConSubidaDeArchivos;
    use SavesExpenseAttachments;

    public string $modo = '';        // 'Fijos' | 'Otros' (paso 1)

    public string $date = '';

    public string $reason = '';      // legacy: aaa (select Fijos / texto Otros)

    public string $detail = '';

    public $total = '';

    /**
     * Monto propuesto por el sistema (02/10, Antony) y de dónde salió, para el
     * aviso bajo el campo. Si el usuario escribe otro monto, la propuesta ya
     * no se vuelve a pisar.
     */
    public string $montoPropuesto = '';

    public string $origenPropuesta = '';

    public string $document_type = '';

    public string $in_charge = '';

    // Adjuntos (imágenes) — se suben en el MISMO paso que el egreso.
    public array $files = [];

    // Permisos cacheados
    public bool $canEditDate = false;

    public bool $canChooseOtros = false;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');

        $user = auth()->user();
        $this->canEditDate = $user->can('caja.bypass-fecha-anterior');
        // "Otros" implica registrar fuera del flujo Diario; lo limitamos a quienes gestionan la caja completa.
        $this->canChooseOtros = $user->canAny(['caja.editar-historico', 'caja.ver-todo']);

        if (! $this->canChooseOtros) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
    }

    public function updatedModo(): void
    {
        $this->reason = '';
        $this->total = '';
        $this->montoPropuesto = '';
        $this->origenPropuesta = '';
        $this->resetErrorBag();
    }

    /** Al cambiar el motivo (select de Fijos o texto de Otros) se propone el monto. */
    public function updatedReason(): void
    {
        $this->proponerMonto();
    }

    /** Al salir del detalle, la propuesta se afina al último egreso con ese mismo detalle. */
    public function updatedDetail(): void
    {
        $this->proponerMonto();
    }

    /**
     * Propone el monto (02/10, Antony): el factor del concepto fijo si lo tiene
     * (legacy cargaconcepto.php; hoy todos están en 0) y, si no, el ÚLTIMO
     * egreso de caja registrado con el mismo motivo, afinado por detalle cuando
     * ya hubo uno igual. Nunca pisa un monto escrito a mano: solo rellena si el
     * campo está vacío o todavía trae la propuesta anterior.
     */
    private function proponerMonto(): void
    {
        if ((string) $this->total !== '' && (string) $this->total !== $this->montoPropuesto) {
            return;
        }
        $propuesta = $this->buscarPropuesta();
        $this->total = $propuesta['monto'] ?? '';
        $this->montoPropuesto = $propuesta['monto'] ?? '';
        $this->origenPropuesta = $propuesta['origen'] ?? '';
    }

    /** @return array{monto: string, origen: string}|null */
    private function buscarPropuesta(): ?array
    {
        $reason = trim($this->reason);
        if ($reason === '') {
            return null;
        }

        if ($this->modo === 'Fijos') {
            $factor = (float) (Concept::where('type', 'egreso')->where('status', 'active')->where('name', $reason)->value('factor_egreso') ?? 0);
            if ($factor > 0) {
                return ['monto' => number_format($factor, 2, '.', ''), 'origen' => "monto fijo del concepto «{$reason}»"];
            }
        }

        // Solo la caja operativa (1): ni el espejo de caja 3 ni la caja legal (4).
        $base = Expense::query()->where('caja', 1)->where('reason', $reason)->where('total', '>', 0)
            ->orderByDesc('date')->orderByDesc('id');
        $detail = trim($this->detail);
        $ultimo = $detail !== '' ? (clone $base)->whereRaw('LOWER(TRIM(detail)) = ?', [mb_strtolower($detail)])->first() : null;
        $conDetalle = $ultimo !== null;
        $ultimo ??= $base->first();
        if (! $ultimo) {
            return null;
        }

        return [
            'monto' => number_format((float) $ultimo->total, 2, '.', ''),
            'origen' => 'último egreso de «'.$reason.($conDetalle ? ' · '.trim($ultimo->detail) : '').'» del '.$ultimo->date?->format('d/m/Y'),
        ];
    }

    protected function rules(): array
    {
        $rules = [
            'modo' => 'required|in:Fijos,Otros',
            'date' => 'required|date',
            'detail' => 'required|string|max:500',
            'total' => 'required|numeric|gt:0',
            'document_type' => 'nullable|string|max:100',
            'in_charge' => 'nullable|string|max:255',
            'files' => 'nullable|array',
            'files.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240',
        ];

        if ($this->modo === 'Fijos') {
            $valid = Concept::where('type', 'egreso')
                ->where('status', 'active')
                ->pluck('name')->all();
            $rules['reason'] = 'required|string|in:'.implode(',', $valid);
        } else {
            $rules['reason'] = 'required|string|max:255';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'modo.required' => 'Seleccione el Tipo de Egreso (Fijos u Otros).',
            'modo.in' => 'Tipo de Egreso inválido.',
            'date.required' => 'Indique la fecha.',
            'reason.required' => 'Indique el motivo (campo "A").',
            'reason.in' => 'El motivo seleccionado no es válido.',
            'detail.required' => 'Ingrese el detalle.',
            'total.required' => 'Ingrese el monto.',
            'total.gt' => 'El monto debe ser mayor a 0.',
            'files.*.image' => 'Cada archivo debe ser una imagen.',
            'files.*.mimes' => 'Formatos válidos: JPG, PNG, GIF o WebP.',
            'files.*.max' => 'Cada imagen debe pesar máximo 10 MB.',
        ];
    }

    public function removeFile(int $i): void
    {
        if (isset($this->files[$i])) {
            unset($this->files[$i]);
            $this->files = array_values($this->files);
        }
    }

    public function clear(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->detail = '';
        $this->total = '';
        $this->montoPropuesto = '';
        $this->origenPropuesta = '';
        $this->document_type = '';
        $this->in_charge = '';
        $this->files = [];
        if ($this->canChooseOtros) {
            $this->modo = '';
            $this->reason = '';
        } else {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
        $this->resetErrorBag();
    }

    public function save()
    {
        $user = auth()->user();

        // Decidir por el PERMISO (las propiedades públicas son manipulables
        // desde el cliente en Livewire).
        if (! $user->canAny(['caja.editar-historico', 'caja.ver-todo'])) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
        if (! $user->can('caja.bypass-fecha-anterior')) {
            $this->date = now()->format('Y-m-d');
        }

        $this->validate();

        try {
            $expense = Expense::create([
                'date' => $this->date,
                'modo' => $this->modo,
                'documento' => 'GUIA',
                'caja' => 1,
                'reason' => $this->reason,
                'detail' => $this->detail,
                'total' => (float) $this->total,
                'document_type' => $this->document_type,
                'in_charge' => $this->in_charge,
                'user_id' => $user->id,
                'headquarter_id' => $user->headquarter_id ?? 1,
            ]);

            // Espejo caja 3 (legacy gastos-nuevo.php): modo='Fijos' inserta también en
            // entrada3 con el MISMO monto. modo='Otros' NO genera copia. Sin imágenes.
            if ($this->modo === 'Fijos') {
                Expense::create([
                    'date' => $this->date,
                    'modo' => $this->modo,
                    'documento' => 'GUIA',
                    'caja' => 3,
                    'parent_id' => $expense->id,
                    'reason' => $this->reason,
                    'detail' => $this->detail,
                    'total' => (float) $this->total,
                    'document_type' => null,
                    'in_charge' => null,
                    'user_id' => $user->id,
                    'headquarter_id' => $user->headquarter_id ?? 1,
                ]);
            }

            // Adjuntos en el MISMO paso (si se cargaron imágenes).
            $count = $this->storeExpenseAttachments($expense, $this->files);

            $msg = $count > 0
                ? "Egreso registrado con {$count} ".($count === 1 ? 'imagen' : 'imágenes').'.'
                : 'Egreso registrado.';
            session()->flash('cash_success', $msg);

            return $this->redirectRoute('cash.expenses');
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al registrar: '.$e->getMessage());
        }
    }

    public function render()
    {
        $concepts = Concept::where('type', 'egreso')
            ->where('status', 'active')
            ->when(! $this->canChooseOtros, fn ($q) => $q->where('name', 'Diario'))
            ->orderBy('name')
            ->get();

        return view('livewire.cash.create-expense', compact('concepts'));
    }
}
