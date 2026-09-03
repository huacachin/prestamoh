<?php

namespace App\Livewire\Legal\Contratos;

use App\Livewire\Legal\Garantias\Create;
use App\Models\Client;
use App\Models\Garantia;
use App\Services\Legal\GeneradorContrato;
use App\Services\Legal\ValidacionContratoException;
use App\Support\Legal\BancosVoucher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulario de EMISIÓN del contrato de garantía mobiliaria (Área Legal — FASE 2).
 *
 * 3 pasos: 1) parámetros del contrato (fecha, destino del desembolso, datos de
 * la persona jurídica y cláusulas adicionales), 2) voucher del desembolso
 * (Anexo 2: banco × modalidad de BancosVoucher + imagen), 3) validación con
 * ValidadorContrato vía GeneradorContrato::previsualizar() y emisión final.
 *
 * Toda la validación LEGAL (montos vs cronograma, fichas, bienes, voucher)
 * vive en ValidadorContrato: aquí solo se captura, se valida formato y se
 * pintan los errores bloqueantes que devuelve el servicio. El Audit también
 * lo registra el servicio — este componente no lo duplica.
 */
class Form extends Component
{
    use WithFileUploads;

    public int $garantiaId;

    /** 'natural' | 'juridica' (copiado de la garantía en mount) */
    public string $tipoPersona = 'natural';

    /** Importe del crédito (ayuda del voucher: su monto debe igualarlo) */
    public ?float $importeCredito = null;

    /** Paso visible (1..3) */
    public int $paso = 1;

    // ─── Paso 1: parámetros ─────────────────────────────────────────────────

    public string $fecha = '';

    /** 'propio' | 'tercero' | 'gerente' (gerente solo persona jurídica) */
    public string $destino = 'propio';

    /** Tercero autorizado a recibir el desembolso (si destino = tercero) */
    public array $tercero = ['nombre' => '', 'dni' => '', 'cuenta' => '', 'motivo' => ''];

    /** Datos de la empresa deudora (solo persona jurídica) */
    public array $empresa = [
        'razon_social' => '', 'ruc' => '', 'partida' => '', 'oficina_registral' => '', 'correo' => '',
    ];

    /** Gerente general de la empresa (solo persona jurídica) */
    public array $gerente = [
        'nombre' => '', 'dni' => '', 'genero' => 'M', 'ocupacion' => '', 'estado_civil' => '', 'domicilio' => '',
    ];

    public string $clausulas_adicionales = '';

    /**
     * Ficha incompleta de los deudores (solo persona natural): por client_id,
     * ['nombre', 'faltan' => campos vacíos en la ficha, y un input por campo].
     * Lo capturado se guarda en el Client al avanzar el paso 1 (patrón fase 1).
     */
    public array $fichas = [];

    // ─── Paso 2: voucher (Anexo 2) ──────────────────────────────────────────

    public string $banco = '';

    public string $modalidad = '';

    /** Valores capturados de los campos dinámicos de BancosVoucher::campos() */
    public array $voucherCampos = [];

    /** Imagen del voucher (upload temporal de Livewire) */
    public $imagen = null;

    /** Ruta ya persistida en el disco 'public' (se sube al previsualizar/emitir) */
    public ?string $imagenPath = null;

    // ─── Paso 3: validación y emisión ───────────────────────────────────────

    /** Errores bloqueantes devueltos por ValidadorContrato */
    public array $erroresValidacion = [];

    /** HTML de la previsualización (habilita el botón Emitir) */
    public ?string $htmlPreview = null;

