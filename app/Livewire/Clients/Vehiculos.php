<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Vehiculo;
use App\Services\Factiliza;
use App\Support\Audit;
use App\Support\Documentos\Nacionalidades;
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

    // ── Copropietario (tramo D) ──
    // El vehículo compartido habilita los contratos de dos deudores (a.3.x):
    // el wizard precarga al copropietario como codeudor y filtra los modelos.

    /** Vehículo cuyo panel de copropietario está abierto (null = ninguno). */
    public ?int $coproVehiculoId = null;

    public string $buscarCopro = '';

    /** Alta rápida de PERSONA RELACIONADA (es_relacionado=1): solo los datos
     *  que el contrato exige — sin expediente, asesor ni capital. */
    public bool $coproCreando = false;

    public array $nuevoCopro = [
        'tipo_documento' => 'DNI', 'documento' => '', 'nombre' => '',
        'apellido_pat' => '', 'apellido_mat' => '', 'sexo' => 'M',
        'nacionalidad' => 'PERUANO', 'ocupacion' => 'transportista',
        'estado_civil' => 'soltero', 'direccion' => '', 'distrito' => '',
        'provincia' => 'LIMA', 'departamento' => 'LIMA', 'email' => '',
    ];

    public ?string $coproDocMsg = null;

    /**
     * Campos del alta rápida que llenó la consulta de documento — se pintan
     * EN ROJO (convención de toda la casa: lo que viene de la API se
     * distingue de lo tecleado).
     *
     * @var array<int, string>
     */
    public array $autoCopro = [];

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

    // ── Copropietario ──

    public function abrirCopro(int $vehiculoId): void
    {
        $this->autorizarEdicion();
        Vehiculo::where('client_id', $this->clientId)->findOrFail($vehiculoId);
        $this->coproVehiculoId = $this->coproVehiculoId === $vehiculoId ? null : $vehiculoId;
        $this->buscarCopro = '';
    }

    public function vincularCopro(int $vehiculoId, int $clientId): void
    {
        $this->autorizarEdicion();
        $v = Vehiculo::where('client_id', $this->clientId)->findOrFail($vehiculoId);

        if ($clientId === $this->clientId) {
            $this->msgType = 'err';
            $this->msg = 'El titular ya es dueño del vehículo: elige a otra persona como copropietario.';

            return;
        }

        $copro = Client::active()->find($clientId);
        if (! $copro) {
            $this->msgType = 'err';
            $this->msg = 'El cliente seleccionado no está activo.';

            return;
        }

        $v->copropietarios()->syncWithoutDetaching([$clientId => ['rol' => 'copropietario']]);

        Audit::log("Vinculó a {$copro->fullName()} como copropietario del vehículo {$v->placa}", $this->client);
        $this->msgType = 'ok';
        $this->msg = "{$copro->fullName()} quedó como copropietario del vehículo {$v->placa}.";
        $this->coproVehiculoId = null;
        $this->buscarCopro = '';
    }

    public function abrirCrearCopro(): void
    {
        $this->autorizarEdicion();
        $this->coproCreando = true;
        $this->coproDocMsg = null;
        // Si lo tipeado en el buscador parece un documento, arranca cargado.
        $term = trim($this->buscarCopro);
        if (preg_match('/^\d{8,12}$/', $term)) {
            $this->nuevoCopro['documento'] = $term;
        }
        $this->resetErrorBag();
    }

    public function cancelarCrearCopro(): void
    {
        $this->coproCreando = false;
        $this->reset('nuevoCopro', 'coproDocMsg', 'autoCopro');
        $this->resetErrorBag();
    }

    /** Autocompleta la persona desde RENIEC/Migraciones (mismo servicio del alta). */
    public function consultarDocCopro(Factiliza $api): void
    {
        $this->autorizarEdicion();
        $doc = trim($this->nuevoCopro['documento']);
        if ($doc === '') {
            $this->coproDocMsg = 'Escribe el documento que quieres consultar.';

            return;
        }

        // Si ya existe una ficha con ese documento (cliente o relacionado),
        // no se duplica: se vincula esa directamente.
        $existente = Client::where('documento', $doc)->first();
        if ($existente !== null) {
            $this->vincularCopro((int) $this->coproVehiculoId, $existente->id);
            $this->cancelarCrearCopro();

            return;
        }

        $r = $this->nuevoCopro['tipo_documento'] === 'CE' ? $api->cee($doc) : $api->dni($doc);
        if (! $r['ok']) {
            $this->coproDocMsg = $r['error'].' Puedes completar los datos a mano.';

            return;
        }

        // Lo que llena la API se pinta EN ROJO ($autoCopro → .campo-api).
        $this->autoCopro = [];
        foreach (['nombre', 'apellido_pat', 'apellido_mat', 'direccion', 'distrito', 'departamento'] as $campo) {
            if (! empty($r['data'][$campo])) {
                $this->nuevoCopro[$campo] = (string) $r['data'][$campo];
                $this->autoCopro[] = $campo;
            }
        }
        if (! empty($r['data']['sexo']) && in_array($r['data']['sexo'], ['M', 'F'], true)) {
            $this->nuevoCopro['sexo'] = $r['data']['sexo'];
            $this->autoCopro[] = 'sexo';
        }
        if (! empty($r['data']['estado_civil']) && isset(Create::ESTADOS_CIVILES[$r['data']['estado_civil']])) {
            $this->nuevoCopro['estado_civil'] = $r['data']['estado_civil'];
            $this->autoCopro[] = 'estado_civil';
        }
        $provincia = mb_strtoupper(trim((string) ($r['data']['provincia'] ?? '')));
        if (isset(Create::PROVINCIAS[$provincia])) {
            $this->nuevoCopro['provincia'] = $provincia;
            $this->autoCopro[] = 'provincia';
        }
        $this->coproDocMsg = 'Datos cargados. Verifica y completa lo que falte.';
    }

    /**
     * Crea la persona RELACIONADA (es_relacionado=1: sin expediente, asesor
     * ni capital — no aparece en el listado ni en reportes) y la vincula como
     * copropietaria del vehículo en el mismo paso.
     */
    public function crearYVincularCopro(): void
    {
        $this->autorizarEdicion();
        $vehiculoId = (int) $this->coproVehiculoId;
        Vehiculo::where('client_id', $this->clientId)->findOrFail($vehiculoId);

        $this->nuevoCopro['documento'] = trim($this->nuevoCopro['documento']);
        $this->nuevoCopro['nacionalidad'] = Nacionalidades::normalizar($this->nuevoCopro['nacionalidad']);

        $this->validate([
            'nuevoCopro.tipo_documento' => 'required|in:DNI,CE',
            'nuevoCopro.documento' => 'required|string|min:8|max:12|unique:clients,documento',
            'nuevoCopro.nombre' => 'required|string|max:200',
            'nuevoCopro.apellido_pat' => 'required|string|max:100',
            'nuevoCopro.apellido_mat' => 'nullable|string|max:100',
            'nuevoCopro.sexo' => 'required|in:M,F',
            'nuevoCopro.nacionalidad' => 'required|in:'.implode(',', Nacionalidades::OPCIONES),
            'nuevoCopro.ocupacion' => 'required|in:'.implode(',', array_keys(Create::OCUPACIONES)),
            'nuevoCopro.estado_civil' => 'required|in:'.implode(',', array_keys(Create::ESTADOS_CIVILES)),
            'nuevoCopro.direccion' => 'required|string|max:255',
            'nuevoCopro.distrito' => 'required|string|max:100',
            'nuevoCopro.provincia' => 'required|in:'.implode(',', array_keys(Create::PROVINCIAS)),
            'nuevoCopro.departamento' => 'required|string|max:100',
            'nuevoCopro.email' => 'required|email|max:150',
        ], [], [
            'nuevoCopro.documento' => 'documento',
            'nuevoCopro.nombre' => 'nombres',
            'nuevoCopro.apellido_pat' => 'apellido paterno',
            'nuevoCopro.email' => 'correo',
        ]);

        $copro = Client::create([
            'nombre' => $this->nuevoCopro['nombre'],
            'apellido_pat' => $this->nuevoCopro['apellido_pat'],
            'apellido_mat' => $this->nuevoCopro['apellido_mat'] ?: null,
            'tipo_documento' => $this->nuevoCopro['tipo_documento'],
            'documento' => $this->nuevoCopro['documento'],
            'sexo' => $this->nuevoCopro['sexo'],
            'nacionalidad' => $this->nuevoCopro['nacionalidad'],
            'ocupacion' => $this->nuevoCopro['ocupacion'],
            'estado_civil' => $this->nuevoCopro['estado_civil'],
            'direccion' => $this->nuevoCopro['direccion'],
            'distrito' => mb_strtoupper(trim($this->nuevoCopro['distrito'])),
            'provincia' => $this->nuevoCopro['provincia'],
            'departamento' => mb_strtoupper(trim($this->nuevoCopro['departamento'])),
            'email' => trim($this->nuevoCopro['email']),
            'es_relacionado' => true,
            'usuario' => auth()->user()->username ?? auth()->user()->name ?? null,
            'fecha_registro' => now()->toDateString(),
            'headquarter_id' => auth()->user()->headquarter_id ?? 1,
            'status' => 'active',
        ]);

        Audit::log("Creó a {$copro->fullName()} como persona relacionada (alta rápida de copropietario)", $this->client);

        $this->cancelarCrearCopro();
        $this->vincularCopro($vehiculoId, $copro->id);
    }

    public function quitarCopro(int $vehiculoId, int $clientId): void
    {
        $this->autorizarEdicion();
        $v = Vehiculo::where('client_id', $this->clientId)->findOrFail($vehiculoId);
        $copro = Client::find($clientId);

        $v->copropietarios()->detach($clientId);

        Audit::log('Quitó a '.($copro?->fullName() ?? "#{$clientId}")." como copropietario del vehículo {$v->placa}", $this->client);
        $this->msgType = 'ok';
        $this->msg = 'Copropietario retirado del vehículo '.$v->placa.'.';
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
        $term = trim($this->buscarCopro);
        $candidatos = ($this->coproVehiculoId !== null && mb_strlen($term) >= 2)
            ? Client::active()
                ->whereKeyNot($this->clientId)
                ->where(function ($q) use ($term) {
                    $q->where('documento', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT_WS(' ', apellido_pat, apellido_mat, nombre) LIKE ?", ["%{$term}%"])
                        ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ["%{$term}%"]);
                })
                ->orderBy('apellido_pat')
                ->limit(10)
                ->get()
            : collect();

        return view('livewire.clients.vehiculos', [
            'listado' => Vehiculo::with('copropietarios')->where('client_id', $this->clientId)->orderBy('id')->get(),
            'coproCandidatos' => $candidatos,
        ]);
    }
}
