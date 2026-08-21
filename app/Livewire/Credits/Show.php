<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use App\Models\MassDeletion;
use App\Services\Printing\TicketPrinter;
use Livewire\Component;

class Show extends Component
{
    public Credit $credit;

    public function mount(int $id)
    {
        $this->credit = Credit::with([
            'client',
            'installments' => fn ($q) => $q->orderBy('num_cuota'),
            'payments' => fn ($q) => $q->orderByDesc('fecha'),
            'payments.user:id,name',
            'massDeletions.details:id,mass_deletion_id,installment_id,amount,tipo',
            'massDeletions.details.installment:id,num_cuota',
            'user:id,name',
            'headquarter:id,name',
        ])->findOrFail($id);

        // Analista (scope-propio): solo SUS créditos
        if ((auth()->user()?->can('clientes.scope-propio') ?? false)
            && $this->credit?->client?->asesor_id !== auth()->id()) {
            abort(403, 'Solo puedes ver tus propios créditos.');
        }
    }

    public function printPayment(int $massDeletionId, TicketPrinter $printer)
    {
        $masivo = MassDeletion::with(['credit.client', 'credit.headquarter', 'details.installment:id,num_cuota'])
            ->where('credit_id', $this->credit->id)
            ->findOrFail($massDeletionId);

        try {
            $printer->printPayment($masivo);
            $this->dispatch('successAlert', ['message' => 'Ticket enviado a la impresora.']);
        } catch (\Throwable $e) {
            $this->dispatch('errorAlert', ['message' => 'Error al imprimir: '.$e->getMessage()]);
        }
    }

    public function render()
    {
        $totalPagado = $this->credit->payments->sum('monto');
        $totalDeuda = $this->credit->installments->sum(fn ($i) => $i->importe_cuota + $i->importe_interes + $i->importe_excedente);
        $saldoPendiente = $totalDeuda - $totalPagado;

        return view('livewire.credits.show', compact('totalPagado', 'totalDeuda', 'saldoPendiente'));
    }
}
