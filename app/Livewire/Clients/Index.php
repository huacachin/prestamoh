<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public $nexpediente = '';
    public $documento = '';
    public $nombre = '';
    public $ruta = '';
    public $giro = '';
    public $ejecutivo = '';

    // Modal de coordenadas (Casa / Negocio)
    public ?int $coordClientId = null;
    public string $coordTipo = '';        // 'casa' | 'negocio'
    public ?string $coordClientName = null;
    public string $coordPaste = '';

    #[On('register_destroy')]
    public function destroy(int $id): void
    {
        if (!auth()->user()?->can('clientes.eliminar')) {
            abort(403);
        }
        Client::findOrFail($id)->update(['status' => 'inactive']);
        $this->dispatch('successAlert', ['message' => 'Cliente desactivado correctamente']);
    }

    /** Mismo gate que el botón Guardar de /clients/edit. */
    private function puedeGuardarCoords(): bool
    {
        return !(auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    public function openCoord(int $id, string $tipo): void
    {
        if (!$this->puedeGuardarCoords()) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para registrar coordenadas.']);
            return;
        }
        if (!in_array($tipo, ['casa', 'negocio'], true)) {
            return;
        }

        $client = Client::select('id', 'apellido_pat', 'apellido_mat', 'nombre')->findOrFail($id);

        $this->coordClientId   = $client->id;
        $this->coordTipo       = $tipo;
        $this->coordClientName = trim("{$client->apellido_pat} {$client->apellido_mat} {$client->nombre}");
        $this->coordPaste      = '';
        $this->resetErrorBag();

        $this->dispatch('coord-open');
    }

    public function saveCoord(): void
    {
        if (!$this->puedeGuardarCoords()) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para registrar coordenadas.']);
            return;
        }
        if (!$this->coordClientId || !in_array($this->coordTipo, ['casa', 'negocio'], true)) {
            return;
        }

        $coords = $this->parseCoords($this->coordPaste);
        if (!$coords) {
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

        \App\Support\Audit::log("Registró coordenadas ({$etiqueta}) del cliente ".$client->fullName(), $client);

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
            $query->where('zona', 'like', '%' . trim($this->ruta) . '%');
        }
        if (trim($this->giro) !== '') {
            $query->where('giro', 'like', '%' . trim($this->giro) . '%');
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
        if (!empty($clientIds)) {
            $clientsWithCredit = \App\Models\Credit::whereIn('client_id', $clientIds)
                ->where('situacion', 'Activo')
                ->distinct()
                ->pluck('client_id')
                ->flip()
                ->toArray();
        }

        $puedeCoords = $this->puedeGuardarCoords();

        return view('livewire.clients.index', compact('clients', 'asesores', 'clientsWithCredit', 'puedeCoords'));
    }
}
