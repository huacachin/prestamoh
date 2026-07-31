<?php

namespace App\Livewire\Cash;

use App\Livewire\Cash\Concerns\SavesExpenseAttachments;
use App\Models\Concept;
use App\Models\Expense;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateExpense extends Component
{
    use SavesExpenseAttachments;
    use WithFileUploads;

    public string $modo = '';        // 'Fijos' | 'Otros' (paso 1)

    public string $date = '';

    public string $reason = '';      // legacy: aaa (select Fijos / texto Otros)

    public string $detail = '';

    public $total = '';

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
        // "Otros" implica registrar fuera del flujo Diario; lo limitamos a quienes pueden tocar histórico.
        $this->canChooseOtros = $user->can('caja.editar-historico');

        if (! $this->canChooseOtros) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
    }

    public function updatedModo(): void
    {
        $this->reason = '';
        $this->total = '';
        $this->resetErrorBag();
    }

    /**
     * Al cambiar el concepto en modo Fijos, propone el monto desde factor_egreso
     * (legacy cargaconcepto.php). Queda editable: el factor es solo una sugerencia.
     */
    public function updatedReason(): void
    {
        if ($this->modo !== 'Fijos' || $this->reason === '') {
            return;
        }
        $concept = Concept::where('type', 'egreso')
            ->where('status', 'active')
            ->where('name', $this->reason)
            ->first();

        $factor = $concept ? (float) $concept->factor_egreso : 0;
        $this->total = $factor > 0 ? number_format($factor, 2, '.', '') : '';
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

        if (! $this->canChooseOtros) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
        if (! $this->canEditDate) {
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

            \App\Support\Audit::log('Registró egreso de '.(float) $this->total, $expense);

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
