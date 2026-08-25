<?php

namespace App\Livewire\Legal\Garantias;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Garantia;
use App\Models\Vehiculo;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Wizard de creación de garantía mobiliaria vehicular (Área Legal — FASE 1).
 *
 * 4 pasos: 1) crédito y deudores, 2) vehículos (existentes o nuevos, máx. 2),
 * 3) parámetros (GPS, custodia, monto de gravamen), 4) resumen y confirmación.
 * Cada "Siguiente" valida SOLO las reglas del paso actual; "Crear garantía"
 * revalida los tres pasos de captura y persiste todo en una transacción.
 * La garantía nace en estado 'en_constitucion': pasa a 'vigente' recién al
 * registrar el aviso SIGM de constitución (Garantia::sincronizarConAvisos()).
 */
class Create extends Component
{
    /** Opciones de estado civil para completar la ficha del deudor */
    public const ESTADOS_CIVILES = [
        'Soltero(a)', 'Casado(a)', 'Conviviente', 'Divorciado(a)', 'Viudo(a)',
    ];

    /** Paso visible del wizard (1..4) */
    public int $paso = 1;

    // ─── Paso 1: crédito y deudores ─────────────────────────────────────────

    public string $buscarCredito = '';

    public ?int $creditId = null;

    public string $tipo_persona = 'natural';

    public string $buscarCodeudor = '';

    public ?int $codeudorId = null;

    /** Completan la ficha del deudor SOLO si al cliente le faltan (se guardan al finalizar) */
    public string $deudorEstadoCivil = '';

    public string $deudorOcupacion = '';

    // ─── Paso 2: vehículos (máx. 2; existentes o nuevos inline) ─────────────

    public string $buscarVehiculo = '';

    /**
     * Vehículos elegidos. Cada ítem:
     *  - vehiculo_id null  → vehículo NUEVO (se crea al finalizar con los campos inline)
     *  - vehiculo_id != null → vehículo existente ('descripcion'/'placa' solo para mostrar)
     *  - es_bien_futuro + acta_notarial/kardex/notario/fecha_acta → datos del pivot
     */
    public array $vehiculos = [];

    // ─── Paso 3: parámetros de la garantía ──────────────────────────────────

    public bool $gps = true;

    public bool $custodia = false;

    public $monto_gravamen = null;

    public string $observaciones = '';

    /** Sugerencia calculada del cronograma (cuota total × n° de cuotas); solo informativa */
    public ?float $montoSugerido = null;

    public function mount(?int $creditId = null): void
    {
        if ($creditId) {
            $credit = Credit::with('client')->find($creditId);
            if ($credit && $credit->situacion === 'Activo') {
                $this->seleccionarCredito($credit->id);
            } elseif ($credit) {
                $this->addError('creditId', "El crédito N° {$creditId} no está activo; busca otro crédito.");
            }
        }
    }

    // ─── Navegación ─────────────────────────────────────────────────────────

    /** Avanza validando únicamente las reglas del paso actual */
    public function siguiente(): void
    {
        if ($this->paso >= 4) {
            return;
        }

        if ($this->paso === 2) {
            $this->normalizarPlacas();
        }

        $this->validate($this->reglasPaso($this->paso));

        if (! $this->coherenciaPaso($this->paso)) {
            return;
        }

        $this->paso++;
    }

    public function anterior(): void
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    /** Los badges del indicador solo permiten regresar a pasos ya recorridos */
    public function irAPaso(int $paso): void
    {
        if ($paso >= 1 && $paso < $this->paso) {
            $this->paso = $paso;
        }
    }

    // ─── Paso 1: acciones ───────────────────────────────────────────────────

