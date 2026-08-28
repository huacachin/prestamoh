<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo1;
use App\Services\Documentos\GeneradorAnexo2;
use App\Services\Documentos\GeneradorContrato;
use App\Support\Audit;
use App\Support\Documentos\BancosVoucher;
use App\Support\Documentos\ModelosContrato;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Apartado "Documentos" del cliente: historial de documentos emitidos
 * (Anexo 1, Contrato, Anexo 2) con descargas en PDF y Word, y los modales de
 * generación del Anexo 1 (cronograma), del Contrato de garantía mobiliaria
 * (wizard con los 32 modelos del área — FASE 2) y del Anexo 2 (constancia de
 * entrega del monto: transcripción del voucher bancario + foto — FASE 3).
 *
 * Todo documento se emite congelando un snapshot: la lista solo lee de
 * documentos_cliente; la generación vive en GeneradorAnexo1/GeneradorContrato/
 * GeneradorAnexo2.
 * Validación SUAVE en el contrato: solo se bloquea lo imposible (deudor sin
 * nombre/DNI, sin vehículo, sin cronograma); el resto son campos editables
 * con defaults del sistema que van al snapshot tal como el usuario los deje.
 */
class Documentos extends Component
{
    use WithFileUploads;

    public int $clientId;

    // ── Estado del modal "Generar Anexo 1" ──
    public ?int $creditoId = null;

    public ?int $vehiculoId = null;

    /** Valor del vehículo (S/) editable; se guarda en la ficha del vehículo. */
    public string $valorVehiculo = '';

    /** Fecha del documento (Y-m-d del input date; el snapshot la lleva d/m/Y). */
    public string $fechaDoc = '';

    /** HTML de la vista previa (render 'previa' del snapshot) para el iframe. */
    public string $htmlPreview = '';

    // ── Estado del modal "Generar Contrato" (garantía mobiliaria — FASE 2) ──

    /** Correo hardcodeado del legacy: jamás se precarga como correo real. */
    private const CORREO_LEGACY = 'g@huacachin.com';

    /** Opciones de estado civil del wizard (valor => etiqueta). */
    public const ESTADOS_CIVILES = [
        'SOLTERO' => 'Soltero(a)',
        'CASADO' => 'Casado(a)',
        'CONVIVIENTE' => 'Conviviente',
        'DIVORCIADO' => 'Divorciado(a)',
        'VIUDO' => 'Viudo(a)',
    ];

    /** Clave del modelo elegido (catálogo ModelosContrato). */
    public string $modeloContrato = '';

    public ?int $contratoCreditoId = null;

    /**
     * Slots de vehículo según el preset del modelo (1 o 2). Cada slot:
     * vehiculo_id, es_futuro (lo fija el preset, no el usuario) y — solo si
     * es bien futuro — acta/kardex/notario editables.
     */
    public array $contratoVehiculos = [];

    /** Deudores EDITABLES (persona natural), precargados de la ficha. */
    public array $deudores = [];

    public string $buscarCodeudor = '';

    public ?int $codeudorClientId = null;

    /** Nombre del codeudor vinculado (solo para pintar el badge). */
    public string $codeudorNombre = '';

    /** Deudora persona jurídica: editable con defaults de la ficha. */
    public array $empresa = [
        'razon_social' => '', 'ruc' => '', 'partida' => '',
        'oficina_registral' => '', 'correo' => '', 'domicilio' => '',
    ];

    /** Gerente general de la empresa deudora (firma en representación). */
    public array $gerente = [
        'nombre' => '', 'dni' => '', 'genero' => 'M', 'ocupacion' => '',
        'estado_civil' => '', 'domicilio' => '',
    ];

    /** Tercero autorizado a recibir el desembolso (modelos de depósito a tercero). */
    public array $tercero = ['nombre' => '', 'dni' => '', 'cuenta' => '', 'motivo' => ''];

    /** Valor del bien (S/); default: suma del valor en ficha de los vehículos. */
    public string $valorBien = '';

    /** Monto máximo de la garantía; default: cuota × n° de cuotas del cronograma. */
    public string $montoMaximo = '';

    /** Cuota del contrato; default: moda del cronograma (readonly salvo "editar"). */
    public string $cuotaContrato = '';

    public bool $editarCuota = false;

    /** Total real del cronograma (texto de ayuda del monto máximo). */
    public ?float $totalCronograma = null;

    /** Clave de BancosVoucher del banco que menciona la cláusula de constancia. */
    public string $bancoDesembolso = '';

    /** Fecha del contrato (Y-m-d del input date; $datos la lleva d/m/Y). */
    public string $fechaContrato = '';

    public string $clausulasAdicionales = '';

    public string $htmlPreviewContrato = '';

    // ── Estado del modal "Generar Anexo 2" (constancia de entrega — FASE 3) ──

    public ?int $anexo2CreditoId = null;

    /** Clave de BancosVoucher::BANCOS del banco del voucher. */
    public string $anexo2Banco = '';

    /** Clave de BancosVoucher::MODALIDADES (solo los combos válidos del banco). */
    public string $anexo2Modalidad = '';

    /** Transcripción del voucher (clave de campo => valor), según ::campos(). */
    public array $anexo2Campos = [];

    /** Fecha del documento (Y-m-d del input date; $datos la lleva d/m/Y). */
    public string $fechaAnexo2 = '';

    /** Foto del comprobante bancario (upload temporal de Livewire, opcional). */
    public $comprobante = null;

    public string $htmlPreviewAnexo2 = '';

    protected function rules(): array
    {
        return [
            'creditoId' => 'required|integer',
            'vehiculoId' => 'nullable|integer',
            'valorVehiculo' => 'nullable|numeric|min:0',
            'fechaDoc' => 'required|date|before_or_equal:today',
        ];
    }

    protected function messages(): array
    {
        return [
            'creditoId.required' => 'Selecciona el crédito del documento.',
            'valorVehiculo.numeric' => 'El valor del vehículo debe ser un número.',
            'valorVehiculo.min' => 'El valor del vehículo no puede ser negativo.',
            'fechaDoc.required' => 'Indica la fecha del documento.',
            'fechaDoc.date' => 'La fecha del documento no es válida.',
            'fechaDoc.before_or_equal' => 'La fecha del documento no puede ser futura.',
        ];
    }

    /** true cuando se renderiza dentro de una pestaña (sin cabecera ni card propios). */
    public bool $embebido = false;

