<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Pagination\AbstractPaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /** true = sin paginar (lo usa el export a Excel para traer todo). */
    public bool $todos = false;

    /** Al cambiar cualquier filtro se vuelve a la página 1. */
    public function updating($name, $value): void
    {
        if (in_array($name, ['nombre', 'codigo', 'ejecutivo', 'seletipl'], true)) {
            $this->resetPage();
        }
    }

    #[Url(as: 'nombre', except: '')]
    public string $nombre = '';

    #[Url(as: 'codigo', except: '')]
    public string $codigo = '';

    #[Url(as: 'asesor', except: '')]
    public string $ejecutivo = '';

    #[Url(as: 'tipo', except: '')]
    public string $seletipl = '';

    public function delete(int $id): void
    {
        $user = auth()->user();
        $credit = Credit::find($id);

        if (! $credit) {
            $this->dispatch('errorAlert', ['message' => 'Crédito no encontrado.']);

            return;
        }

        if (! $user->can('creditos.eliminar')) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permisos para eliminar créditos.']);

            return;
        }

        $totalPagado = CreditInstallment::where('credit_id', $id)
            ->sum('importe_aplicado');

        // Sin permiso de bypass, solo se eliminan créditos sin pagos, del día y no refinanciados.
        if (! $user->can('caja.bypass-fecha-anterior')) {
            if ($totalPagado > 0) {
                $this->dispatch('errorAlert', ['message' => 'No se puede eliminar: el crédito tiene pagos aplicados.']);

                return;
            }
            $hoy = now()->format('Y-m-d');
            $fechaCredit = $credit->fecha_prestamo?->format('Y-m-d');
            if ($fechaCredit !== $hoy || $credit->refinanciado) {
                $this->dispatch('errorAlert', ['message' => 'Solo se pueden eliminar créditos del día y no refinanciados.']);

                return;
            }
        }

        // Eliminar cascade
        CreditInstallment::where('credit_id', $id)->delete();
        Payment::where('credit_id', $id)->delete();
        $credit->delete();

        $this->dispatch('successAlert', ['message' => 'Préstamo eliminado correctamente.']);
    }

    public function render()
    {
        $query = Credit::query()
            // client.asesor eager: el blade lo pinta por fila y era un N+1
            // de ~180 queries a users en cada render
            ->with(['client:id,expediente,nombre,apellido_pat,apellido_mat,documento,asesor_id', 'client.asesor:id,name,username', 'user:id,name,username'])
            ->where('estado', 1)
            ->where('situacion', '<>', 'Cancelado');

        // Analista (scope-propio): solo SUS créditos (clientes a su cargo)
        if (auth()->user()?->can('clientes.scope-propio')) {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', auth()->id()));
        }

        if (trim($this->nombre) !== '') {
            $term = trim($this->nombre);
            $query->whereHas('client', function ($c) use ($term) {
                $c->where('nombre', 'like', "%{$term}%")
                    ->orWhere('apellido_pat', 'like', "%{$term}%")
                    ->orWhere('apellido_mat', 'like', "%{$term}%");
            });
        }

        if (trim($this->codigo) !== '') {
            $query->where('id', 'like', '%'.trim($this->codigo).'%');
        }

        if (trim($this->ejecutivo) !== '') {
            $query->whereHas('client', fn ($c) => $c->where('asesor_id', $this->ejecutivo));
        }

        if (trim($this->seletipl) !== '' && $this->seletipl !== '0000') {
            $query->where('tipo_planilla', $this->seletipl);
        }

        // Totales generales: por SQL sobre el conjunto COMPLETO filtrado
        // (la tabla se pagina, pero los totales siguen siendo globales)
        $tot = (clone $query)->reorder()
            ->selectRaw('COALESCE(SUM(importe),0) s, COALESCE(SUM(ROUND(importe * interes / 100, 2)),0) i')
            ->toBase()->first();
        $pag = CreditInstallment::whereIn('credit_id', (clone $query)->reorder()->select('id'))
            ->selectRaw('COALESCE(SUM(importe_aplicado),0) iapli, COALESCE(SUM(interes_aplicado),0) aplido')
            ->first();

        $sumtotal = (float) $tot->s;
        $suminter = (float) $tot->i;
        $sumtotax = $sumtotal + $suminter;
        $sumpagos = (float) $pag->iapli + (float) $pag->aplido;
        $sumsaldo = $sumtotal - (float) $pag->iapli - (float) $pag->aplido + $suminter;

        // Paginado (100 por página como /clients); el Excel pide todo con $todos
        $ordered = $query->orderByDesc('fecha_prestamo')->orderByDesc('id');
        $credits = $this->todos ? $ordered->get() : $ordered->paginate(100);

        // Pre-calcular sumas de pagos por credit_id de la página (evita N+1)
        $creditIds = collect($credits instanceof AbstractPaginator ? $credits->items() : $credits)
            ->pluck('id')->toArray();
        $pagosMap = [];
        if (! empty($creditIds)) {
            $sums = CreditInstallment::whereIn('credit_id', $creditIds)
                ->selectRaw('credit_id, sum(importe_aplicado) as iapli, sum(interes_aplicado) as aplido')
                ->groupBy('credit_id')
                ->get();
            foreach ($sums as $s) {
                $pagosMap[$s->credit_id] = [
                    'iapli' => (float) $s->iapli,
                    'aplido' => (float) $s->aplido,
                ];
            }
        }

        $asesores = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        return view('livewire.credits.index', [
            'credits' => $credits,
            'pagosMap' => $pagosMap,
            'asesores' => $asesores,
            'sumtotal' => $sumtotal,
            'suminter' => $suminter,
            'sumtotax' => $sumtotax,
            'sumpagos' => $sumpagos,
            'sumsaldo' => $sumsaldo,
        ]);
    }
}
