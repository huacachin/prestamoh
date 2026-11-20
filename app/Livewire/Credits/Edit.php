<?php

namespace App\Livewire\Credits;

use App\Models\Credit;
use App\Support\Audit;
use App\Support\Auditoria\ConCapturaDeAuditoria;
use App\Support\ConReglasDeEliminacion;
use App\Support\EdicionCredito;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Edit extends Component
{
    use ConCapturaDeAuditoria;
    use ConReglasDeEliminacion;

    public Credit $credit;

    #[Locked]
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

    /**
     * 20/11/2026: 'expediente' = se llegó desde el paso a paso de la ficha del cliente; al guardar o
     * volver se regresa ahí, no a la ficha del crédito (el cliente: "te lleva a un lado y luego tiene
     * que regresar al cliente").
     */
    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    protected $rules = [
        'fecha_prestamo' => 'required|date',
        'importe' => 'required|numeric|min:1',
        'cuotas' => 'required|integer|min:1',
        'tipo_planilla' => 'required|in:1,3,4',
        'interes' => 'required|numeric|min:0',
        'moneda' => 'required|in:PEN,USD',
        'situacion' => 'required|in:Activo,Cancelado,Refinanciado,Eliminado',
    ];

    public function mount(int $id)
    {
        // Analista (scope-propio): solo ve/paga SUS créditos — no crea ni edita
        abort_if(auth()->user()?->can('clientes.scope-propio') ?? false, 403, EdicionCredito::MOTIVO_ROL);
        $this->credit = Credit::with('payments')->findOrFail($id);

        // Mapa 21/08: sin editar-historico solo se editan créditos registrados HOY. Y desde el 20/11, con
        // el contrato emitido no lo edita nadie (EdicionCredito).
        $motivo = EdicionCredito::motivoBloqueo(auth()->user(), $this->credit);
        abort_if($motivo !== null, 403, (string) $motivo);
        $this->creditId = $id;

        $this->fecha_prestamo = $this->credit->fecha_prestamo?->format('Y-m-d') ?? '';
        $this->importe = (float) $this->credit->importe;
        $this->cuotas = (int) $this->credit->cuotas;
        $this->tipo_planilla = (int) $this->credit->tipo_planilla;
        $this->interes = (float) $this->credit->interes;
        $this->moneda = $this->credit->moneda ?? 'PEN';
        $this->documento = $this->credit->documento;
        $this->glosa = $this->credit->glosa;
        $this->situacion = $this->credit->situacion;
    }

    public function update()
    {
        $this->validate();

        $user = auth()->user();

        // Re-validación de lo que mount() exige: las acciones Livewire no
        // vuelven a pasar por mount, así que el guard debe vivir también aquí.
        $motivo = EdicionCredito::motivoBloqueo($user, $this->credit);
        abort_if($motivo !== null, 403, (string) $motivo);

        // Sin bypass-fecha-anterior la fecha del crédito no se toca.
        if (! ($user?->can('caja.bypass-fecha-anterior') ?? false)) {
            $this->fecha_prestamo = $this->credit->fecha_prestamo?->format('Y-m-d') ?? $this->fecha_prestamo;
        }

        // Cambiar la situación desde aquí exige el permiso de Cambiar Estado y
        // respeta la regla de saldo (mismo gate que /credits/change-status).
        if ($this->situacion !== $this->credit->situacion) {
            abort_unless($user?->can('registro.estado') ?? false, 403, 'Sin permiso para cambiar el estado.');
            if (in_array($this->situacion, ['Cancelado', 'Refinanciado'], true)) {
                $saldo = $this->credit->saldoPendienteCronograma();
                if ($saldo > 0.01) {
                    $this->dispatch('errorAlert', ['message' => 'No se puede cambiar a '.$this->situacion.': saldo pendiente de S/ '.number_format($saldo, 2).'.']);

                    return;
                }
            }
            if ($this->situacion === 'Eliminado') {
                abort_unless($user?->can('creditos.eliminar') ?? false, 403, 'Sin permiso para eliminar créditos.');
                // MISMA regla que el borrado directo (05/09): marcar "Eliminado"
                // desde aquí era la puerta de atrás para saltarse el día.
                abort_unless(
                    ($user?->can('caja.editar-historico') ?? false)
                    || ($this->credit->fecha_prestamo?->format('Y-m-d') === now()->format('Y-m-d')
                        && (int) $this->credit->user_id === (int) $user?->id),
                    403, 'Solo se pueden eliminar créditos registrados hoy por ti.'
                );
            }
        }

        $this->credit->update([
            'fecha_prestamo' => $this->fecha_prestamo,
            'importe' => $this->importe,
            'cuotas' => $this->cuotas,
            'tipo_planilla' => $this->tipo_planilla,
            'interes' => $this->interes,
            'moneda' => $this->moneda,
            'documento' => $this->documento,
            'glosa' => $this->glosa,
            'situacion' => $this->situacion,
        ]);

        session()->flash('credit_success', 'Crédito actualizado correctamente.');

        return redirect()->to($this->urlDeRegreso());
    }

    /** A dónde se vuelve al guardar o cancelar: al paso a paso de la ficha si se vino de ahí; si no, a la ficha del crédito. */
    public function urlDeRegreso(): string
    {
        return $this->desde === 'expediente' && (auth()->user()?->can('clientes') ?? false)
            ? route('clients.edit', ['id' => $this->credit->client_id, 'prestamo' => $this->creditId, 'tab' => 'prestamos'])
            : route('credits.show', $this->creditId);
    }

    public function questionDelete(int $id): void
    {
        if ($this->eliminacionBloqueada($this->credit)) {
            return;
        }
        $this->dispatch('questionDelete', [
            'id' => $id,
            'role' => 'crédito',
            'name' => '#'.$this->credit->id.' — '.$this->credit->client?->fullName(),
        ]);
    }

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        // Solo el crédito montado (el evento es global) y con los MISMOS gates
        // que Credits/Index::delete: permiso + del día (salvo histórico) + sin pagos.
        if ($id !== $this->creditId) {
            return;
        }

        $user = auth()->user();
        abort_unless($user?->can('creditos.eliminar') ?? false, 403, 'Sin permiso para eliminar créditos.');

        $credit = Credit::withCount('payments')->findOrFail($id);
        if ($this->eliminacionBloqueada($credit)) {
            return;
        }
        abort_unless(
            ($user?->can('caja.editar-historico') ?? false)
            || ($credit->fecha_prestamo?->format('Y-m-d') === now()->format('Y-m-d')
                && ! $credit->refinanciado
                && (int) $credit->user_id === (int) $user?->id),
            403, 'Solo se pueden eliminar créditos registrados hoy por ti.'
        );
        if ($credit->payments_count > 0) {
            $this->dispatch('errorAlert', ['message' => 'No se puede eliminar: el crédito tiene pagos registrados.']);

            return;
        }

        $credit->sinAuditoriaAutomatica(fn () => $credit->update(['situacion' => 'Eliminado']));
        Audit::log("Eliminó el crédito #{$id}", $credit);
        session()->flash('credit_success', 'Crédito eliminado.');
        $this->redirectRoute('credits.index');
    }

    public function render()
    {
        $hasPayments = $this->credit->payments->count() > 0;

        return view('livewire.credits.edit', compact('hasPayments'));
    }
}
