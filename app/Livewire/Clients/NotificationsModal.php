<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Support\Garantias;
use App\Support\NumerosEnLetras;
use Carbon\Carbon;
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

    /** Crédito seleccionado para notificar (auto si solo hay uno atrasado). */
    public ?int $creditId = null;

    /** Créditos atrasados (≥2 vencidas) del cliente, para el selector. */
    public array $creditosAtrasados = [];

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

    /** Cuotas vencidas impagas de UN crédito, a hoy. */
    private function cuotasVencidasDeCredito(?int $creditId): int
    {
        if (! $creditId) {
            return 0;
        }

        return (int) DB::table('credit_installments')
            ->where('credit_id', $creditId)
            ->where('pagado', 0)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now()->format('Y-m-d'))
            ->count();
    }

    /**
     * Créditos activos atrasados (≥2 vencidas, el umbral de notificación)
     * con lo necesario para el selector: vencidas, monto atrasado y la
     * última notificación enviada de ese crédito.
     */
    private function creditosAtrasadosDe(int $clientId): array
    {
        $rows = DB::table('credits as c')
            ->join('credit_installments as i', 'i.credit_id', '=', 'c.id')
            ->where('c.client_id', $clientId)
            ->where('c.situacion', 'Activo')
            ->where('i.pagado', 0)
            ->whereNotNull('i.fecha_vencimiento')
            ->where('i.fecha_vencimiento', '<', now()->format('Y-m-d'))
            ->groupBy('c.id', 'c.tipo_planilla')
            ->havingRaw('COUNT(*) >= 2')
            ->selectRaw('c.id, c.tipo_planilla, COUNT(*) vencidas,
                SUM(i.importe_cuota + i.importe_interes + i.importe_excedente
                    - i.importe_aplicado - i.interes_aplicado - i.excedente_aplicado) atrasado')
            ->orderByDesc('vencidas')
            ->get();

        $ultimas = DB::table('client_notifications')
            ->where('client_id', $clientId)
            ->whereIn('credit_id', $rows->pluck('id'))
            ->selectRaw('credit_id, MAX(numero) numero, MAX(created_at) fecha')
            ->groupBy('credit_id')
            ->get()->keyBy('credit_id');

        return $rows->map(function ($r) use ($ultimas) {
            $u = $ultimas->get($r->id);

            return [
                'id' => (int) $r->id,
                'tipo' => match ((int) $r->tipo_planilla) {
                    1 => 'Semanal', 3 => 'Mensual', 4 => 'Diario', default => '—',
                },
                'vencidas' => (int) $r->vencidas,
                'atrasado' => round((float) $r->atrasado, 2),
                'ultima' => $u ? 'N° '.$u->numero.' del '.Carbon::parse($u->fecha)->format('d/m/Y') : null,
            ];
        })->values()->all();
    }

    #[On('abrir-notifs')]
    public function abrir(int $clientId): void
    {
        $client = Client::findOrFail($clientId);

        $this->clientId = $clientId;
        $this->clientName = trim("{$client->apellido_pat} {$client->apellido_mat} {$client->nombre}");
        $this->telefono = preg_replace('/\D/', '', (string) $client->celular1);
        $this->vencidas = $this->cuotasVencidasDe($clientId);
        // Multi-crédito: con un solo atrasado se auto-selecciona (misma
        // experiencia de siempre); con varios, el selector obliga a elegir.
        $this->creditosAtrasados = $this->creditosAtrasadosDe($clientId);
        $this->creditId = count($this->creditosAtrasados) === 1 ? $this->creditosAtrasados[0]['id'] : null;
        if ($this->creditId) {
            $this->vencidas = $this->creditosAtrasados[0]['vencidas'];
        }
        $this->editor = false;
        $this->texto = '';
        $this->compNotifId = null;
        $this->resetErrorBag();

        $this->dispatch('notif-open');
    }

    /** Selecciona el crédito a notificar (solo créditos del selector). */
    public function seleccionarCredito(int $creditId): void
    {
        $sel = collect($this->creditosAtrasados)->firstWhere('id', $creditId);
        if (! $sel) {
            return;
        }

        $this->creditId = $creditId;
        $this->vencidas = $sel['vencidas'];
        $this->editor = false;
        $this->texto = '';
    }

    /**
     * Abre el editor precargado según el NIVEL de morosidad actual:
     * el último mensaje enviado del mismo nivel (2 vencidas vs 3+), o la
     * plantilla base de ese nivel si nunca se envió una. Así, si el cliente
     * se puso al día y recae, vuelve a salir el mensaje del nivel que toca.
     */
    public function nuevaNotif(): void
    {
        if (! $this->creditId) {
            $this->dispatch('errorAlert', ['message' => 'Selecciona el crédito a notificar.']);

            return;
        }

        // Recalcula AL MOMENTO del click (pudo cambiar desde que se abrió el modal)
        $this->vencidas = $this->cuotasVencidasDeCredito($this->creditId);
        $nivel3 = $this->vencidas >= 3;

        // Reutiliza el último mensaje del mismo nivel DE ESTE CRÉDITO (así el
        // texto recuperado ya trae el número de crédito correcto).
        $q = DB::table('client_notifications')
            ->where('client_id', $this->clientId)
            ->where('credit_id', $this->creditId);
        $nivel3
            ? $q->where('cuotas_vencidas', '>=', 3)
            : $q->where('cuotas_vencidas', 2);
        $ultima = $q->orderByDesc('numero')->value('mensaje');

        $this->texto = $ultima ?? ($nivel3
            ? $this->plantillaNivel3()
            : $this->plantillaDosCuotas());
        $this->editor = true;
    }

    /**
     * Plantilla de nivel 3+ según la GARANTÍA del cliente (prefijo del campo
     * "T. Crédito" = clients.zona): requerimiento final vehicular SIGM,
     * requerimiento final hipotecario, o el comunicado genérico si la
     * garantía no es ninguna de las dos. Texto legal provisto por el área
     * legal (21/08); queda editable antes de enviar.
     */
    private function plantillaNivel3(): string
    {
        $client = Client::find($this->clientId);
        $garantia = Garantias::de($client?->zona);

        if ($garantia === Garantias::OTRA) {
            return "⚖️ *COMUNICADO DE REGULARIZACIÓN DE PAGO*\nEstimado(a) Sr.(a) {$this->clientName}:\nReferencia: Crédito N° {$this->creditId}\n";
        }

        // Variables del requerimiento
        $nombre = mb_strtoupper(trim(($client->nombre ?? '').' '.($client->apellido_pat ?? '').' '.($client->apellido_mat ?? '')));
        $fem = ($client->sexo ?? '') === 'F';
        $estimado = $fem ? 'Estimada' : 'Estimado';
        $notificado = $fem ? 'notificada' : 'notificado';
        $cuotas = NumerosEnLetras::conteo($this->vencidas);

        // Monto atrasado = lo impago de las cuotas vencidas (el "Retraso")
        $retraso = round((float) DB::table('credit_installments')
            ->where('credit_id', $this->creditId)
            ->where('pagado', 0)
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', now()->format('Y-m-d'))
            ->selectRaw('COALESCE(SUM(importe_cuota + importe_interes + importe_excedente
                - importe_aplicado - interes_aplicado - excedente_aplicado), 0) as s')
            ->value('s'), 2);
        $monto = 'S/ '.number_format($retraso, 2).' ('.NumerosEnLetras::monto($retraso).')';

        $cuerpoComun = "Por medio de la presente, se le comunica que, de la revisión de su crédito, se ha verificado el incumplimiento del pago de *{$cuotas} CUOTAS VENCIDAS*, manteniendo un monto total atrasado de *{$monto}*, más los intereses moratorios y demás conceptos que correspondan conforme al contrato.\n\n"
            ."En tal sentido, se le *REQUIERE DE MANERA FINAL E IMPRORROGABLE* que, dentro del plazo máximo de *CUARENTA Y OCHO (48) HORAS*, contadas desde la recepción del presente comunicado, proceda a *regularizar íntegramente las cuotas vencidas y el monto pendiente de pago*, o presente una propuesta formal de pago para evaluación exclusiva del acreedor.\n\n";

        $firma = "Atentamente,\n*⚖️Abog. Rosa Linda Tafur Cuenca*\n*Área Legal Huacachin*";

        if ($garantia === Garantias::VEHICULAR) {
            return "*REQUERIMIENTO FINAL DE REGULARIZACIÓN DE PAGO - GARANTIA VEHICULAR SIGM⚖️*\n"
                ."{$estimado} sr(a) *{$nombre}*:\nReferencia: Crédito N° {$this->creditId}\n"
                .$cuerpoComun
                ."De conformidad con lo pactado en el contrato de credito vehicular con Garantia Mobiliaria en el SIGM, así como de lo dispuesto en el artículo 1323° del Código Civil; se le *APERCIBE EXPRESAMENTE* que, de no cumplir con la regularización dentro del plazo otorgado, el acreedor podrá declarar el *VENCIMIENTO ANTICIPADO* de la obligación, quedando vencidas y exigibles la totalidad de las cuotas pendientes de pago, e iniciar de manera inmediata la *EJECUCIÓN EXTRAJUDICIAL DE LA GARANTÍA MOBILIARIA*, incluyendo la *TOMA DE POSESIÓN DEL VEHÍCULO*, conforme a lo pactado contractualmente y a la normativa vigente, sin necesidad de cursar un nuevo requerimiento.\n\n"
                ."Asimismo, cualquier pago parcial efectuado luego del vencimiento del plazo otorgado no suspenderá ni dejará sin efecto las acciones correspondientes, salvo aceptación expresa del acreedor.\n\n"
                ."La presente constituye el *ÚLTIMO REQUERIMIENTO PREVIO A LA EJECUCIÓN EXTRAJUDICIAL Y TOMA DE POSESIÓN DEL VEHÍCULO*, quedando usted debidamente {$notificado} para los fines legales correspondientes.\n\n"
                .$firma;
        }

        return "*REQUERIMIENTO FINAL DE REGULARIZACIÓN DE PAGO - GARANTIA HIPOTECARIA⚖️*\n"
            ."{$estimado} sr(a) *{$nombre}*:\nReferencia: Crédito N° {$this->creditId}\n"
            .$cuerpoComun
            ."Se le *APERCIBE EXPRESAMENTE* que, de no cumplir con la regularización dentro del plazo otorgado, de conformidad con lo pactado contractualmente y con el *artículo 1323° del Código Civil*, el incumplimiento de tres (03) cuotas faculta al acreedor a *DECLARAR EL VENCIMIENTO ANTICIPADO DE LA OBLIGACIÓN Y EXIGIR EL PAGO INMEDIATO DEL SALDO TOTAL PENDIENTE*, dándose por vencidas y exigibles las cuotas que se encontraban pendientes de vencimiento.\n\n"
            ."En consecuencia, el acreedor podrá iniciar de manera inmediata la *EJECUCIÓN DE LA GARANTÍA HIPOTECARIA*, a efectos de hacer efectivo el cobro de la totalidad de la obligación mediante el *REMATE DEL INMUEBLE HIPOTECADO*, conforme a lo pactado contractualmente y a la normativa vigente, sin necesidad de cursar un nuevo requerimiento.\n\n"
            ."Asimismo, cualquier pago parcial efectuado luego del vencimiento del plazo otorgado *no suspenderá ni dejará sin efecto el vencimiento anticipado ni las acciones de ejecución de la garantía*, salvo aceptación expresa del acreedor.\n\n"
            ."La presente constituye el *ÚLTIMO REQUERIMIENTO PREVIO A LA EJECUCIÓN DE LA GARANTÍA HIPOTECARIA Y REMATE DEL INMUEBLE*, quedando usted debidamente {$notificado} para los fines legales correspondientes.\n\n"
            .$firma;
    }

    /**
     * Aviso preventivo para 2 cuotas vencidas. Queda editable antes de enviar:
     * esto es el punto de partida, no un texto cerrado.
     *
     * Los *asteriscos* son la negrita de WhatsApp, no un error de formato.
     */
    private function plantillaDosCuotas(): string
    {
        return <<<TEXTO
            ⚠️ *AVISO PREVENTIVO DE INCUMPLIMIENTO CONTRACTUAL*
            Estimado(a) Sr.(a) {$this->clientName}:
            Se le comunica que, a la fecha, mantiene *DOS (02) CUOTAS VENCIDAS E IMPAGAS* correspondientes a su crédito N° {$this->creditId} con constitución de garantía mobiliaria inscrita en el Sistema Informativo de Garantías Mobiliarias – SIGM.
            Conforme a lo establecido en su contrato, al incurrir en el incumplimiento de la *TERCERA CUOTA*, sea sucesiva o no, se declarará el vencimiento anticipado de la obligación y se activará el procedimiento de ejecución de la garantía mobiliaria, de conformidad con el Decreto Legislativo N.° 1400.
            En tal sentido, se le otorga un plazo máximo de *CUARENTA Y OCHO (48) HORAS* para regularizar las cuotas vencidas. De no efectuarse el pago dentro del plazo señalado, su expediente será derivado inmediatamente al Área Legal para iniciar las acciones contractuales y legales correspondientes.
            📞 Para coordinar la regularización, comuníquese con el Área de Cobranza al 982 333 689.
            Lic. Licet Tafur Collantes
            Área de Cobranza
            TEXTO;
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
        if (! $this->creditId) {
            $this->dispatch('errorAlert', ['message' => 'Selecciona el crédito a notificar.']);

            return;
        }

        DB::transaction(function () {
            // Correlativo POR CRÉDITO: la escalada (aviso 1, 2, legal) es
            // contractual de cada crédito. El historial viejo sin credit_id
            // conserva su numeración por cliente y no interfiere aquí.
            $numero = (int) DB::table('client_notifications')
                ->where('client_id', $this->clientId)
                ->where('credit_id', $this->creditId)
                ->lockForUpdate()
                ->max('numero') + 1;

            DB::table('client_notifications')->insert([
                'client_id' => $this->clientId,
                'credit_id' => $this->creditId,
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
        // Orden por fecha: los correlativos ahora son por crédito y ya no
        // definen un orden global del historial del cliente.
        $notifs = $this->clientId
            ? DB::table('client_notifications as n')
                ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
                ->where('n.client_id', $this->clientId)
                ->orderByDesc('n.created_at')->orderByDesc('n.id')
                ->get(['n.*', 'u.username as usuario', 'u.name as usuario_name'])
            : collect();

        return view('livewire.clients.notifications-modal', compact('notifs'));
    }
}
