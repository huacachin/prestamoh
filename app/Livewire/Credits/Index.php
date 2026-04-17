<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public string $nombre    = '';
    public string $codigo    = '';
    public string $ejecutivo = '';
    public string $seletipl  = '';

    public function delete(int $id): void
    {
        $user = auth()->user();
        $credit = Credit::find($id);

        if (!$credit) {
            $this->dispatch('errorAlert', ['message' => 'Crédito no encontrado.']);
            return;
        }

        // Verificar que no tenga pagos aplicados
        $totalPagado = CreditInstallment::where('credit_id', $id)
            ->sum('importe_aplicado');

        if ($totalPagado > 0 && !$user->hasRole('superusuario')) {
            $this->dispatch('errorAlert', ['message' => 'No se puede eliminar: el crédito tiene pagos aplicados.']);
            return;
        }

        // SuperUsuario sin pagos puede eliminar siempre
        // Otros: solo si es de hoy y no es refinanciado
        if (!$user->hasRole('superusuario')) {
            $hoy = now()->format('Y-m-d');
            $fechaCredit = $credit->fecha_prestamo?->format('Y-m-d');
            if ($fechaCredit !== $hoy || $credit->refinanciado) {
                $this->dispatch('errorAlert', ['message' => 'Solo se pueden eliminar créditos del día y no refinanciados.']);
                return;
            }
        }

        // Eliminar cascade
        CreditInstallment::where('credit_id', $id)->delete();
        \App\Models\Payment::where('credit_id', $id)->delete();
        $credit->delete();

        $this->dispatch('successAlert', ['message' => 'Préstamo eliminado correctamente.']);
    }

    public function render()
    {
        $query = Credit::query()
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id', 'user:id,name,username'])
            ->where('estado', 1)
            ->where('situacion', '<>', 'Cancelado');

        if (trim($this->nombre) !== '') {
            $term = trim($this->nombre);
            $query->whereHas('client', function ($c) use ($term) {
                $c->where('nombre', 'like', "%{$term}%")
                  ->orWhere('apellido_pat', 'like', "%{$term}%")
                  ->orWhere('apellido_mat', 'like', "%{$term}%");
            });
        }

        if (trim($this->codigo) !== '') {
            $query->where('id', 'like', '%' . trim($this->codigo) . '%');
        }

        if (trim($this->ejecutivo) !== '') {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->ejecutivo));
        }

        if (trim($this->seletipl) !== '' && $this->seletipl !== '0000') {
            $query->where('tipo_planilla', $this->seletipl);
        }

        $credits = $query->orderByDesc('fecha_prestamo')->get();

        // Pre-calcular sumas de pagos por credit_id (para evitar N+1)
        $creditIds = $credits->pluck('id')->toArray();
        $pagosMap = [];
        if (!empty($creditIds)) {
            $sums = CreditInstallment::whereIn('credit_id', $creditIds)
                ->selectRaw('credit_id, sum(importe_aplicado) as iapli, sum(interes_aplicado) as aplido')
                ->groupBy('credit_id')
                ->get();
            foreach ($sums as $s) {
                $pagosMap[$s->credit_id] = [
                    'iapli'  => (float) $s->iapli,
                    'aplido' => (float) $s->aplido,
                ];
            }
        }

        // Asesores para dropdown
        $asesores = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['asesor', 'superusuario', 'administrador', 'director']))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        // Totales generales
        $sumtotal = 0;
        $suminter = 0;
        $sumtotax = 0;
        $sumpagos = 0;
        $sumsaldo = 0;

        foreach ($credits as $c) {
            $iapli  = $pagosMap[$c->id]['iapli'] ?? 0;
            $aplido = $pagosMap[$c->id]['aplido'] ?? 0;
            $inter  = round(($c->importe * $c->interes) / 100, 2);

            $sumtotal += $c->importe;
            $suminter += $inter;
            $sumtotax += $c->importe + $inter;
            $sumpagos += $iapli + $aplido;
            $sumsaldo += $c->importe - $iapli - $aplido + $inter;
        }

        return view('livewire.credits.index', [
            'credits'   => $credits,
            'pagosMap'  => $pagosMap,
            'asesores'  => $asesores,
            'sumtotal'  => $sumtotal,
            'suminter'  => $suminter,
            'sumtotax'  => $sumtotax,
            'sumpagos'  => $sumpagos,
            'sumsaldo'  => $sumsaldo,
        ]);
    }
}
