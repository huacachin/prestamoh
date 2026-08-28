<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\User;
use App\Services\Factiliza;
use App\Support\Audit;
use App\Support\Documentos\Nacionalidades;
use App\Support\TiposCredito;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Alta de cliente en DOS PASOS (28/08):
 *   1. Datos del cliente (correo, ocupación y estado civil obligatorios).
 *   2. Vehículos — VARIOS por cliente y totalmente opcional: se puede
 *      terminar el alta sin registrar ninguno.
 *
 * Las consultas de DNI / RUC / carné de extranjería y de placa van contra
 * Factiliza (App\Services\Factiliza), que entrega los nombres ya separados:
 * se acabó el split posicional que rompía con apellidos compuestos.
 */
class Create extends Component
{
    public const OCUPACIONES = ['dependiente' => 'Dependiente', 'independiente' => 'Independiente', 'transportista' => 'Transportista'];

    public const ESTADOS_CIVILES = ['soltero' => 'Soltero(a)', 'casado' => 'Casado(a)', 'viudo' => 'Viudo(a)', 'divorciado' => 'Divorciado(a)'];

    public const TIPOS_DOCUMENTO = ['DNI' => 'DNI', 'RUC' => 'RUC', 'CE' => 'Carné de extranjería'];

    /**
     * Provincias que opera la financiera. El área legal las trata como un
     * dropdown porque la frase del contrato cambia: Lima colapsa a
     * "PROVINCIA Y DEPARTAMENTO DE LIMA" y Callao a "PROVINCIA
     * CONSTITUCIONAL DEL CALLAO" (ver DomicilioLegal).
     */
    public const PROVINCIAS = ['LIMA' => 'Lima', 'CALLAO' => 'Callao'];

    // ── Wizard ─────────────────────────────────────────────
    public int $paso = 1;

    // ── Búsqueda de documento ──────────────────────────────
    public string $docBuscar = '';

    public ?string $docMsg = null;

    public ?string $docMsgType = null; // 'ok' | 'warn' | 'err'

    // ── Paso 1: cliente ────────────────────────────────────
    public string $apellido_pat = '';

    public string $apellido_mat = '';

    public string $nombre = '';

    public string $nacionalidad = Nacionalidades::DEFECTO;

    public string $sexo = 'M';

    public ?string $fecha_nacimiento = null;

    public string $documento = '';

    public string $tipo_documento = 'DNI';

    public string $email = '';

    public string $ocupacion = 'transportista';

    public string $estado_civil = 'soltero';

    public ?int $expediente = null;

    public ?string $direccion = null;

    /**
     * Ubigeo del domicilio legal. La API los devuelve y hasta ahora se
     * descartaban: el mapeo solo listaba direccion y distrito, así que el
     * asesor tenía que retipear el domicilio entero en cada contrato.
     * `provincia` gobierna además el giro registral (Lima vs Callao).
     */
    public ?string $distrito = null;

    public string $provincia = 'LIMA';

    public ?string $departamento = 'LIMA';

    public ?string $giro = null;     // Legacy: input "Giro"

    public $capital = null;          // Capital declarado (línea de crédito = 25%)

    public ?string $zona = null;     // Legacy: input "T. Credito"

    public ?string $celular1 = null; // Legacy: "Celular / Whatsapp"

    public ?string $celular2 = null;

    public ?int $asesor_id = null;

    /**
     * ── Paso 2: vehículos (0..N) ───────────────────────────
     *
     * @var array<int, array<string, string|null>>
     */
    public array $vehiculos = [];

    public ?string $vehMsg = null;

    public ?string $vehMsgType = null;

    /**
     * Campos que llenó la consulta a la API (se pintan en rojo para que el
     * operador distinga lo traído de RENIEC/SUNAT/placa de lo que tecleó él).
     *
     * @var array<int, string>
     */
    public array $autoCliente = [];

    /** @var array<int, array<int, string>> por índice de vehículo */
    public array $autoVehiculo = [];

    public $asesores;

    public ?int $newClientId = null;

