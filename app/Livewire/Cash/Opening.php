<?php

namespace App\Livewire\Cash;

use App\Models\CashOpening;
use Livewire\Component;

class Opening extends Component
{
    public string $fechaera = '';
    public $solesm = '';

    // Para edición inline (SuperUsuario)
    public $editingId = null;
    public $editingValue = '';

    public function mount(): void
    {
        $this->fechaera = now()->format('Y-m-d');
    }

    public function save(): void
    {
        $user = auth()->user();

        // Validar
        if ($this->solesm === '' || !is_numeric($this->solesm)) {
            $this->dispatch('errorAlert', ['message' => 'Debe ingresar un importe válido.']);
            return;
        }

        $importe = (float) $this->solesm;
        $fecha = $this->fechaera ?: now()->format('Y-m-d');
        $hora = now()->format('H:i');

        // Buscar apertura del mes/año actual (legacy: 1 registro por mes)
        $existing = CashOpening::whereYear('fecha', date('Y', strtotime($fecha)))
            ->whereMonth('fecha', date('m', strtotime($fecha)))
            ->where('moneda', 'Soles')
            ->first();

        if ($existing) {
            // Si existe, solo SuperUsuario puede actualizar
            if (!$user->hasRole('superusuario')) {
                $this->dispatch('errorAlert', ['message' => 'Ya existe apertura para este mes. Solo SuperUsuario puede actualizar.']);
                return;
            }

            $existing->update(['saldo_inicial' => $importe]);
            $this->dispatch('successAlert', ['message' => 'Se actualizó la caja con éxito']);
        } else {
            // Crear nueva
            CashOpening::create([
                'fecha'          => $fecha,
                'hora'           => $hora,
                'saldo_inicial'  => $importe,
                'saldo_final'    => 0,
                'estado'         => 'abierto',
                'moneda'         => 'Soles',
                'user_id'        => $user->id,
                'headquarter_id' => $user->headquarter_id ?? 1,
            ]);
            $this->dispatch('successAlert', ['message' => 'Se aperturó la caja con éxito']);
        }

        $this->solesm = '';
    }

    public function clear(): void
    {
        $this->solesm = '';
    }

    public function startEdit(int $id): void
    {
        if (!auth()->user()->hasRole('superusuario')) {
            return;
        }
        $opening = CashOpening::find($id);
        if ($opening) {
            $this->editingId = $id;
            $this->editingValue = $opening->saldo_inicial;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editingValue = '';
    }

    public function updateInline(int $id): void
    {
        if (!auth()->user()->hasRole('superusuario')) {
            $this->dispatch('errorAlert', ['message' => 'No autorizado.']);
            return;
        }

        if (!is_numeric($this->editingValue)) {
            $this->dispatch('errorAlert', ['message' => 'Importe inválido.']);
            return;
        }

        $opening = CashOpening::find($id);
        if ($opening) {
            $opening->update(['saldo_inicial' => (float) $this->editingValue]);
            $this->dispatch('successAlert', ['message' => 'Se actualizó la caja con éxito']);
        }

        $this->editingId = null;
        $this->editingValue = '';
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperUsuario = $user->hasRole('superusuario');

        // Apertura del mes/año actual (legacy: solo Soles)
        $currentMonth = CashOpening::whereYear('fecha', date('Y'))
            ->whereMonth('fecha', date('m'))
            ->where('moneda', 'Soles')
            ->orderBy('id')
            ->first();

        // Histórico (todos los Soles, descendente, sin limit)
        $history = CashOpening::where('moneda', 'Soles')
            ->with('user:id,name,username')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return view('livewire.cash.opening', [
            'currentMonth'   => $currentMonth,
            'history'        => $history,
            'isSuperUsuario' => $isSuperUsuario,
            'horaActual'     => now()->format('H:i'),
        ]);
    }
}
