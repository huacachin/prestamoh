<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Credit;
use App\Models\DocumentoCliente;
use App\Models\Vehiculo;
use App\Services\Documentos\GeneradorAnexo1;
use App\Support\Audit;
use Carbon\Carbon;
use Livewire\Component;

/**
 * Apartado "Documentos" del cliente: historial de documentos emitidos
 * (Anexo 1, Contrato, Anexo 2) con descargas en PDF y Word, y el modal de
 * generación del Anexo 1 (cronograma). Contrato y Anexo 2 llegan en las
 * fases 2-3 (por eso sus botones son placeholders deshabilitados).
 *
 * Todo documento se emite congelando un snapshot: la lista solo lee de
 * documentos_cliente; la generación vive en GeneradorAnexo1.
 */
class Documentos extends Component
{
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

    public function mount(int $id): void
    {
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

    public function render()
    {
        $client = Client::findOrFail($this->clientId);

        $documentos = DocumentoCliente::with(['credit', 'generadoPor'])
            ->where('client_id', $this->clientId)
            ->orderByDesc('id')
            ->get();

        return view('livewire.clients.documentos', [
            'client' => $client,
            'documentos' => $documentos,
            'creditosActivos' => $this->creditosActivos(),
            'vehiculos' => $this->vehiculosCliente(),
        ]);
    }
}
