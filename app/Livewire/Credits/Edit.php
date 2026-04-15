<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Attributes\On;
use Livewire\Component;

class Edit extends Component
{
    public Credit $credit;
    public int $creditId;

    public string $fecha_prestamo = '';
    public float $importe = 0;
    public int $cuotas = 1;
    public int $tipo_planilla = 3;
    public float $interes = 0;
    public string $moneda = 'PEN';
    public ?string $documento = null;
    public ?string $glosa = null;
    public string $situacion = 'Activo';

    protected $rules = [
        'fecha_prestamo' => 'required|date',
        'importe'        => 'required|numeric|min:1',
        'cuotas'         => 'required|integer|min:1',
        'tipo_planilla'  => 'required|in:1,3,4',
        'interes'        => 'required|numeric|min:0',
        'moneda'         => 'required|in:PEN,USD',
        'situacion'      => 'required|in:Activo,Cancelado,Refinanciado,Eliminado',
    ];

    public function mount(int $id)
    {
        $this->credit = Credit::with('payments')->findOrFail($id);
        $this->creditId = $id;

        $this->fecha_prestamo = $this->credit->fecha_prestamo?->format('Y-m-d') ?? '';
        $this->importe        = (float) $this->credit->importe;
        $this->cuotas         = (int) $this->credit->cuotas;
        $this->tipo_planilla  = (int) $this->credit->tipo_planilla;
        $this->interes        = (float) $this->credit->interes;
        $this->moneda         = $this->credit->moneda ?? 'PEN';
        $this->documento      = $this->credit->documento;
        $this->glosa          = $this->credit->glosa;
        $this->situacion      = $this->credit->situacion;
    }

    public function update()
    {
        $this->validate();

        $this->credit->update([
            'fecha_prestamo' => $this->fecha_prestamo,
            'importe'        => $this->importe,
            'cuotas'         => $this->cuotas,
            'tipo_planilla'  => $this->tipo_planilla,
            'interes'        => $this->interes,
            'moneda'         => $this->moneda,
            'documento'      => $this->documento,
            'glosa'          => $this->glosa,
            'situacion'      => $this->situacion,
        ]);

        session()->flash('credit_success', 'Crédito actualizado correctamente.');
        return redirect()->route('credits.show', $this->creditId);
    }

    public function questionDelete(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        $credit = Credit::findOrFail($id);
        $credit->update(['situacion' => 'Eliminado']);
        session()->flash('credit_success', 'Crédito eliminado.');
        $this->redirectRoute('credits.index');
    }

    public function render()
    {
        $hasPayments = $this->credit->payments->count() > 0;
        return view('livewire.credits.edit', compact('hasPayments'));
    }
}
