<?php

namespace App\Support\Documentos;

use Illuminate\Support\Facades\DB;

/**
 * Correlativo propio del área legal para el Anexo 1: "2026-230" (15/09).
 *
 * El Anexo 1 mostraba el id interno del crédito, pero el maestro del área
 * lleva SU numeración —"Nro. 2026-142" en la plantilla— que es la que citan
 * en sus registros y ante la notaría. Van por el 229, así que el siguiente
 * es el 230.
 *
 * La serie es POR AÑO: en 2027 vuelve a empezar. Se guarda una fila por año
 * en `correlativos` (tipo "AnexoLegal:2026"), que es donde el sistema ya
 * lleva los correlativos de cliente y crédito.
 */
class CorrelativoAnexo
{
    /** Número que se asignaría, SIN consumirlo (para la vista previa). */
    public static function proximo(?int $anio = null): string
    {
        $anio ??= (int) now()->year;

        return self::formatear($anio, self::actual($anio) + 1);
    }

    /**
     * Consume y devuelve el siguiente. Va con bloqueo porque dos personas
     * generando a la vez no pueden llevarse el mismo número: el documento se
     * firma y se registra, así que un duplicado se arrastra hacia afuera.
     *
     * Debe llamarse DENTRO de la transacción que emite el documento.
     */
    public static function siguiente(?int $anio = null): string
    {
        $anio ??= (int) now()->year;
        $tipo = self::tipo($anio);

        $fila = DB::table('correlativos')->where('tipo', $tipo)->lockForUpdate()->first();

        if (! $fila) {
            DB::table('correlativos')->insert([
                'tipo' => $tipo, 'correl' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return self::formatear($anio, 1);
        }

        $siguiente = (int) $fila->correl + 1;
        DB::table('correlativos')->where('tipo', $tipo)->update([
            'correl' => $siguiente, 'updated_at' => now(),
        ]);

        return self::formatear($anio, $siguiente);
    }

    private static function actual(int $anio): int
    {
        return (int) (DB::table('correlativos')->where('tipo', self::tipo($anio))->value('correl') ?? 0);
    }

    private static function tipo(int $anio): string
    {
        return "AnexoLegal:{$anio}";
    }

    /** "2026-230". Sin ceros a la izquierda, como en los maestros del área. */
    private static function formatear(int $anio, int $n): string
    {
        return "{$anio}-{$n}";
    }
}
