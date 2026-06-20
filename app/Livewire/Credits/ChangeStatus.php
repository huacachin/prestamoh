<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use Carbon\Carbon;
use Livewire\Component;

class ChangeStatus extends Component
{
    public string $tipoe = 'Credito';
    public string $fecha = '';
    public string $search = '';
    public ?int $selectedId = null;
    public bool $showDropdown = false;

    // Legacy estado.php solo deja la opción "Cancelado" activa en el <select>
    // (las demás están comentadas en el HTML). Mantenemos la misma semántica.
    public string $selecsitu = 'Cancelado';

    public function mount(): void
    {
        $this->fecha = now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->selectedId = null;
        $this->showDropdown = strlen(trim($this->search)) >= 1;
    }

    public function selectCredit(int $id): void
    {
        $credit = Credit::with('client:id,nombre,apellido_pat,apellido_mat,documento')->find($id);
        if (!$credit) return;

        $this->selectedId  = $id;
        $this->showDropdown = false;
        $this->search = $credit->id . ' - ' . trim(($credit->client?->apellido_pat ?? '') . ' ' . ($credit->client?->apellido_mat ?? '') . ' ' . ($credit->client?->nombre ?? ''));
    }

    public function changeStatus(): void
    {
        if (!$this->selectedId) {
            $this->dispatch('errorAlert', ['message' => 'Debe seleccionar un crédito.']);
            return;
        }
        if (!$this->fecha) {
            $this->dispatch('errorAlert', ['message' => 'La fecha es obligatoria.']);
            return;
        }

        // Bloqueo de fecha de mes anterior (legacy: solo SuperUsuario bypass)
        $hoy = now();
        $sel = Carbon::parse($this->fecha);
        if ($sel->format('Ym') < $hoy->format('Ym') && !auth()->user()?->can('caja.bypass-fecha-anterior')) {
            $this->dispatch('errorAlert', ['message' => 'No es posible eliminar, Fecha mes anterior.']);
            return;
        }

        $credit = Credit::find($this->selectedId);
        if (!$credit) {
            $this->dispatch('errorAlert', ['message' => 'El crédito seleccionado no existe.']);
            return;
        }

        // Legacy: UPDATE cab_cuentacorriente SET situacion='Cancelado', estado=0, fechacan=$fecha WHERE id=...
        $credit->update([
            'situacion'         => 'Cancelado',
            'estado'            => 0,
            'fecha_cancelacion' => $this->fecha,
        ]);

        \App\Support\Audit::log("Anuló (canceló) el crédito #{$credit->id} con fecha {$this->fecha}", $credit);

        $this->reset(['selectedId', 'search', 'showDropdown']);
        $this->fecha = now()->format('Y-m-d');
        $this->dispatch('successAlert', ['message' => 'Cambio realizado Satisfactoriamente']);
    }

    public function render()
    {
        $results = collect();
        $selectedCredit = null;

        if ($this->showDropdown && strlen(trim($this->search)) >= 1) {
            $term = trim($this->search);
            $results = Credit::query()
                ->with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->select('id', 'client_id', 'importe', 'situacion', 'fecha_cancelacion', 'cuotas', 'tipo_planilla', 'interes', 'fecha_prestamo')
                ->where(fn ($q) => $q
                    ->where('id', 'like', "%{$term}%")
                    ->orWhereHas('client', fn ($c) => $c
                        ->where('nombre', 'like', "%{$term}%")
                        ->orWhere('apellido_pat', 'like', "%{$term}%")
                        ->orWhere('apellido_mat', 'like', "%{$term}%")
                        ->orWhere('documento', 'like', "%{$term}%")
                    )
                )
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        if ($this->selectedId) {
            $selectedCredit = Credit::with('client:id,nombre,apellido_pat,apellido_mat,documento')
                ->find($this->selectedId);
        }

        return view('livewire.credits.change-status', compact('results', 'selectedCredit'));
    }
}
