<?php

namespace App\Support\Documentos;

use InvalidArgumentException;

/**
 * Catálogo de los 32 modelos de contrato de garantía mobiliaria del área
 * legal, con sus NOMBRES REALES (los de la carpeta de plantillas Word). Cada
 * modelo es un preset de parámetros del contrato; el texto legal es el mismo
 * motor de cláusulas (partials) para todos.
 *
 * Claves = prefijo del archivo Word ('a.1' → 'a1', 'a.1.1' → 'a11'...).
 *
 * Parámetros del preset:
 *  - personas: 1 | 2 | 'empresa' — cuántos deudores redacta el contrato.
 *  - gps:      cláusula de instalación de GPS (serie a.*, salvo los .6).
 *  - custodia: cláusula de custodia/posesión (solo a.1.6, a.2.6, a.3.6).
 *  - bienes:   'presente' | 'futuro' | '2presentes' | '2futuros' | 'futuro_presente'.
 *  - destino:  'propio' | 'tercero' | 'gerente' — a qué cuenta fue el desembolso
 *              (gobierna la cláusula de constancia de entrega).
 *
 * El GÉNERO no es del preset: se toma del sexo real del cliente. Los modelos
 * Deudor/Deudora comparten parámetros pero se conservan como entradas
 * separadas porque el área los reconoce por su nombre.
 */
final class ModelosContrato
{
    public const MODELOS = [
        // ─── Serie a.1 — GPS, un deudor ───
        'a1' => ['nombre' => 'a.1 GPS. Deudor - Contrato SIGM', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio'],
        'a11' => ['nombre' => 'a.1.1 GPS. Deudor - Deposito tercero', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero'],
        'a12' => ['nombre' => 'a.1.2 GPS. Deudor. 2 Bienes Presentes', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio'],
        'a13' => ['nombre' => 'a.1.3 GPS. Deudor. 2 Bienes Futuros', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2futuros', 'destino' => 'propio'],
        'a14' => ['nombre' => 'a.1.4 GPS. Deudor. Bien futuro', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro', 'destino' => 'propio'],
        'a15' => ['nombre' => 'a.1.5 GPS. Deudor. Bien futuro y bien presente', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro_presente', 'destino' => 'propio'],
        'a16' => ['nombre' => 'a.1.6 Custodia. Deudor - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => true, 'bienes' => 'presente', 'destino' => 'propio'],

        // ─── Serie a.2 — GPS, una deudora ───
        'a2' => ['nombre' => 'a.2 GPS. Deudora - Contrato SIGM', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio'],
        'a21' => ['nombre' => 'a.2.1 GPS. Deudora - Deposito Tercero', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero'],
        'a22' => ['nombre' => 'a.2.2 GPS. Deudora. 2 Bienes Presentes', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio'],
        'a23' => ['nombre' => 'a.2.3 GPS. Deudora. 2 Bienes Futuros', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2futuros', 'destino' => 'propio'],
        'a24' => ['nombre' => 'a.2.4 GPS. Deudora. Bien futuro', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro', 'destino' => 'propio'],
        'a25' => ['nombre' => 'a.2.5 GPS. Deudora. Bien futuro y bien presente', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro_presente', 'destino' => 'propio'],
        'a26' => ['nombre' => 'a.2.6 Custodia. Deudora - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => true, 'bienes' => 'presente', 'destino' => 'propio'],

        // ─── Serie a.3 — GPS, dos deudores ───
        'a3' => ['nombre' => 'a.3 GPS. Deudores - Contrato SIGM', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio'],
        'a31' => ['nombre' => 'a.3.1 GPS. Deudores - Deposito Tercero', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero'],
        'a32' => ['nombre' => 'a.3.2 GPS. Deudores. 2 Bienes Presentes', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio'],
        'a33' => ['nombre' => 'a.3.3 GPS. Deudores. 2 Bienes Futuros', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => '2futuros', 'destino' => 'propio'],
        'a34' => ['nombre' => 'a.3.4 GPS. Deudores. Bien futuro', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro', 'destino' => 'propio'],
        'a35' => ['nombre' => 'a.3.5 GPS. Deudores. Bien futuro y bien presente', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro_presente', 'destino' => 'propio'],
        'a36' => ['nombre' => 'a.3.6 Custodia. Deudores - Contrato SIGM', 'personas' => 2, 'gps' => false, 'custodia' => true, 'bienes' => 'presente', 'destino' => 'propio'],

        // ─── Serie a.4 — GPS, persona jurídica ───
        'a4' => ['nombre' => 'a.4 GPS. Deudor Empresa', 'personas' => 'empresa', 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio'],
        'a41' => ['nombre' => 'a.4.1 GPS. Deudor Empresa - Deposito Gerente', 'personas' => 'empresa', 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'gerente'],

        // ─── Serie b.1 — sin GPS, un deudor ───
        'b1' => ['nombre' => 'b.1 Deudor - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio'],
        'b11' => ['nombre' => 'b.1.1 Deudor - Deposito tercero', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero'],
        'b12' => ['nombre' => 'b.1.2 Deudor. 2 Bienes Presentes', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio'],

        // ─── Serie b.2 — sin GPS, una deudora ───
        'b2' => ['nombre' => 'b.2 Deudora - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio'],
        'b21' => ['nombre' => 'b.2.1 Deudora - Deposito tercero', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero'],
        'b22' => ['nombre' => 'b.2.2 Deudora. 2 Bienes Presentes', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio'],

        // ─── Serie b.3 — sin GPS, dos deudores ───
        'b3' => ['nombre' => 'b.3 Deudores - Contrato SIGM', 'personas' => 2, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio'],
        'b31' => ['nombre' => 'b.3.1 Deudores - Deposito tercero', 'personas' => 2, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero'],
        'b32' => ['nombre' => 'b.3.2 Deudores. 2 Bienes Presentes', 'personas' => 2, 'gps' => false, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio'],
    ];

    /**
     * es_futuro por slot de vehículo, para cada valor de 'bienes'.
     *
     * Mapa explícito a propósito: la versión anterior parseaba la cadena con
     * una regex que exigía un dígito, así que 'futuro' y 'futuro_presente'
     * no enganchaban y caían a un único bien presente — los 6 modelos .4/.5
     * se emitían como el contrato base. El ORDEN importa: en a.1.5 el
     * vehículo 1 es el futuro y el 2 el presente.
     */
    public const SLOTS_BIENES = [
        'presente' => [false],
        'futuro' => [true],
        '2presentes' => [false, false],
        '2futuros' => [true, true],
        'futuro_presente' => [true, false],
    ];

    /** @return array<int, bool> es_futuro por slot; 1 bien presente si el valor es desconocido. */
    public static function slots(string $bienes): array
    {
        return self::SLOTS_BIENES[$bienes] ?? [false];
    }

    /** @return array<string, array{nombre: string, personas: int|string, gps: bool, custodia: bool, bienes: string, destino: string}> */
    public static function todos(): array
    {
        return self::MODELOS;
    }

    /** @return array{nombre: string, personas: int|string, gps: bool, custodia: bool, bienes: string, destino: string} */
    public static function get(string $clave): array
    {
        if (! isset(self::MODELOS[$clave])) {
            throw new InvalidArgumentException("Modelo de contrato desconocido: '{$clave}'.");
        }

        return self::MODELOS[$clave];
    }

    /** @return array<string, string> clave => nombre real (para el selector del wizard) */
    public static function nombres(): array
    {
        return array_map(fn (array $m) => $m['nombre'], self::MODELOS);
    }
}
