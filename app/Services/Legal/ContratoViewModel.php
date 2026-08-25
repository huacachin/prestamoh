<?php

namespace App\Services\Legal;

use App\Support\Legal\Genero;
use App\Support\Legal\Ordinales;

/**
 * View-model inmutable que consumen las vistas Blade del contrato de garantía
 * mobiliaria y sus anexos (resources/views/legal/pdf/). Se construye desde los
 * datos vivos (ContratoViewModelFactory::crear) o desde el snapshot congelado
 * de un contrato ya emitido (::desdeSnapshot) — ambos caminos producen
 * exactamente el mismo documento.
 *
 * Convenciones para los partials:
 *  - $vm->g          → Genero del CONJUNTO de deudores (menciones colectivas).
 *  - $vm->deudores   → lista; cada deudor trae su propio 'g' (Genero individual)
 *                      y, si es persona jurídica, 'gerente' con su 'g'.
 *  - $vm->ord        → Ordinales de las cláusulas ACTIVAS ($vm->clausulas);
 *                      referencias cruzadas SIEMPRE via $vm->ord->de('clave').
 *  - $vm->monto('x') → "S/ 7,000.00 (SIETE MIL CON 00/100 SOLES)".
 */
final class ContratoViewModel
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
    public function esJuridica(): bool
    {
        return ($this->deudores[0]['esJuridica'] ?? false) === true;
    }

    /** Constante de legal_settings ya resuelta (acreedor, apoderada, usuaria_sigm, representantes...) */
    public function constante(string $clave, mixed $default = null): mixed
    {
        return $this->constantes[$clave] ?? $default;
    }
}
