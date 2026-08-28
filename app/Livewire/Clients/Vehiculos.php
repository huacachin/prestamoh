<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Vehiculo;
use App\Services\Factiliza;
use App\Support\Audit;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Vehículos del cliente (pestaña de /clients/{id}/edit, 28/08).
 *
 * Hasta ahora los vehículos SOLO se podían crear en el alta y no había
 * ninguna pantalla para agregar uno nuevo, corregir una placa mal tecleada
 * ni borrar: un cliente con dos vehículos solo se armaba por SQL.
 */
class Vehiculos extends Component
{
    #[Locked]
    public int $clientId;

    public Client $client;

    public bool $puedeEditar = true;

    /** Fila en edición: null = ninguna, 0 = alta nueva, N = id del vehículo. */
    public ?int $editandoId = null;

    public bool $creando = false;

    // Campos del formulario de la fila
    public string $placa = '';

    public string $marca = '';

    public string $modelo = '';

    public string $nro_motor = '';

    public string $nro_serie = '';

    public string $categoria = '';

    public string $anio_modelo = '';

    public string $carroceria = '';

    public string $color = '';

    public string $combustible = '';

    public $valor = null;

    public ?string $msg = null;

    public ?string $msgType = null;

    /**
     * Campos que llenó la consulta de placa (se pintan en rojo).
     *
     * @var array<int, string>
     */
    public array $autoCampos = [];

    public function mount(int $id): void
    {
        $this->client = Client::findOrFail($id);
        $this->clientId = $id;

        // Analista (scope-propio): solo su cartera y sin editar
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $this->client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );
        $this->puedeEditar = ! (auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    protected function rules(): array
    {
        $ignorar = $this->editandoId ?: null;

        return [
            'placa' => 'required|string|max:10|unique:vehiculos,placa'.($ignorar ? ",{$ignorar}" : ''),
            'marca' => 'nullable|string|max:50',
            'modelo' => 'nullable|string|max:50',
            'nro_motor' => 'nullable|string|max:30',
            'nro_serie' => 'nullable|string|max:30',
            'categoria' => 'nullable|string|max:30',
            'anio_modelo' => 'nullable|string|max:10',
            'carroceria' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:50',
            'combustible' => 'nullable|string|max:30',
            'valor' => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'placa.required' => 'La placa es obligatoria.',
            'placa.unique' => 'Esa placa ya está registrada en otro cliente.',
        ];
    }

    public function nuevo(): void
    {
        $this->autorizarEdicion();
        $this->limpiarFormulario();
        $this->creando = true;
        $this->editandoId = null;
    }

    public function editar(int $id): void
    {
        $this->autorizarEdicion();
        $v = Vehiculo::where('client_id', $this->clientId)->findOrFail($id);

        $this->editandoId = $id;
        $this->creando = false;
        $this->autoCampos = [];
        foreach (['placa', 'marca', 'modelo', 'nro_motor', 'nro_serie', 'categoria', 'anio_modelo', 'carroceria', 'color', 'combustible'] as $campo) {
            $this->{$campo} = (string) ($v->{$campo} ?? '');
        }
        $this->valor = $v->valor;
        $this->resetErrorBag();
        $this->msg = null;
    }

    public function cancelar(): void
    {
        $this->limpiarFormulario();
    }

    public function guardar(): void
    {
        $this->autorizarEdicion();

        foreach (['placa', 'nro_motor', 'nro_serie'] as $campo) {
            $this->{$campo} = strtoupper(trim($this->{$campo}));
        }
        $this->validate();

        $datos = [
            'client_id' => $this->clientId,
            'placa' => $this->placa,
            'marca' => $this->marca ?: null,
            'modelo' => $this->modelo ?: null,
            'nro_motor' => $this->nro_motor ?: null,
            'nro_serie' => $this->nro_serie ?: null,
            'categoria' => $this->categoria ?: null,
            'anio_modelo' => $this->anio_modelo ?: null,
            'carroceria' => $this->carroceria ?: null,
            'color' => $this->color ?: null,
            'combustible' => $this->combustible ?: null,
            'valor' => ($this->valor === '' || $this->valor === null) ? null : $this->valor,
        ];

        if ($this->editandoId) {
            $v = Vehiculo::where('client_id', $this->clientId)->findOrFail($this->editandoId);
            $v->update($datos);
            Audit::log("Editó el vehículo {$this->placa} del cliente ".$this->client->fullName(), $this->client);
            $this->msg = "Vehículo {$this->placa} actualizado.";
        } else {
            Vehiculo::create($datos);
            Audit::log("Agregó el vehículo {$this->placa} al cliente ".$this->client->fullName(), $this->client);
            $this->msg = "Vehículo {$this->placa} agregado.";
        }

        $this->msgType = 'ok';
        $this->limpiarFormulario();
    }

    public function eliminar(int $id): void
    {
        $this->autorizarEdicion();
        $v = Vehiculo::where('client_id', $this->clientId)->findOrFail($id);
        $placa = $v->placa;
        $v->delete();

        Audit::log("Eliminó el vehículo {$placa} del cliente ".$this->client->fullName(), $this->client);
        $this->msgType = 'ok';
        $this->msg = "Vehículo {$placa} eliminado.";
        $this->limpiarFormulario();
    }

    /** Autocompleta el formulario con los datos de la placa (Factiliza). */
    public function consultarPlaca(Factiliza $api): void
    {
        $this->autorizarEdicion();

        $placa = strtoupper(trim($this->placa));
        if ($placa === '') {
            $this->msgType = 'err';
            $this->msg = 'Escribe la placa que quieres consultar.';

            return;
        }

        $resultado = $api->placa($placa);
        if (! $resultado['ok']) {
            $this->msgType = 'err';
            $this->msg = $resultado['error'].' Puedes completar los datos a mano.';

            return;
        }

        $this->autoCampos = [];
        foreach ($resultado['data'] as $campo => $valor) {
            if ($valor !== '' && property_exists($this, $campo)) {
                $this->{$campo} = $valor;
                $this->autoCampos[] = $campo;
            }
        }

        $this->msgType = 'ok';
        $this->msg = "Datos de la placa {$placa} cargados. Verifica antes de guardar.";
    }

    private function autorizarEdicion(): void
    {
        abort_unless($this->puedeEditar, 403, 'Tu rol no permite editar vehículos.');
    }

    private function limpiarFormulario(): void
    {
        $this->reset(['placa', 'marca', 'modelo', 'nro_motor', 'nro_serie', 'categoria',
            'anio_modelo', 'carroceria', 'color', 'combustible', 'valor', 'editandoId', 'creando', 'autoCampos']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.clients.vehiculos', [
            'listado' => Vehiculo::where('client_id', $this->clientId)->orderBy('id')->get(),
        ]);
    }
}