    public function seleccionarCredito(int $id): void
    {
        $credit = Credit::with('client')
            ->where('situacion', 'Activo')
            ->find($id);

        if (! $credit) {
            $this->dispatch('errorAlert', ['message' => 'El crédito seleccionado no está activo.']);

            return;
        }

        $this->creditId = $credit->id;
        $this->buscarCredito = '';
        $this->resetErrorBag('creditId');

        // El deudor no puede ser su propio codeudor
        if ($this->codeudorId === $credit->client_id) {
            $this->quitarCodeudor();
        }

        // Precarga los datos civiles ya registrados (los inputs inline solo se
        // muestran cuando faltan; ver la vista)
        $this->deudorEstadoCivil = (string) ($credit->client->estado_civil ?? '');
        $this->deudorOcupacion = (string) ($credit->client->ocupacion ?? '');

        // Sugerencia de gravamen desde el cronograma (editable en el paso 3)
        $this->montoSugerido = $this->calcularSugerenciaGravamen($credit);
        if (blank($this->monto_gravamen) && $this->montoSugerido !== null) {
            $this->monto_gravamen = $this->montoSugerido;
        }
    }

    public function quitarCredito(): void
    {
        $this->creditId = null;
        $this->montoSugerido = null;
        $this->deudorEstadoCivil = '';
        $this->deudorOcupacion = '';
    }

    public function seleccionarCodeudor(int $id): void
    {
        if ($this->creditId) {
            $deudorId = Credit::whereKey($this->creditId)->value('client_id');
            if ((int) $deudorId === $id) {
                $this->dispatch('errorAlert', ['message' => 'El codeudor no puede ser el mismo deudor del crédito.']);

                return;
            }
        }

        $this->codeudorId = $id;
        $this->buscarCodeudor = '';
        $this->resetErrorBag('codeudorId');
    }

    public function quitarCodeudor(): void
    {
        $this->codeudorId = null;
        $this->buscarCodeudor = '';
    }

    // ─── Paso 2: acciones ───────────────────────────────────────────────────

    public function agregarVehiculoExistente(int $id): void
    {
        if (count($this->vehiculos) >= 2) {
            $this->dispatch('errorAlert', ['message' => 'Una garantía admite como máximo 2 vehículos.']);

            return;
        }
        if (collect($this->vehiculos)->contains(fn ($v) => (int) ($v['vehiculo_id'] ?? 0) === $id)) {
            $this->dispatch('errorAlert', ['message' => 'Ese vehículo ya está agregado a la garantía.']);

            return;
        }

        $vehiculo = Vehiculo::where('estado', 'activo')->find($id);
        if (! $vehiculo) {
            $this->dispatch('errorAlert', ['message' => 'El vehículo seleccionado no está activo.']);

            return;
        }

        $item = $this->itemVehiculoVacio();
        $item['vehiculo_id'] = $vehiculo->id;
        $item['descripcion'] = $vehiculo->descripcion();
        $item['placa'] = $vehiculo->placa;

        $this->vehiculos[] = $item;
        $this->buscarVehiculo = '';
    }

    public function agregarVehiculoNuevo(): void
    {
        if (count($this->vehiculos) >= 2) {
            $this->dispatch('errorAlert', ['message' => 'Una garantía admite como máximo 2 vehículos.']);

            return;
        }

        $this->vehiculos[] = $this->itemVehiculoVacio();
        $this->buscarVehiculo = '';
    }

    public function quitarVehiculo(int $index): void
    {
        unset($this->vehiculos[$index]);
        $this->vehiculos = array_values($this->vehiculos);
        $this->resetErrorBag();
    }

    // ─── Paso 3: acciones ───────────────────────────────────────────────────

    /** Copia la sugerencia del cronograma al campo editable */
    public function usarSugerencia(): void
    {
        if ($this->montoSugerido !== null) {
            $this->monto_gravamen = $this->montoSugerido;
            $this->resetErrorBag('monto_gravamen');
        }
    }

    // ─── Guardar (paso 4) ───────────────────────────────────────────────────