    public function mount(): void
    {
        // Analista (scope-propio): solo ve/paga SUS créditos — no crea ni edita
        abort_if(auth()->user()?->can('clientes.scope-propio') ?? false, 403,
            'Tu rol no permite esta acción.');
        $this->asesores = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $correl = (int) (DB::table('correlativos')->where('tipo', 'Cliente')->value('correl') ?? 0);
        $this->expediente = $correl + 1;
    }

    // ─────────────────────────────────────────────────────────
    //  Consulta de documento (Factiliza)
    // ─────────────────────────────────────────────────────────

    /** El tipo de documento elegido decide el endpoint: DNI / RUC / CE. */
    public function consultarDocumento(Factiliza $api): void
    {
        $this->docMsg = null;
        $this->docMsgType = null;

        $doc = trim((string) $this->docBuscar);
        // DNI y RUC son numéricos; el carné de extranjería es alfanumérico.
        if ($this->tipo_documento !== 'CE') {
            $doc = preg_replace('/\D+/', '', $doc);
        }

        if ($error = $this->validarLargoDocumento($doc)) {
            $this->docMsgType = 'err';
            $this->docMsg = $error;

            return;
        }

        // Bloqueo de duplicados antes de gastar una consulta
        $existe = Client::where('documento', $doc)->first(['id', 'expediente', 'nombre', 'apellido_pat', 'apellido_mat']);
        if ($existe) {
            $this->docMsgType = 'warn';
            $this->docMsg = "Documento ya registrado: #{$existe->id} · {$existe->fullName()} (exp. {$existe->expediente}).";

            return;
        }

        // El documento se propaga SIEMPRE, aunque la consulta falle: así el
        // operador no tiene que volver a tipearlo para cargarlo a mano.
        $this->documento = $doc;

        $resultado = match ($this->tipo_documento) {
            'RUC' => $api->ruc($doc),
            'CE' => $api->cee($doc),
            default => $api->dni($doc),
        };

        if (! $resultado['ok']) {
            $this->docMsgType = 'err';
            $this->docMsg = $resultado['error'].' Puedes completar los datos a mano.';

            return;
        }

        $this->aplicarDatosDocumento($resultado['data']);
    }

    private function validarLargoDocumento(string $doc): ?string
    {
        return match ($this->tipo_documento) {
            'DNI' => strlen($doc) === 8 ? null : 'El DNI debe tener 8 dígitos.',
            'RUC' => strlen($doc) === 11 ? null : 'El RUC debe tener 11 dígitos.',
            'CE' => (strlen($doc) >= 8 && strlen($doc) <= 12) ? null : 'El carné de extranjería debe tener entre 8 y 12 caracteres.',
            default => 'Selecciona el tipo de documento.',
        };
    }

    /** @param array<string, mixed> $d */
    private function aplicarDatosDocumento(array $d): void
    {
        $this->autoCliente = [];

        if ($this->tipo_documento === 'RUC') {
            // La razón social entera va al campo Nombres; los apellidos no aplican.
            $this->nombre = (string) ($d['nombre'] ?? '');
            $this->apellido_pat = '';
            $this->apellido_mat = '';
            $this->autoCliente[] = 'nombre';
            $this->docMsg = 'Razón social cargada en "Nombres". Apellidos no aplican para RUC.';
        } else {
            // DNI y CE: la API ya entrega los nombres separados.
            $this->nombre = (string) ($d['nombre'] ?? '');
            $this->apellido_pat = (string) ($d['apellido_pat'] ?? '');
            $this->apellido_mat = (string) ($d['apellido_mat'] ?? '');
            foreach (['nombre', 'apellido_pat', 'apellido_mat'] as $campo) {
                if ($this->{$campo} !== '') {
                    $this->autoCliente[] = $campo;
                }
            }
            $this->docMsg = $this->tipo_documento === 'CE'
                ? 'Datos cargados desde Migraciones. Verifica apellidos y nombres.'
                : 'Datos cargados desde RENIEC. Verifica apellidos y nombres.';
        }

        foreach (['direccion', 'distrito', 'departamento'] as $campo) {
            if (! empty($d[$campo])) {
                $this->{$campo} = (string) $d[$campo];
                $this->autoCliente[] = $campo;
            }
        }

        // La provincia es un combo cerrado (gobierna el giro registral del
        // contrato): solo se autocompleta si la API devuelve una de las que
        // opera la financiera. Si trae otra, queda la elegida a mano.
        $provincia = mb_strtoupper(trim((string) ($d['provincia'] ?? '')));
        if (isset(self::PROVINCIAS[$provincia])) {
            $this->provincia = $provincia;
            $this->autoCliente[] = 'provincia';
        }
        if (! empty($d['fecha_nacimiento'])) {
            $this->fecha_nacimiento = (string) $d['fecha_nacimiento'];
            $this->autoCliente[] = 'fecha_nacimiento';
        }
        if (! empty($d['sexo'])) {
            $this->sexo = (string) $d['sexo'];
            $this->autoCliente[] = 'sexo';
        }
        $this->autoCliente[] = 'documento';

        $this->docMsgType = 'ok';
    }

