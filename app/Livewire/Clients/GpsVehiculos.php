<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Vehiculo;
use App\Models\VehiculoGpsReporte;
use App\Models\VehiculoGpsReporteFoto;
use App\Support\Miniatura;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Reportes de GPS de los vehículos del cliente (26/09/2026), debajo de la
 * ubicación de Casa en la pestaña GPS. Un solo formulario: uno o varios
 * puntos del recorrido (etiqueta, horario de estadía, dirección, enlace) y
 * fotos por arrastrar. Lo que ya se sabe del cliente —nombre, expediente,
 * placas, domicilio y su enlace de Casa— se rellena solo.
 */
class GpsVehiculos extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $clientId;

    public Client $client;

    public bool $puedeEditar = true;

    public bool $mostrarForm = false;

    /** Reporte abierto en el panel de detalle (null = ninguno). */
    public ?int $verId = null;

    /** Filtro de la tabla por placa ('' = todas). */
    public string $filtroPlaca = '';

    public array $form = [];

    /** Fotos elegidas en el formulario (se suben al guardar). */
    public array $files = [];

    /** Fotos arrastradas sobre un reporte ya guardado (se suben al instante). */
    public array $fotosExtra = [];

    public ?string $msg = null;

    public ?string $msgType = null;

    public function mount(int $id): void
    {
        $this->client = Client::findOrFail($id);
        $this->clientId = $id;
        // Mismo gate que la pestaña GPS
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
        $this->verId = null;
        $this->files = [];
        $this->form = [
            'vehiculo_id' => $vehiculos->count() === 1 ? (string) $vehiculos->first()->id : '',
            'fecha' => now()->format('Y-m-d\TH:i'), // fecha y hora del reporte (datetime-local)
            'inicio_desde' => '', 'inicio_hasta' => '',
            'fin_desde' => '', 'fin_hasta' => '',
            // Lo que ya se sabe del cliente: domicilio y su enlace de Casa.
            'domicilio_direccion' => $this->domicilioFicha(),
            'domicilio_link' => $this->enlaceCasa(),
            // false = se usa lo de la ficha tal cual; true = el usuario lo cambió solo para este reporte
            'domicilio_personalizado' => false,
            // El primer punto sin etiqueta: sale en el formato corto. Al agregar más, cada uno trae la suya.
            'puntos' => [$this->puntoVacio('')],
        ];
        $this->mostrarForm = true;
    }

    /** Enlace de Google Maps de la ubicación de Casa registrada en la pestaña (o ''). */
    public function enlaceCasa(): string
    {
        return ($this->client->latitud && $this->client->longitud)
            ? "https://maps.google.com/?q={$this->client->latitud},{$this->client->longitud}"
            : '';
    }

    /** Domicilio tal como está en la ficha: dirección + distrito. */
    public function domicilioFicha(): string
    {
        return trim(implode(', ', array_filter([
            trim((string) $this->client->direccion), trim((string) $this->client->distrito),
        ])));
    }

    private function puntoVacio(string $etiqueta = ''): array
    {
        return ['etiqueta' => $etiqueta, 'etiqueta_otra' => '', 'estadia_desde' => '', 'estadia_hasta' => '', 'direccion' => '', 'link' => ''];
    }

    /** Etiqueta final de un punto: la elegida en la lista o la escrita en "Otra". */
    private function etiquetaDe(array $p): string
    {
        $e = trim((string) ($p['etiqueta'] ?? ''));

        return $e === 'Otra' ? trim((string) ($p['etiqueta_otra'] ?? '')) : $e;
    }

    public function agregarPunto(): void
    {
        $n = count($this->form['puntos'] ?? []);
        if ($n === 1 && trim((string) ($this->form['puntos'][0]['etiqueta'] ?? '')) === '') {
            $this->form['puntos'][0]['etiqueta'] = VehiculoGpsReporte::ETIQUETAS[0]; // "Donde se queda"
        }
        $this->form['puntos'][] = $this->puntoVacio(VehiculoGpsReporte::ETIQUETAS[min($n, 2)]);
    }

    public function quitarPunto(int $i): void
    {
        if (count($this->form['puntos'] ?? []) <= 1) {
            return;
        }
        unset($this->form['puntos'][$i]);
        $this->form['puntos'] = array_values($this->form['puntos']);
    }

    public function removeFile(int $i): void
    {
        if (isset($this->files[$i])) {
            unset($this->files[$i]);
            $this->files = array_values($this->files);
        }
    }

    public function removeFotoExtra(int $i): void
    {
        if (isset($this->fotosExtra[$i])) {
            unset($this->fotosExtra[$i]);
            $this->fotosExtra = array_values($this->fotosExtra);
        }
    }

    public function cancelar(): void
    {
        $this->mostrarForm = false;
        $this->form = [];
        $this->files = [];
        $this->resetErrorBag();
    }

    /** Reporte SIN guardar armado desde el formulario, para la vista previa del mensaje. */
    public function vistaPrevia(): string
    {
        if (! $this->mostrarForm || $this->form === []) {
            return '';
        }
        $vehiculo = $this->vehiculos()->firstWhere('id', (int) ($this->form['vehiculo_id'] ?? 0));
        $r = new VehiculoGpsReporte($this->datosDesdeForm($vehiculo?->placa ?? '—'));
        $r->setRelation('client', $this->client);

        return $r->texto();
    }

    /** Campos del reporte a partir del formulario (comparte guardar() y vistaPrevia()). */
    private function datosDesdeForm(string $placa): array
    {
        $puntos = array_map(fn ($p) => [
            'etiqueta' => $this->etiquetaDe($p),
            'estadia_desde' => ($p['estadia_desde'] ?? '') ?: null,
            'estadia_hasta' => ($p['estadia_hasta'] ?? '') ?: null,
            'direccion' => trim((string) ($p['direccion'] ?? '')),
            'link' => trim((string) ($p['link'] ?? '')),
        ], $this->form['puntos'] ?? []);

        $personalizado = (bool) ($this->form['domicilio_personalizado'] ?? false);

        return [
            'client_id' => $this->clientId,
            'placa' => $placa,
            'fecha' => $this->form['fecha'] ?? now()->format('Y-m-d H:i'),
            'inicio_desde' => ($this->form['inicio_desde'] ?? '') ?: null,
            'inicio_hasta' => ($this->form['inicio_hasta'] ?? '') ?: null,
            'fin_desde' => ($this->form['fin_desde'] ?? '') ?: null,
            'fin_hasta' => ($this->form['fin_hasta'] ?? '') ?: null,
            'domicilio_direccion' => ($personalizado ? trim((string) ($this->form['domicilio_direccion'] ?? '')) : $this->domicilioFicha()) ?: null,
            'domicilio_link' => ($personalizado ? trim((string) ($this->form['domicilio_link'] ?? '')) : $this->enlaceCasa()) ?: null,
            'puntos' => $puntos,
        ];
    }

    protected function rules(): array
    {
        $hora = ['nullable', 'regex:/^\d{2}:\d{2}$/'];

        return [
            'form.vehiculo_id' => 'required|integer',
            'form.fecha' => 'required|date',
            'form.inicio_desde' => $hora, 'form.inicio_hasta' => $hora,
            'form.fin_desde' => $hora, 'form.fin_hasta' => $hora,
            'form.domicilio_direccion' => 'nullable|string|max:255',
            'form.domicilio_link' => 'nullable|string|max:500',
            'form.domicilio_personalizado' => 'boolean',
            'form.puntos' => 'required|array|min:1',
            'form.puntos.*.etiqueta' => 'nullable|string|max:60',
            'form.puntos.*.etiqueta_otra' => 'nullable|string|max:60',
            'form.puntos.*.estadia_desde' => $hora, 'form.puntos.*.estadia_hasta' => $hora,
            'form.puntos.*.direccion' => 'required|string|max:255',
            'form.puntos.*.link' => 'nullable|string|max:500',
            'files' => 'nullable|array',
            'files.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'form.vehiculo_id.required' => 'Elige la placa.',
            'form.fecha.required' => 'La fecha del reporte es obligatoria.',
            'form.puntos.*.direccion.required' => 'Escribe la ubicación del vehículo.',
            'form.*.regex' => 'La hora debe ser HH:MM.',
            'form.puntos.*.*.regex' => 'La hora debe ser HH:MM.',
            'files.*.image' => 'Cada archivo debe ser una imagen.',
            'files.*.mimes' => 'Formatos válidos: JPG, PNG, GIF o WebP.',
            'files.*.max' => 'Cada imagen debe pesar máximo 10 MB.',
            'fotosExtra.*.image' => 'Cada archivo debe ser una imagen.',
            'fotosExtra.*.mimes' => 'Formatos válidos: JPG, PNG, GIF o WebP.',
            'fotosExtra.*.max' => 'Cada imagen debe pesar máximo 10 MB.',
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

        $reporte = VehiculoGpsReporte::create($this->datosDesdeForm($vehiculo->placa) + [
            'vehiculo_id' => $vehiculo->id,
            'registrado_por' => auth()->id(),
        ]);
        $fotos = $this->guardarFotos($reporte, $this->files);

        $this->cancelar();
        $this->verId = $reporte->id;
        $this->msgType = 'ok';
        $this->msg = "Reporte de GPS del vehículo {$vehiculo->placa} guardado. Abajo tienes el texto listo para copiar.";
    }

    /** Guarda las fotos (con miniatura) en storage/app/public/gps/reportes/{id}. */
    private function guardarFotos(VehiculoGpsReporte $reporte, array $archivos): int
    {
        $disco = Storage::disk('public');
        $n = 0;
        foreach ($archivos as $file) {
            if (! $file) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $nombre = Str::uuid()->toString().'.'.$ext;
            $carpeta = "gps/reportes/{$reporte->id}";
            $disco->putFileAs($carpeta, $file, $nombre);
            $thumb = "{$carpeta}/thumbs/{$nombre}";
            $ok = Miniatura::crear($disco->path("{$carpeta}/{$nombre}"), $disco->path($thumb), 400);
            VehiculoGpsReporteFoto::create([
                'reporte_id' => $reporte->id,
                'path' => "{$carpeta}/{$nombre}",
                'thumb_path' => $ok ? $thumb : null,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
            $n++;
        }

        return $n;
    }

    /** Fotos arrastradas sobre un reporte abierto: se suben al instante, sin botón. */
    public function updatedFotosExtra(): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para adjuntar fotos.');
        $reporte = $this->verId ? VehiculoGpsReporte::where('client_id', $this->clientId)->find($this->verId) : null;
        if (! $reporte) {
            $this->fotosExtra = [];

            return;
        }
        $this->validate(['fotosExtra.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:10240']);

        $n = $this->guardarFotos($reporte, $this->fotosExtra);
        $this->fotosExtra = [];
        $this->msgType = 'ok';
        $this->msg = $n === 1 ? 'Foto adjuntada al reporte.' : "{$n} fotos adjuntadas al reporte.";
    }

    public function eliminarFoto(int $fotoId): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para eliminar fotos.');
        $foto = VehiculoGpsReporteFoto::whereHas('reporte', fn ($q) => $q->where('client_id', $this->clientId))->find($fotoId);
        if (! $foto) {
            return;
        }
        $disco = Storage::disk('public');
        foreach ([$foto->path, $foto->thumb_path] as $ruta) {
            if ($ruta && $disco->exists($ruta)) {
                $disco->delete($ruta);
            }
        }
        $foto->delete();
    }

    public function ver(int $id): void
    {
        $this->verId = $this->verId === $id ? null : $id;
        $this->fotosExtra = [];
    }

    public function eliminar(int $id): void
    {
        abort_unless($this->puedeEditar, 403, 'No tienes permiso para eliminar reportes.');
        $r = VehiculoGpsReporte::where('client_id', $this->clientId)->find($id);
        if (! $r) {
            return;
        }
        $disco = Storage::disk('public');
        foreach ($r->fotos as $foto) {
            foreach ([$foto->path, $foto->thumb_path] as $ruta) {
                if ($ruta && $disco->exists($ruta)) {
                    $disco->delete($ruta);
                }
            }
        }
        $r->delete();
        if ($this->verId === $id) {
            $this->verId = null;
        }
        $this->msgType = 'ok';
        $this->msg = 'Reporte eliminado.';
    }

    public function render()
    {
        // Del último registrado hacia abajo (orden de registro, no de fecha del reporte).
        $reportes = VehiculoGpsReporte::with(['registradoPor:id,name,username', 'fotos'])
            ->where('client_id', $this->clientId)
            ->when($this->filtroPlaca !== '', fn ($q) => $q->where('placa', $this->filtroPlaca))
            ->orderByDesc('id')
            ->get();

        // Placas con reportes (para el filtro), aunque el vehículo ya no exista.
        $placas = VehiculoGpsReporte::where('client_id', $this->clientId)
            ->distinct()->orderBy('placa')->pluck('placa');

        return view('livewire.clients.gps-vehiculos', [
            'reportes' => $reportes,
            'vehiculos' => $this->vehiculos(),
            'placas' => $placas,
            'reporteVer' => $this->verId ? VehiculoGpsReporte::with('fotos')->where('client_id', $this->clientId)->find($this->verId) : null,
        ]);
    }
}