    public function mount(int $id, bool $embebido = false): void
    {
        $this->embebido = $embebido;
        $client = Client::findOrFail($id);

        // Analista (scope-propio): solo SUS clientes
        abort_if(
            (auth()->user()?->can('clientes.scope-propio') ?? false)
            && (int) $client->asesor_id !== (int) auth()->id(),
            403, 'Este cliente no pertenece a tu cartera.'
        );

        $this->clientId = $id;
    }

    /** Abre el modal del Anexo 1 con los selects precargados. */
    public function abrirModalAnexo1(): void
    {
        $creditos = $this->creditosActivos();
        $vehiculos = $this->vehiculosCliente();

        $this->creditoId = $creditos->count() === 1 ? $creditos->first()->id : null;
        $this->vehiculoId = $vehiculos->count() === 1 ? $vehiculos->first()->id : null;
        $this->valorVehiculo = $this->valorDe($this->vehiculoId);
        $this->fechaDoc = now()->format('Y-m-d');
        $this->htmlPreview = '';
        $this->resetErrorBag();

        $this->dispatch('anexo1-modal-open');
    }

    /** Al cambiar de vehículo se precarga su valor actual (editable). */
    public function updatedVehiculoId(): void
    {
        $this->valorVehiculo = $this->valorDe($this->vehiculoId);
        $this->htmlPreview = '';
    }

    /** Cualquier cambio de datos invalida la vista previa mostrada. */
    public function updatedCreditoId(): void
    {
        $this->htmlPreview = '';
    }

    public function updatedFechaDoc(): void
    {
        $this->htmlPreview = '';
    }

    public function updatedValorVehiculo(): void
    {
        $this->htmlPreview = '';
    }

    public function previsualizar(): void
    {
        $this->validate();

        [$client, $credit, $vehiculo] = $this->datosSeleccionados();
        if (! $credit) {
            return;
        }

        try {
            $this->htmlPreview = app(GeneradorAnexo1::class)
                ->previsualizar($client, $credit, $vehiculo, $this->overrides($vehiculo));
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('errorAlert', ['message' => 'No se pudo generar la vista previa: '.$e->getMessage()]);
        }
    }

