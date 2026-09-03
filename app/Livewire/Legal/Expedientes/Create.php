<?php

namespace App\Livewire\Legal\Expedientes;

use App\Models\Client;
use App\Models\Credit;
use App\Models\ExpedienteJudicial;
use App\Models\Garantia;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Alta de expediente judicial (Área Legal — FASE 4).
 *
 * Formulario de una sola página: se busca el cliente activo (al elegirlo se
 * autocompleta el exp. interno con clients.expediente y se ofrecen sus
 * créditos activos y garantías como vínculos opcionales), se capturan los
 * datos del cuaderno principal y, opcionalmente, se crea EN LA MISMA
 * TRANSACCIÓN el cuaderno cautelar: su N° se deriva del principal con
 * ExpedienteJudicial::nroCautelarDesde() (dígito de cuaderno 0 → 1) y nace
 * en estado 'solicitada' colgando del principal vía expediente_padre_id.
 */
class Create extends Component
{
    /** Materias frecuentes del área (datalist: el input admite otras). */
    public const MATERIAS = [
        'OBLIGACION DE DAR SUMA DE DINERO',
        'EJECUCIÓN DE ACTA DE CONCILIACIÓN',
        'EJECUCIÓN DE GARANTÍAS',
        'INCAUTACIÓN JUDICIAL',
        'OTORGAMIENTO DE ESCRITURA PÚBLICA',
    ];

    /** Tipos de proceso frecuentes (datalist: el input admite otros). */
    public const PROCESOS = [
        'Ejecutivo',
        'Único de ejecución',
        'Sumarísimo',
    ];

    // ─── Cliente y vínculos opcionales ──────────────────────────────────────

    public string $buscarCliente = '';

    public ?int $client_id = null;

    /** Crédito activo del cliente (opcional). */
    public $credit_id = null;

    /** Garantía del cliente (opcional). */
    public $garantia_id = null;

    // ─── Datos del cuaderno principal ───────────────────────────────────────

    /** Código interno (clients.expediente); se autocompleta al elegir cliente. */
    public string $exp_interno = '';

    public string $nro_expediente = '';

    /** Clave de ExpedienteJudicial::VIAS. */
    public string $via = '';

    public string $materia = '';

    public string $proceso = '';

    public string $juzgado = '';

    public string $distrito_judicial = '';

    public string $juez = '';

    public string $secretario = '';

    public $monto_pretension = null;

    public string $fecha_inicio = '';

    public ?int $asesor_responsable_id = null;

    public string $observaciones = '';

    // ─── Cuaderno cautelar opcional ─────────────────────────────────────────

    public bool $crearCautelar = false;

    /** Clave de ExpedienteJudicial::FORMAS_MEDIDA. */
    public string $forma_medida = '';

    /** Ej. "VEHÍCULO placa ABC-123". */
    public string $bien_descripcion = '';

    public string $fecha_inicio_cautelar = '';

    public function mount(): void
    {
        $this->fecha_inicio = now()->toDateString();
        $this->fecha_inicio_cautelar = now()->toDateString();
        $this->asesor_responsable_id = auth()->id();
    }

    // ─── Cliente: buscador ──────────────────────────────────────────────────

    public function seleccionarCliente(int $id): void
    {
        $client = Client::where('status', 'active')->find($id);
        if (! $client) {
            $this->dispatch('errorAlert', ['message' => 'El cliente seleccionado no está activo.']);

            return;
        }

        $this->client_id = $client->id;
        $this->exp_interno = (string) ($client->expediente ?? '');
        $this->credit_id = null;
        $this->garantia_id = null;
        $this->buscarCliente = '';
        $this->resetErrorBag(['client_id', 'credit_id', 'garantia_id']);
    }

    public function quitarCliente(): void
    {
        $this->client_id = null;
        $this->credit_id = null;
        $this->garantia_id = null;
        $this->exp_interno = '';
        $this->buscarCliente = '';
    }

    // ─── Normalizaciones en vivo ────────────────────────────────────────────

