<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Livewire\Component;

class ChangeStatus extends Component
{
    public $tipoe = 'Credito';
    public $fecha;
    public $search = '';
    public $selectedId = null;
    public $showDropdown = false;
    public $selecsitu = '';

    protected $situaciones = [
        'Cancelado',
        'Vigente',
        'R. Capital',
        'R.C.P. Int. Cong',
        'Judicializado',
        'Condonado',
    ];

    public function mount()
    {
        $this->fecha = now()->format('Y-m-d');
    }

    public function updatedSearch()
    {
        $this->selectedId = null;
        $this->showDropdown = strlen(trim($this->search)) >= 1;
    }

    public function selectCredit($id)
    {
        $this->selectedId = $id;
        $this->showDropdown = false;

        $credit = Credit::with('client:id,nombre,apellido_pat,apellido_mat,documento')->find($id);
        if ($credit) {
            $this->search = $credit->id . ' - ' . ($credit->client?->nombre ?? '') . ' ' . ($credit->client?->apellido_pat ?? '');
        }
    }

    public function changeStatus()
    {
        if (!$this->selectedId) {
            $this->dispatch('errorAlert', ['message' => 'Debe seleccionar un crédito.']);
            return;
        }

        if (!$this->selecsitu || $this->selecsitu === '0000') {
            $this->dispatch('errorAlert', ['message' => 'Debe seleccionar una situación.']);
            return;
        }

        if (!$this->fecha) {
            $this->dispatch('errorAlert', ['message' => 'La fecha es obligatoria.']);
            return;
        }

        // Validar mes anterior (solo SuperUsuario puede)
        $user = auth()->user();
        $fechaHoy = now();
        $fechaSeleccionada = \Carbon\Carbon::parse($this->fecha);

        if (!$user->hasRole('superusuario')) {
            if ($fechaSeleccionada->format('Ym') < $fechaHoy->format('Ym')) {
                $this->dispatch('errorAlert', ['message' => 'No es posible cambiar estado a fecha de mes anterior.']);
                return;
            }
        }

        $credit = Credit::find($this->selectedId);
        if (!$credit) {
            $this->dispatch('errorAlert', ['message' => 'El crédito seleccionado no existe.']);
            return;
        }

        // Mapear situación legacy → nueva
        $situacionMap = [
            'Vigente'          => 'Activo',
            'Cancelado'        => 'Cancelado',
            'R. Capital'       => 'Cancelado',
            'R.C.P. Int. Cong' => 'Cancelado',
            'Judicializado'    => 'Activo',
            'Condonado'        => 'Cancelado',
        ];

        $newSituacion = $situacionMap[$this->selecsitu] ?? $this->selecsitu;
        $credit->situacion = $newSituacion;
        $credit->estado = 0;

        // Fecha específica según situación
        if ($this->selecsitu === 'Cancelado') {
            $credit->fecha_cancelacion = $this->fecha;
        } elseif ($this->selecsitu === 'Vigente') {
            $credit->estado = 1;
            $credit->fecha_cancelacion = null;
        }

        $credit->save();

        $this->selectedId = null;
        $this->search = '';
        $this->selecsitu = '';
        $this->showDropdown = false;
        $this->fecha = now()->format('Y-m-d');
        $this->dispatch('successAlert', ['message' => 'Cambio realizado Satisfactoriamente']);
    }

    public function render()
    {
        $results = collect();
        $selectedCredit = null;

        if ($this->showDropdown && strlen(trim($this->search)) >= 1) {
            $term = trim($this->search);

            $query = Credit::query()
                ->with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->select('id', 'client_id', 'importe', 'situacion', 'fecha_cancelacion', 'cuotas', 'tipo_planilla', 'interes', 'fecha_prestamo');

            $query->where(function ($q) use ($term) {
                $q->where('id', 'like', "%{$term}%")
                  ->orWhereHas('client', fn ($c) =>
                      $c->where('nombre', 'like', "%{$term}%")
                        ->orWhere('apellido_pat', 'like', "%{$term}%")
                        ->orWhere('apellido_mat', 'like', "%{$term}%")
                        ->orWhere('documento', 'like', "%{$term}%")
                  );
            });

            $results = $query->orderByDesc('id')->limit(20)->get();
        }

        if ($this->selectedId) {
            $selectedCredit = Credit::with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->find($this->selectedId);
        }

        return view('livewire.credits.change-status', [
            'results'        => $results,
            'selectedCredit' => $selectedCredit,
            'situaciones'    => $this->situaciones,
        ]);
    }
}
