<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de notificaciones WhatsApp de cobranza (hijo de Clients\Index).
 *
 * Es componente aparte a propósito: sus interacciones (abrir, redactar,
 * enviar, compromisos) solo re-renderizan el modal (~KB) y no la lista de
 * clientes (~cientos de KB). El padre lo abre con dispatch('abrir-notifs').
 */
class NotificationsModal extends Component
{
    public ?int $clientId = null;

    public string $clientName = '';

    public string $telefono = '';

    public int $vencidas = 0;

    /** Editor de "nueva notificación" visible dentro del modal. */
    public bool $editor = false;

    public string $texto = '';

    // Compromiso de pago (edición inline por notificación)
    public ?int $compNotifId = null;

    public string $compFecha = '';

    public string $compDetalle = '';

    /** Cuotas vencidas impagas del PEOR crédito activo del cliente, a hoy. */
    private function cuotasVencidasDe(?int $clientId): int
    {
        if (! $clientId) {
            return 0;
        }

        return (int) (DB::table('credits as c')
            ->join('credit_installments as i', 'i.credit_id', '=', 'c.id')
            ->where('c.client_id', $clientId)
            ->where('c.situacion', 'Activo')
            ->where('i.pagado', 0)
            ->whereNotNull('i.fecha_vencimiento')
            ->where('i.fecha_vencimiento', '<', now()->format('Y-m-d'))
            ->groupBy('c.id')
            ->selectRaw('COUNT(*) v')
            ->orderByDesc('v')
            ->value('v') ?? 0);
    }

    #[On('abrir-notifs')]
    public function abrir(int $clientId): void
    {
        $client = Client::findOrFail($clientId);

        $this->clientId = $clientId;
        $this->clientName = trim("{$client->apellido_pat} {$client->apellido_mat} {$client->nombre}");
        $this->telefono = preg_replace('/\D/', '', (string) $client->celular1);
        $this->vencidas = $this->cuotasVencidasDe($clientId);
        $this->editor = false;
        $this->texto = '';
        $this->compNotifId = null;
        $this->resetErrorBag();

        $this->dispatch('notif-open');
    }

    /**
     * Abre el editor precargado según el NIVEL de morosidad actual:
     * el último mensaje enviado del mismo nivel (2 vencidas vs 3+), o la
     * plantilla base de ese nivel si nunca se envió una. Así, si el cliente
     * se puso al día y recae, vuelve a salir el mensaje del nivel que toca.
     */
    public function nuevaNotif(): void
    {
        // Recalcula AL MOMENTO del click (pudo cambiar desde que se abrió el modal)
        $this->vencidas = $this->cuotasVencidasDe($this->clientId);
        $nivel3 = $this->vencidas >= 3;

        $q = DB::table('client_notifications')->where('client_id', $this->clientId);
        $nivel3
            ? $q->where('cuotas_vencidas', '>=', 3)
            : $q->where('cuotas_vencidas', 2);
        $ultima = $q->orderByDesc('numero')->value('mensaje');

        $this->texto = $ultima ?? ($nivel3
            ? "⚖️ *COMUNICADO DE REGULARIZACIÓN DE PAGO*\nEstimado(a) Sr.(a) {$this->clientName}:\n"
            : "⚠️ *AVISO PREVENTIVO DE INCUMPLIMIENTO CONTRACTUAL*\nEstimado(a) Sr.(a) {$this->clientName}:\n");
        $this->editor = true;
    }

    /** Guarda la notificación (correlativo por cliente) y abre WhatsApp con el texto. */
    public function enviarNotif(): void
    {
        $this->validate(
            ['texto' => 'required|string|max:20000'],
            ['texto.required' => 'Escribe el mensaje a enviar.']
        );
        if (! $this->clientId || $this->telefono === '') {
            $this->dispatch('errorAlert', ['message' => 'El cliente no tiene número de celular.']);

            return;
        }

        DB::transaction(function () {
            $numero = (int) DB::table('client_notifications')
                ->where('client_id', $this->clientId)
                ->lockForUpdate()
                ->max('numero') + 1;

            DB::table('client_notifications')->insert([
                'client_id' => $this->clientId,
                'user_id' => auth()->id(),
                'numero' => $numero,
                'mensaje' => $this->texto,
                'telefono' => $this->telefono,
                'cuotas_vencidas' => $this->vencidas,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->editor = false;
        $url = 'https://api.whatsapp.com/send?phone=51'.$this->telefono.'&text='.rawurlencode($this->texto);
        $this->dispatch('notif-wa', url: $url);
        // Aviso al padre para refrescar el check ✓ "enviado hoy" de la fila
        $this->dispatch('notif-enviada');
    }

    /** Abre el mini-form de compromiso de una notificación (precarga si ya tiene). */
    public function abrirCompromiso(int $notifId): void
    {
        $n = DB::table('client_notifications')
            ->where('id', $notifId)->where('client_id', $this->clientId)->first();
        if (! $n) {
            return;
        }

        $this->compNotifId = $notifId;
        $this->compFecha = $n->compromiso_fecha ?? '';
        $this->compDetalle = (string) ($n->compromiso_detalle ?? '');
        $this->resetErrorBag();
    }

    public function guardarCompromiso(): void
    {
        $this->validate(
            ['compFecha' => 'required|date', 'compDetalle' => 'nullable|string|max:5000'],
            ['compFecha.required' => 'Indica la fecha en que se compromete a pagar.']
        );

        DB::table('client_notifications')
            ->where('id', $this->compNotifId)->where('client_id', $this->clientId)
            ->update([
                'compromiso_fecha' => $this->compFecha,
                'compromiso_detalle' => $this->compDetalle !== '' ? $this->compDetalle : null,
                'compromiso_user_id' => auth()->id(),
                'compromiso_registrado_at' => now(),
                'compromiso_cumplido_at' => null,
                'updated_at' => now(),
            ]);

        $this->compNotifId = null;
        $this->dispatch('successAlert', ['message' => 'Compromiso de pago guardado.']);
    }

    public function render()
    {
        $notifs = $this->clientId
            ? DB::table('client_notifications as n')
                ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
                ->where('n.client_id', $this->clientId)
                ->orderByDesc('n.numero')
                ->get(['n.*', 'u.username as usuario', 'u.name as usuario_name'])
            : collect();

        return view('livewire.clients.notifications-modal', compact('notifs'));
    }
}
