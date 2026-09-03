<?php

namespace App\Support\Documentos;

/**
 * Nacionalidades del deudor: catálogo cerrado para el alta y la ficha.
 *
 * INVARIABLE POR GÉNERO — decisión del negocio (28/08). El contrato escribe
 * siempre "DE NACIONALIDAD PERUANO" tanto para un deudor como para una
 * deudora; lo que sí flexiona a su lado es IDENTIFICADO/IDENTIFICADA y el
 * estado civil.
 *
 * El motivo es que las 32 maestras del área NO tienen una regla: escaneadas,
 * dan 68 "PERUANO" contra 6 "PERUANA", y esas 6 están repartidas sin criterio
 * entre modelos masculinos y femeninos (a.1.5 escribe "PERUANA, IDENTIFICADA"
 * para un hombre; a.3 escribe "PERUANO, IDENTIFICADO" para una mujer). Son
 * ejemplos rellenados a mano con erratas, no un patrón. Fijar la forma
 * masculina las vuelve consistentes y coincide con la mayoría y con la fila
 * DEUDORA de "Modulo Contratos. Indicaciones.docx".
 *
 * Si el área decide después que sí debe flexionar, el cambio es una sola
 * línea en clausula-datos (volver a envolver en $g->flex()); pero entonces
 * habrá que decidir caso por caso, porque la regla -o/-a no sirve para
 * ESPAÑOL → ESPAÑOLA ni para invariables como ESTADOUNIDENSE.
 */
final class Nacionalidades
{
    /** @var list<string> */
    public const OPCIONES = ['PERUANO', 'VENEZOLANO'];

    public const DEFECTO = 'PERUANO';

    /**
     * Normaliza al catálogo. Un valor fuera de la lista (cliente migrado,
     * dato metido por SQL) se respeta tal cual: el contrato lo imprime como
     * esté antes que perderlo.
     */
    public static function normalizar(?string $valor): string
    {
        $v = mb_strtoupper(trim((string) $valor));

        return $v !== '' ? $v : self::DEFECTO;
    }

    public static function esConocida(?string $valor): bool
    {
        return in_array(mb_strtoupper(trim((string) $valor)), self::OPCIONES, true);
    }

    /**
     * Opciones + el valor histórico ya guardado, para que editar una ficha
     * vieja no invalide su nacionalidad ni la cambie en silencio. Mismo
     * criterio que TiposCredito::paraValor().
     *
     * @return list<string>
     */
    public static function paraValor(?string $actual): array
    {
        $v = mb_strtoupper(trim((string) $actual));

        return ($v !== '' && ! in_array($v, self::OPCIONES, true))
            ? [...self::OPCIONES, $v]
            : self::OPCIONES;
    }
}
