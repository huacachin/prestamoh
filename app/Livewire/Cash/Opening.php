<?php

namespace App\Livewire\Cash;

use App\Models\CashOpening;
use Livewire\Component;

class Opening extends Component
{
    public string $fechaera = '';

    public $solesm = '';

    /**
     * 10/10 (Antony): true en celular (lo fija Alpine al cargar) para pintar SOLO
     * las tarjetas; en escritorio solo la tabla. Antes iban las dos listas
     * siempre (123 aperturas × 2 = 350 KB por respuesta).
     */
    public bool $movil = false;

    public function mount(): void
    {
        $this->fechaera = now()->format('Y-m-d');
    }

    public function save(): void
    {
        $user = auth()->user();
        $canBypassDate = $user->can('caja.bypass-fecha-anterior');
        $canEditHistorico = $user->can('caja.editar-historico');

        if ($this->solesm === '' || ! is_numeric($this->solesm)) {
            $this->addError('solesm', 'Debe ingresar un importe válido.');

            return;
        }

        $importe = (float) $this->solesm;
        $fecha = $this->fechaera ?: now()->format('Y-m-d');
        $hora = now()->format('H:i');

        if (! $canBypassDate && $fecha > now()->format('Y-m-d')) {
            $this->addError('fechaera', 'No se puede aperturar caja con fecha futura.');

            return;
        }

        // Buscar duplicado por mes/año + sede (no global)
        $hqId = $user->headquarter_id ?? 1;
        $existing = CashOpening::whereYear('fecha', date('Y', strtotime($fecha)))
            ->whereMonth('fecha', date('m', strtotime($fecha)))
            ->where('moneda', 'Soles')
            ->where('headquarter_id', $hqId)
            ->first();

        if ($existing) {
            if (! $canEditHistorico) {
                $this->addError('solesm', 'Ya existe apertura de Soles para este mes en tu sede. No tienes permiso para sobrescribirla.');

                return;
            }

            $existing->update(['saldo_inicial' => $importe]);
            $this->dispatch('successAlert', ['message' => 'Se actualizó la caja con éxito']);
        } else {
            $opening = CashOpening::create([
                'fecha' => $fecha,
                'hora' => $hora,
                'saldo_inicial' => $importe,
                'saldo_final' => 0,
                'estado' => 'abierto',
                'moneda' => 'Soles',
                'user_id' => $user->id,
                'headquarter_id' => $hqId,
            ]);
            $this->dispatch('successAlert', ['message' => 'Se aperturó la caja con éxito']);
        }

        $this->solesm = '';
        $this->resetErrorBag();
    }

    public function clear(): void
    {
        $this->solesm = '';
    }

    /**
     * Edición en línea del importe. 10/10 (Antony): el input se abre y se cierra
     * en el navegador (Alpine: editando / valor), sin viaje al servidor; antes
     * startEdit/cancelEdit redibujaban la pantalla entera por cada clic y en
     * producción tardaban 2-3 s en mostrar el input. Solo Guardar llega aquí,
     * con el valor como parámetro; el permiso se sigue validando en el servidor.
     */
    public function updateInline(int $id, $valor = null): void
    {
        if (! auth()->user()?->can('caja.editar-historico')) {
            $this->dispatch('errorAlert', ['message' => 'No autorizado.']);

            return;
        }

        $valor = is_string($valor) ? str_replace(',', '', trim($valor)) : $valor;
        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            $this->dispatch('errorAlert', ['message' => 'Importe inválido.']);

            return;
        }

        $opening = CashOpening::find($id);
        if ($opening) {
            $opening->update(['saldo_inicial' => (float) $valor]);
            $this->dispatch('successAlert', ['message' => 'Se actualizó la caja con éxito']);
        }

        // Cierra el input de esa fila en el navegador.
        $this->dispatch('apertura-guardada', id: $id);
    }

    public function render()
    {
        $user = auth()->user();
        $crossHQ = $user->can('acceso.cross-headquarter');
        $hqId = $user->headquarter_id ?? 1;

        $currentMonth = CashOpening::whereYear('fecha', date('Y'))
            ->whereMonth('fecha', date('m'))
            ->where('moneda', 'Soles')
            ->when(! $crossHQ, fn ($q) => $q->where('headquarter_id', $hqId))
            ->orderBy('id')
            ->first();

        $history = CashOpening::where('moneda', 'Soles')
            ->when(! $crossHQ, fn ($q) => $q->where('headquarter_id', $hqId))
            ->with(['user:id,name,username', 'headquarter:id,name'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return view('livewire.cash.opening', [
            'currentMonth' => $currentMonth,
            'history' => $history,
            'puedeEditar' => $user->can('caja.editar-historico'),
            'horaActual' => now()->format('H:i'),
        ]);
    }
}
