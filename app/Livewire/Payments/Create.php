<?php

namespace App\Livewire\Payments;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use Livewire\Component;

class Create extends Component
{
    public ?int $credit_id = null;
    public ?string $creditInfo = null;
    public string $searchCredit = '';
    public $credits = [];

    public ?int $installment_id = null;
    public string $tipo = 'CAPITAL';
    public float $monto = 0;
    public string $fecha = '';
    public ?string $nro_recibo = null;
    public ?string $detalle = null;

    // Credit detail
    public $installments = [];
    public $selectedCredit = null;

    protected $rules = [
        'credit_id'      => 'required|exists:credits,id',
        'installment_id' => 'required|exists:credit_installments,id',
        'tipo'           => 'required|in:CAPITAL,INTERES,MORA',
        'monto'          => 'required|numeric|min:0.01',
        'fecha'          => 'required|date',
    ];

    protected $messages = [
        'credit_id.required'      => 'Seleccione un crédito.',
        'installment_id.required' => 'Seleccione una cuota.',
        'monto.min'               => 'El monto debe ser mayor a 0.',
    ];

    public function mount(?int $creditId = null)
    {
        $this->fecha = now()->format('Y-m-d');

        if ($creditId) {
            $credit = Credit::with('client')->find($creditId);
            if ($credit) {
                $this->selectCredit($credit->id);
            }
        }
    }

    public function updatedSearchCredit()
    {
        if (strlen($this->searchCredit) >= 2) {
            $term = $this->searchCredit;
            $this->credits = Credit::with('client')
                ->where('situacion', 'Activo')
                ->where(function ($q) use ($term) {
                    $q->whereHas('client', fn ($c) =>
                        $c->where('nombre', 'like', "%{$term}%")
                          ->orWhere('apellido_pat', 'like', "%{$term}%")
                          ->orWhere('documento', 'like', "%{$term}%")
                    );
                })
                ->limit(10)
                ->get();
        } else {
            $this->credits = [];
        }
    }

    public function selectCredit(int $id)
    {
        $credit = Credit::with(['client', 'installments' => fn ($q) => $q->orderBy('num_cuota')])->find($id);
        if ($credit) {
            $this->credit_id = $credit->id;
            $this->creditInfo = $credit->client?->fullName() . ' - ' . $credit->client?->documento
                . ' | Crédito #' . $credit->id . ' | S/. ' . number_format($credit->importe, 2);
            $this->selectedCredit = $credit;
            $this->installments = $credit->installments;
            $this->searchCredit = '';
            $this->credits = [];
        }
    }

    public function clearCredit()
    {
        $this->credit_id = null;
        $this->creditInfo = null;
        $this->selectedCredit = null;
        $this->installments = [];
        $this->installment_id = null;
    }

    public function save()
    {
        $this->validate();

        $installment = CreditInstallment::findOrFail($this->installment_id);

        // Validate installment belongs to selected credit
        if ($installment->credit_id !== $this->credit_id) {
            $this->addError('installment_id', 'La cuota no pertenece al crédito seleccionado.');
            return;
        }

        // Create payment
        $payment = Payment::create([
            'credit_id'      => $this->credit_id,
            'installment_id' => $this->installment_id,
            'modo'           => 'EFECTIVO',
            'tipo'           => $this->tipo,
            'documento'      => $this->selectedCredit?->documento,
            'nro_recibo'     => $this->nro_recibo,
            'fecha'          => $this->fecha,
            'monto'          => $this->monto,
            'moneda'         => $this->selectedCredit?->moneda ?? 'PEN',
            'tipo_cambio'    => 1,
            'detalle'        => $this->detalle,
            'asesor'         => auth()->user()->name,
            'user_id'        => auth()->id(),
            'headquarter_id' => auth()->user()->headquarter_id,
        ]);

        // Update installment based on tipo
        if ($this->tipo === 'CAPITAL') {
            $installment->importe_aplicado = $installment->importe_aplicado + $this->monto;
        } elseif ($this->tipo === 'INTERES') {
            $installment->interes_aplicado = $installment->interes_aplicado + $this->monto;
        } elseif ($this->tipo === 'MORA') {
            // Mora reduces from importe_mora or adds to aplicado
            $installment->importe_mora = max(0, $installment->importe_mora - $this->monto);
        }

        // Check if fully paid
        $totalAplicado = $installment->importe_aplicado + $installment->interes_aplicado;
        $totalCuota = $installment->importe_cuota + $installment->importe_interes;

        if ($totalAplicado >= $totalCuota) {
            $installment->pagado = true;
            $installment->fecha_pago = $this->fecha;
        }

        $installment->save();

        session()->flash('payment_success', 'Pago registrado exitosamente. Recibo: ' . ($this->nro_recibo ?? 'S/N'));
        return redirect()->route('credits.show', $this->credit_id);
    }

    public function render()
    {
        return view('livewire.payments.create');
    }
}
