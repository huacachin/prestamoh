<?php

namespace App\Services\Legal;

use App\Models\Contrato;
use App\Models\Garantia;
use App\Support\Audit;
use App\Support\Correlativo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Orquestador de la generación del contrato de garantía mobiliaria:
 * validar → view-model → render PDF (contrato + Anexos 1 y 2) → archivo en
 * storage + fila en contratos con snapshot congelado, hash y versión.
 * Regenerar produce SIEMPRE una versión nueva — un PDF emitido jamás se pisa.
 */
class GeneradorContrato
{
    public function __construct(
        private readonly ContratoViewModelFactory $factory = new ContratoViewModelFactory,
        private readonly ValidadorContrato $validador = new ValidadorContrato,
    ) {}

    /**
     * Genera y emite el contrato (PDF combinado con Anexos 1 y 2).
     *
     * @throws ValidacionContratoException si hay errores bloqueantes
     */
    public function generar(Garantia $garantia, array $parametros): Contrato
    {
        $errores = $this->validador->validar($garantia, $parametros, paraEmitir: true);
        if ($errores !== []) {
            throw new ValidacionContratoException($errores);
        }

        return DB::transaction(function () use ($garantia, $parametros) {
            $anio = now()->format('Y');
            $numero = sprintf('CGM %s-%04d', $anio, Correlativo::siguiente('Contrato'));
            $version = ((int) Contrato::where('garantia_id', $garantia->id)->max('version')) + 1;

            $parametros['numero'] = $numero;
            $vm = $this->factory->crear($garantia, $parametros);

            $binario = Pdf::loadView('legal.pdf.contrato', ['vm' => $vm, 'paraPdf' => true])
                ->setPaper('a4')
                ->output();

            $path = sprintf(
                'legal/garantia-%d/%s-v%d.pdf',
                $garantia->id,
                str_replace(' ', '-', mb_strtolower($numero)),
                $version
            );
            Storage::disk('public')->put($path, $binario);

            $contrato = Contrato::create([
                'garantia_id' => $garantia->id,
                'credit_id' => $garantia->credit_id,
                'client_id' => $garantia->client_id,
                'numero' => $numero,
                'tipo' => 'garantia_mobiliaria',
                'version' => $version,
                'parametros' => $this->parametrosPersistibles($parametros),
                'datos_snapshot' => $vm->snapshot,
                'estado' => 'emitido',
                'pdf_path' => $path,
                'sha256' => hash('sha256', $binario),
                'generado_por' => auth()->id(),
            ]);

            Audit::log("Generó el contrato {$numero} v{$version} (garantía #{$garantia->id})", $contrato, [
                'sha256' => $contrato->sha256,
            ]);

            return $contrato;
        });
    }

    /**
     * HTML del contrato para la vista previa (iframe), sin persistir nada.
     * Valida en modo borrador (no exige aún voucher ni imagen).
     *
     * @throws ValidacionContratoException si hay errores bloqueantes
     */
    public function previsualizar(Garantia $garantia, array $parametros): string
    {
        $errores = $this->validador->validar($garantia, $parametros, paraEmitir: false);
        if ($errores !== []) {
            throw new ValidacionContratoException($errores);
        }

        $parametros['numero'] = $parametros['numero'] ?? 'BORRADOR';
        $vm = $this->factory->crear($garantia, $parametros);

        return view('legal.pdf.contrato', ['vm' => $vm, 'paraPdf' => false])->render();
    }

    /** Re-render exacto de un contrato emitido, desde su snapshot congelado. */
    public function htmlDesdeContrato(Contrato $contrato): string
    {
        $vm = ContratoViewModelFactory::desdeSnapshot($contrato->datos_snapshot);

        return view('legal.pdf.contrato', ['vm' => $vm, 'paraPdf' => false])->render();
    }

    /** Los parámetros sin la imagen binaria ni claves internas (para la columna JSON). */
    private function parametrosPersistibles(array $parametros): array
    {
        unset($parametros['numero']);

        return $parametros;
    }
}