    /** El formato del PJ es en mayúsculas: normaliza mientras se tipea. */
    public function updatedNroExpediente($value): void
    {
        $this->nro_expediente = mb_strtoupper(trim((string) $value));
    }

    /** Al activar el cautelar, su fecha parte de la fecha del principal. */
    public function updatedCrearCautelar($value): void
    {
        if ($value && blank($this->fecha_inicio_cautelar)) {
            $this->fecha_inicio_cautelar = $this->fecha_inicio ?: now()->toDateString();
        }
    }

    // ─── Validación ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        $reglas = [
            'client_id' => [
                'required', 'integer',
                Rule::exists('clients', 'id')->where('status', 'active'),
            ],
            'credit_id' => [
                'nullable', 'integer',
                Rule::exists('credits', 'id')->where('client_id', $this->client_id),
            ],
            'garantia_id' => [
                'nullable', 'integer',
                Rule::exists('garantias', 'id')->where('client_id', $this->client_id),
            ],
            'exp_interno' => ['nullable', 'string', 'max:10'],
            'nro_expediente' => [
                'required', 'string', 'max:35',
                function ($attribute, $value, $fail) {
                    if (! ExpedienteJudicial::formatoValido((string) $value)) {
                        $fail('El N° de expediente no tiene el formato del Poder Judicial. Formato esperado: 04388-2024-0-3209-JP-CI-01.');
                    }
                },
                Rule::unique('expedientes_judiciales', 'nro_expediente'),
            ],
            'via' => ['required', Rule::in(array_keys(ExpedienteJudicial::VIAS))],
            'materia' => ['nullable', 'string', 'max:100'],
            'proceso' => ['nullable', 'string', 'max:40'],
            'juzgado' => ['nullable', 'string', 'max:150'],
            'distrito_judicial' => ['nullable', 'string', 'max:60'],
            'juez' => ['nullable', 'string', 'max:120'],
            'secretario' => ['nullable', 'string', 'max:120'],
            'monto_pretension' => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'fecha_inicio' => ['required', 'date'],
            'asesor_responsable_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('status', 'active'),
            ],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->crearCautelar) {
            $reglas['forma_medida'] = ['required', Rule::in(array_keys(ExpedienteJudicial::FORMAS_MEDIDA))];
            $reglas['bien_descripcion'] = ['required', 'string', 'max:255'];
            $reglas['fecha_inicio_cautelar'] = ['required', 'date'];
        }

        return $reglas;
    }

    protected function messages(): array
    {
        return [
            'client_id.required' => 'Busca y selecciona el cliente del expediente.',
            'client_id.exists' => 'El cliente seleccionado no existe o no está activo.',
            'credit_id.exists' => 'El crédito seleccionado no pertenece al cliente elegido.',
            'garantia_id.exists' => 'La garantía seleccionada no pertenece al cliente elegido.',
            'exp_interno.max' => 'El exp. interno no debe exceder 10 caracteres.',
            'nro_expediente.required' => 'Ingresa el N° de expediente del Poder Judicial (ej. 04388-2024-0-3209-JP-CI-01).',
            'nro_expediente.max' => 'El N° de expediente no debe exceder 35 caracteres.',
            'nro_expediente.unique' => 'Ese N° de expediente ya está registrado.',
            'via.required' => 'Selecciona la vía de recupero del expediente.',
            'via.in' => 'La vía seleccionada no es válida.',
            'materia.max' => 'La materia no debe exceder 100 caracteres.',
            'proceso.max' => 'El proceso no debe exceder 40 caracteres.',
            'juzgado.max' => 'El juzgado no debe exceder 150 caracteres.',
            'distrito_judicial.max' => 'El distrito judicial no debe exceder 60 caracteres.',
            'juez.max' => 'El nombre del juez no debe exceder 120 caracteres.',
            'secretario.max' => 'El nombre del secretario no debe exceder 120 caracteres.',
            'monto_pretension.numeric' => 'El monto de la pretensión debe ser numérico.',
            'monto_pretension.gt' => 'El monto de la pretensión debe ser mayor a 0.',
            'monto_pretension.max' => 'El monto de la pretensión excede el máximo permitido.',
            'fecha_inicio.required' => 'Indica la fecha de inicio del proceso.',
            'fecha_inicio.date' => 'La fecha de inicio no es una fecha válida.',
            'asesor_responsable_id.required' => 'Selecciona el asesor responsable del expediente.',
            'asesor_responsable_id.exists' => 'El asesor seleccionado no es válido.',
            'observaciones.max' => 'Las observaciones no deben exceder 2000 caracteres.',
            'forma_medida.required' => 'Selecciona la forma de la medida cautelar.',
            'forma_medida.in' => 'La forma de medida seleccionada no es válida.',
            'bien_descripcion.required' => 'Describe el bien afectado por la medida (ej. VEHÍCULO placa ABC-123).',
            'bien_descripcion.max' => 'La descripción del bien no debe exceder 255 caracteres.',
            'fecha_inicio_cautelar.required' => 'Indica la fecha de inicio del cuaderno cautelar.',
            'fecha_inicio_cautelar.date' => 'La fecha del cuaderno cautelar no es una fecha válida.',
        ];
    }

    /** Vacíos → null y textos recortados ANTES de validar. */
    private function normalizar(): void
    {
        $this->nro_expediente = mb_strtoupper(trim($this->nro_expediente));
        $this->exp_interno = trim($this->exp_interno);
        $this->materia = trim($this->materia);
        $this->proceso = trim($this->proceso);
        $this->juzgado = trim($this->juzgado);
        $this->distrito_judicial = trim($this->distrito_judicial);
        $this->juez = trim($this->juez);
        $this->secretario = trim($this->secretario);
        $this->bien_descripcion = trim($this->bien_descripcion);
        $this->observaciones = trim($this->observaciones);
        $this->credit_id = ($this->credit_id !== null && $this->credit_id !== '') ? (int) $this->credit_id : null;
        $this->garantia_id = ($this->garantia_id !== null && $this->garantia_id !== '') ? (int) $this->garantia_id : null;
        $this->monto_pretension = ($this->monto_pretension !== null && $this->monto_pretension !== '') ? $this->monto_pretension : null;
    }

    // ─── Guardar ────────────────────────────────────────────────────────────

    public function guardar()
    {
        if (! auth()->user()?->can('legal.judicial')) {
            abort(403);
        }

        $this->normalizar();
        $this->validate();

        // El N° del cautelar se deriva del principal (dígito de cuaderno → 1)
        $nroCautelar = $this->crearCautelar
            ? ExpedienteJudicial::nroCautelarDesde($this->nro_expediente)
            : null;

        if ($nroCautelar !== null) {
            if ($nroCautelar === $this->nro_expediente) {
                $this->addError('nro_expediente', 'No se pudo derivar el N° del cuaderno cautelar: revisa el dígito de cuaderno del N° principal (debería ser 0).');

                return;
            }
            if (ExpedienteJudicial::where('nro_expediente', $nroCautelar)->exists()) {
                $this->addError('nro_expediente', "Ya existe un expediente registrado con el N° del cuaderno cautelar ({$nroCautelar}).");

                return;
            }
        }

        // Datos comunes a ambos cuadernos (mismo proceso, mismo juzgado)
        $base = [
            'client_id' => $this->client_id,
            'credit_id' => $this->credit_id,
            'garantia_id' => $this->garantia_id,
            'exp_interno' => $this->exp_interno !== '' ? $this->exp_interno : null,
            'juzgado' => $this->juzgado !== '' ? $this->juzgado : null,
            'distrito_judicial' => $this->distrito_judicial !== '' ? $this->distrito_judicial : null,
            'materia' => $this->materia !== '' ? $this->materia : null,
            'proceso' => $this->proceso !== '' ? $this->proceso : null,
            'juez' => $this->juez !== '' ? $this->juez : null,
            'secretario' => $this->secretario !== '' ? $this->secretario : null,
            'via' => $this->via,
            'asesor_responsable_id' => $this->asesor_responsable_id,
        ];

        [$principal, $cautelar] = DB::transaction(function () use ($base, $nroCautelar) {
            $principal = ExpedienteJudicial::create($base + [
                'nro_expediente' => $this->nro_expediente,
                'cuaderno' => 'principal',
                'estado' => 'en_tramite',
                'monto_pretension' => $this->monto_pretension,
                'fecha_inicio' => $this->fecha_inicio,
                'observaciones' => $this->observaciones !== '' ? $this->observaciones : null,
            ]);

            $cautelar = null;
            if ($nroCautelar !== null) {
                $cautelar = ExpedienteJudicial::create($base + [
                    'nro_expediente' => $nroCautelar,
                    'cuaderno' => 'cautelar',
                    'expediente_padre_id' => $principal->id,
                    'estado' => 'solicitada',
                    'forma_medida' => $this->forma_medida,
                    'bien_descripcion' => $this->bien_descripcion,
                    'fecha_inicio' => $this->fecha_inicio_cautelar,
                ]);
            }

            return [$principal, $cautelar];
        });

        Audit::log("Registró el expediente judicial {$principal->nro_expediente}", $principal, [
            'cuaderno' => 'principal',
            'client_id' => $principal->client_id,
            'credit_id' => $principal->credit_id,
            'garantia_id' => $principal->garantia_id,
            'via' => $principal->via,
            'materia' => $principal->materia,
            'monto_pretension' => $principal->monto_pretension,
            'asesor_responsable_id' => $principal->asesor_responsable_id,
        ]);

        if ($cautelar) {
            Audit::log("Registró el expediente judicial {$cautelar->nro_expediente}", $cautelar, [
                'cuaderno' => 'cautelar',
                'expediente_padre_id' => $principal->id,
                'forma_medida' => $cautelar->forma_medida,
                'bien_descripcion' => $cautelar->bien_descripcion,
            ]);
        }

        session()->flash('legal_success', $cautelar
            ? "Expediente {$principal->nro_expediente} registrado junto con su cuaderno cautelar {$cautelar->nro_expediente}."
            : "Expediente {$principal->nro_expediente} registrado correctamente.");

        return $this->redirectRoute('legal.expedientes.show', ['id' => $principal->id]);
    }

    public function render()
    {
        // Cliente elegido (siempre fresco de BD)
        $cliente = $this->client_id ? Client::find($this->client_id) : null;

        // Buscador de clientes activos (mínimo 2 caracteres, máximo 10 resultados)
        $clientesEncontrados = collect();
        $term = trim($this->buscarCliente);
        if (! $this->client_id && mb_strlen($term) >= 2) {
            $clientesEncontrados = Client::query()
                ->where('status', 'active')
                ->where(function ($q) use ($term) {
                    $q->where('documento', 'like', "%{$term}%")
                        ->orWhere('expediente', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT_WS(' ', apellido_pat, apellido_mat, nombre) LIKE ?", ["%{$term}%"])
                        ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ["%{$term}%"]);
                })
                ->orderBy('apellido_pat')
                ->limit(10)
                ->get(['id', 'nombre', 'apellido_pat', 'apellido_mat', 'documento', 'expediente']);
        }

        // Créditos activos y garantías del cliente elegido (vínculos opcionales)
        $creditos = $this->client_id
            ? Credit::activo()->where('client_id', $this->client_id)->orderByDesc('id')->get(['id', 'importe', 'cuotas'])
            : collect();
        $garantias = $this->client_id
            ? Garantia::with('vehiculos')->where('client_id', $this->client_id)->orderByDesc('id')->get()
            : collect();

        $usuarios = User::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        // Vista previa en vivo del N° del cuaderno cautelar (readonly)
        $nroCautelarPreview = '';
        if ($this->crearCautelar && ExpedienteJudicial::formatoValido($this->nro_expediente)) {
            $nroCautelarPreview = ExpedienteJudicial::nroCautelarDesde($this->nro_expediente);
        }

        return view('livewire.legal.expedientes.create', compact(
            'cliente', 'clientesEncontrados', 'creditos', 'garantias', 'usuarios', 'nroCautelarPreview',
        ));
    }
}
