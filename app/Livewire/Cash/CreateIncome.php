<?php

namespace App\Livewire\Cash;

use App\Livewire\Cash\Concerns\SavesIncomeAttachments;
use App\Models\Concept;
use App\Models\Income;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateIncome extends Component
{
    use SavesIncomeAttachments;
    use WithFileUploads;

    public string $modo = '';        // 'Fijos' | 'Otros' (paso 1)

    public string $date = '';

    public string $reason = '';      // legacy: aa (Fijos) o aaa (Otros)

    public string $detail = '';

    public $total = '';

    // Adjuntos (imágenes) — se suben en el MISMO paso que el ingreso.
    public array $files = [];

    // Precio por concepto (legacy: cargaconcepto.php)
    public $cantidad = 1;            // default 1

    public $precio_unitario = 0;     // 0 = libre; >0 = del concepto (readonly)

    // Permisos cacheados
    public bool $canEditDate = false;

    public bool $canChooseOtros = false;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');

        $user = auth()->user();
        $this->canEditDate = $user->can('caja.bypass-fecha-anterior');
        $this->canChooseOtros = $user->can('caja.editar-historico');

        if (! $this->canChooseOtros) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
    }

    public function updatedModo(): void
    {
        // Si cambian a Otros, limpiar reason (estaba con valor de Fijos quizás)
        $this->reason = '';
        $this->cantidad = 1;
        $this->precio_unitario = 0;
        $this->total = '';
        $this->resetErrorBag();
    }

    /**
     * Al cambiar el concepto en modo Fijos, carga el precio (factor_ingreso).
     * Réplica del legacy cargaconcepto.php.
     */
    public function updatedReason(): void
    {
        if ($this->modo !== 'Fijos' || $this->reason === '') {
            $this->precio_unitario = 0;

            return;
        }
        $concept = Concept::where('type', 'ingreso')
            ->where('status', 'active')
            ->where('name', $this->reason)
            ->first();

        $factor = $concept ? (float) $concept->factor_ingreso : 0;
        $this->cantidad = 1;
        if ($factor > 0) {
            $this->precio_unitario = $factor;
            $this->total = number_format($factor, 2, '.', '');
        } else {
            $this->precio_unitario = 0;
            $this->total = '';  // limpiar para que el usuario sepa que debe escribirlo
        }
    }

    /**
     * Recalcula total = cantidad × precio cuando cambia cualquiera de los dos.
     * Si precio o cantidad son 0, el total queda libre para edición manual.
     */
    public function updatedCantidad(): void
    {
        $this->recalcMonto();
    }

    public function updatedPrecioUnitario(): void
    {
        $this->recalcMonto();
    }

    private function recalcMonto(): void
    {
        $cant = (float) $this->cantidad;
        $precio = (float) $this->precio_unitario;
        if ($precio > 0 && $cant > 0) {
            $this->total = number_format($cant * $precio, 2, '.', '');
        }
    }

    protected function rules(): array
    {
        $rules = [
            'modo' => 'required|in:Fijos,Otros',
            'date' => 'required|date',
            'detail' => 'required|string|max:500',
            'total' => 'required|numeric|gt:0',
            'files' => 'nullable|array',
            'files.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240',
        ];

        if ($this->modo === 'Fijos') {
            $valid = Concept::where('type', 'ingreso')
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
            'modo.required' => 'Seleccione el Tipo de Ingreso (Fijos u Otros).',
            'modo.in' => 'Tipo de Ingreso inválido.',
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
        $this->cantidad = 1;
        $this->precio_unitario = 0;
        $this->files = [];
        if ($this->canChooseOtros) {
            $this->modo = '';
            $this->reason = '';
        } else {
            // Asesor/Cobranza: mantener defaults forzados
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $user = auth()->user();

        // Forzar restricciones por rol antes de validar (defensa server-side)
        if (! $this->canChooseOtros) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
        if (! $this->canEditDate) {
            $this->date = now()->format('Y-m-d');
        }

        $this->validate();

        try {
            $income = Income::create([
                'date' => $this->date,
                'modo' => $this->modo,
                'documento' => 'GUIA',
                'caja' => 1,
                'reason' => $this->reason,
                'detail' => $this->detail,
                'total' => (float) $this->total,
                'user_id' => $user->id,
                'headquarter_id' => $user->headquarter_id ?? 1,
            ]);

            // Espejo caja 3 (legacy ingreso-nuevo2.php): modo='Fijos' inserta también en
            // ingreso3 con el NETO = monto - (factor_egreso × cantidad). Ver L15-18 del legacy
            // ($montonuevo = $monto - ($egreso * $cant1); $egreso = facdivi = factor_egreso).
            // modo='Otros' NO genera copia. Sin imágenes.
            if ($this->modo === 'Fijos') {
                $concept = Concept::where('type', 'ingreso')
                    ->where('status', 'active')
                    ->where('name', $this->reason)
                    ->first();

                $factorEgreso = $concept ? (float) $concept->factor_egreso : 0.0;
                $cantidad = (float) $this->cantidad;
                $neto = (float) $this->total - ($factorEgreso * $cantidad);

                Income::create([
                    'date' => $this->date,
                    'modo' => $this->modo,
                    'documento' => 'GUIA',
                    'caja' => 3,
                    'parent_id' => $income->id,
                    'reason' => $this->reason,
                    'detail' => $this->detail,
                    'total' => $neto,
                    'user_id' => $user->id,
                    'headquarter_id' => $user->headquarter_id ?? 1,
                ]);
            }

            // Adjuntos en el MISMO paso (si se cargaron imágenes).
            $count = $this->storeIncomeAttachments($income, $this->files);

            $msg = $count > 0
                ? "Ingreso registrado con {$count} ".($count === 1 ? 'imagen' : 'imágenes').'.'
                : 'Ingreso registrado.';
            session()->flash('cash_success', $msg);

            $this->redirectRoute('cash.incomes');
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al registrar: '.$e->getMessage());
        }
    }

    public function render()
    {
        $user = auth()->user();

        // Conceptos: si Asesor/Cobranza solo "Diario"; resto todos
        $concepts = Concept::where('type', 'ingreso')
            ->where('status', 'active')
            ->when(! $this->canChooseOtros, fn ($q) => $q->where('name', 'Diario'))
            ->orderBy('name')
            ->get();

        return view('livewire.cash.create-income', compact('concepts'));
    }
}
