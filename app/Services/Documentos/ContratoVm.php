<?php

namespace App\Services\Documentos;

use App\Support\Documentos\Genero;
use App\Support\Documentos\Ordinales;

/**
 * View-model inmutable que consumen las vistas Blade del contrato de garantía
 * mobiliaria (resources/views/documentos/pdf/). Se construye desde los datos
 * vivos (GeneradorContrato::construirSnapshot) o desde el snapshot congelado
 * de un DocumentoCliente ya emitido (GeneradorContrato::vmDesdeSnapshot) —
 * ambos caminos producen exactamente el mismo documento.
 *
 * Convenciones para los partials:
 *  - $vm->g          → Genero del CONJUNTO de deudores (menciones colectivas).
 *  - $vm->deudores   → lista; cada deudor trae su propio 'g' (Genero individual)
 *                      y, si es persona jurídica, 'gerente' con su 'g'.
 *  - $vm->ord        → Ordinales de las cláusulas ACTIVAS ($vm->clausulas);
 *                      referencias cruzadas SIEMPRE via $vm->ord->de('clave').
 *  - $vm->monto('x') → "S/ 7,000.00 (SIETE MIL CON 00/100 SOLES)".
 */
final class ContratoVm
{
    public function __construct(
        public readonly string $numero,
        public readonly Genero $g,
        public readonly array $deudores,
        public readonly array $constantes,
        public readonly Ordinales $ord,
        public readonly array $clausulas,
        public readonly bool $gps,
        public readonly bool $custodia,
        public readonly string $destino,
        public readonly ?array $tercero,
        public readonly array $bienes,
        public readonly array $montos,
        public readonly int $numCuotas,
        public readonly string $frecuencia,
        public readonly string $fechaLarga,
        public readonly string $fechaSimple,
        public readonly ?array $voucher,
        public readonly array $cronograma,
        public readonly ?string $clausulasAdicionales,
        public readonly array $snapshot,
    ) {}

    /** "S/ 7,000.00 (SIETE MIL CON 00/100 SOLES)" — claves: valor_bien, obligacion, monto_maximo, cuota */
    public function monto(string $clave): string
    {
        $m = $this->montos[$clave] ?? null;
        if (! $m) {
            return '—';
        }

        return "S/ {$m['cifra']} ({$m['letras']} SOLES)";
    }

    /** Solo la cifra: "S/ 7,000.00" */
    public function montoCifra(string $clave): string
    {
        return isset($this->montos[$clave]) ? "S/ {$this->montos[$clave]['cifra']}" : '—';
    }

    /** true si el contrato es de persona jurídica (empresa deudora) */
    /**
     * Título oficial del contrato según la variante (a.1 / a.1.4 / a.1.5 /
     * a.1.6). ÚNICA fuente: lo consumen el encabezado y la cita del Anexo 1
     * en la cláusula de ejecución — antes esta última hardcodeaba el título
     * base y en los modelos de bien futuro citaba un documento con OTRO
     * nombre que el de su propia carátula.
     */
    public function titulo(): string
    {
        $futuros = array_filter($this->bienes, fn ($b) => ! empty($b['esFuturo']));
        $todosFuturos = $futuros !== [] && count($futuros) === count($this->bienes);
        $mixto = $futuros !== [] && ! $todosFuturos;

        $nucleo = match (true) {
            $this->custodia => 'CONSTITUCIÓN DE GARANTÍA MOBILIARIA CON POSESIÓN',
            $todosFuturos => 'PRE-CONSTITUCIÓN DE GARANTÍA MOBILIARIA',
            $mixto => 'CONSTITUCIÓN DE GARANTÍA MOBILIARIA SOBRE BIEN FUTURO Y BIEN PRESENTE',
            default => 'CONSTITUCIÓN DE GARANTÍA MOBILIARIA',
        };

        return "CONTRATO DE CRÉDITO VEHICULAR CON {$nucleo} EN EL SISTEMA INFORMATIVO DE GARANTÍAS MOBILIARIAS – SIGM";
    }

    public function esJuridica(): bool
    {
        return ($this->deudores[0]['esJuridica'] ?? false) === true;
    }

    /** Constante de config('documentos') ya resuelta (acreedor, apoderada, usuaria_sigm, representantes...) */
    public function constante(string $clave, mixed $default = null): mixed
    {
        return $this->constantes[$clave] ?? $default;
    }
}