    public function generar(): void
    {
        $this->validate();

        [$client, $credit, $vehiculo] = $this->datosSeleccionados();
        if (! $credit) {
            return;
        }

        try {
            $doc = app(GeneradorAnexo1::class)
                ->generar($client, $credit, $vehiculo, $this->overrides($vehiculo));

            Audit::log("Generó el Anexo 1 v{$doc->version} del crédito #{$credit->id} ({$client->fullName()})", $doc);

            $this->htmlPreview = '';
            $this->dispatch('anexo1-modal-close');
            $this->dispatch('successAlert', ['message' => "Anexo 1 v{$doc->version} generado."]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('errorAlert', ['message' => 'No se pudo generar el documento: '.$e->getMessage()]);
        }
    }

    // ═══ Contrato de garantía mobiliaria (FASE 2) ═══

    /** Abre el modal del contrato con deudor, fecha y montos precargados. */
    public function abrirModalContrato(): void
    {
        $client = Client::findOrFail($this->clientId);
        $creditos = $this->creditosActivos();

        $this->modeloContrato = '';
        $this->contratoCreditoId = $creditos->count() === 1 ? $creditos->first()->id : null;
        $this->contratoVehiculos = [];
        $this->deudores = [$this->deudorDesdeFicha($client)];
        $this->buscarCodeudor = '';
        $this->codeudorClientId = null;
        $this->codeudorNombre = '';
        $this->empresa = [
            'razon_social' => mb_strtoupper($client->fullName()),
            'ruc' => trim((string) $client->documento),
            'partida' => '',
            'oficina_registral' => '',
            'correo' => $this->correoFicha($client),
            'domicilio' => $this->domicilioDe($client),
        ];
        $this->gerente = [
            'nombre' => '', 'dni' => '', 'genero' => 'M', 'ocupacion' => '',
            'estado_civil' => '', 'domicilio' => '',
        ];
        $this->tercero = ['nombre' => '', 'dni' => '', 'cuenta' => '', 'motivo' => ''];
        $this->valorBien = '';
        $this->bancoDesembolso = '';
        $this->fechaContrato = now()->format('Y-m-d');
        $this->clausulasAdicionales = '';
        $this->htmlPreviewContrato = '';
        $this->resetErrorBag();

        $this->recalcularMontosContrato();

        $this->dispatch('contrato-modal-open');
    }

    /** Cambiar de modelo rearma las secciones condicionales del wizard. */
    public function updatedModeloContrato(): void
    {
        $this->sincronizarSeccionesModelo();
    }

    public function updatedContratoCreditoId(): void
    {
        $this->recalcularMontosContrato();
    }

    /** Al elegir vehículos se precarga el valor del bien (suma de las fichas). */
    public function updatedContratoVehiculos($valor, $clave): void
    {
        if (str_ends_with((string) $clave, 'vehiculo_id')) {
            $this->valorBien = $this->sumaValorVehiculos();
        }
    }

    /** Cualquier cambio de datos del contrato invalida su vista previa. */
    public function updated($name): void
    {
        $raiz = explode('.', (string) $name)[0];
        $propsContrato = [
            'modeloContrato', 'contratoCreditoId', 'contratoVehiculos', 'deudores',
            'empresa', 'gerente', 'tercero', 'valorBien', 'montoMaximo',
            'cuotaContrato', 'bancoDesembolso', 'fechaContrato', 'clausulasAdicionales',
        ];
        if (in_array($raiz, $propsContrato, true)) {
            $this->htmlPreviewContrato = '';
        }

        // Ídem para el Anexo 2 (el comprobante no invalida: la previa nunca lo incluye)
        $propsAnexo2 = ['anexo2CreditoId', 'anexo2Banco', 'anexo2Modalidad', 'anexo2Campos', 'fechaAnexo2'];
        if (in_array($raiz, $propsAnexo2, true)) {
            $this->htmlPreviewAnexo2 = '';
        }
    }

    /** Vincula el codeudor buscado y precarga sus campos editables. */
    public function seleccionarCodeudorContrato(int $id): void
    {
        if ($id === $this->clientId) {
            $this->dispatch('errorAlert', ['message' => 'El codeudor no puede ser el mismo deudor del contrato.']);

            return;
        }

        $codeudor = Client::active()->find($id);
        if (! $codeudor) {
            $this->dispatch('errorAlert', ['message' => 'El codeudor seleccionado no está activo.']);

            return;
        }

        $this->codeudorClientId = $codeudor->id;
        $this->codeudorNombre = $codeudor->fullName();
        $this->deudores[1] = $this->deudorDesdeFicha($codeudor);
        $this->buscarCodeudor = '';
        $this->htmlPreviewContrato = '';
        $this->resetErrorBag();
    }

    public function quitarCodeudorContrato(): void
    {
        $this->codeudorClientId = null;
        $this->codeudorNombre = '';
        $this->buscarCodeudor = '';
        if (isset($this->deudores[1])) {
            $this->deudores[1] = $this->deudorVacio();
        }
        $this->htmlPreviewContrato = '';
    }

    public function previsualizarContrato(): void
    {
        $this->validate($this->reglasContrato(), $this->mensajesContrato());

        $seleccion = $this->seleccionContrato();
        if (! $seleccion) {
            return;
        }
        [$client, $credit, $vehiculoIds] = $seleccion;

        try {
            $this->htmlPreviewContrato = GeneradorContrato::previsualizar(
                $client, $credit, $vehiculoIds, $this->modeloContrato, $this->datosContrato()
            );
        } catch (\Throwable $e) {
            $this->htmlPreviewContrato = '';
            $this->dispatch('errorAlert', ['message' => $this->mensajeDeExcepcion($e, 'No se pudo generar la vista previa')]);
        }
    }

    public function generarContrato(): void
    {
        $this->validate($this->reglasContrato(), $this->mensajesContrato());

        $seleccion = $this->seleccionContrato();
        if (! $seleccion) {
            return;
        }
        [$client, $credit, $vehiculoIds] = $seleccion;

        try {
            // El Audit del contrato lo registra el propio servicio (patrón del generador).
            $doc = GeneradorContrato::generar(
                $client, $credit, $vehiculoIds, $this->modeloContrato, $this->datosContrato()
            );

            $this->htmlPreviewContrato = '';
            $this->dispatch('contrato-modal-close');
            $this->dispatch('successAlert', ['message' => "Contrato v{$doc->version} generado."]);
        } catch (\Throwable $e) {
            $this->dispatch('errorAlert', ['message' => $this->mensajeDeExcepcion($e, 'No se pudo generar el contrato')]);
        }
    }

    /** Nombre "humano" de un modelo para la lista (fallback: la clave tal cual). */
    public function nombreModelo(string $clave): string
    {
        try {
            return ModelosContrato::get($clave)['nombre'] ?? $clave;
        } catch (\Throwable) {
            return $clave;
        }
    }

    // ═══ Anexo 2 — Constancia de entrega del monto (FASE 3) ═══

    /** Abre el modal del Anexo 2 con el crédito y la fecha precargados. */
    public function abrirModalAnexo2(): void
    {
        $creditos = $this->creditosActivos();

        $this->anexo2CreditoId = $creditos->count() === 1 ? $creditos->first()->id : null;
        $this->anexo2Banco = '';
        $this->anexo2Modalidad = '';
        $this->anexo2Campos = [];
        $this->fechaAnexo2 = now()->format('Y-m-d');
        $this->comprobante = null;
        $this->htmlPreviewAnexo2 = '';
        $this->resetErrorBag();

        $this->dispatch('anexo2-modal-open');
    }

    /** Al cambiar de crédito se vuelve a sugerir el monto del voucher. */
    public function updatedAnexo2CreditoId(): void
    {
        $this->sugerirMontoAnexo2();
    }

    /** Cambiar de banco rearma la modalidad (autoselección si solo hay una). */
    public function updatedAnexo2Banco(): void
    {
        $modalidades = BancosVoucher::combosDisponibles()[$this->anexo2Banco] ?? [];
        $this->anexo2Modalidad = count($modalidades) === 1 ? $modalidades[0] : '';
        $this->rearmarCamposAnexo2();
    }

    public function updatedAnexo2Modalidad(): void
    {
        $this->rearmarCamposAnexo2();
    }

    /** Valida la imagen apenas se sube; si no pasa, se descarta el archivo. */
    public function updatedComprobante(): void
    {
        try {
            $this->validateOnly('comprobante', $this->reglasAnexo2(), $this->mensajesAnexo2());
        } catch (ValidationException $e) {
            $this->comprobante = null;

            throw $e;
        }
    }

    /** Vista previa SIN imagen (imagen_path null → recuadro placeholder). */
    public function previsualizarAnexo2(): void
    {
        $this->validate($this->reglasAnexo2(), $this->mensajesAnexo2());

        $seleccion = $this->seleccionAnexo2();
        if (! $seleccion) {
            return;
        }
        [$client, $credit] = $seleccion;

        try {
            $this->htmlPreviewAnexo2 = GeneradorAnexo2::previsualizar($client, $credit, $this->datosAnexo2(null));
        } catch (InvalidArgumentException $e) {
            // Campos requeridos faltantes o monto que no cuadra con el desembolso
            $this->htmlPreviewAnexo2 = '';
            $this->dispatch('errorAlert', ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->htmlPreviewAnexo2 = '';
            report($e);
            $this->dispatch('errorAlert', ['message' => 'No se pudo generar la vista previa: '.$e->getMessage()]);
        }
    }

    public function generarAnexo2(): void
    {
        $this->validate($this->reglasAnexo2(), $this->mensajesAnexo2());

        $seleccion = $this->seleccionAnexo2();
        if (! $seleccion) {
            return;
        }
        [$client, $credit] = $seleccion;

        $path = null;

        try {
            if ($this->comprobante) {
                $path = $this->comprobante->store("documentos/cliente-{$this->clientId}", 'public');
            }

            // El Audit del Anexo 2 lo registra el propio servicio (patrón del generador).
            $doc = GeneradorAnexo2::generar($client, $credit, $this->datosAnexo2($path));

            $this->comprobante = null;
            $this->htmlPreviewAnexo2 = '';
            $this->dispatch('anexo2-modal-close');
            $this->dispatch('successAlert', ['message' => "Anexo 2 v{$doc->version} generado."]);
        } catch (InvalidArgumentException $e) {
            $this->limpiarComprobanteFallido($path);
            $this->dispatch('errorAlert', ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->limpiarComprobanteFallido($path);
            report($e);
            $this->dispatch('errorAlert', ['message' => 'No se pudo generar el documento: '.$e->getMessage()]);
        }
    }

    /** Client + Credit activo del cliente + combo banco×modalidad, validados. */
    private function seleccionAnexo2(): ?array
    {
        $client = Client::findOrFail($this->clientId);

        $credit = Credit::activo()
            ->where('client_id', $this->clientId)
            ->find($this->anexo2CreditoId);
        if (! $credit) {
            $this->dispatch('errorAlert', ['message' => 'El crédito seleccionado no es un crédito activo del cliente.']);

            return null;
        }

        if (! BancosVoucher::esComboValido($this->anexo2Banco, $this->anexo2Modalidad)) {
            $this->dispatch('errorAlert', ['message' => 'La modalidad elegida no está disponible para ese banco.']);

            return null;
        }

        return [$client, $credit];
    }

    /** $datos con el contrato de claves que espera GeneradorAnexo2. */
    private function datosAnexo2(?string $imagenPath): array
    {
        return [
            'banco' => $this->anexo2Banco,
            'modalidad' => $this->anexo2Modalidad,
            'campos' => array_map(fn ($v) => trim((string) $v), $this->anexo2Campos),
            'imagen_path' => $imagenPath,
            'fecha' => Carbon::parse($this->fechaAnexo2)->format('d/m/Y'),
        ];
    }

    /** Rearma los inputs dinámicos del combo elegido (todos vacíos + monto sugerido). */
    private function rearmarCamposAnexo2(): void
    {
        $this->anexo2Campos = [];

        if (! BancosVoucher::esComboValido($this->anexo2Banco, $this->anexo2Modalidad)) {
            return;
        }

        foreach (array_keys(BancosVoucher::campos($this->anexo2Banco, $this->anexo2Modalidad)) as $clave) {
            $this->anexo2Campos[$clave] = '';
        }

        $this->sugerirMontoAnexo2();
    }

    /** Pre-sugiere el campo 'monto' con el importe desembolsado del crédito. */
    private function sugerirMontoAnexo2(): void
    {
        if (! array_key_exists('monto', $this->anexo2Campos)) {
            return;
        }

        $importe = $this->importeCreditoAnexo2();
        if ($importe !== null) {
            $this->anexo2Campos['monto'] = number_format($importe, 2, '.', '');
        }
    }

    /** Importe del crédito activo elegido en el modal del Anexo 2 (null si no hay). */
    private function importeCreditoAnexo2(): ?float
    {
        if (! $this->anexo2CreditoId) {
            return null;
        }

        $credit = Credit::activo()
            ->where('client_id', $this->clientId)
            ->find($this->anexo2CreditoId);

        return $credit ? round((float) $credit->importe, 2) : null;
    }

    /** Borra el comprobante ya guardado cuando la generación falló después. */
    private function limpiarComprobanteFallido(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function reglasAnexo2(): array
    {
        return [
            'anexo2CreditoId' => ['required', 'integer'],
            'anexo2Banco' => ['required', Rule::in(array_keys(BancosVoucher::BANCOS))],
            'anexo2Modalidad' => ['required', Rule::in(array_keys(BancosVoucher::MODALIDADES))],
            'fechaAnexo2' => ['required', 'date', 'before_or_equal:today'],
            'comprobante' => ['nullable', 'image', 'max:4096'],
        ];
    }

    private function mensajesAnexo2(): array
    {
        return [
            'anexo2CreditoId.required' => 'Selecciona el crédito desembolsado.',
            'anexo2Banco.required' => 'Selecciona el banco del voucher.',
            'anexo2Banco.in' => 'Banco no válido.',
            'anexo2Modalidad.required' => 'Selecciona la modalidad de la operación.',
            'anexo2Modalidad.in' => 'Modalidad no válida.',
            'fechaAnexo2.required' => 'Indica la fecha del documento.',
            'fechaAnexo2.date' => 'La fecha del documento no es válida.',
            'fechaAnexo2.before_or_equal' => 'La fecha del documento no puede ser futura.',
            'comprobante.image' => 'El comprobante debe ser una imagen (JPG, PNG, WEBP…).',
            'comprobante.max' => 'La imagen del comprobante debe pesar máximo 4 MB.',
        ];
    }

    /**
     * Anula un documento emitido: queda tachado en la lista pero sus
     * descargas siguen disponibles (constancia de lo que se entregó).
     */
    public function anular(int $docId): void
    {
        $doc = DocumentoCliente::where('client_id', $this->clientId)->find($docId);
        if (! $doc || $doc->estado === 'anulado') {
            return;
        }

        $doc->update(['estado' => 'anulado']);
        Audit::log("Anuló el documento {$doc->tipoLabel()} v{$doc->version} del crédito #{$doc->credit_id}", $doc);

        $this->dispatch('successAlert', ['message' => "{$doc->tipoLabel()} v{$doc->version} anulado."]);
    }

    /** Client + Credit (activo del cliente) + Vehiculo (del cliente) validados. */
    private function datosSeleccionados(): array
    {
        $client = Client::findOrFail($this->clientId);

        $credit = Credit::activo()
            ->where('client_id', $this->clientId)
            ->find($this->creditoId);
        if (! $credit) {
            $this->dispatch('errorAlert', ['message' => 'El crédito seleccionado no es un crédito activo del cliente.']);

            return [$client, null, null];
        }

        $vehiculo = $this->vehiculoId
            ? Vehiculo::where('client_id', $this->clientId)->find($this->vehiculoId)
            : null;
        if ($this->vehiculoId && ! $vehiculo) {
            $this->dispatch('errorAlert', ['message' => 'El vehículo seleccionado no pertenece al cliente.']);

            return [$client, null, null];
        }

        return [$client, $credit, $vehiculo];
    }

    /** Overrides del generador: 'fecha' en d/m/Y y 'valor_vehiculo' float|null. */
    private function overrides(?Vehiculo $vehiculo): array
    {
        return [
            'fecha' => Carbon::parse($this->fechaDoc)->format('d/m/Y'),
            'valor_vehiculo' => ($vehiculo && $this->valorVehiculo !== '')
                ? round((float) $this->valorVehiculo, 2)
                : null,
        ];
    }

    private function creditosActivos()
    {
        return Credit::activo()
            ->where('client_id', $this->clientId)
            ->orderByDesc('id')
            ->get();
    }

    private function vehiculosCliente()
    {
        return Vehiculo::where('client_id', $this->clientId)->orderBy('id')->get();
    }

    private function valorDe(?int $vehiculoId): string
    {
        $valor = $vehiculoId ? Vehiculo::find($vehiculoId)?->valor : null;

        return $valor !== null ? number_format((float) $valor, 2, '.', '') : '';
    }

    // ─── Helpers del wizard del contrato ────────────────────────────────────

    /** Preset crudo del catálogo (null si la clave no existe o el catálogo falla). */
    private function presetDe(string $clave): ?array
    {
        try {
            $preset = ModelosContrato::get($clave);

            return is_array($preset) ? $preset : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Preset del modelo elegido, normalizado para el wizard: personas
     * (1|2|'empresa'), slots (es_futuro por vehículo), destino
     * (propio|tercero|gerente), gps, custodia y sexo sugerido del deudor.
     */
    private function presetNormalizado(): ?array
    {
        if ($this->modeloContrato === '') {
            return null;
        }

        $preset = $this->presetDe($this->modeloContrato);
        if ($preset === null) {
            return null;
        }

        $destino = mb_strtolower(trim((string) ($preset['destino'] ?? 'propio')));
        $sexo = mb_strtoupper(trim((string) ($preset['sexo'] ?? '')));

        return [
            'nombre' => (string) ($preset['nombre'] ?? $this->modeloContrato),
            'personas' => $this->personasDe($preset),
            'slots' => $this->slotsFuturoDe($preset),
            'destino' => in_array($destino, ['tercero', 'gerente'], true) ? $destino : 'propio',
            'gps' => (bool) ($preset['gps'] ?? str_starts_with($this->modeloContrato, 'a')),
            'custodia' => (bool) ($preset['custodia'] ?? false),
            'sexo' => in_array($sexo, ['M', 'F'], true) ? $sexo : null,
        ];
    }

    /** 1 | 2 | 'empresa' desde el preset (la única variante no numérica es la jurídica). */
    private function personasDe(array $preset): int|string
    {
        $p = $preset['personas'] ?? 1;
        if (is_string($p) && ! is_numeric($p)) {
            return 'empresa';
        }

        return max(1, min(2, (int) $p));
    }

    /**
     * es_futuro por slot de vehículo. Tolera las formas razonables del
     * catálogo: lista por vehículo ('futuro'/'presente' o ['es_futuro' =>
     * bool]), un conteo (1, 2, '2') o un código compacto ('2f', '1f1p'),
     * más 'futuros'/'futuro' como override global del preset.
     */
    private function slotsFuturoDe(array $preset): array
    {
        $spec = $preset['bienes'] ?? $preset['vehiculos'] ?? 1;
        $slots = [];

        if (is_array($spec)) {
            foreach (array_values($spec) as $item) {
                $slots[] = is_array($item)
                    ? (bool) ($item['es_futuro'] ?? $item['futuro'] ?? false)
                    : str_starts_with(mb_strtolower(trim((string) $item)), 'f');
            }
        } else {
            preg_match_all('/(\d)\s*([a-z]*)/', mb_strtolower(trim((string) $spec)), $m, PREG_SET_ORDER);
            foreach ($m as $token) {
                $futuro = str_starts_with($token[2], 'f');
                for ($i = 0; $i < (int) $token[1]; $i++) {
                    $slots[] = $futuro;
                }
            }
        }

        $slots = array_slice($slots === [] ? [false] : $slots, 0, 2);

        $futuros = $preset['futuros'] ?? $preset['futuro'] ?? null;
        if (is_bool($futuros)) {
            $slots = array_fill(0, count($slots), $futuros);
        } elseif (is_array($futuros)) {
            foreach ($slots as $i => $actual) {
                $slots[$i] = (bool) ($futuros[$i] ?? $actual);
            }
        }

        return $slots;
    }

    /** Rearma slots de vehículo y deudores según el preset del modelo elegido. */
    private function sincronizarSeccionesModelo(): void
    {
        $preset = $this->presetNormalizado();
        if (! $preset) {
            $this->contratoVehiculos = [];

            return;
        }

        // Vehículos: conserva lo ya elegido; autoselecciona si el cliente
        // tiene exactamente los vehículos que pide el modelo.
        $previos = array_values($this->contratoVehiculos);
        $vehiculos = $this->vehiculosCliente()->values();
        $this->contratoVehiculos = [];
        foreach ($preset['slots'] as $i => $esFuturo) {
            $previo = $previos[$i] ?? null;
            $this->contratoVehiculos[] = [
                'vehiculo_id' => $previo['vehiculo_id']
                    ?? ($vehiculos->count() === count($preset['slots']) ? $vehiculos->get($i)?->id : null),
                'es_futuro' => $esFuturo,
                'acta' => (string) ($previo['acta'] ?? ''),
                'kardex' => (string) ($previo['kardex'] ?? ''),
                'notario' => (string) ($previo['notario'] ?? ''),
            ];
        }
        $this->valorBien = $this->sumaValorVehiculos();

        // Deudores según el modelo
        $client = Client::findOrFail($this->clientId);
        if ($preset['personas'] === 'empresa') {
            if (trim((string) $this->empresa['razon_social']) === '') {
                $this->empresa['razon_social'] = mb_strtoupper($client->fullName());
                $this->empresa['ruc'] = trim((string) $client->documento);
            }
        } else {
            if (! isset($this->deudores[0])) {
                $this->deudores[0] = $this->deudorDesdeFicha($client);
            }
            if ($preset['sexo'] !== null) {
                $this->deudores[0]['sexo'] = $preset['sexo'];
            }
            if ($preset['personas'] === 2) {
                $this->deudores[1] ??= $this->deudorVacio();
            } else {
                unset($this->deudores[1]);
                $this->codeudorClientId = null;
                $this->codeudorNombre = '';
                $this->buscarCodeudor = '';
            }
        }

        $this->resetErrorBag();
    }

    /** Cuota (moda), monto máximo (cuota × n) y total real desde el cronograma. */
    private function recalcularMontosContrato(): void
    {
        $this->cuotaContrato = '';
        $this->montoMaximo = '';
        $this->totalCronograma = null;
        $this->editarCuota = false;

        if (! $this->contratoCreditoId) {
            return;
        }

        $credit = Credit::activo()
            ->where('client_id', $this->clientId)
            ->find($this->contratoCreditoId);
        if (! $credit) {
            return;
        }

        // Cronograma real de credit_installments (sin filas de monto 0)
        $filas = $credit->installments()
            ->orderBy('num_cuota')
            ->get(['num_cuota', 'importe_cuota', 'importe_interes', 'importe_excedente'])
            ->map(fn ($i) => round((float) $i->importe_cuota + (float) $i->importe_interes + (float) $i->importe_excedente, 2))
            ->filter(fn ($m) => $m > 0)
            ->values();

        if ($filas->isEmpty()) {
            return;
        }

        // Cuota = moda (clave STRING: countBy con float trunca a entero)
        $cuota = (float) $filas
            ->countBy(fn ($m) => number_format($m, 2, '.', ''))
            ->sortDesc()
            ->keys()
            ->first();

        $this->cuotaContrato = number_format($cuota, 2, '.', '');
        $this->montoMaximo = number_format(round($cuota * $filas->count(), 2), 2, '.', '');
        $this->totalCronograma = round($filas->sum(), 2);
    }

    /** Suma del valor en ficha de los vehículos elegidos ('' si ninguno lo tiene). */
    private function sumaValorVehiculos(): string
    {
        $ids = array_filter(array_map(
            fn ($slot) => (int) ($slot['vehiculo_id'] ?? 0),
            $this->contratoVehiculos
        ));
        if ($ids === []) {
            return '';
        }

        $valores = Vehiculo::where('client_id', $this->clientId)
            ->whereIn('id', $ids)
            ->pluck('valor')
            ->filter(fn ($v) => $v !== null);

        return $valores->isEmpty()
            ? ''
            : number_format($valores->sum(fn ($v) => (float) $v), 2, '.', '');
    }

    /** Deudor precargado de la ficha (todo editable en el wizard). */
    private function deudorDesdeFicha(Client $client): array
    {
        $sexo = mb_strtoupper(trim((string) $client->sexo)) === 'F' ? 'F' : 'M';

        return [
            'nombre' => mb_strtoupper($client->fullName()),
            'dni' => trim((string) $client->documento),
            'nacionalidad' => $sexo === 'F' ? 'PERUANA' : 'PERUANO',
            'ocupacion' => mb_strtoupper(trim((string) ($client->ocupacion ?? ''))),
            'estado_civil' => $this->estadoCivilNormalizado($client->estado_civil ?? null),
            'domicilio' => $this->domicilioDe($client),
            'correo' => $this->correoFicha($client),
            'sexo' => $sexo,
        ];
    }

    private function deudorVacio(): array
    {
        return [
            'nombre' => '', 'dni' => '', 'nacionalidad' => 'PERUANO', 'ocupacion' => '',
            'estado_civil' => '', 'domicilio' => '', 'correo' => '', 'sexo' => 'M',
        ];
    }

    /** Correo de la ficha; el hardcodeado del legacy se precarga VACÍO. */
    private function correoFicha(Client $client): string
    {
        $correo = trim((string) $client->email);

        return mb_strtolower($correo) === self::CORREO_LEGACY ? '' : $correo;
    }

    /** Ficha → opción del select de estado civil ('' si no calza ninguna). */
    private function estadoCivilNormalizado(?string $valor): string
    {
        $v = mb_strtoupper(trim((string) $valor));
        if ($v === '') {
            return '';
        }
        foreach (array_keys(self::ESTADOS_CIVILES) as $opcion) {
            if (str_starts_with($v, mb_substr($opcion, 0, 4))) {
                return $opcion;
            }
        }

        return '';
    }

    /** Domicilio legal armado de la ficha (mismo formato que el Anexo 1). */
    private function domicilioDe(Client $client): string
    {
        $tramos = [];

        if (filled($client->direccion)) {
            $tramos[] = mb_strtoupper(trim($client->direccion));
        }
        if (filled($client->distrito)) {
            $tramos[] = 'DISTRITO DE '.mb_strtoupper(trim($client->distrito));
        }

        $provincia = filled($client->provincia) ? mb_strtoupper(trim($client->provincia)) : null;
        $departamento = filled($client->departamento) ? mb_strtoupper(trim($client->departamento)) : null;

        if ($provincia && $departamento && $provincia === $departamento) {
            $tramos[] = 'PROVINCIA Y DEPARTAMENTO DE '.$provincia;
        } else {
            if ($provincia) {
                $tramos[] = 'PROVINCIA DE '.$provincia;
            }
            if ($departamento) {
                $tramos[] = 'DEPARTAMENTO DE '.$departamento;
            }
        }

        return implode(', ', $tramos);
    }

    /** Client + Credit activo + ids de vehículos del cliente, validados. */
    private function seleccionContrato(): ?array
    {
        $client = Client::findOrFail($this->clientId);

        $credit = Credit::activo()
            ->where('client_id', $this->clientId)
            ->find($this->contratoCreditoId);
        if (! $credit) {
            $this->dispatch('errorAlert', ['message' => 'El crédito seleccionado no es un crédito activo del cliente.']);

            return null;
        }

        $ids = array_map(fn ($slot) => (int) $slot['vehiculo_id'], $this->contratoVehiculos);
        if (count(array_unique($ids)) !== count($ids)) {
            $this->dispatch('errorAlert', ['message' => 'Los vehículos del contrato deben ser distintos.']);

            return null;
        }

        $propios = Vehiculo::where('client_id', $this->clientId)->whereIn('id', $ids)->count();
        if ($propios !== count($ids)) {
            $this->dispatch('errorAlert', ['message' => 'Uno de los vehículos seleccionados no pertenece al cliente.']);

            return null;
        }

        return [$client, $credit, $ids];
    }

    /** $datos con el contrato de claves que espera GeneradorContrato. */
    private function datosContrato(): array
    {
        $preset = $this->presetNormalizado();
        $personas = $preset['personas'] ?? 1;

        $datos = [
            'fecha' => Carbon::parse($this->fechaContrato)->format('d/m/Y'),
            'valor_bien' => $this->valorBien !== '' ? round((float) $this->valorBien, 2) : null,
            'monto_maximo' => $this->montoMaximo !== '' ? round((float) $this->montoMaximo, 2) : null,
            'cuota' => $this->cuotaContrato !== '' ? round((float) $this->cuotaContrato, 2) : null,
            'banco' => $this->bancoDesembolso,
            'clausulas_adicionales' => trim($this->clausulasAdicionales) !== '' ? trim($this->clausulasAdicionales) : null,
            'deudores' => [],
            'codeudor_client_id' => null,
            'empresa' => null,
            'tercero' => null,
            'bienes' => [],
        ];

        if ($personas === 'empresa') {
            $datos['empresa'] = [
                'razon_social' => trim((string) $this->empresa['razon_social']),
                'ruc' => trim((string) $this->empresa['ruc']),
                'partida' => trim((string) $this->empresa['partida']) ?: null,
                'oficina_registral' => trim((string) $this->empresa['oficina_registral']) ?: null,
                'correo' => trim((string) $this->empresa['correo']) ?: null,
                'domicilio' => trim((string) $this->empresa['domicilio']) ?: null,
                'gerente' => [
                    'nombre' => trim((string) $this->gerente['nombre']),
                    'dni' => trim((string) $this->gerente['dni']),
                    'genero' => ($this->gerente['genero'] ?? 'M') === 'F' ? 'F' : 'M',
                    'ocupacion' => trim((string) $this->gerente['ocupacion']),
                    'estado_civil' => trim((string) $this->gerente['estado_civil']),
                    'domicilio' => trim((string) $this->gerente['domicilio']),
                ],
            ];
        } else {
            foreach (array_slice(array_values($this->deudores), 0, (int) $personas) as $d) {
                $datos['deudores'][] = [
                    'nombre' => trim((string) $d['nombre']),
                    'dni' => trim((string) $d['dni']),
                    'nacionalidad' => trim((string) $d['nacionalidad']),
                    'ocupacion' => trim((string) $d['ocupacion']),
                    'estado_civil' => trim((string) $d['estado_civil']),
                    'domicilio' => trim((string) $d['domicilio']),
                    'correo' => trim((string) $d['correo']),
                    'sexo' => ($d['sexo'] ?? 'M') === 'F' ? 'F' : 'M',
                ];
            }
            $datos['codeudor_client_id'] = (int) $personas === 2 ? $this->codeudorClientId : null;
        }

        if (($preset['destino'] ?? 'propio') === 'tercero') {
            $datos['tercero'] = [
                'nombre' => trim((string) $this->tercero['nombre']),
                'dni' => trim((string) $this->tercero['dni']),
                'cuenta' => trim((string) $this->tercero['cuenta']) ?: null,
                'motivo' => trim((string) $this->tercero['motivo']) ?: null,
            ];
        }

        foreach ($this->contratoVehiculos as $slot) {
            $datos['bienes'][(int) $slot['vehiculo_id']] = [
                'es_futuro' => (bool) $slot['es_futuro'],
                'acta' => trim((string) $slot['acta']) ?: null,
                'kardex' => trim((string) $slot['kardex']) ?: null,
                'notario' => trim((string) $slot['notario']) ?: null,
            ];
        }

        return $datos;
    }

    /**
     * Validación SUAVE del wizard: solo lo imposible es required (modelo,
     * crédito, vehículos, nombre/DNI del deudor, banco de la constancia);
     * el resto son editables que viajan al snapshot tal como queden.
     */
    private function reglasContrato(): array
    {
        $preset = $this->presetNormalizado();

        $reglas = [
            'modeloContrato' => ['required', 'string'],
            'contratoCreditoId' => ['required', 'integer'],
            'fechaContrato' => ['required', 'date'],
            'bancoDesembolso' => ['required', Rule::in(array_keys(BancosVoucher::NOMBRES_LEGALES))],
            'valorBien' => ['nullable', 'numeric', 'min:0'],
            'montoMaximo' => ['nullable', 'numeric', 'min:0'],
            'cuotaContrato' => ['nullable', 'numeric', 'min:0'],
            'clausulasAdicionales' => ['nullable', 'string', 'max:5000'],
            'contratoVehiculos' => ['required', 'array', 'min:1'],
            'contratoVehiculos.*.vehiculo_id' => ['required', 'integer'],
            'contratoVehiculos.*.acta' => ['nullable', 'string', 'max:60'],
            'contratoVehiculos.*.kardex' => ['nullable', 'string', 'max:20'],
            'contratoVehiculos.*.notario' => ['nullable', 'string', 'max:120'],
        ];

        if (! $preset) {
            return $reglas; // sin modelo válido, 'modeloContrato' dispara el error
        }

        if ($preset['personas'] === 'empresa') {
            $reglas += [
                'empresa.razon_social' => ['required', 'string', 'max:200'],
                'empresa.ruc' => ['required', 'string', 'max:15'],
                'empresa.partida' => ['nullable', 'string', 'max:30'],
                'empresa.oficina_registral' => ['nullable', 'string', 'max:80'],
                'empresa.correo' => ['nullable', 'email', 'max:150'],
                'empresa.domicilio' => ['nullable', 'string', 'max:300'],
                'gerente.nombre' => ['required', 'string', 'max:150'],
                'gerente.dni' => ['required', 'string', 'max:15'],
                'gerente.genero' => ['required', Rule::in(['M', 'F'])],
                'gerente.ocupacion' => ['nullable', 'string', 'max:100'],
                'gerente.estado_civil' => ['nullable', 'string', 'max:30'],
                'gerente.domicilio' => ['nullable', 'string', 'max:300'],
            ];
        } else {
            for ($i = 0; $i < (int) $preset['personas']; $i++) {
                $reglas["deudores.{$i}.nombre"] = ['required', 'string', 'max:200'];
                $reglas["deudores.{$i}.dni"] = ['required', 'string', 'max:15'];
                $reglas["deudores.{$i}.sexo"] = ['required', Rule::in(['M', 'F'])];
                $reglas["deudores.{$i}.nacionalidad"] = ['nullable', 'string', 'max:50'];
                $reglas["deudores.{$i}.ocupacion"] = ['nullable', 'string', 'max:100'];
                $reglas["deudores.{$i}.estado_civil"] = ['nullable', 'string', 'max:30'];
                $reglas["deudores.{$i}.domicilio"] = ['nullable', 'string', 'max:300'];
                $reglas["deudores.{$i}.correo"] = ['nullable', 'email', 'max:150'];
            }
        }

        if ($preset['destino'] === 'tercero') {
            $reglas += [
                'tercero.nombre' => ['required', 'string', 'max:150'],
                'tercero.dni' => ['required', 'string', 'max:15'],
                'tercero.cuenta' => ['nullable', 'string', 'max:40'],
                'tercero.motivo' => ['nullable', 'string', 'max:300'],
            ];
        }

        return $reglas;
    }

    private function mensajesContrato(): array
    {
        return [
            'modeloContrato.required' => 'Selecciona el modelo del contrato.',
            'contratoCreditoId.required' => 'Selecciona el crédito que garantiza el contrato.',
            'fechaContrato.required' => 'Indica la fecha del contrato.',
            'fechaContrato.date' => 'La fecha del contrato no es válida.',
            'bancoDesembolso.required' => 'Elige el banco del desembolso (la cláusula de constancia lo menciona).',
            'bancoDesembolso.in' => 'Banco del desembolso no válido.',
            'valorBien.numeric' => 'El valor del bien debe ser un número.',
            'valorBien.min' => 'El valor del bien no puede ser negativo.',
            'montoMaximo.numeric' => 'El monto máximo debe ser un número.',
            'montoMaximo.min' => 'El monto máximo no puede ser negativo.',
            'cuotaContrato.numeric' => 'La cuota debe ser un número.',
            'cuotaContrato.min' => 'La cuota no puede ser negativa.',
            'clausulasAdicionales.max' => 'Las cláusulas adicionales no deben exceder 5000 caracteres.',
            'contratoVehiculos.required' => 'El modelo requiere al menos un vehículo en garantía.',
            'contratoVehiculos.min' => 'El modelo requiere al menos un vehículo en garantía.',
            'contratoVehiculos.*.vehiculo_id.required' => 'Selecciona el vehículo de este slot de garantía.',
            'contratoVehiculos.*.vehiculo_id.integer' => 'Vehículo no válido.',
            'deudores.*.nombre.required' => 'Ingresa el nombre completo del deudor.',
            'deudores.*.dni.required' => 'Ingresa el DNI del deudor.',
            'deudores.*.sexo.required' => 'Indica el sexo del deudor (define EL DEUDOR / LA DEUDORA).',
            'deudores.*.sexo.in' => 'El sexo del deudor debe ser M o F.',
            'deudores.*.correo.email' => 'El correo del deudor no es válido.',
            'empresa.razon_social.required' => 'Ingresa la razón social de la empresa deudora.',
            'empresa.ruc.required' => 'Ingresa el RUC de la empresa deudora.',
            'empresa.correo.email' => 'El correo de la empresa no es válido.',
            'gerente.nombre.required' => 'Ingresa el nombre del gerente general.',
            'gerente.dni.required' => 'Ingresa el DNI del gerente general.',
            'gerente.genero.required' => 'Indica el género del gerente general.',
            'gerente.genero.in' => 'El género del gerente debe ser M o F.',
            'tercero.nombre.required' => 'Ingresa el nombre del tercero autorizado a recibir el desembolso.',
            'tercero.dni.required' => 'Ingresa el DNI del tercero autorizado.',
        ];
    }

    /**
     * Mensaje legible de una excepción del generador: si el servicio lanzó
     * una excepción de validación con lista pública "errores", se muestran
     * tal cual; en cualquier otro caso se reporta y se muestra el mensaje.
     */
    private function mensajeDeExcepcion(\Throwable $e, string $prefijo): string
    {
        $errores = $e->errores ?? null;
        if (is_array($errores) && $errores !== []) {
            return $prefijo.': '.implode(' · ', array_map('strval', $errores));
        }

        report($e);

        return $prefijo.': '.$e->getMessage();
    }

    /**
     * Catálogo agrupado para el select del modelo: 'Con GPS' / 'Custodia' /
     * 'Sin GPS' (vacío si el catálogo aún no está disponible).
     */
    private function modelosAgrupados(): array
    {
        try {
            $nombres = ModelosContrato::nombres();
        } catch (\Throwable) {
            return [];
        }

        $grupos = ['Con GPS' => [], 'Custodia' => [], 'Sin GPS' => []];
        foreach ($nombres as $clave => $nombre) {
            $preset = $this->presetDe((string) $clave) ?? [];
            $custodia = (bool) ($preset['custodia'] ?? str_contains(mb_strtolower((string) $nombre), 'custodia'));
            $gps = (bool) ($preset['gps'] ?? str_starts_with((string) $clave, 'a'));
            $grupos[$custodia ? 'Custodia' : ($gps ? 'Con GPS' : 'Sin GPS')][$clave] = $nombre;
        }

        return array_filter($grupos);
    }

    public function render()
    {
        $client = Client::findOrFail($this->clientId);

        $documentos = DocumentoCliente::with(['credit', 'generadoPor'])
            ->where('client_id', $this->clientId)
            ->orderByDesc('id')
            ->get();

        // Buscador de codeudor del contrato (mín. 2 caracteres, máx. 10 resultados)
        $presetContrato = $this->presetNormalizado();
        $codeudoresEncontrados = collect();
        $term = trim($this->buscarCodeudor);
        if ($presetContrato && $presetContrato['personas'] === 2 && ! $this->codeudorClientId && mb_strlen($term) >= 2) {
            $codeudoresEncontrados = Client::active()
                ->where('id', '!=', $this->clientId)
                ->where(function ($q) use ($term) {
                    $q->where('documento', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT_WS(' ', apellido_pat, apellido_mat, nombre) LIKE ?", ["%{$term}%"])
                        ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_pat, apellido_mat) LIKE ?", ["%{$term}%"]);
                })
                ->orderBy('apellido_pat')
                ->limit(10)
                ->get();
        }

        // Anexo 2: modalidades válidas del banco elegido y campos del combo
        $modalidadesAnexo2 = $this->anexo2Banco !== ''
            ? (BancosVoucher::combosDisponibles()[$this->anexo2Banco] ?? [])
            : [];
        $camposAnexo2 = BancosVoucher::esComboValido($this->anexo2Banco, $this->anexo2Modalidad)
            ? BancosVoucher::campos($this->anexo2Banco, $this->anexo2Modalidad)
            : [];

        return view('livewire.clients.documentos', [
            'client' => $client,
            'documentos' => $documentos,
            'creditosActivos' => $this->creditosActivos(),
            'vehiculos' => $this->vehiculosCliente(),
            'modelosAgrupados' => $this->modelosAgrupados(),
            'presetContrato' => $presetContrato,
            'codeudoresEncontrados' => $codeudoresEncontrados,
            'bancosDesembolso' => BancosVoucher::NOMBRES_LEGALES,
            'estadosCiviles' => self::ESTADOS_CIVILES,
            'bancosVoucher' => BancosVoucher::BANCOS,
            'modalidadesVoucher' => BancosVoucher::MODALIDADES,
            'modalidadesAnexo2' => $modalidadesAnexo2,
            'camposAnexo2' => $camposAnexo2,
            'tituloVoucherAnexo2' => $camposAnexo2 !== []
                ? BancosVoucher::titulo($this->anexo2Banco, $this->anexo2Modalidad)
                : '',
            'montoDesembolsoAnexo2' => $this->importeCreditoAnexo2(),
        ]);
    }
}