    /** Cambiar el tipo de documento limpia el mensaje anterior (endpoint distinto). */
    public function updatedTipoDocumento(): void
    {
        $this->docMsg = null;
        $this->docMsgType = null;
    }

    // ─────────────────────────────────────────────────────────
    //  Wizard
    // ─────────────────────────────────────────────────────────

    /** Paso 1 → 2: valida SOLO los campos del cliente. */
    public function siguientePaso(): void
    {
        $this->nacionalidad = Nacionalidades::normalizar($this->nacionalidad);
        $this->validate($this->reglasCliente(), $this->messages());
        $this->paso = 2;
    }

    public function pasoAnterior(): void
    {
        $this->paso = 1;
    }

    // ─────────────────────────────────────────────────────────
    //  Paso 2: vehículos (varios, opcional)
    // ─────────────────────────────────────────────────────────

    public function agregarVehiculo(): void
    {
        $this->vehiculos[] = [
            'placa' => '', 'marca' => '', 'modelo' => '', 'nro_motor' => '',
            'nro_serie' => '', 'categoria' => '', 'anio_modelo' => '', 'carroceria' => '',
            'color' => '', 'combustible' => '', 'valor' => '',
        ];
        $this->vehMsg = null;
    }

    public function quitarVehiculo(int $i): void
    {
        unset($this->vehiculos[$i], $this->autoVehiculo[$i]);
        $this->vehiculos = array_values($this->vehiculos);
        $this->autoVehiculo = array_values($this->autoVehiculo);
        $this->resetErrorBag();
        $this->vehMsg = null;
    }

    /** Autocompleta una fila de vehículo con los datos de la placa (Factiliza). */
    public function consultarPlaca(int $i, Factiliza $api): void
    {
        $this->vehMsg = null;
        $this->vehMsgType = null;

        $placa = strtoupper(trim((string) ($this->vehiculos[$i]['placa'] ?? '')));
        if ($placa === '') {
            $this->vehMsgType = 'err';
            $this->vehMsg = 'Escribe la placa que quieres consultar.';

            return;
        }

        $resultado = $api->placa($placa);
        if (! $resultado['ok']) {
            $this->vehMsgType = 'err';
            $this->vehMsg = $resultado['error'].' Puedes completar los datos a mano.';

            return;
        }

        $this->autoVehiculo[$i] = [];
        foreach ($resultado['data'] as $campo => $valor) {
            if ($valor !== '' && array_key_exists($campo, $this->vehiculos[$i])) {
                $this->vehiculos[$i][$campo] = $valor;
                $this->autoVehiculo[$i][] = $campo;
            }
        }

        $this->vehMsgType = 'ok';
        $this->vehMsg = "Datos de la placa {$placa} cargados. Verifica antes de guardar.";
    }

