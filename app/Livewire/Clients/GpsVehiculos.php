<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsCuenta;
use App\Models\VehiculoGpsReporte;
use App\Support\ConReglasDeEliminacion;
use App\Support\Coordenadas;
use App\Support\HorarioEliminacion;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ubicaciones GPS de los vehículos del cliente, en la pestaña GPS debajo de
 * las direcciones (10/10/2026, Antony): se listan TODAS las placas del
 * cliente (propias y donde es copropietario); cada una con su botón "Agregar
 * ubicación" (coordenadas pegadas o enlace de Google Maps, y una descripción)
 * y, debajo, sus ubicaciones. Sin vehículos, se avisa que aún no se agregaron.
 * La fecha de registro se pone sola al guardar. Editar (12/10): mismo
 * formulario; quien no es director solo edita lo registrado el mismo día.
 *
 * Cuenta GPS (06/11/2026, Trello; Antony: "una para cada placa, un botón que abre un modal
 * al costado de agregar ubicación"): aplicativo, enlace, usuario y contraseña de la
 * plataforma del GPS. La ve quien ve la pestaña; la registra o cambia quien puede editar.
 */
class GpsVehiculos extends Component
{
    use ConReglasDeEliminacion;

    #[Locked]
    public int $clientId;

    public Client $client;

    public bool $puedeEditar = true;

    /** Vehículo cuyo formulario de "Agregar ubicación" está abierto (null = ninguno). */
    public ?int $formVehiculoId = null;

    /** Ubicación que se está editando en ese formulario (null = es un alta). 12/10: Antony pidió editar. */
    public ?int $editId = null;

    /** @var array{coordenadas?: string, descripcion?: string} */
    public array $form = [];

    public ?string $msg = null;

    public ?string $msgType = null;

    /** Vehículo cuyo modal "Cuenta GPS" está abierto (null = ninguno). */
    public ?int $cuentaVehiculoId = null;

    /** @var array{aplicativo: string, enlace: string, usuario: string, contrasena: string} */
    public array $cuenta = [];

    public function mount(int $id): void
    {
        $this->client = Client::findOrFail($id);
        $this->clientId = $id;
        // Mismo gate que la pestaña GPS: el analista de cartera propia solo mira.
        $this->puedeEditar = ! (auth()->user()?->can('clientes.scope-propio') ?? false);
    }

    /** Vehículos del cliente: propios y donde es copropietario, por placa. */
    public function vehiculos(): Collection
    {
        return $this->client->vehiculos()->orderBy('placa')->get()
            ->concat($this->client->vehiculosCompartidos()->orderBy('placa')->get())
            ->unique('id')
            ->sortBy('placa')
            ->values();
    }

    public function nuevo(int $vehiculoId): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar ubicaciones.');
        if (! $this->vehiculos()->firstWhere('id', $vehiculoId) instanceof Vehiculo) {
            $this->dispatch('errorAlert', ['message' => 'Ese vehículo no es de este cliente.']);

            return;
        }

        $this->resetErrorBag();
        $this->msg = null;
        $this->formVehiculoId = $vehiculoId;
        $this->editId = null;
        $this->form = ['coordenadas' => '', 'descripcion' => ''];
    }

    /**
     * Abre la ubicación en el formulario de su placa para corregir coordenadas
     * o descripción. Quien no es director solo edita lo registrado hoy
     * (edicionBloqueada: avisa y deja rastro); la fecha de registro no cambia.
     */
    public function editar(int $id): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para editar ubicaciones.');
        $r = VehiculoGpsReporte::where('client_id', $this->clientId)->find($id);
        if (! $r || ! $this->vehiculos()->firstWhere('id', $r->vehiculo_id) instanceof Vehiculo) {
            $this->dispatch('errorAlert', ['message' => 'Esa ubicación ya no se puede editar desde aquí.']);

            return;
        }
        if ($this->edicionBloqueada($r)) {
            return;
        }

        $this->resetErrorBag();
        $this->msg = null;
        $this->formVehiculoId = $r->vehiculo_id;
        $this->editId = $r->id;
        $this->form = ['coordenadas' => $r->coordenadas(), 'descripcion' => (string) $r->descripcion];
    }

    /** Quién puede editar ESA ubicación ahora (para pintar o no el botón). */
    public function puedeEditarUbicacion(VehiculoGpsReporte $r): bool
    {
        return $this->puedeEditar
            && $r->vehiculo_id !== null
            && HorarioEliminacion::motivoBloqueo($r, null, null, 'editar') === null;
    }

    public function cancelar(): void
    {
        $this->formVehiculoId = null;
        $this->editId = null;
        $this->form = [];
        $this->resetErrorBag();
    }

    protected function rules(): array
    {
        return [
            'form.coordenadas' => 'required|string|max:500',
            'form.descripcion' => 'nullable|string|max:1000',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.coordenadas.required' => 'Pega las coordenadas del vehículo (o el enlace de Google Maps).',
            'form.descripcion.max' => 'La descripción admite hasta 1000 caracteres.',
        ];
    }

    public function guardar(): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar ubicaciones.');
        $vehiculo = $this->formVehiculoId ? $this->vehiculos()->firstWhere('id', $this->formVehiculoId) : null;
        if (! $vehiculo instanceof Vehiculo) {
            $this->dispatch('errorAlert', ['message' => 'Elige primero la placa con "Agregar ubicación".']);

            return;
        }
        $this->validate();

        // Una sola lectura: un enlace corto de Maps sale a la red para resolverse.
        $coords = Coordenadas::parse((string) $this->form['coordenadas']);
        if ($coords === null) {
            $this->addError('form.coordenadas', Gps::mensajeFormato((string) $this->form['coordenadas']));

            return;
        }
        [$lat, $lng] = $coords;

        if ($this->editId) {
            $r = VehiculoGpsReporte::where('client_id', $this->clientId)->find($this->editId);
            // Se vuelve a mirar la regla al guardar: la página pudo quedar abierta hasta el día siguiente.
            if (! $r || $this->edicionBloqueada($r, 'guardar')) {
                return;
            }
            $r->update([
                'latitud' => $lat,
                'longitud' => $lng,
                'descripcion' => trim((string) ($this->form['descripcion'] ?? '')) ?: null,
            ]);
            $this->cancelar();
            $this->msgType = 'ok';
            $this->msg = "Ubicación del vehículo {$vehiculo->placa} actualizada.";

            return;
        }

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
        $this->msg = "Ubicación del vehículo {$vehiculo->placa} guardada.";
    }

    public function eliminar(int $id): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para eliminar ubicaciones.');
        $r = VehiculoGpsReporte::where('client_id', $this->clientId)->find($id);
        if (! $r || $this->eliminacionBloqueada($r)) {
            return;
        }
        $r->delete();
        if ($this->editId === $id) {
            $this->cancelar();
        }
        $this->msgType = 'ok';
        $this->msg = "Ubicación del vehículo {$r->placa} eliminada.";
    }

    /** Abre el modal "Cuenta GPS" de la placa con lo ya registrado (vacío si es nueva). */
    public function abrirCuenta(int $vehiculoId): void
    {
        if (! $this->vehiculos()->firstWhere('id', $vehiculoId) instanceof Vehiculo) {
            $this->dispatch('errorAlert', ['message' => 'Ese vehículo no es de este cliente.']);

            return;
        }
        $cuenta = VehiculoGpsCuenta::where('vehiculo_id', $vehiculoId)->first();
        if (! $cuenta && ! $this->puedeEditar) {
            $this->dispatch('errorAlert', ['message' => 'Esta placa aún no tiene cuenta GPS registrada.']);

            return;
        }

        $this->resetErrorBag();
        $this->cuentaVehiculoId = $vehiculoId;
        $this->cuenta = [
            'aplicativo' => (string) $cuenta?->aplicativo,
            'enlace' => (string) $cuenta?->enlace,
            'usuario' => (string) $cuenta?->usuario,
            'contrasena' => (string) $cuenta?->contrasena,
        ];
        $this->dispatch('cuenta-gps-modal-open');
    }

    /** Al cerrar el modal la contraseña sale del estado del componente. */
    public function cerrarCuenta(): void
    {
        $this->cuentaVehiculoId = null;
        $this->cuenta = [];
        $this->resetErrorBag();
    }

    public function guardarCuenta(): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para registrar la cuenta del GPS.');
        $vehiculo = $this->cuentaVehiculoId ? $this->vehiculos()->firstWhere('id', $this->cuentaVehiculoId) : null;
        if (! $vehiculo instanceof Vehiculo) {
            $this->dispatch('errorAlert', ['message' => 'Elige primero la placa con "Cuenta GPS".']);

            return;
        }

        $this->cuenta = array_map(fn ($valor) => trim((string) $valor), $this->cuenta);
        $this->cuenta['enlace'] = (string) VehiculoGpsCuenta::normalizarEnlace($this->cuenta['enlace'] ?? '');
        $this->validate([
            'cuenta.aplicativo' => 'required|string|max:100',
            'cuenta.enlace' => 'nullable|url|max:500',
            'cuenta.usuario' => 'required|string|max:150',
            'cuenta.contrasena' => 'required|string|max:150',
        ], [
            'cuenta.aplicativo.required' => 'Indica el aplicativo o la plataforma del GPS.',
            'cuenta.enlace.url' => 'El enlace no parece una dirección web (ej.: https://gps.empresa.com).',
            'cuenta.usuario.required' => 'Indica el usuario de la cuenta.',
            'cuenta.contrasena.required' => 'Indica la contraseña de la cuenta.',
        ]);

        $cuenta = VehiculoGpsCuenta::firstOrNew(['vehiculo_id' => $vehiculo->id]);
        $cuenta->fill([
            'aplicativo' => $this->cuenta['aplicativo'],
            'enlace' => $this->cuenta['enlace'] ?: null,
            'usuario' => $this->cuenta['usuario'],
            'contrasena' => $this->cuenta['contrasena'],
        ]);
        $esNueva = ! $cuenta->exists;
        if ($esNueva) {
            $cuenta->registrado_por = auth()->id();
        } elseif ($cuenta->isDirty()) {
            $cuenta->actualizado_por = auth()->id();
        }
        $cuenta->save();

        $this->cerrarCuenta();
        $this->dispatch('cuenta-gps-modal-close');
        $this->msgType = 'ok';
        $this->msg = $esNueva ? "Cuenta GPS del vehículo {$vehiculo->placa} registrada." : "Cuenta GPS del vehículo {$vehiculo->placa} actualizada.";
    }

    public function render()
    {
        $vehiculos = $this->vehiculos();

        // Del último registrado hacia abajo, agrupadas por vehículo.
        $reportes = VehiculoGpsReporte::with('registradoPor:id,name,username')
            ->where('client_id', $this->clientId)
            ->orderByDesc('id')
            ->get();
        $porVehiculo = $reportes->whereIn('vehiculo_id', $vehiculos->pluck('id'))->groupBy('vehiculo_id');
        // Ubicaciones cuyo vehículo ya no está en la ficha: se siguen viendo, por placa.
        $huerfanas = $reportes->whereNotIn('vehiculo_id', $vehiculos->pluck('id'))->groupBy('placa');

        $cuentas = VehiculoGpsCuenta::with(['registradoPor:id,name,username', 'actualizadoPor:id,name,username'])
            ->whereIn('vehiculo_id', $vehiculos->pluck('id'))
            ->get()
            ->keyBy('vehiculo_id');

        return view('livewire.clients.gps-vehiculos', [
            'vehiculos' => $vehiculos,
            'porVehiculo' => $porVehiculo,
            'huerfanas' => $huerfanas,
            'cuentas' => $cuentas,
            'vehiculoCuenta' => $this->cuentaVehiculoId ? $vehiculos->firstWhere('id', $this->cuentaVehiculoId) : null,
            // Los aplicativos ya usados se sugieren para que el mismo no se escriba de dos formas.
            'aplicativosPrevios' => $this->cuentaVehiculoId
                ? VehiculoGpsCuenta::query()->distinct()->orderBy('aplicativo')->pluck('aplicativo')
                : collect(),
        ]);
    }
}
