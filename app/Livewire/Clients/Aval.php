<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientAval;
use App\Services\Factiliza;
use App\Support\Audit;
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
            'dni' => 'required|string|min:8|max:11',
            'nombre' => 'required|string|max:200',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
        ];
    }

    public function mount(int $id): void
    {
        $this->client = Client::findOrFail($id);

        // Analista (scope-propio): solo SUS clientes
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $this->client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );
        $this->clientId = $id;
        $this->puedeEditar = ! (auth()->user()?->can('clientes.scope-propio') ?? false);
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
            $this->nombre = $clienteLocal->fullName();
            $this->direccion = $clienteLocal->direccion;
            $this->telefono = $clienteLocal->celular1;
            $this->dniMsgType = 'ok';
            $this->dniMsg = 'Encontrado en BD local: '.$clienteLocal->fullName();

            return;
        }

        // 2. Buscar en Factiliza (DNI o RUC según longitud)
        $api = app(Factiliza::class);
        $resultado = strlen($doc) === 8 ? $api->dni($doc) : $api->ruc($doc);

        if (! $resultado['ok']) {
            $this->dniMsgType = 'warn';
            $this->dniMsg = 'No está en BD local. '.$resultado['error'].' Ingrese los datos a mano.';

            return;
        }

        $d = $resultado['data'];
        if (strlen($doc) === 8) {
            // La tabla de avales guarda un solo campo `nombre`
            $this->nombre = trim(($d['apellido_pat'] ?? '').' '.($d['apellido_mat'] ?? '').' '.($d['nombre'] ?? ''));
        } else {
            $this->nombre = (string) ($d['nombre'] ?? '');
        }
        if (! empty($d['direccion'])) {
            $this->direccion = (string) $d['direccion'];
        }

        $this->dniMsgType = 'ok';
        $this->dniMsg = 'Cargado desde '.(strlen($doc) === 8 ? 'RENIEC' : 'SUNAT').'. Verifique antes de guardar.';
    }

    private function resetCamposAval(): void
    {
        $this->nombre = '';
        $this->direccion = null;
        $this->telefono = null;
    }

    public function save()
    {
        if (! $this->puedeEditar) {
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

        $aval = ClientAval::create([
            'client_id' => $this->clientId,
            'dni' => $doc,
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
        ]);

        Audit::log("Agregó un aval al cliente #{$this->clientId}", $aval);

        $this->resetCamposAval();
        $this->dni = '';
        $this->dniMsg = null;
        $this->dniMsgType = null;
        $this->resetErrorBag();

        $this->dispatch('successAlert', ['message' => 'Aval agregado.']);
    }

    public function questionDelete(int $id): void
    {
        if (! $this->puedeEditar) {
            return;
        }
        $this->dispatch('questionDelete', ['id' => $id]);
    }

    #[On('register_destroy')]
    public function deleteAval(int $id): void
    {
        if (! $this->puedeEditar) {
            return;
        }
        ClientAval::where('client_id', $this->clientId)->where('id', $id)->delete();
        Audit::log("Eliminó el aval #{$id}");
        // El custom.js ya muestra "Eliminado!" — no dispatcheamos successAlert para no duplicar.
    }

    public function render()
    {
        $avales = ClientAval::where('client_id', $this->clientId)
            ->orderByDesc('id')->get();

        return view('livewire.clients.aval', compact('avales'));
    }
}