    public function clean(): void
    {
        $this->reset([
            'docBuscar', 'docMsg', 'docMsgType',
            'apellido_pat', 'apellido_mat', 'nombre', 'sexo', 'fecha_nacimiento',
            'documento', 'tipo_documento', 'email', 'ocupacion', 'estado_civil',
            'nacionalidad', 'direccion', 'distrito', 'provincia', 'departamento',
            'giro', 'zona', 'celular1', 'celular2',
            'vehiculos', 'vehMsg', 'vehMsgType', 'autoCliente', 'autoVehiculo',
        ]);
        $this->sexo = 'M';
        $this->tipo_documento = 'DNI';
        $this->ocupacion = 'transportista';
        $this->estado_civil = 'soltero';
        $this->nacionalidad = Nacionalidades::DEFECTO;
        $this->provincia = 'LIMA';
        $this->departamento = 'LIMA';
        $this->paso = 1;
        $this->resetErrorBag();
    }

    // ─────────────────────────────────────────────────────────
    //  Validación
    // ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function reglasCliente(): array
    {
        $isRuc = $this->tipo_documento === 'RUC';

        return [
            'nombre' => 'required|string|max:200',
            'apellido_pat' => $isRuc ? 'nullable|string|max:100' : 'required|string|max:100',
            'apellido_mat' => 'nullable|string|max:100',
            'sexo' => 'required|in:M,F',
            'fecha_nacimiento' => 'nullable|date',
            'tipo_documento' => 'required|in:'.implode(',', array_keys(self::TIPOS_DOCUMENTO)),
            'documento' => 'required|string|min:8|max:12|unique:clients,documento',
            'email' => 'required|email|max:150',
            'ocupacion' => 'required|in:'.implode(',', array_keys(self::OCUPACIONES)),
            'estado_civil' => 'required|in:'.implode(',', array_keys(self::ESTADOS_CIVILES)),
            'expediente' => 'required|integer|min:1',
            // El domicilio legal es la cláusula PRIMERO del contrato: sin
            // dirección arranca en "DISTRITO DE" y sin ubigeo queda a medias.
            'nacionalidad' => 'required|in:'.implode(',', Nacionalidades::OPCIONES),
            'direccion' => 'required|string|max:255',
            'distrito' => 'required|string|max:100',
            'provincia' => 'required|in:'.implode(',', array_keys(self::PROVINCIAS)),
            'departamento' => 'required|string|max:100',
            'giro' => 'nullable|string|max:100',
            'capital' => 'nullable|numeric|min:0',
            'zona' => 'nullable|string|in:'.implode(',', TiposCredito::OPCIONES),
            'celular1' => 'nullable|string|max:20',
            'celular2' => 'nullable|string|max:20',
            'asesor_id' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Vehículos: la lista puede ir VACÍA (paso opcional). De cada fila
     * agregada solo se exige la placa — es la clave del registro.
     *
     * @return array<string, mixed>
     */
    private function reglasVehiculos(): array
    {
        return [
            'vehiculos' => 'array',
            'vehiculos.*.placa' => 'required|string|max:10|distinct|unique:vehiculos,placa',
            'vehiculos.*.marca' => 'nullable|string|max:50',
            'vehiculos.*.modelo' => 'nullable|string|max:50',
            'vehiculos.*.nro_motor' => 'nullable|string|max:30',
            'vehiculos.*.nro_serie' => 'nullable|string|max:30',
            'vehiculos.*.categoria' => 'nullable|string|max:30',
            'vehiculos.*.anio_modelo' => 'nullable|string|max:10',
            'vehiculos.*.carroceria' => 'nullable|string|max:50',
            'vehiculos.*.color' => 'nullable|string|max:50',
            'vehiculos.*.combustible' => 'nullable|string|max:30',
            'vehiculos.*.valor' => 'nullable|numeric|min:0',
        ];
    }

    protected function rules(): array
    {
        return array_merge($this->reglasCliente(), $this->reglasVehiculos());
    }

    protected function messages(): array
    {
        return [
            'vehiculos.*.placa.required' => 'Ingresa la placa (o quita el vehículo de la lista).',
            'vehiculos.*.placa.unique' => 'Esa placa ya está registrada en otro cliente.',
            'vehiculos.*.placa.distinct' => 'La placa está repetida en la lista.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Escribe un correo electrónico válido.',
            'ocupacion.required' => 'Selecciona la ocupación.',
            'estado_civil.required' => 'Selecciona el estado civil.',
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Guardado
    // ─────────────────────────────────────────────────────────

    public function save()
    {
        abort_if(auth()->user()?->can('clientes.scope-propio') ?? false, 403,
            'Tu rol no permite esta acción.');

        // Normalizar ANTES de validar, para que el unique de placa compare
        // contra lo que realmente se guardará.
        foreach ($this->vehiculos as $i => $v) {
            foreach (['placa', 'nro_motor', 'nro_serie'] as $campo) {
                $this->vehiculos[$i][$campo] = strtoupper(trim((string) ($v[$campo] ?? ''))) ?: null;
            }
        }

        $this->validate();

        // Re-confirmar duplicado al guardar (race condition)
        if (Client::where('documento', $this->documento)->exists()) {
            $this->paso = 1;
            $this->addError('documento', 'El documento ya está registrado.');

            return;
        }

        $expediente = (int) $this->expediente;

        DB::transaction(function () use ($expediente) {
            $clientId = DB::table('clients')->insertGetId([
                'expediente' => (string) $expediente,
                'nombre' => $this->nombre,
                'apellido_pat' => $this->apellido_pat,
                'apellido_mat' => $this->apellido_mat,
                'tipo_documento' => $this->tipo_documento,
                'documento' => $this->documento,
                'fecha_nacimiento' => $this->fecha_nacimiento,
                'sexo' => $this->sexo,
                'nacionalidad' => Nacionalidades::normalizar($this->nacionalidad),
                'email' => trim($this->email),
                'ocupacion' => $this->ocupacion,
                'estado_civil' => $this->estado_civil,
                'direccion' => $this->direccion,
                'distrito' => $this->distrito,
                'provincia' => $this->provincia,
                'departamento' => $this->departamento,
                'giro' => $this->giro,
                'capital' => $this->capital !== null && $this->capital !== '' ? $this->capital : null,
                'zona' => $this->zona,
                'celular1' => $this->celular1,
                'celular2' => $this->celular2,
                'asesor_id' => $this->asesor_id,
                'headquarter_id' => auth()->user()->headquarter_id ?? 1,
                'usuario' => auth()->user()->username ?? auth()->user()->name ?? null,
                'fecha_registro' => now()->toDateString(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($this->vehiculos as $v) {
                if (empty($v['placa'])) {
                    continue;
                }
                DB::table('vehiculos')->insert([
                    'client_id' => $clientId,
                    'placa' => $v['placa'],
                    'marca' => $v['marca'] ?: null,
                    'modelo' => $v['modelo'] ?: null,
                    'nro_motor' => $v['nro_motor'] ?: null,
                    'nro_serie' => $v['nro_serie'] ?: null,
                    'categoria' => $v['categoria'] ?: null,
                    'anio_modelo' => $v['anio_modelo'] ?: null,
                    'carroceria' => $v['carroceria'] ?: null,
                    'color' => $v['color'] ?: null,
                    'combustible' => $v['combustible'] ?: null,
                    'valor' => ($v['valor'] ?? '') !== '' ? $v['valor'] : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Avanzar correlativo al expediente que terminó usando el usuario
            DB::table('correlativos')
                ->where('tipo', 'Cliente')
                ->update(['correl' => $expediente, 'updated_at' => now()]);

            $placas = collect($this->vehiculos)->pluck('placa')->filter()->implode(', ');
            $msg = "Cliente registrado · expediente {$expediente}."
                .($placas !== '' ? " Vehículos: {$placas}." : '');
            session()->flash('client_success', $msg);
            $this->newClientId = $clientId;
        });

        $createdClient = Client::find($this->newClientId);
        $placas = collect($this->vehiculos)->pluck('placa')->filter()->values()->all();
        Audit::log('Creó el cliente '.($createdClient?->fullName() ?? $this->documento), $createdClient,
            $placas !== [] ? ['vehiculos' => $placas] : []);

        return redirect()->route('clients.gallery', $this->newClientId);
    }

    public function render()
    {
        return view('livewire.clients.create');
    }
}
