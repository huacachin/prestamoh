<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientAval;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Component;

class Aval extends Component
{
    public Client $client;
    public int $clientId;

    public string $dni = '';
    public string $nombre = '';
    public ?string $direccion = null;
    public ?string $telefono = null;

    public ?string $dniMsg = null;
    public ?string $dniMsgType = null; // 'ok' | 'warn' | 'err'

    public bool $puedeEditar = true;

    protected function rules(): array
    {
        return [
            'dni'       => 'required|string|min:8|max:11',
            'nombre'    => 'required|string|max:200',
            'direccion' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:20',
        ];
    }

    public function mount(int $id): void
    {
        $this->client   = Client::findOrFail($id);
        $this->clientId = $id;
        $this->puedeEditar = !(auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    /**
     * Busca el DNI: primero en BD local (clients), luego en migo.pe si no se encuentra.
     */
    public function consultarDni(): void
    {
        $this->dniMsg = null;
        $this->dniMsgType = null;

        $doc = preg_replace('/\D+/', '', (string) $this->dni);
        $this->dni = $doc;

        if (strlen($doc) !== 8 && strlen($doc) !== 11) {
            $this->dniMsgType = 'err';
            $this->dniMsg = 'Ingrese DNI (8 dígitos) o RUC (11 dígitos).';
            return;
        }

        // Bloquear self-aval
        if ($doc === $this->client->documento) {
            $this->dniMsgType = 'err';
            $this->dniMsg = 'El cliente no puede ser aval de sí mismo.';
            $this->resetCamposAval();
            return;
        }

        // Bloquear duplicado para este cliente
        $exists = ClientAval::where('client_id', $this->clientId)->where('dni', $doc)->exists();
        if ($exists) {
            $this->dniMsgType = 'warn';
            $this->dniMsg = 'Este DNI ya está registrado como aval de este cliente.';
            $this->resetCamposAval();
            return;
        }

        // 1. Buscar en BD local
        $clienteLocal = Client::where('documento', $doc)->first();
        if ($clienteLocal) {
            $this->nombre    = $clienteLocal->fullName();
            $this->direccion = $clienteLocal->direccion;
            $this->telefono  = $clienteLocal->celular1;
            $this->dniMsgType = 'ok';
            $this->dniMsg = 'Encontrado en BD local: ' . $clienteLocal->fullName();
            return;
        }

        // 2. Buscar en migo.pe
        $token = config('services.migo.token');
        $base  = rtrim((string) config('services.migo.base'), '/');
        if (!$token) {
            $this->dniMsgType = 'warn';
            $this->dniMsg = 'No está en BD local. Ingrese los datos manualmente (RENIEC no configurado).';
            return;
        }

        try {
            $endpoint = strlen($doc) === 8 ? '/dni' : '/ruc';
            $payload  = strlen($doc) === 8 ? ['token' => $token, 'dni' => $doc] : ['token' => $token, 'ruc' => $doc];

            $resp = Http::timeout(8)->acceptJson()->asJson()->post("$base$endpoint", $payload);
            if (!$resp->ok()) {
                $this->dniMsgType = 'warn';
                $this->dniMsg = "No está en BD local y RENIEC respondió HTTP {$resp->status()}. Ingrese a mano.";
                return;
            }

            $j = $resp->json();
            if (empty($j) || (isset($j['success']) && $j['success'] === false)) {
                $this->dniMsgType = 'warn';
                $this->dniMsg = 'No está en BD local ni en RENIEC. Ingrese los datos manualmente.';
                return;
            }

            if (strlen($doc) === 8) {
                // DNI: campo único 'nombre' con "APELLIDOPAT APELLIDOMAT NOMBRES"
                $full = trim((string) ($j['nombre'] ?? ''));
                $this->nombre = mb_convert_case($full, MB_CASE_TITLE, 'UTF-8');
            } else {
                // RUC: razón social entera
                $razon = trim((string) ($j['nombre_o_razon_social'] ?? ''));
                $this->nombre = $razon;
                $direc = trim((string) ($j['direccion_simple'] ?? $j['direccion'] ?? ''));
                if ($direc !== '') {
                    $this->direccion = mb_convert_case($direc, MB_CASE_TITLE, 'UTF-8');
                }
            }
            $this->dniMsgType = 'ok';
            $this->dniMsg = 'Cargado desde RENIEC. Verifique antes de guardar.';
        } catch (\Throwable $e) {
            $this->dniMsgType = 'warn';
            $this->dniMsg = 'No está en BD local y RENIEC falló: ' . $e->getMessage() . '. Ingrese a mano.';
        }
    }

    private function resetCamposAval(): void
    {
        $this->nombre = '';
        $this->direccion = null;
        $this->telefono = null;
    }

    public function save()
    {
        if (!$this->puedeEditar) {
            $this->dispatch('errorAlert', ['message' => 'No tienes permiso para agregar avales.']);
            return;
        }

        $this->validate();

        $doc = preg_replace('/\D+/', '', (string) $this->dni);

        // Re-validar bloqueos en server (race condition)
        if ($doc === $this->client->documento) {
            $this->addError('dni', 'El cliente no puede ser aval de sí mismo.');
            return;
        }
        if (ClientAval::where('client_id', $this->clientId)->where('dni', $doc)->exists()) {
            $this->addError('dni', 'Este DNI ya está registrado como aval de este cliente.');
            return;
        }

        ClientAval::create([
            'client_id' => $this->clientId,
            'dni'       => $doc,
            'nombre'    => $this->nombre,
            'direccion' => $this->direccion,
            'telefono'  => $this->telefono,
        ]);

        $this->resetCamposAval();
        $this->dni = '';
        $this->dniMsg = null;
        $this->dniMsgType = null;
        $this->resetErrorBag();

        $this->dispatch('successAlert', ['message' => 'Aval agregado.']);
    }

    public function questionDelete(int $id): void
    {
        if (!$this->puedeEditar) return;
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function deleteAval(int $id): void
    {
        if (!$this->puedeEditar) return;
        ClientAval::where('client_id', $this->clientId)->where('id', $id)->delete();
        // El custom.js ya muestra "Eliminado!" — no dispatcheamos successAlert para no duplicar.
    }

    public function render()
    {
        $avales = ClientAval::where('client_id', $this->clientId)
            ->orderByDesc('id')->get();

        return view('livewire.clients.aval', compact('avales'));
    }
}