    public function guardar()
    {
        if (! auth()->user()?->can('legal.garantias')) {
            abort(403);
        }

        $this->normalizarPlacas();

        // Revalida los tres pasos de captura antes de confirmar
        $this->validate(array_merge(
            $this->reglasPaso(1),
            $this->reglasPaso(2),
            $this->reglasPaso(3),
        ));

        foreach ([1, 2, 3] as $p) {
            if (! $this->coherenciaPaso($p)) {
                $this->paso = $p;

                return;
            }
        }

        $garantia = DB::transaction(function () {
            $credit = Credit::with('client')->findOrFail($this->creditId);
            $client = $credit->client;

            // 1) Completar la ficha del deudor si se llenaron los campos faltantes
            $cambios = [];
            if (blank($client->estado_civil) && filled($this->deudorEstadoCivil)) {
                $cambios['estado_civil'] = $this->deudorEstadoCivil;
            }
            if (blank($client->ocupacion) && filled($this->deudorOcupacion)) {
                $cambios['ocupacion'] = trim($this->deudorOcupacion);
            }
            if ($cambios) {
                $client->update($cambios);
            }

            // 2) Crear los vehículos nuevos y armar los datos del pivot
            $attach = [];
            foreach (array_values($this->vehiculos) as $i => $item) {
                if (blank($item['vehiculo_id'])) {
                    $vehiculo = Vehiculo::create([
                        'client_id' => $client->id,
                        'propietario_tipo' => 'cliente',
                        'placa' => $item['placa'],
                        'marca' => $item['marca'] ?: null,
                        'modelo' => $item['modelo'] ?: null,
                        'nro_motor' => $item['nro_motor'] ?: null,
                        'nro_serie' => $item['nro_serie'] ?: null,
                        'categoria' => $item['categoria'] ?: null,
                        'anio' => $item['anio'] ?: null,
                        'carroceria' => $item['carroceria'] ?: null,
                        'color' => $item['color'] ?: null,
                        'combustible' => $item['combustible'] ?: null,
                        'valor' => $item['valor'] !== '' && $item['valor'] !== null ? $item['valor'] : null,
                        'estado' => 'activo',
                    ]);
                    $vehiculoId = $vehiculo->id;
                } else {
                    $vehiculoId = (int) $item['vehiculo_id'];
                }

                $esFuturo = (bool) $item['es_bien_futuro'];
                $attach[$vehiculoId] = [
                    'es_bien_futuro' => $esFuturo,
                    'acta_notarial' => $esFuturo ? $item['acta_notarial'] : null,
                    'kardex' => $esFuturo ? $item['kardex'] : null,
                    'notario' => $esFuturo ? $item['notario'] : null,
                    'fecha_acta' => $esFuturo ? $item['fecha_acta'] : null,
                    'orden' => $i + 1,
                ];
            }

            // 3) Crear la garantía en constitución y vincular los vehículos
            $garantia = Garantia::create([
                'credit_id' => $credit->id,
                'client_id' => $client->id,
                'codeudor_client_id' => $this->codeudorId,
                'tipo' => 'mobiliaria_vehicular',
                'tipo_persona' => $this->tipo_persona,
                'gps' => $this->gps,
                'custodia' => $this->custodia,
                'monto_gravamen' => $this->monto_gravamen,
                'estado' => 'en_constitucion',
                'observaciones' => trim($this->observaciones) ?: null,
                'registrado_por' => auth()->id(),
            ]);
            $garantia->vehiculos()->attach($attach);

            return $garantia;
        });

        Audit::log('Creó la garantía del crédito N° '.$this->creditId, $garantia, [
            'tipo_persona' => $this->tipo_persona,
            'codeudor_client_id' => $this->codeudorId,
            'monto_gravamen' => $this->monto_gravamen,
            'gps' => $this->gps,
            'custodia' => $this->custodia,
            'vehiculos' => collect($this->vehiculos)->pluck('placa')->all(),
        ]);

        session()->flash('legal_success', "Garantía N° {$garantia->id} del crédito N° {$this->creditId} registrada en constitución.");

        return $this->redirectRoute('legal.garantias.show', ['id' => $garantia->id]);
    }

