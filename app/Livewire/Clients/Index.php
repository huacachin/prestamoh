<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url(as: 'expediente', except: '')]
    public $nexpediente = '';

    #[Url(as: 'documento', except: '')]
    public $documento = '';

    #[Url(as: 'nombre', except: '')]
    public $nombre = '';

    #[Url(as: 'ruta', except: '')]
    public $ruta = '';

    #[Url(as: 'giro', except: '')]
    public $giro = '';

    #[Url(as: 'asesor', except: '')]
    public $ejecutivo = '';

    /** Filtro de morosidad: '' (todos) | 'aldia' | 'naranja' (2 venc.) | 'rojo' (3+). */
    #[Url(as: 'estado', except: '')]
    public $morosidadFiltro = '';

    /** Toggle del chip: click en el activo lo desactiva. */
    public function filtrarMorosidad(string $estado): void
    {
        $this->morosidadFiltro = $this->morosidadFiltro === $estado ? '' : $estado;
    }

    // ─── Modal de notificaciones WhatsApp (cobranza) ───────────────────

    public ?int $notifClientId = null;

    public string $notifClientName = '';

    public string $notifTelefono = '';

    public int $notifVencidas = 0;

    /** Editor de "nueva notificación" visible dentro del modal. */
    public bool $notifEditor = false;

    public string $notifTexto = '';

    // Compromiso de pago (edición inline por notificación)
    public ?int $compNotifId = null;

    public string $compFecha = '';

    public string $compDetalle = '';

    /** Abre el modal de notificaciones del cliente (botón WhatsApp de la fila). */
    public function abrirNotifs(int $clientId): void
    {
        $client = Client::findOrFail($clientId);

        $this->notifClientId = $clientId;
        $this->notifClientName = trim("{$client->apellido_pat} {$client->apellido_mat} {$client->nombre}");
        $this->notifTelefono = preg_replace('/\D/', '', (string) $client->celular1);
        $this->notifVencidas = (int) (DB::table('credits as c')
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
        $this->notifEditor = false;
        $this->notifTexto = '';
        $this->compNotifId = null;
        $this->resetErrorBag();

        $this->dispatch('notif-open');
    }

    /** Abre el editor precargado: último texto enviado, o la plantilla base. */
    public function nuevaNotif(): void
    {
        $ultima = DB::table('client_notifications')
            ->where('client_id', $this->notifClientId)
            ->orderByDesc('numero')
            ->value('mensaje');

        $this->notifTexto = $ultima
            ?? "⚠️ AVISO PREVENTIVO DE INCUMPLIMIENTO CONTRACTUAL\nEstimado(a) Sr.(a) {$this->notifClientName}:\n";
        $this->notifEditor = true;
    }

    /** Guarda la notificación (correlativo por cliente) y abre WhatsApp con el texto. */
    public function enviarNotif(): void
    {
        $this->validate(
            ['notifTexto' => 'required|string|max:20000'],
            ['notifTexto.required' => 'Escribe el mensaje a enviar.']
        );
        if (! $this->notifClientId || $this->notifTelefono === '') {
            $this->dispatch('errorAlert', ['message' => 'El cliente no tiene número de celular.']);

            return;
        }

        DB::transaction(function () {
            $numero = (int) DB::table('client_notifications')
                ->where('client_id', $this->notifClientId)
                ->lockForUpdate()
                ->max('numero') + 1;

            DB::table('client_notifications')->insert([
                'client_id' => $this->notifClientId,
                'user_id' => auth()->id(),
                'numero' => $numero,
                'mensaje' => $this->notifTexto,
                'telefono' => $this->notifTelefono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->notifEditor = false;
        $url = 'https://api.whatsapp.com/send?phone=51'.$this->notifTelefono.'&text='.rawurlencode($this->notifTexto);
        $this->dispatch('notif-wa', url: $url);
    }

    /** Abre el mini-form de compromiso de una notificación (precarga si ya tiene). */
    public function abrirCompromiso(int $notifId): void
    {
        $n = DB::table('client_notifications')
            ->where('id', $notifId)->where('client_id', $this->notifClientId)->first();
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
            ->where('id', $this->compNotifId)->where('client_id', $this->notifClientId)
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

    // Modal de coordenadas (Casa / Negocio)
    public ?int $coordClientId = null;

    public string $coordTipo = '';        // 'casa' | 'negocio'

    public ?string $coordClientName = null;

    public string $coordPaste = '';

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (! auth()->user()?->can('clientes.eliminar')) {
            abort(403);
        }
        Client::findOrFail($id)->update(['status' => 'inactive']);
        $this->dispatch('successAlert', ['message' => 'Cliente desactivado correctamente']);
    }

    /** Mismo gate que el botón Guardar de /clients/edit. */
    private function puedeGuardarCoords(): bool
    {
        return ! (auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    public function openCoord(int $id, string $tipo): void
    {
        if (! $this->puedeGuardarCoords()) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para registrar coordenadas.']);

            return;
        }
        if (! in_array($tipo, ['casa', 'negocio'], true)) {
            return;
        }

        $client = Client::select('id', 'apellido_pat', 'apellido_mat', 'nombre')->findOrFail($id);

        $this->coordClientId = $client->id;
        $this->coordTipo = $tipo;
        $this->coordClientName = trim("{$client->apellido_pat} {$client->apellido_mat} {$client->nombre}");
        $this->coordPaste = '';
        $this->resetErrorBag();

        $this->dispatch('coord-open');
    }

    public function saveCoord(): void
    {
        if (! $this->puedeGuardarCoords()) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para registrar coordenadas.']);

            return;
        }
        if (! $this->coordClientId || ! in_array($this->coordTipo, ['casa', 'negocio'], true)) {
            return;
        }

        $coords = $this->parseCoords($this->coordPaste);
        if (! $coords) {
            $this->dispatch('errorAlert', ['message' => 'Formato inválido. Pega las coordenadas como: -12.014431, -76.824936']);

            return;
        }
        [$lat, $lng] = $coords;

        $client = Client::findOrFail($this->coordClientId);
        if ($this->coordTipo === 'casa') {
            $client->update(['latitud' => $lat, 'longitud' => $lng]);
            $etiqueta = 'Casa';
        } else {
            $client->update(['latitud2' => $lat, 'longitud2' => $lng]);
            $etiqueta = 'Negocio';
        }

        Audit::log("Registró coordenadas ({$etiqueta}) del cliente ".$client->fullName(), $client);

        $this->dispatch('coord-close');
        $this->reset(['coordClientId', 'coordTipo', 'coordClientName', 'coordPaste']);
        $this->dispatch('successAlert', ['message' => "Coordenadas de {$etiqueta} guardadas correctamente"]);
    }

    /**
     * Normaliza un texto pegado a [lat, lng]. Acepta "lat, lng", separados por
     * espacio, paréntesis o incluso una URL de Google Maps. Solo considera
     * números con decimales (ignora enteros sueltos como el zoom "17z" o números
     * de dirección). Devuelve null si no hay 2 coordenadas válidas en rango.
     */
    private function parseCoords(string $raw): ?array
    {
        preg_match_all('/-?\d+\.\d+/', trim($raw), $m);
        $nums = $m[0] ?? [];
        if (count($nums) < 2) {
            return null;
        }

        $lat = (float) $nums[0];
        $lng = (float) $nums[1];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [round($lat, 7), round($lng, 7)];
    }

    public function render()
    {
        $user = auth()->user();

        $query = Client::query()
            ->where('status', 'active')
            ->with(['asesor:id,name,username', 'headquarter:id,name'])
            ->withCount(['avales', 'attachments']);

        if ($user->can('clientes.scope-propio')) {
            $query->where('asesor_id', $user->id);
        }

        // Filtros individuales
        if (trim($this->documento) !== '') {
            $query->where('documento', trim($this->documento));
        }
        if (trim($this->nombre) !== '') {
            // Cada palabra debe aparecer en algún campo de nombre. Así "Obregon
            // Lopez Fernando" (nombre completo, repartido en 3 columnas) calza,
            // en cualquier orden, en vez de exigir la frase entera en una columna.
            foreach (preg_split('/\s+/', trim($this->nombre)) as $word) {
                if ($word === '') {
                    continue;
                }
                $query->where(function ($q) use ($word) {
                    $q->where('nombre', 'like', "%{$word}%")
                        ->orWhere('apellido_pat', 'like', "%{$word}%")
                        ->orWhere('apellido_mat', 'like', "%{$word}%");
                });
            }
        }
        if (trim($this->nexpediente) !== '') {
            $query->where('expediente', trim($this->nexpediente));
        }
        if (trim($this->ejecutivo) !== '') {
            if ($this->ejecutivo === 'Ninguno') {
                $query->whereNull('asesor_id');
            } else {
                $query->where('asesor_id', $this->ejecutivo);
            }
        }
        if (trim($this->ruta) !== '') {
            $query->where('zona', 'like', '%'.trim($this->ruta).'%');
        }
        if (trim($this->giro) !== '') {
            $query->where('giro', 'like', '%'.trim($this->giro).'%');
        }

        $clients = $query->orderByRaw('CAST(expediente AS UNSIGNED) ASC')->get();

        // Asesores para dropdown: cualquier usuario que pueda ser "asesor responsable" o tenga acceso amplio.
        $asesores = User::permission('creditos.ser-asesor-responsable')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        // IDs de clientes con crédito vigente (para colorear)
        $clientIds = $clients->pluck('id')->toArray();
        $clientsWithCredit = [];
        $morosidad = [];
        if (! empty($clientIds)) {
            $clientsWithCredit = Credit::whereIn('client_id', $clientIds)
                ->where('situacion', 'Activo')
                ->distinct()
                ->pluck('client_id')
                ->flip()
                ->toArray();

            // Morosidad: cuotas vencidas impagas por crédito activo; por cliente
            // se toma el PEOR de sus créditos (2 → fila naranja, 3+ → fila roja).
            $rows = DB::table('credits as c')
                ->join('credit_installments as i', 'i.credit_id', '=', 'c.id')
                ->whereIn('c.client_id', $clientIds)
                ->where('c.situacion', 'Activo')
                ->where('i.pagado', 0)
                ->whereNotNull('i.fecha_vencimiento')
                ->where('i.fecha_vencimiento', '<', now()->format('Y-m-d'))
                ->groupBy('c.id', 'c.client_id')
                ->selectRaw('c.client_id, COUNT(*) as vencidas')
                ->get();
            foreach ($rows as $r) {
                $morosidad[$r->client_id] = max($morosidad[$r->client_id] ?? 0, (int) $r->vencidas);
            }
        }

        // Conteos para los chips (sobre la lista ya filtrada por los demás criterios)
        $vencDe = fn ($c) => $morosidad[$c->id] ?? 0;
        $countRojo = $clients->filter(fn ($c) => $vencDe($c) >= 3)->count();
        $countNaranja = $clients->filter(fn ($c) => $vencDe($c) === 2)->count();
        $countAldia = $clients->count() - $countRojo - $countNaranja;

        // Aplicar el chip seleccionado
        $clients = match ($this->morosidadFiltro) {
            'rojo' => $clients->filter(fn ($c) => $vencDe($c) >= 3)->values(),
            'naranja' => $clients->filter(fn ($c) => $vencDe($c) === 2)->values(),
            'aldia' => $clients->filter(fn ($c) => $vencDe($c) < 2)->values(),
            default => $clients,
        };

        // Clientes con notificación WhatsApp enviada HOY (check compartido)
        $waEnviadosHoy = [];
        if ($clients->isNotEmpty()) {
            $waEnviadosHoy = DB::table('client_notifications')
                ->whereDate('created_at', now()->format('Y-m-d'))
                ->whereIn('client_id', $clients->pluck('id'))
                ->distinct()
                ->pluck('client_id')
                ->flip()
                ->toArray();
        }

        // Historial del modal de notificaciones (solo si está abierto)
        $notifs = $this->notifClientId
            ? DB::table('client_notifications as n')
                ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
                ->where('n.client_id', $this->notifClientId)
                ->orderByDesc('n.numero')
                ->get(['n.*', 'u.username as usuario', 'u.name as usuario_name'])
            : collect();

        $puedeCoords = $this->puedeGuardarCoords();

        return view('livewire.clients.index', compact('clients', 'asesores', 'clientsWithCredit', 'morosidad', 'puedeCoords', 'countAldia', 'countNaranja', 'countRojo', 'waEnviadosHoy', 'notifs'));
    }
}
