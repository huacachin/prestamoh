<?php

namespace App\Services\Documentos;

use App\Support\Documentos\Genero;
use App\Support\Documentos\Ordinales;
use App\Support\Documentos\Resaltado;
use App\Support\NumerosEnLetras;
use Illuminate\Support\HtmlString;

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
        /** Nivel de MaquetaContrato con el que el PDF cupo en el tope de hojas (0 = maqueta normal). */
        public readonly int $ajuste = 0,
        /** true solo en la previa del wizard: v() pinta en rojo los datos variables. */
        public readonly bool $resaltar = false,
    ) {}

    /**
     * Dato VARIABLE del contrato (ficha, crédito, vehículos, wizard), listo
     * para imprimir con {{ }}: en la previa del wizard va en rojo (span
     * .variable; pedido de Antony, 16/10/2026: "en la vista previa los datos
     * variables en rojo") y en el PDF y el Word sale el texto escapado tal
     * cual, byte a byte igual que antes. Las constantes (acreedor, apoderada,
     * usuaria SIGM, representantes) NO pasan por aquí: van en negro.
     * Concatenado dentro de un @php (nucleo, bienLinea…) el string resultante
     * ya lleva HTML y se imprime con {!! !!}.
     */
    public function v(mixed $texto): HtmlString
    {
        return Resaltado::dato($texto, $this->resaltar);
    }

    /** "S/ 7,000.00 (SIETE MIL CON 00/100 SOLES)" — claves: valor_bien, obligacion, monto_maximo, cuota */
    public function monto(string $clave): string
    {
        $m = $this->montos[$clave] ?? null;
        if (! $m) {
            return '—';
        }

        return "S/ {$m['cifra']} ({$m['letras']} SOLES)";
    }

    /**
     * El mismo formato, para un importe suelto que no vive en $montos.
     *
     * Lo usa la cláusula del valor cuando hay VARIOS bienes: el maestro
     * (18/09, Desktop/contrato1.jpeg) exige el valor de CADA vehículo por
     * separado, no la suma —"el valor de los bienes no debe ir sumado"—, y
     * ese importe es el de cada bien, no una clave de $montos.
     */
    public function montoDe(?float $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return 'S/ '.number_format($valor, 2).' ('.NumerosEnLetras::monto($valor).' SOLES)';
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

    /**
     * Perfil de las cláusulas COMUNES (ejecución, GPS, representantes y
     * prohibición) cuando la garantía son dos bienes: 'presentes' (maestras
     * a.x.2 / b.x.2), 'futuros' (a.x.3) o 'mixto' (a.x.5). null con un solo
     * bien: el texto de a.1.
     *
     * También es null en los contratos emitidos ANTES del 19/11/2026 (snapshot
     * sin 'redaccion'): hasta ese día esas cuatro cláusulas salían en singular
     * aunque hubiera dos vehículos, y su Word debe seguir saliendo igual que
     * el PDF que se firmó.
     *
     * @return 'presentes'|'futuros'|'mixto'|null
     */
    public function perfilBienes(): ?string
    {
        if (count($this->bienes) < 2 || (int) ($this->snapshot['redaccion'] ?? 1) < 2) {
            return null;
        }

        $futuros = count(array_filter($this->bienes, fn ($bien) => ! empty($bien['esFuturo'])));

        return match ($futuros) {
            0 => 'presentes',
            count($this->bienes) => 'futuros',
            default => 'mixto',
        };
    }

    /**
     * Texto de una mención al vehículo o al bien según cuántos son: $uno con
     * un solo bien y $dos con dos. $dos es un texto único cuando las tres
     * maestras de dos bienes coinciden, o un mapa por perfil cuando no — el
     * perfil que el mapa no trae se queda con $uno, que es lo que hace su
     * maestra (p. ej. la de dos presentes no pluraliza "LA TRANSFERENCIA DE
     * EL VEHÍCULO" y la de dos futuros sí).
     *
     * @param  string|array{presentes?: string, futuros?: string, mixto?: string}  $dos
     */
    public function segunBienes(string $uno, string|array $dos): string
    {
        $perfil = $this->perfilBienes();
        if ($perfil === null) {
            return $uno;
        }

        return is_array($dos) ? ($dos[$perfil] ?? $uno) : $dos;
    }

    /** Constante de config('documentos') ya resuelta (acreedor, apoderada, usuaria_sigm, representantes...) */
    public function constante(string $clave, mixed $default = null): mixed
    {
        return $this->constantes[$clave] ?? $default;
    }
}