    // ─── Reglas de validación por paso ──────────────────────────────────────

    protected function reglasPaso(int $paso): array
    {
        if ($paso === 1) {
            return [
                'creditId' => [
                    'required', 'integer',
                    Rule::exists('credits', 'id')->where('situacion', 'Activo'),
                ],
                'tipo_persona' => ['required', Rule::in(array_keys(Garantia::TIPO_PERSONAS))],
                'codeudorId' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('status', 'active')],
                'deudorEstadoCivil' => ['nullable', 'string', 'max:30'],
                'deudorOcupacion' => ['nullable', 'string', 'max:100'],
            ];
        }

        if ($paso === 2) {
            $rules = ['vehiculos' => 'required|array|min:1|max:2'];

            foreach ($this->vehiculos as $i => $item) {
                if (blank($item['vehiculo_id'])) {
                    // Vehículo NUEVO: se crea al finalizar
                    $rules["vehiculos.{$i}.placa"] = ['required', 'string', 'max:10', Rule::unique('vehiculos', 'placa')];
                    $rules["vehiculos.{$i}.marca"] = 'required|string|max:50';
                    $rules["vehiculos.{$i}.modelo"] = 'required|string|max:50';
                    $rules["vehiculos.{$i}.nro_motor"] = 'nullable|string|max:30';
                    $rules["vehiculos.{$i}.nro_serie"] = 'nullable|string|max:30';
                    $rules["vehiculos.{$i}.categoria"] = 'nullable|string|max:30';
                    $rules["vehiculos.{$i}.anio"] = 'nullable|integer|between:1950,'.(now()->year + 1);
                    $rules["vehiculos.{$i}.carroceria"] = 'nullable|string|max:50';
                    $rules["vehiculos.{$i}.color"] = 'nullable|string|max:50';
                    $rules["vehiculos.{$i}.combustible"] = 'nullable|string|max:30';
                    $rules["vehiculos.{$i}.valor"] = 'nullable|numeric|gte:0';
                } else {
                    $rules["vehiculos.{$i}.vehiculo_id"] = 'required|integer|exists:vehiculos,id';
                }

                // Bien futuro: los datos del acta de transferencia son obligatorios
                if (! empty($item['es_bien_futuro'])) {
                    $rules["vehiculos.{$i}.acta_notarial"] = 'required|string|max:60';
                    $rules["vehiculos.{$i}.kardex"] = 'required|string|max:20';
                    $rules["vehiculos.{$i}.notario"] = 'required|string|max:120';
                    $rules["vehiculos.{$i}.fecha_acta"] = 'required|date';
                }
            }

            return $rules;
        }

        if ($paso === 3) {
            return [
                'gps' => 'boolean',
                'custodia' => 'boolean',
                'monto_gravamen' => 'required|numeric|gt:0',
                'observaciones' => 'nullable|string|max:1000',
            ];
        }