    public function mount(int $garantiaId): void
    {
        abort_unless(auth()->user()?->can('legal.contratos') ?? false, 403);

        $garantia = Garantia::with(['client', 'codeudor', 'credit', 'vehiculos'])->findOrFail($garantiaId);

        $this->garantiaId = $garantia->id;
        $this->tipoPersona = $garantia->tipo_persona;
        $this->importeCredito = $garantia->credit ? round((float) $garantia->credit->importe, 2) : null;
        $this->fecha = now()->toDateString();

        if ($this->tipoPersona === 'juridica') {
            // Prefill desde la ficha del cliente (editable: la razón social real
            // y el RUC pueden diferir de cómo se registró el cliente)
            $this->empresa['razon_social'] = (string) ($garantia->client?->fullName() ?? '');
            $this->empresa['ruc'] = (string) ($garantia->client?->documento ?? '');
            $this->empresa['correo'] = (string) ($garantia->client?->email ?? '');
        } else {
            $this->cargarFichas($garantia);
        }
    }

    // ─── Navegación ─────────────────────────────────────────────────────────

    public function siguiente(): void
    {
        if ($this->paso >= 3) {
            return;
        }

        $this->validate($this->reglasPaso($this->paso));

        if ($this->paso === 1) {
            $this->guardarFichas();
        }

        if ($this->paso === 2 && ! BancosVoucher::esComboValido($this->banco, $this->modalidad)) {
            $this->addError('modalidad', 'La modalidad elegida no está disponible para ese banco.');

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

    // ─── Hooks ──────────────────────────────────────────────────────────────

    /** Cualquier cambio invalida la previsualización: lo emitido debe ser lo previsualizado */
    public function updated($name): void
    {
        $this->htmlPreview = null;
        $this->erroresValidacion = [];
    }

    public function updatedBanco(): void
    {
        $modalidades = BancosVoucher::combosDisponibles()[$this->banco] ?? [];
        // Si el banco tiene una sola modalidad, se autoselecciona
        $this->modalidad = count($modalidades) === 1 ? $modalidades[0] : '';
        $this->sincronizarCamposVoucher();
    }

    public function updatedModalidad(): void
    {
        $this->sincronizarCamposVoucher();
    }

    public function updatedImagen(): void
    {
        $this->validateOnly('imagen', ['imagen' => 'nullable|image|max:4096']);

        // Reemplazo de imagen: la anterior ya subida deja de valer
        if ($this->imagenPath && Storage::disk('public')->exists($this->imagenPath)) {
            Storage::disk('public')->delete($this->imagenPath);
        }
        $this->imagenPath = null;
    }

    // ─── Paso 3: validación y emisión ───────────────────────────────────────

    /** Valida contra ValidadorContrato (modo borrador) y arma la vista previa. */
    public function previsualizar(): void
    {
        $this->subirImagenSiExiste();

        try {
            $this->htmlPreview = (new GeneradorContrato)
                ->previsualizar($this->garantia(), $this->armarParametros());
            $this->erroresValidacion = [];
        } catch (ValidacionContratoException $e) {
            $this->erroresValidacion = $e->errores;
            $this->htmlPreview = null;
        }
    }

    /** Emite el contrato (PDF + fila en contratos). El Audit lo hace el servicio. */
    public function emitir()
    {
        if (! auth()->user()?->can('legal.contratos')) {
            abort(403);
        }

        if ($this->htmlPreview === null) {
            $this->erroresValidacion = ['Primero usa "Validar y previsualizar": el contrato se emite tal cual se previsualizó.'];

            return;
        }

        $this->subirImagenSiExiste();

        try {
            $contrato = (new GeneradorContrato)
                ->generar($this->garantia(), $this->armarParametros());
        } catch (ValidacionContratoException $e) {
            $this->erroresValidacion = $e->errores;
            $this->htmlPreview = null;

            return;
        }

        session()->flash('legal_success', "Contrato {$contrato->numero} emitido.");

        return $this->redirectRoute('legal.garantias.show', ['id' => $this->garantiaId]);
    }

    // ─── Reglas de validación por paso ──────────────────────────────────────

    protected function reglasPaso(int $paso): array
    {
        if ($paso === 1) {
            $destinos = $this->tipoPersona === 'juridica'
                ? ['propio', 'tercero', 'gerente']
                : ['propio', 'tercero'];

            $reglas = [
                'fecha' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
                'destino' => ['required', Rule::in($destinos)],
                'clausulas_adicionales' => ['nullable', 'string', 'max:5000'],
            ];

            if ($this->destino === 'tercero') {
                $reglas['tercero.nombre'] = ['required', 'string', 'max:150'];
                $reglas['tercero.dni'] = ['required', 'regex:/^\d{8}$/'];
                $reglas['tercero.cuenta'] = ['required', 'string', 'max:40'];
                $reglas['tercero.motivo'] = ['required', 'string', 'max:300'];
            }

            if ($this->tipoPersona === 'juridica') {
                $reglas += [
                    'empresa.razon_social' => ['required', 'string', 'max:200'],
                    'empresa.ruc' => ['required', 'regex:/^\d{11}$/'],
                    'empresa.partida' => ['nullable', 'string', 'max:30'],
                    'empresa.oficina_registral' => ['nullable', 'string', 'max:80'],
                    'empresa.correo' => ['nullable', 'email', 'max:150'],
                    'gerente.nombre' => ['required', 'string', 'max:150'],
                    'gerente.dni' => ['required', 'regex:/^\d{8}$/'],
                    'gerente.genero' => ['required', Rule::in(['M', 'F'])],
                    'gerente.ocupacion' => ['nullable', 'string', 'max:100'],
                    'gerente.estado_civil' => ['nullable', 'string', 'max:30'],
                    'gerente.domicilio' => ['nullable', 'string', 'max:250'],
                ];
            } else {
                foreach ($this->fichas as $clientId => $ficha) {
                    foreach ($ficha['faltan'] as $campo) {
                        $reglas["fichas.{$clientId}.{$campo}"] = match ($campo) {
                            'sexo' => ['required', Rule::in(['M', 'F'])],
                            'email' => ['required', 'email', 'max:150'],
                            'estado_civil' => ['required', 'string', 'max:30'],
                            default => ['required', 'string', 'max:100'],
                        };
                    }
                }
            }

            return $reglas;
        }

        if ($paso === 2) {
            return [
                'banco' => ['required', Rule::in(array_keys(BancosVoucher::BANCOS))],
                'modalidad' => ['required', Rule::in(array_keys(BancosVoucher::MODALIDADES))],
                'imagen' => ['nullable', 'image', 'max:4096'],
            ];
        }

        return [];
    }

    protected function messages(): array
    {
        return [
            'fecha.required' => 'Ingresa la fecha del contrato.',
            'fecha.date' => 'La fecha del contrato no es válida.',
            'fecha.before_or_equal' => 'La fecha del contrato no puede ser futura.',
            'destino.required' => 'Elige el destino del desembolso.',
            'destino.in' => 'Destino del desembolso no válido.',
            'tercero.nombre.required' => 'Ingresa el nombre del tercero autorizado.',
            'tercero.dni.required' => 'Ingresa el DNI del tercero autorizado.',
            'tercero.dni.regex' => 'El DNI del tercero debe tener 8 dígitos.',
            'tercero.cuenta.required' => 'Ingresa el N° de cuenta del tercero autorizado.',
            'tercero.motivo.required' => 'Ingresa el motivo del depósito a tercero (la constancia lo consigna).',
            'empresa.razon_social.required' => 'Ingresa la razón social de la empresa deudora.',
            'empresa.ruc.required' => 'Ingresa el RUC de la empresa deudora.',
            'empresa.ruc.regex' => 'El RUC debe tener 11 dígitos.',
            'empresa.correo.email' => 'El correo de la empresa no es válido.',
            'gerente.nombre.required' => 'Ingresa el nombre del gerente general.',
            'gerente.dni.required' => 'Ingresa el DNI del gerente general.',
            'gerente.dni.regex' => 'El DNI del gerente debe tener 8 dígitos.',
            'gerente.genero.required' => 'Indica el género del gerente general.',
            'clausulas_adicionales.max' => 'Las cláusulas adicionales no deben exceder 5000 caracteres.',
            'fichas.*.sexo.required' => 'Indica el sexo del deudor (define EL DEUDOR / LA DEUDORA).',
            'fichas.*.sexo.in' => 'El sexo del deudor debe ser M o F.',
            'fichas.*.estado_civil.required' => 'Ingresa el estado civil del deudor.',
            'fichas.*.ocupacion.required' => 'Ingresa la ocupación del deudor.',
            'fichas.*.email.required' => 'Ingresa el correo electrónico del deudor (la cláusula de comunicaciones lo consigna).',
            'fichas.*.email.email' => 'El correo electrónico del deudor no es válido.',
            'banco.required' => 'Elige el banco del voucher.',
            'banco.in' => 'Banco no válido.',
            'modalidad.required' => 'Elige la modalidad de la operación.',
            'modalidad.in' => 'Modalidad no válida.',
            'imagen.image' => 'El voucher debe ser una imagen (JPG, PNG...).',
            'imagen.max' => 'La imagen del voucher debe pesar máximo 4 MB.',
        ];
    }

    // ─── Helpers internos ───────────────────────────────────────────────────

    private function garantia(): Garantia
    {
        return Garantia::with(['client', 'codeudor', 'credit.installments', 'vehiculos'])
            ->findOrFail($this->garantiaId);
    }

    /** Parámetros con la forma que esperan ValidadorContrato y la factory del VM. */
    private function armarParametros(): array
    {
        $parametros = [
            'fecha' => $this->fecha,
            'destino' => $this->destino,
            'clausulas_adicionales' => trim($this->clausulas_adicionales) !== '' ? trim($this->clausulas_adicionales) : null,
        ];

        if ($this->destino === 'tercero') {
            $parametros['tercero'] = [
                'nombre' => mb_strtoupper(trim((string) ($this->tercero['nombre'] ?? ''))),
                'dni' => trim((string) ($this->tercero['dni'] ?? '')),
                'cuenta' => trim((string) ($this->tercero['cuenta'] ?? '')),
                'motivo' => mb_strtoupper(trim((string) ($this->tercero['motivo'] ?? ''))),
            ];
        }

        if ($this->tipoPersona === 'juridica') {
            $parametros['empresa'] = [
                'razon_social' => trim((string) ($this->empresa['razon_social'] ?? '')),
                'ruc' => trim((string) ($this->empresa['ruc'] ?? '')),
                'partida' => trim((string) ($this->empresa['partida'] ?? '')) ?: null,
                'oficina_registral' => trim((string) ($this->empresa['oficina_registral'] ?? '')) ?: null,
                'correo' => trim((string) ($this->empresa['correo'] ?? '')) ?: null,
                'gerente' => [
                    'nombre' => trim((string) ($this->gerente['nombre'] ?? '')),
                    'dni' => trim((string) ($this->gerente['dni'] ?? '')),
                    'genero' => (string) ($this->gerente['genero'] ?? 'M'),
                    'ocupacion' => trim((string) ($this->gerente['ocupacion'] ?? '')),
                    'estado_civil' => trim((string) ($this->gerente['estado_civil'] ?? '')),
                    'domicilio' => trim((string) ($this->gerente['domicilio'] ?? '')),
                ],
            ];
        }

        if ($this->banco !== '' && $this->modalidad !== '') {
            $voucher = [
                'banco' => $this->banco,
                'modalidad' => $this->modalidad,
                'campos' => array_map(fn ($v) => trim((string) $v), $this->voucherCampos),
            ];
            if ($this->imagenPath) {
                $voucher['imagen_path'] = $this->imagenPath;
            }
            $parametros['voucher'] = $voucher;
        }

        return $parametros;
    }

    /** Sube la imagen del voucher (una sola vez) al previsualizar o emitir. */
    private function subirImagenSiExiste(): void
    {
        if ($this->imagen && ! $this->imagenPath) {
            $this->validate(['imagen' => 'image|max:4096']);
            $this->imagenPath = $this->imagen->store("legal/garantia-{$this->garantiaId}", 'public');
        }
    }

    /** Alinea los inputs dinámicos del voucher con el catálogo del combo elegido. */
    private function sincronizarCamposVoucher(): void
    {
        if (! BancosVoucher::esComboValido($this->banco, $this->modalidad)) {
            $this->voucherCampos = [];

            return;
        }

        $previos = $this->voucherCampos;
        $this->voucherCampos = [];
        foreach (array_keys(BancosVoucher::campos($this->banco, $this->modalidad)) as $clave) {
            $this->voucherCampos[$clave] = (string) ($previos[$clave] ?? '');
        }

        // El monto del voucher debe igualar el importe del crédito: se sugiere
        if (trim($this->voucherCampos['monto'] ?? '') === '' && $this->importeCredito !== null) {
            $this->voucherCampos['monto'] = number_format($this->importeCredito, 2, '.', '');
        }
    }

    /** Ficha incompleta de los deudores naturales (campos que exige el contrato). */
    private function cargarFichas(Garantia $garantia): void
    {
        $this->fichas = [];
        foreach (array_filter([$garantia->client, $garantia->codeudor]) as $cliente) {
            $faltan = [];
            foreach (['sexo', 'estado_civil', 'ocupacion', 'email'] as $campo) {
                if (blank($cliente->{$campo})) {
                    $faltan[] = $campo;
                }
            }
            if ($faltan !== []) {
                $this->fichas[$cliente->id] = [
                    'nombre' => $cliente->fullName(),
                    'faltan' => $faltan,
                    'sexo' => '',
                    'estado_civil' => '',
                    'ocupacion' => '',
                    'email' => '',
                ];
            }
        }
    }

    /** Guarda en el Client SOLO los campos que estaban vacíos (patrón fase 1). */
    private function guardarFichas(): void
    {
        if ($this->tipoPersona === 'juridica' || $this->fichas === []) {
            return;
        }

        foreach ($this->fichas as $clientId => $ficha) {
            $cliente = Client::find($clientId);
            if (! $cliente) {
                continue;
            }

            $cambios = [];
            foreach ($ficha['faltan'] as $campo) {
                $valor = trim((string) ($ficha[$campo] ?? ''));
                if ($valor !== '' && blank($cliente->{$campo})) {
                    $cambios[$campo] = $campo === 'sexo' ? mb_strtoupper($valor) : $valor;
                }
            }
            if ($cambios !== []) {
                $cliente->update($cambios);
            }
        }

        // Los campos ya guardados dejan de pedirse
        $this->cargarFichas(Garantia::with(['client', 'codeudor'])->findOrFail($this->garantiaId));
    }

    public function render()
    {
        $garantia = Garantia::with(['client', 'codeudor', 'credit', 'vehiculos'])
            ->findOrFail($this->garantiaId);

        $combos = BancosVoucher::combosDisponibles();
        $modalidadesBanco = $combos[$this->banco] ?? [];
        $camposDef = BancosVoucher::esComboValido($this->banco, $this->modalidad)
            ? BancosVoucher::campos($this->banco, $this->modalidad)
            : [];

        return view('livewire.legal.contratos.form', [
            'garantia' => $garantia,
            'combos' => $combos,
            'modalidadesBanco' => $modalidadesBanco,
            'camposDef' => $camposDef,
            'bancos' => BancosVoucher::BANCOS,
            'modalidades' => BancosVoucher::MODALIDADES,
            'estadosCiviles' => Create::ESTADOS_CIVILES,
        ]);
    }
}
