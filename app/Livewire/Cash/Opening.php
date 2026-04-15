<?php

namespace App\Livewire\Cash;

use App\Models\CashOpening;
use Livewire\Attributes\On;
use Livewire\Component;

class Opening extends Component
{
    public string $fecha = '';
    public string $saldo_inicial = '';

    public function mount(): void
    {
        $this->fecha = now()->format('Y-m-d');
    }

    protected $rules = [
        'fecha'         => 'required|date',
        'saldo_inicial' => 'required|numeric|min:0',
    ];

    public function save(): void
    {
        try {
            $this->validate();

            $user = auth()->user();

            $exists = CashOpening::where('fecha', $this->fecha)
                ->where('headquarter_id', $user->headquarter_id)
                ->first();

            if ($exists) {
                session()->flash('cash_error', 'Ya existe una apertura de caja para esta fecha.');
                return;
            }

            CashOpening::create([
                'fecha'          => $this->fecha,
                'saldo_inicial'  => $this->saldo_inicial,
                'saldo_final'    => 0,
                'estado'         => 'abierto',
                'user_id'        => $user->id,
                'headquarter_id' => $user->headquarter_id,
            ]);

            $this->saldo_inicial = '';
            session()->flash('cash_success', 'Apertura de caja registrada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            session()->flash('cash_error', 'Error al registrar: ' . $e->getMessage());
        }
    }

    public function questionClose(int $id): void
    {
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function close(int $id): void
    {
        if (!auth()->user()?->hasAnyRole('superusuario', 'administrador', 'director')) {
            abort(403);
        }

        $opening = CashOpening::findOrFail($id);
        $opening->update(['estado' => 'cerrado']);
        session()->flash('cash_success', 'Caja cerrada correctamente.');
    }

    public function render()
    {
        $user = auth()->user();
        $openings = CashOpening::where('headquarter_id', $user->headquarter_id)
            ->with('user:id,name')
            ->orderByDesc('fecha')
            ->limit(30)
            ->get();

        return view('livewire.cash.opening', compact('openings'));
    }
}
