<?php

namespace App\Livewire\Cash;

use App\Models\Concept;
use App\Models\Expense;
use Livewire\Component;

class CreateExpense extends Component
{
    public string $modo = '';        // 'Fijos' | 'Otros' (paso 1)
    public string $date = '';
    public string $reason = '';      // legacy: aaa (select Fijos / texto Otros)
    public string $detail = '';
    public $total = '';
    public string $document_type = '';
    public string $in_charge = '';

    // Precio por concepto (legacy: cargaconcepto.php)
    public $cantidad = 1;
    public $precio_unitario = 0;

    // Permisos cacheados
    public bool $canEditDate = false;
    public bool $canChooseOtros = false;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');

        $user = auth()->user();
        $this->canEditDate    = $user->can('caja.bypass-fecha-anterior');
        // "Otros" implica registrar fuera del flujo Diario; lo limitamos a quienes pueden tocar histórico.
        $this->canChooseOtros = $user->can('caja.editar-historico');

        if (!$this->canChooseOtros) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
    }

    public function updatedModo(): void
    {
        $this->reason = '';
        $this->cantidad = 1;
        $this->precio_unitario = 0;
        $this->total = '';
        $this->resetErrorBag();
    }

    /**
     * Al cambiar el concepto en modo Fijos, carga factor_egreso (legacy cargaconcepto.php).
     */
    public function updatedReason(): void
    {
        if ($this->modo !== 'Fijos' || $this->reason === '') {
            $this->precio_unitario = 0;
            return;
        }
        $concept = Concept::where('type', 'egreso')
            ->where('status', 'active')
            ->where('name', $this->reason)
            ->first();

        $factor = $concept ? (float) $concept->factor_egreso : 0;
        $this->cantidad = 1;
        if ($factor > 0) {
            $this->precio_unitario = $factor;
            $this->total = number_format($factor, 2, '.', '');
        } else {
            $this->precio_unitario = 0;
            $this->total = '';
        }
    }

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
        $cant   = (float) $this->cantidad;
        $precio = (float) $this->precio_unitario;
        if ($precio > 0 && $cant > 0) {
            $this->total = number_format($cant * $precio, 2, '.', '');
        }
    }

    protected function rules(): array
    {
        $rules = [
            'modo'          => 'required|in:Fijos,Otros',
            'date'          => 'required|date',
            'detail'        => 'required|string|max:500',
            'total'         => 'required|numeric|gt:0',
            'document_type' => 'nullable|string|max:100',
            'in_charge'     => 'nullable|string|max:255',
        ];

        if ($this->modo === 'Fijos') {
            $valid = Concept::where('type', 'egreso')
                ->where('status', 'active')
                ->pluck('name')->all();
            $rules['reason'] = 'required|string|in:' . implode(',', $valid);
        } else {
            $rules['reason'] = 'required|string|max:255';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'modo.required'   => 'Seleccione el Tipo de Egreso (Fijos u Otros).',
            'modo.in'         => 'Tipo de Egreso inválido.',
            'date.required'   => 'Indique la fecha.',
            'reason.required' => 'Indique el motivo (campo "A").',
            'reason.in'       => 'El motivo seleccionado no es válido.',
            'detail.required' => 'Ingrese el detalle.',
            'total.required'  => 'Ingrese el monto.',
            'total.gt'        => 'El monto debe ser mayor a 0.',
        ];
    }

    public function clear(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->detail = '';
        $this->total = '';
        $this->document_type = '';
        $this->in_charge = '';
        $this->cantidad = 1;
        $this->precio_unitario = 0;
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

        if (!$this->canChooseOtros) {
            $this->modo = 'Fijos';
            $this->reason = 'Diario';
        }
        if (!$this->canEditDate) {
            $this->date = now()->format('Y-m-d');
        }

        $this->validate();

        try {
            $expense = Expense::create([
                'date'           => $this->date,
                'modo'           => $this->modo,
                'documento'      => 'GUIA',
                'caja'           => 1,
                'reason'         => $this->reason,
                'detail'         => $this->detail,
                'total'          => (float) $this->total,
                'document_type'  => $this->document_type,
                'in_charge'      => $this->in_charge,
                'user_id'        => $user->id,
                'headquarter_id' => $user->headquarter_id ?? 1,
            ]);

            session()->flash('cash_success', 'Egreso registrado. Subí los adjuntos abajo.');
            return $this->redirectRoute('cash.expenses.gallery', $expense->id);
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al registrar: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $concepts = Concept::where('type', 'egreso')
            ->where('status', 'active')
            ->when(!$this->canChooseOtros, fn ($q) => $q->where('name', 'Diario'))
            ->orderBy('name')
            ->get();

        return view('livewire.cash.create-expense', compact('concepts'));
    }
}
