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
 *  - sexo:     'M' | 'F' | null — para qué sexo de deudor es el modelo. Es lo
 *              único que separa a los 10 pares Deudor/Deudora (a.1↔a.2,
 *              a.1.1↔a.2.1, …), que en todo lo demás son idénticos. null en
 *              los de dos deudores y en los de empresa, que no lo distinguen.
 *
 * Las otras 5 dimensiones dan 22 combinaciones únicas: los 32 modelos son 22
 * contratos distintos más los 10 duplicados por género. Por eso el asesor NO
 * elige entre 32 nombres — responde las decisiones del negocio y resolver()
 * deduce la clave, con el sexo saliendo de la ficha del cliente.
 */
final class ModelosContrato
{
    public const MODELOS = [
        // ─── Serie a.1 — GPS, un deudor ───
        'a1' => ['nombre' => 'a.1 GPS. Deudor - Contrato SIGM', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => 'M'],
        'a11' => ['nombre' => 'a.1.1 GPS. Deudor - Deposito tercero', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero', 'sexo' => 'M'],
        'a12' => ['nombre' => 'a.1.2 GPS. Deudor. 2 Bienes Presentes', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio', 'sexo' => 'M'],
        'a13' => ['nombre' => 'a.1.3 GPS. Deudor. 2 Bienes Futuros', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2futuros', 'destino' => 'propio', 'sexo' => 'M'],
        'a14' => ['nombre' => 'a.1.4 GPS. Deudor. Bien futuro', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro', 'destino' => 'propio', 'sexo' => 'M'],
        'a15' => ['nombre' => 'a.1.5 GPS. Deudor. Bien futuro y bien presente', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro_presente', 'destino' => 'propio', 'sexo' => 'M'],
        'a16' => ['nombre' => 'a.1.6 Custodia. Deudor - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => true, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => 'M'],

        // ─── Serie a.2 — GPS, una deudora ───
        'a2' => ['nombre' => 'a.2 GPS. Deudora - Contrato SIGM', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => 'F'],
        'a21' => ['nombre' => 'a.2.1 GPS. Deudora - Deposito Tercero', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero', 'sexo' => 'F'],
        'a22' => ['nombre' => 'a.2.2 GPS. Deudora. 2 Bienes Presentes', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio', 'sexo' => 'F'],
        'a23' => ['nombre' => 'a.2.3 GPS. Deudora. 2 Bienes Futuros', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => '2futuros', 'destino' => 'propio', 'sexo' => 'F'],
        'a24' => ['nombre' => 'a.2.4 GPS. Deudora. Bien futuro', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro', 'destino' => 'propio', 'sexo' => 'F'],
        'a25' => ['nombre' => 'a.2.5 GPS. Deudora. Bien futuro y bien presente', 'personas' => 1, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro_presente', 'destino' => 'propio', 'sexo' => 'F'],
        'a26' => ['nombre' => 'a.2.6 Custodia. Deudora - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => true, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => 'F'],

        // ─── Serie a.3 — GPS, dos deudores ───
        'a3' => ['nombre' => 'a.3 GPS. Deudores - Contrato SIGM', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => null],
        'a31' => ['nombre' => 'a.3.1 GPS. Deudores - Deposito Tercero', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero', 'sexo' => null],
        'a32' => ['nombre' => 'a.3.2 GPS. Deudores. 2 Bienes Presentes', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio', 'sexo' => null],
        'a33' => ['nombre' => 'a.3.3 GPS. Deudores. 2 Bienes Futuros', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => '2futuros', 'destino' => 'propio', 'sexo' => null],
        'a34' => ['nombre' => 'a.3.4 GPS. Deudores. Bien futuro', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro', 'destino' => 'propio', 'sexo' => null],
        'a35' => ['nombre' => 'a.3.5 GPS. Deudores. Bien futuro y bien presente', 'personas' => 2, 'gps' => true, 'custodia' => false, 'bienes' => 'futuro_presente', 'destino' => 'propio', 'sexo' => null],
        'a36' => ['nombre' => 'a.3.6 Custodia. Deudores - Contrato SIGM', 'personas' => 2, 'gps' => false, 'custodia' => true, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => null],

        // ─── Serie a.4 — GPS, persona jurídica ───
        'a4' => ['nombre' => 'a.4 GPS. Deudor Empresa', 'personas' => 'empresa', 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => null],
        'a41' => ['nombre' => 'a.4.1 GPS. Deudor Empresa - Deposito Gerente', 'personas' => 'empresa', 'gps' => true, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'gerente', 'sexo' => null],

        // ─── Serie b.1 — sin GPS, un deudor ───
        'b1' => ['nombre' => 'b.1 Deudor - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => 'M'],
        'b11' => ['nombre' => 'b.1.1 Deudor - Deposito tercero', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero', 'sexo' => 'M'],
        'b12' => ['nombre' => 'b.1.2 Deudor. 2 Bienes Presentes', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio', 'sexo' => 'M'],

        // ─── Serie b.2 — sin GPS, una deudora ───
        'b2' => ['nombre' => 'b.2 Deudora - Contrato SIGM', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => 'F'],
        'b21' => ['nombre' => 'b.2.1 Deudora - Deposito tercero', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero', 'sexo' => 'F'],
        'b22' => ['nombre' => 'b.2.2 Deudora. 2 Bienes Presentes', 'personas' => 1, 'gps' => false, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio', 'sexo' => 'F'],

        // ─── Serie b.3 — sin GPS, dos deudores ───
        'b3' => ['nombre' => 'b.3 Deudores - Contrato SIGM', 'personas' => 2, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'propio', 'sexo' => null],
        'b31' => ['nombre' => 'b.3.1 Deudores - Deposito tercero', 'personas' => 2, 'gps' => false, 'custodia' => false, 'bienes' => 'presente', 'destino' => 'tercero', 'sexo' => null],
        'b32' => ['nombre' => 'b.3.2 Deudores. 2 Bienes Presentes', 'personas' => 2, 'gps' => false, 'custodia' => false, 'bienes' => '2presentes', 'destino' => 'propio', 'sexo' => null],
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

    /**
     * Modelos aplicables a un cliente según su sexo y su naturaleza.
     *
     * Es el filtro que pedía el negocio: un cliente hombre no debe poder
     * elegir "a.2 Deudora", y una empresa solo tiene a.4 / a.4.1. Los de dos
     * deudores (sexo null) aparecen siempre que el contrato lleve codeudor,
     * porque el plural no distingue género.
     *
     * @param  string|null  $sexo  'M'|'F' de clients.sexo
     * @param  bool  $juridica  el cliente es persona jurídica (RUC)
     * @param  bool  $conCodeudor  se anexó una segunda persona al contrato
     * @return array<string, array<string, mixed>> clave => preset
     */
    public static function aplicables(?string $sexo, bool $juridica = false, bool $conCodeudor = false): array
    {
        $sexo = in_array(mb_strtoupper(trim((string) $sexo)), ['M', 'F'], true)
            ? mb_strtoupper(trim((string) $sexo))
            : null;

        return array_filter(self::MODELOS, function (array $m) use ($sexo, $juridica, $conCodeudor) {
            if ($juridica) {
                return $m['personas'] === 'empresa';
            }
            if ($m['personas'] === 'empresa') {
                return false;
            }
            if ($conCodeudor) {
                return $m['personas'] === 2;
            }

            return $m['personas'] === 1 && ($sexo === null || $m['sexo'] === $sexo);
        });
    }

    /**
     * Deduce la clave del modelo desde las decisiones del negocio.
     * Devuelve null cuando la combinación NO EXISTE en las maestras — que es
     * información útil, no un error: p. ej. no hay depósito a tercero con dos
     * vehículos, ni bien futuro sin GPS, ni empresa con codeudor.
     *
     * @param  string  $garantia  'gps' | 'sin_gps' | 'custodia'
     */
    public static function resolver(
        int|string $personas,
        ?string $sexo,
        string $garantia,
        string $bienes,
        string $destino,
    ): ?string {
        $sexo = in_array(mb_strtoupper(trim((string) $sexo)), ['M', 'F'], true)
            ? mb_strtoupper(trim((string) $sexo))
            : null;
        $gps = $garantia === 'gps';
        $custodia = $garantia === 'custodia';

        foreach (self::MODELOS as $clave => $m) {
            if ($m['personas'] !== $personas
                || $m['gps'] !== $gps
                || $m['custodia'] !== $custodia
                || $m['bienes'] !== $bienes
                || $m['destino'] !== $destino) {
                continue;
            }
            // Solo los de UN deudor distinguen sexo; el resto lo traen null.
            if ($m['sexo'] !== null && $m['sexo'] !== $sexo) {
                continue;
            }

            return $clave;
        }

        return null;
    }

    /**
     * ¿El modelo elegido es coherente con el sexo del cliente? Sirve para que
     * el guard no deje emitir "a.2 Deudora" sobre un cliente con sexo M — el
     * defecto que hacía que el contrato saliera en masculino sin avisar.
     */
    public static function coherenteConSexo(string $clave, ?string $sexo): bool
    {
        $modelo = self::MODELOS[$clave] ?? null;
        if ($modelo === null || $modelo['sexo'] === null) {
            return true;
        }

        return $modelo['sexo'] === mb_strtoupper(trim((string) $sexo));
    }
}
