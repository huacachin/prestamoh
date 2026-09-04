<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientEmail;
use App\Models\ClientEmpresa;
use App\Models\User;
use App\Services\Factiliza;
use App\Support\Audit;
use App\Support\Documentos\DomicilioLegal;
use App\Support\Documentos\Nacionalidades;
use App\Support\Ubigeo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public const TIPOS_DOCUMENTO = ['DNI' => 'DNI', 'CE' => 'CE — Carné de extranjería', 'RUC' => 'RUC'];

    /**
     * Opciones (clave => etiqueta) conservando un valor guardado fuera del
     * catálogo — la ficha acepta texto libre (03/09); el wizard de contratos
     * mantiene su catálogo estricto y no hereda valores fuera de él.
     */
    public static function ocupacionesPara(?string $actual): array
    {
        $actual = trim((string) $actual);

        return ($actual !== '' && ! isset(self::OCUPACIONES[$actual]))
            ? [$actual => $actual] + self::OCUPACIONES
            : self::OCUPACIONES;
    }

    public static function estadosCivilesPara(?string $actual): array
    {
        $actual = trim((string) $actual);

        return ($actual !== '' && ! isset(self::ESTADOS_CIVILES[$actual]))
            ? [$actual => $actual] + self::ESTADOS_CIVILES
            : self::ESTADOS_CIVILES;
    }

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

    /**
     * Correos del cliente (04/09): filas {email, principal}. El principal
     * es el que sale en contratos; clients.email lo espeja al guardar.
     * En el alta siempre hay al menos una fila.
     *
     * @var array<int, array{email: string, principal: bool}>
     */
    public array $correos = [['email' => '', 'principal' => true]];

    public function agregarCorreo(): void
    {
        $this->correos[] = ['email' => '', 'principal' => count($this->correos) === 0];
    }

    public function quitarCorreo(int $i): void
    {
        if (! isset($this->correos[$i]) || count($this->correos) <= 1) {
            return; // siempre queda una fila (el correo es obligatorio en el alta)
        }
        $eraPrincipal = $this->correos[$i]['principal'];
        unset($this->correos[$i]);
        $this->correos = array_values($this->correos);
        if ($eraPrincipal) {
            $this->correos[0]['principal'] = true;
        }
    }

    public function marcarPrincipal(int $i): void
    {
        if (! isset($this->correos[$i])) {
            return;
        }
        foreach ($this->correos as $j => $c) {
            $this->correos[$j]['principal'] = ($j === $i);
        }
    }

    /** El correo principal (para clients.email, empresa y ficha). */
    private function correoPrincipal(): string
    {
        $fila = collect($this->correos)->firstWhere('principal', true) ?? ($this->correos[0] ?? null);

        return trim((string) ($fila['email'] ?? ''));
    }

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

    public string $provincia = 'Lima';

    public ?string $departamento = 'Lima';

    /**
     * Cascada de ubigeo (catálogo App\Support\Ubigeo): al cambiar el
     * departamento, si la provincia elegida no le pertenece se resetea a la
     * capital (primera del catálogo); al cambiar provincia, el distrito se
     * conserva solo si pertenece a la nueva (si no, se limpia para que el
     * select2 no muestre un distrito de otra provincia).
     */
    public function updatedDepartamento(): void
    {
        $this->departamento = Ubigeo::resolverDepartamento($this->departamento) ?? $this->departamento;
        if (Ubigeo::resolverProvincia($this->departamento, $this->provincia) === null) {
            $this->provincia = Ubigeo::provinciasDe($this->departamento)[0] ?? $this->provincia;
            $this->updatedProvincia();
        }
    }

    public function updatedProvincia(): void
    {
        // Provincia de OTRO departamento (la API o un set directo la mandan
        // antes que el departamento): arrastra a su departamento y normaliza.
        if (Ubigeo::resolverProvincia($this->departamento, $this->provincia) === null) {
            $ubicada = Ubigeo::buscarProvincia($this->provincia);
            if ($ubicada !== null) {
                [$this->departamento, $this->provincia] = $ubicada;
            }
        } else {
            $this->provincia = Ubigeo::resolverProvincia($this->departamento, $this->provincia);
        }

        // El distrito se conserva si pertenece a la nueva provincia; si la
        // provincia quedo fuera del catalogo (historico) no se toca nada.
        $distritos = Ubigeo::distritosDe($this->departamento, $this->provincia);
        if (filled($this->distrito) && $distritos !== []) {
            $enCatalogo = collect($distritos)
                ->contains(fn ($d) => mb_strtoupper($d) === mb_strtoupper(trim((string) $this->distrito)));
            if (! $enCatalogo) {
                $this->distrito = null;
            }
        }
    }

    public ?string $giro = null;     // Legacy: input "Giro"

    public $capital = null;          // Capital declarado (línea de crédito = 25%)

    public ?string $zona = null;     // Legacy: input "T. Credito"

    public ?string $celular1 = null; // Legacy: "Celular / Whatsapp"

    public ?string $celular2 = null;

    public ?int $asesor_id = null;

    /**
     * REPRESENTANTE LEGAL (solo alta con RUC). La empresa no tiene sexo,
     * ocupación, estado civil ni nacionalidad: esos datos son de la persona
     * que la representa, y el contrato a.4 los exige del GERENTE. Se guardan
     * en client_empresas + empresa_representantes (vigente=1) y el wizard de
     * contrato los precarga solo.
     */
    public array $representante = [
        'nombre' => '', 'tipo_documento' => 'DNI', 'dni' => '', 'sexo' => 'M',
        'nacionalidad' => 'PERUANO', 'ocupacion' => 'independiente',
        'estado_civil' => 'soltero', 'domicilio' => '',
    ];

    /** Campos del representante llenados por consulta/herencia (van en rojo). @var array<int, string> */
    public array $autoRep = [];

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
        $existe = Client::where('documento', $doc)->first();
        if ($existe && ! $existe->es_relacionado) {
            $this->docMsgType = 'warn';
            $this->docMsg = "Documento ya registrado: #{$existe->id} · {$existe->fullName()} (exp. {$existe->expediente}).";

            return;
        }

        // PROMOCIÓN: existe como persona relacionada (copropietario del alta
        // rápida). Se precarga su ficha y al guardar se COMPLETA esa misma
        // fila — mismo id, sus vehículos y vínculos intactos — en vez de
        // duplicar a la persona.
        if ($existe) {
            $this->documento = $doc;
            $this->tipo_documento = $existe->tipo_documento ?: $this->tipo_documento;
            foreach (['nombre', 'apellido_pat', 'apellido_mat', 'direccion', 'distrito', 'departamento'] as $campo) {
                $this->{$campo} = (string) ($existe->{$campo} ?? '');
            }
            $this->sexo = $existe->sexo ?: 'M';
            $this->nacionalidad = $existe->nacionalidad ?: $this->nacionalidad;
            $correosPrevios = $existe->emails()->orderByDesc('principal')->orderBy('id')
                ->get(['email', 'principal'])
                ->map(fn ($e) => ['email' => (string) $e->email, 'principal' => (bool) $e->principal])
                ->all();
            $this->correos = $correosPrevios !== [] ? $correosPrevios
                : [['email' => (string) ($existe->email ?? ''), 'principal' => true]];
            $this->ocupacion = $existe->ocupacion ?: $this->ocupacion;
            $this->estado_civil = $existe->estado_civil ?: $this->estado_civil;
            $this->departamento = Ubigeo::resolverDepartamento($existe->departamento) ?? ($existe->departamento ?: $this->departamento);
            $this->provincia = Ubigeo::resolverProvincia($this->departamento, $existe->provincia)
                ?? ($existe->provincia ?: $this->provincia);
            $this->fecha_nacimiento = $existe->fecha_nacimiento?->format('Y-m-d');
            $this->docMsgType = 'ok';
            $this->docMsg = "{$existe->fullName()} ya existe como PERSONA RELACIONADA: al guardar se completará su ficha como cliente (mismo registro, conserva sus vínculos).";

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

        // La provincia se autocompleta si existe en el catálogo (Lima con sus
        // 10 provincias o Callao) y arrastra el departamento correcto. Si la
        // API trae una de otro departamento no operado, queda la elegida a mano.
        $ubicada = Ubigeo::buscarProvincia((string) ($d['provincia'] ?? ''));
        if ($ubicada !== null) {
            [$this->departamento, $this->provincia] = $ubicada;
            $this->autoCliente[] = 'provincia';
            $this->autoCliente[] = 'departamento';
        }
        if (! empty($d['fecha_nacimiento'])) {
            $this->fecha_nacimiento = (string) $d['fecha_nacimiento'];
            $this->autoCliente[] = 'fecha_nacimiento';
        }
        if (! empty($d['estado_civil']) && isset(self::ESTADOS_CIVILES[$d['estado_civil']])) {
            $this->estado_civil = (string) $d['estado_civil'];
            $this->autoCliente[] = 'estado_civil';
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

    /**
     * Consulta el documento del REPRESENTANTE (alta RUC): ficha local primero
     * (hereda sexo, nacionalidad, ocupación y estado civil del cliente si ya
     * está registrado), endpoint de DNI/CE después. Lo llenado va en rojo.
     */
    public function consultarDocRepresentante(Factiliza $api): void
    {
        $doc = trim((string) $this->representante['dni']);
        if ($doc === '') {
            $this->docMsgType = 'err';
            $this->docMsg = 'Escribe el documento del representante antes de consultar.';

            return;
        }

        $this->autoRep = [];

        $ficha = Client::where('documento', $doc)->first();
        if ($ficha !== null) {
            $this->representante['nombre'] = mb_strtoupper($ficha->fullName());
            $this->representante['sexo'] = $ficha->sexo === 'F' ? 'F' : 'M';
            $this->representante['nacionalidad'] = $ficha->nacionalidad ?: Nacionalidades::DEFECTO;
            if (isset(self::OCUPACIONES[(string) $ficha->ocupacion])) {
                $this->representante['ocupacion'] = (string) $ficha->ocupacion;
            }
            if (isset(self::ESTADOS_CIVILES[(string) $ficha->estado_civil])) {
                $this->representante['estado_civil'] = (string) $ficha->estado_civil;
            }
            $this->autoRep = ['nombre', 'sexo', 'nacionalidad', 'ocupacion', 'estado_civil'];
            $this->docMsgType = 'ok';
            $this->docMsg = "Representante heredado de la ficha de {$ficha->fullName()}.";

            return;
        }

        $r = $this->representante['tipo_documento'] === 'CE' ? $api->cee($doc) : $api->dni($doc);
        if (! $r['ok']) {
            $this->docMsgType = 'err';
            $this->docMsg = $r['error'].' Puedes completar los datos del representante a mano.';

            return;
        }

        $nombre = trim(implode(' ', array_filter([
            $r['data']['nombre'] ?? '', $r['data']['apellido_pat'] ?? '', $r['data']['apellido_mat'] ?? '',
        ])));
        if ($nombre !== '') {
            $this->representante['nombre'] = mb_strtoupper($nombre);
            $this->autoRep[] = 'nombre';
        }
        if (in_array($r['data']['sexo'] ?? null, ['M', 'F'], true)) {
            $this->representante['sexo'] = $r['data']['sexo'];
            $this->autoRep[] = 'sexo';
        }
        if (! empty($r['data']['estado_civil']) && isset(self::ESTADOS_CIVILES[$r['data']['estado_civil']])) {
            $this->representante['estado_civil'] = $r['data']['estado_civil'];
            $this->autoRep[] = 'estado_civil';
        }
        $this->docMsgType = 'ok';
        $this->docMsg = 'Datos del representante cargados. Verifica y completa lo que falte.';
    }

    public function clean(): void
    {
        $this->reset([
            'docBuscar', 'docMsg', 'docMsgType',
            'apellido_pat', 'apellido_mat', 'nombre', 'sexo', 'fecha_nacimiento',
            'documento', 'tipo_documento', 'correos', 'ocupacion', 'estado_civil',
            'nacionalidad', 'direccion', 'distrito', 'provincia', 'departamento',
            'giro', 'zona', 'celular1', 'celular2',
            'vehiculos', 'vehMsg', 'vehMsgType', 'autoCliente', 'autoVehiculo',
        ]);
        $this->sexo = 'M';
        $this->tipo_documento = 'DNI';
        $this->ocupacion = 'transportista';
        $this->estado_civil = 'soltero';
        $this->nacionalidad = Nacionalidades::DEFECTO;
        $this->reset('representante', 'autoRep');
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

        $reglas = [
            'nombre' => 'required|string|max:200',
            'apellido_pat' => $isRuc ? 'nullable|string|max:100' : 'required|string|max:100',
            'apellido_mat' => 'nullable|string|max:100',
            // La EMPRESA no tiene datos personales: son del representante.
            'sexo' => $isRuc ? 'nullable' : 'required|in:M,F',
            'fecha_nacimiento' => 'nullable|date',
            'tipo_documento' => 'required|in:'.implode(',', array_keys(self::TIPOS_DOCUMENTO)),
            // unique SOLO contra clientes de verdad: si el documento existe
            // como persona relacionada, guardar PROMUEVE esa ficha (misma
            // fila) en vez de duplicarla.
            'documento' => ['required', 'string', 'min:8', 'max:12',
                Rule::unique('clients', 'documento')->where(fn ($q) => $q->where('es_relacionado', false))],
            'correos' => 'required|array|min:1',
            'correos.*.email' => 'required|email|max:150',
            'ocupacion' => $isRuc ? 'nullable' : 'required|string|max:100',
            'estado_civil' => $isRuc ? 'nullable' : 'required|string|max:100',
            'expediente' => 'required|integer|min:1',
            // El domicilio legal es la cláusula PRIMERO del contrato: sin
            // dirección arranca en "DISTRITO DE" y sin ubigeo queda a medias.
            'nacionalidad' => $isRuc ? 'nullable' : 'required|in:'.implode(',', Nacionalidades::OPCIONES),
            'direccion' => 'required|string|max:255',
            'distrito' => 'required|string|max:100',
            'provincia' => 'required|string|max:100',
            'departamento' => 'required|string|max:100',
            'giro' => 'nullable|string|max:100',
            'capital' => 'nullable|numeric|min:0',
            'zona' => 'nullable|string|max:100',
            'celular1' => 'nullable|string|max:20',
            'celular2' => 'nullable|string|max:20',
            'asesor_id' => 'nullable|exists:users,id',
        ];

        if ($isRuc) {
            $reglas += [
                'representante.nombre' => 'required|string|max:150',
                'representante.tipo_documento' => 'required|in:DNI,CE',
                'representante.dni' => 'required|string|min:8|max:12',
                'representante.sexo' => 'required|in:M,F',
                'representante.nacionalidad' => 'required|in:'.implode(',', Nacionalidades::OPCIONES),
                'representante.ocupacion' => 'required|in:'.implode(',', array_keys(self::OCUPACIONES)),
                'representante.estado_civil' => 'required|in:'.implode(',', array_keys(self::ESTADOS_CIVILES)),
                'representante.domicilio' => 'nullable|string|max:300',
            ];
        }

        return $reglas;
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

        // Re-confirmar duplicado al guardar (race condition) — los
        // relacionados no cuentan: a esos se los promueve.
        if (Client::titulares()->where('documento', $this->documento)->exists()) {
            $this->paso = 1;
            $this->addError('documento', 'El documento ya está registrado.');

            return;
        }

        $expediente = (int) $this->expediente;

        DB::transaction(function () use ($expediente) {
            $isRuc = $this->tipo_documento === 'RUC';
            $datosCliente = [
                'expediente' => (string) $expediente,
                'nombre' => $this->nombre,
                'apellido_pat' => $this->apellido_pat,
                'apellido_mat' => $this->apellido_mat,
                'tipo_documento' => $this->tipo_documento,
                'documento' => $this->documento,
                // La EMPRESA no tiene datos personales: quedan NULL y los del
                // representante van a empresa_representantes.
                'fecha_nacimiento' => $isRuc ? null : $this->fecha_nacimiento,
                'sexo' => $isRuc ? null : $this->sexo,
                'nacionalidad' => $isRuc ? null : Nacionalidades::normalizar($this->nacionalidad),
                'email' => $this->correoPrincipal(),
                'ocupacion' => $isRuc ? null : $this->ocupacion,
                'estado_civil' => $isRuc ? null : $this->estado_civil,
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
                'updated_at' => now(),
            ];

            // PROMOCIÓN: si la persona ya existe como relacionada (alta
            // rápida de copropietario), se completa ESA fila y se apaga la
            // marca — mismo id, sus vehículos y vínculos quedan intactos.
            $relacionado = Client::where('es_relacionado', true)
                ->where('documento', $this->documento)
                ->lockForUpdate()
                ->first();

            if ($relacionado !== null) {
                $relacionado->update($datosCliente + ['es_relacionado' => false]);
                $clientId = $relacionado->id;
            } else {
                $clientId = DB::table('clients')->insertGetId($datosCliente + ['created_at' => now()]);
            }

            // Correos múltiples (04/09): se guardan TODOS; el principal se
            // espeja en clients.email vía ClientEmail::espejar.
            DB::table('client_emails')->where('client_id', $clientId)->update(['principal' => 0]);
            foreach ($this->correos as $fila) {
                $correo = trim((string) $fila['email']);
                if ($correo === '') {
                    continue;
                }
                DB::table('client_emails')->updateOrInsert(
                    ['client_id' => $clientId, 'email' => $correo],
                    ['principal' => $fila['principal'] ? 1 : 0, 'updated_at' => now()],
                );
            }
            ClientEmail::espejar($clientId);

            // Empresa: ficha registral + representante legal VIGENTE. El
            // wizard de contrato los precarga (representanteVigente), así que
            // el a.4 sale sin retipear al gerente.
            if ($isRuc) {
                $empresa = ClientEmpresa::updateOrCreate(
                    ['client_id' => $clientId],
                    [
                        'domicilio' => DomicilioLegal::armar(
                            $this->direccion, $this->distrito, $this->provincia, $this->departamento
                        ) ?: null,
                        'correo' => $this->correoPrincipal() ?: null,
                    ],
                );
                $empresa->representantes()->create([
                    'nombre' => mb_strtoupper(trim($this->representante['nombre'])),
                    'tipo_documento' => $this->representante['tipo_documento'],
                    'documento' => trim($this->representante['dni']),
                    'sexo' => $this->representante['sexo'] === 'F' ? 'F' : 'M',
                    'nacionalidad' => Nacionalidades::normalizar($this->representante['nacionalidad']),
                    'ocupacion' => $this->representante['ocupacion'],
                    'estado_civil' => $this->representante['estado_civil'],
                    'domicilio' => trim((string) $this->representante['domicilio'])
                        ?: (DomicilioLegal::armar(
                            $this->direccion, $this->distrito, $this->provincia, $this->departamento
                        ) ?: null),
                    'vigente' => true,
                ]);
            }

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