        return [];
    }

    protected function messages(): array
    {
        return [
            'creditId.required' => 'Busca y selecciona el crédito a garantizar.',
            'creditId.exists' => 'El crédito seleccionado no existe o no está activo.',
            'tipo_persona.required' => 'Indica el tipo de persona del deudor.',
            'tipo_persona.in' => 'Tipo de persona inválido.',
            'codeudorId.exists' => 'El codeudor seleccionado no existe o no está activo.',
            'deudorEstadoCivil.max' => 'El estado civil no debe exceder 30 caracteres.',
            'deudorOcupacion.max' => 'La ocupación no debe exceder 100 caracteres.',
            'vehiculos.required' => 'Agrega al menos un vehículo a la garantía.',
            'vehiculos.min' => 'Agrega al menos un vehículo a la garantía.',
            'vehiculos.max' => 'Una garantía admite como máximo 2 vehículos.',
            'vehiculos.*.vehiculo_id.exists' => 'Uno de los vehículos seleccionados ya no existe.',
            'vehiculos.*.placa.required' => 'Ingresa la placa del vehículo nuevo.',
            'vehiculos.*.placa.max' => 'La placa no debe exceder 10 caracteres.',
            'vehiculos.*.placa.unique' => 'Esa placa ya está registrada; búscala como vehículo existente.',
            'vehiculos.*.marca.required' => 'Ingresa la marca del vehículo nuevo.',
            'vehiculos.*.modelo.required' => 'Ingresa el modelo del vehículo nuevo.',
            'vehiculos.*.anio.integer' => 'El año del vehículo debe ser un número.',
            'vehiculos.*.anio.between' => 'El año del vehículo no es válido.',
            'vehiculos.*.valor.numeric' => 'El valor del vehículo debe ser numérico.',
            'vehiculos.*.valor.gte' => 'El valor del vehículo no puede ser negativo.',
            'vehiculos.*.acta_notarial.required' => 'Ingresa el N° de acta notarial del bien futuro.',
            'vehiculos.*.kardex.required' => 'Ingresa el kardex del bien futuro (ej. 0373-2026).',
            'vehiculos.*.notario.required' => 'Ingresa el nombre del notario del bien futuro.',
            'vehiculos.*.fecha_acta.required' => 'Ingresa la fecha del acta del bien futuro.',
            'vehiculos.*.fecha_acta.date' => 'La fecha del acta no es una fecha válida.',
            'monto_gravamen.required' => 'Ingresa el monto de gravamen de la garantía.',
            'monto_gravamen.numeric' => 'El monto de gravamen debe ser numérico.',
            'monto_gravamen.gt' => 'El monto de gravamen debe ser mayor a 0.',
            'observaciones.max' => 'Las observaciones no deben exceder 1000 caracteres.',
        ];
    }

    /** Validaciones cruzadas que las reglas por campo no cubren */
    private function coherenciaPaso(int $paso): bool
    {
        if ($paso === 1 && $this->creditId && $this->codeudorId) {
            $deudorId = (int) Credit::whereKey($this->creditId)->value('client_id');
            if ($deudorId === $this->codeudorId) {
                $this->addError('codeudorId', 'El codeudor no puede ser el mismo deudor del crédito.');

                return false;
            }
        }

        if ($paso === 2) {
            $placasNuevas = collect($this->vehiculos)
                ->filter(fn ($v) => blank($v['vehiculo_id']))
                ->pluck('placa')
                ->filter();
            if ($placasNuevas->count() !== $placasNuevas->unique()->count()) {
                $this->addError('vehiculos', 'Los dos vehículos nuevos no pueden tener la misma placa.');

                return false;
            }
        }

        return true;
    }

    // ─── Helpers internos ───────────────────────────────────────────────────

    private function itemVehiculoVacio(): array
    {
        return [
            'vehiculo_id' => null,
            'descripcion' => '',
            'placa' => '',
            'marca' => '',
            'modelo' => '',
            'nro_motor' => '',
            'nro_serie' => '',
            'categoria' => '',
            'anio' => '',
            'carroceria' => '',
            'color' => '',
            'combustible' => '',
            'valor' => '',
            'es_bien_futuro' => false,
            'acta_notarial' => '',
            'kardex' => '',
            'notario' => '',
            'fecha_acta' => '',
        ];
    }

    private function normalizarPlacas(): void
    {
        foreach ($this->vehiculos as $i => $item) {
            if (blank($item['vehiculo_id'])) {
                $this->vehiculos[$i]['placa'] = mb_strtoupper(trim((string) $item['placa']));
            }
        }
    }

    /**
     * Sugerencia de gravamen leyendo el cronograma: (capital + interés +
     * excedente) de la primera cuota real × n° de cuotas del crédito.
     * Se ignoran las filas de fin de semana de los diarios (importe_cuota = 0).
     */
    private function calcularSugerenciaGravamen(Credit $credit): ?float
    {
        $primera = $credit->installments()
            ->where('importe_cuota', '>', 0)
            ->orderBy('num_cuota')
            ->orderBy('id')
            ->first();

        if (! $primera || (int) $credit->cuotas <= 0) {
            return null;
        }

        $cuotaTotal = (float) $primera->importe_cuota
            + (float) $primera->importe_interes
            + (float) $primera->importe_excedente;

        return round($cuotaTotal * (int) $credit->cuotas, 2);
    }

    public function render()
    {
        // Datos del crédito/codeudor seleccionados (siempre frescos de BD)
        $credit = $this->creditId ? Credit::with('client')->find($this->creditId) : null;
        $codeudor = $this->codeudorId ? Client::find($this->codeudorId) : null;

        // Aviso de ficha incompleta del deudor (campos nuevos del contrato)
        $faltaEstadoCivil = $credit && blank($credit->client?->estado_civil);
        $faltaOcupacion = $credit && blank($credit->client?->ocupacion);

        // Aviso informativo si el crédito ya tiene garantías (refinanciamiento)
        $garantiasPrevias = $credit
            ? Garantia::where('credit_id', $credit->id)->count()
            : 0;

        // Buscador de créditos activos (por N° o por nombre/documento del cliente)
        $creditosEncontrados = collect();
        if ($this->paso === 1 && trim($this->buscarCredito) !== '' && ! $this->creditId) {
            $term = trim($this->buscarCredito);
            $creditosEncontrados = Credit::with('client')
                ->where('situacion', 'Activo')
                ->where(function ($q) use ($term) {
                    if (ctype_digit($term)) {
                        $q->orWhere('id', (int) $term);
                    }
                    $q->orWhereHas('client', function ($c) use ($term) {
                        $c->where('documento', 'like', "%{$term}%")
                            ->orWhereRaw("CONCAT_WS(' ', apellido_pat, apellido_mat, nombre) LIKE ?", ["%{$term}%"])
                            ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ["%{$term}%"]);
                    });
                })
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        // Buscador de codeudor (clientes activos, distinto del deudor)
        $codeudoresEncontrados = collect();
        if ($this->paso === 1 && trim($this->buscarCodeudor) !== '' && ! $this->codeudorId) {
            $term = trim($this->buscarCodeudor);
            $codeudoresEncontrados = Client::query()
                ->where('status', 'active')
                ->when($credit, fn ($q) => $q->where('id', '!=', $credit->client_id))
                ->where(function ($q) use ($term) {
                    $q->where('documento', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT_WS(' ', apellido_pat, apellido_mat, nombre) LIKE ?", ["%{$term}%"])
                        ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ["%{$term}%"]);
                })
                ->orderBy('apellido_pat')
                ->limit(10)
                ->get();
        }

        // Buscador de vehículos activos por placa (excluye los ya agregados)
        $vehiculosEncontrados = collect();
        if ($this->paso === 2 && trim($this->buscarVehiculo) !== '' && count($this->vehiculos) < 2) {
            $term = trim($this->buscarVehiculo);
            $yaElegidos = collect($this->vehiculos)->pluck('vehiculo_id')->filter()->all();
            $vehiculosEncontrados = Vehiculo::with('client')
                ->where('estado', 'activo')
                ->whereNotIn('id', $yaElegidos)
                ->where('placa', 'like', "%{$term}%")
                ->orderBy('placa')
                ->limit(10)
                ->get();
        }

        $estadosCiviles = self::ESTADOS_CIVILES;

        return view('livewire.legal.garantias.create', compact(
            'credit', 'codeudor', 'faltaEstadoCivil', 'faltaOcupacion', 'garantiasPrevias',
            'creditosEncontrados', 'codeudoresEncontrados', 'vehiculosEncontrados', 'estadosCiviles',
        ));
    }
}
