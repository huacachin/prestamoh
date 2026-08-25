<?php

namespace App\Support\Legal;

/**
 * Concordancia gramatical de los documentos legales: el contrato de garantía
 * mobiliaria flexiona género y número en ~40 puntos (EL DEUDOR / LA DEUDORA /
 * LOS DEUDORES, identificado/identificada, PROPIETARIO/PROPIETARIA...). Hoy
 * eso se corrige a mano en 32 plantillas Word — y los propios modelos traen
 * errores ("GINA ... IDENTIFICADO").
 *
 * Uso: una instancia POR DEUDOR (menciones individuales: "identificada con
 * DNI N° ...") y una instancia DEL CONJUNTO (menciones colectivas: "LOS
 * DEUDORES declaran..."). La persona jurídica se redacta como femenino
 * singular (LA DEUDORA = la empresa), con el gerente flexionado aparte.
 */
final class Genero
{
    private function __construct(
        private readonly bool $femenino,
        private readonly bool $plural,
    ) {}

    /** @param string $genero 'M'|'F' (clients.sexo) */
    public static function de(string $genero, bool $juridica = false): self
    {
        return new self($juridica || mb_strtoupper(trim($genero)) === 'F', false);
    }

    /** @param list<string> $generos géneros individuales, ej. ['M','F'] — plural masculino si hay al menos un 'M' */
    public static function conjunto(array $generos): self
    {
        $generos = array_map(fn ($g) => mb_strtoupper(trim((string) $g)), $generos);

        if (count($generos) <= 1) {
            return self::de($generos[0] ?? 'M');
        }

        return new self(! in_array('M', $generos, true), true);
    }

    public function esFemenino(): bool
    {
        return $this->femenino;
    }

    public function esPlural(): bool
    {
        return $this->plural;
    }

    /**
     * Flexión regular de una palabra terminada en -o (o -O):
     * flex('identificado') → identificada / identificados / identificadas.
     * Respeta mayúsculas: flex('PROPIETARIO') → PROPIETARIA / PROPIETARIOS.
     */
    public function flex(string $baseMasculina): string
    {
        $ultima = mb_substr($baseMasculina, -1);
        $mayuscula = ($ultima === mb_strtoupper($ultima) && $ultima !== mb_strtolower($ultima));

        $out = $baseMasculina;
        if ($this->femenino && in_array(mb_strtolower($ultima), ['o'], true)) {
            $out = mb_substr($out, 0, -1).($mayuscula ? 'A' : 'a');
        }
        if ($this->plural) {
            $out .= $mayuscula ? 'S' : 's';
        }

        return $out;
    }

    /** verbo('declara', 'declaran') → según número */
    public function verbo(string $singular, string $plural): string
    {
        return $this->plural ? $plural : $singular;
    }

    /** Selección explícita de las 4 formas (para palabras irregulares) */
    public function forma(string $ms, string $fs, string $mp, string $fp): string
    {
        return match (true) {
            ! $this->plural && ! $this->femenino => $ms,
            ! $this->plural && $this->femenino => $fs,
            $this->plural && ! $this->femenino => $mp,
            default => $fp,
        };
    }

    // ─── Flexiones con nombre propio (las del contrato) ───

    /** EL DEUDOR / LA DEUDORA / LOS DEUDORES / LAS DEUDORAS */
    public function deudor(): string
    {
        return $this->forma('EL DEUDOR', 'LA DEUDORA', 'LOS DEUDORES', 'LAS DEUDORAS');
    }

    /** DEUDOR / DEUDORA / DEUDORES / DEUDORAS (sin artículo, para títulos) */
    public function deudorSolo(): string
    {
        return $this->forma('DEUDOR', 'DEUDORA', 'DEUDORES', 'DEUDORAS');
    }

    public function identificado(): string
    {
        return $this->flex('identificado');
    }

    public function propietario(): string
    {
        return $this->flex('PROPIETARIO');
    }

    public function domiciliado(): string
    {
        return $this->flex('domiciliado');
    }

    public function obligado(): string
    {
        return $this->flex('obligado');
    }

    public function notificado(): string
    {
        return $this->flex('notificado');
    }

    public function senalado(): string
    {
        return $this->femenino
            ? 'señalada'.($this->plural ? 's' : '')
            : 'señalado'.($this->plural ? 's' : '');
    }

    public function el(): string
    {
        return $this->forma('el', 'la', 'los', 'las');
    }

    public function del(): string
    {
        return $this->forma('del', 'de la', 'de los', 'de las');
    }

    public function al(): string
    {
        return $this->forma('al', 'a la', 'a los', 'a las');
    }

    public function senor(): string
    {
        return $this->forma('el señor', 'la señora', 'los señores', 'las señoras');
    }

    public function don(): string
    {
        return $this->forma('don', 'doña', 'los señores', 'las señoras');
    }

    public function quien(): string
    {
        return $this->verbo('quien', 'quienes');
    }

    public function mismo(): string
    {
        return $this->forma('el mismo', 'la misma', 'los mismos', 'las mismas');
    }

    public function su(): string
    {
        return $this->verbo('su', 'sus');
    }

    public function firma(): string
    {
        return $this->verbo('firma', 'firman');
    }

    public function declara(): string
    {
        return $this->verbo('declara', 'declaran');
    }
}
