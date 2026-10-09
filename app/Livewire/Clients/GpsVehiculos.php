<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsReporte;
use App\Support\ConReglasDeEliminacion;
use App\Support\Coordenadas;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Reportes de GPS de los vehículos del cliente, en la pestaña GPS debajo de
 * las direcciones. Simplificado el 09/10/2026 (Antony): placa, coordenadas
 * (pegadas o desde el enlace de Google Maps, como en Casa/Negocio) y una
 * descripción; la fecha de registro se pone sola al guardar. Nada más.
 */
class GpsVehiculos extends Component
{
    use ConReglasDeEliminacion;

    #[Locked]
    public int $clientId;

    public Client $client;

    public bool $puedeEditar = true;

    public bool $mostrarForm = false;

    /** @var array{vehiculo_id?: string, coordenadas?: string, descripcion?: string} */
    public array $form = [];

    public ?string $msg = null;

    public ?string $msgType = null;

    public function mount(int $id): void
    {
        $this->client = Client::findOrFail($id);
        $this->clientId = $id;
        // Mismo gate que la pestaña GPS: el analista de cartera propia solo mira.
        $this->puedeEditar = ! (auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    /** Vehículos del cliente: propios y donde es copropietario. */
    public function vehiculos(): Collection
    {
        return $this->client->vehiculos()->orderBy('placa')->get()
            ->concat($this->client->vehiculosCompartidos()->orderBy('placa')->get())
            ->unique('id')
            ->values();
    }

    public function nuevo(): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar reportes.');
        $vehiculos = $this->vehiculos();

        $this->resetErrorBag();
        $this->msg = null;
        // La fecha de registro no se digita: es el momento en que se guarda.
        $this->form = [
            'vehiculo_id' => $vehiculos->count() === 1 ? (string) $vehiculos->first()->id : '',
            'coordenadas' => '',
            'descripcion' => '',
        ];
        $this->mostrarForm = true;
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->form = [];
        $this->resetErrorBag();
    }

    protected function rules(): array
    {
        return [
            'form.vehiculo_id' => 'required|integer',
            'form.coordenadas' => 'required|string|max:500',
            'form.descripcion' => 'nullable|string|max:1000',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.vehiculo_id.required' => 'Elige la placa.',
            'form.coordenadas.required' => 'Pega las coordenadas del vehículo (o el enlace de Google Maps).',
            'form.descripcion.max' => 'La descripción admite hasta 1000 caracteres.',
        ];
    }

    public function guardar(): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar reportes.');
        $this->validate();

        $vehiculo = $this->vehiculos()->firstWhere('id', (int) $this->form['vehiculo_id']);
        if (! $vehiculo instanceof Vehiculo) {
            $this->addError('form.vehiculo_id', 'La placa no es de este cliente.');

            return;
        }

        // Una sola lectura: un enlace corto de Maps sale a la red para resolverse.
        $coords = Coordenadas::parse((string) $this->form['coordenadas']);
        if ($coords === null) {
            $this->addError('form.coordenadas', Gps::mensajeFormato((string) $this->form['coordenadas']));

            return;
        }
        [$lat, $lng] = $coords;

        VehiculoGpsReporte::create([
            'client_id' => $this->clientId,
            'vehiculo_id' => $vehiculo->id,
            'placa' => $vehiculo->placa,
            'fecha' => now(),
            'latitud' => $lat,
            'longitud' => $lng,
            'descripcion' => trim((string) ($this->form['descripcion'] ?? '')) ?: null,
            'registrado_por' => auth()->id(),
        ]);

        $this->cancelar();
        $this->msgType = 'ok';
        $this->msg = "Reporte de GPS del vehículo {$vehiculo->placa} guardado.";
    }

    public function eliminar(int $id): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para eliminar reportes.');
        $r = VehiculoGpsReporte::where('client_id', $this->clientId)->find($id);
        if (! $r || $this->eliminacionBloqueada($r)) {
            return;
        }
        $r->delete();
        $this->msgType = 'ok';
        $this->msg = 'Reporte eliminado.';
    }

    public function render()
    {
        // Del último registrado hacia abajo.
        $reportes = VehiculoGpsReporte::with('registradoPor:id,name,username')
            ->where('client_id', $this->clientId)
            ->orderByDesc('id')
            ->get();

        return view('livewire.clients.gps-vehiculos', [
            'reportes' => $reportes,
            'vehiculos' => $this->vehiculos(),
        ]);
    }
}
