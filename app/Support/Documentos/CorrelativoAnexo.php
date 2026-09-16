<?php

namespace App\Support\Documentos;

use Illuminate\Support\Facades\DB;

/**
 * Correlativo propio del área legal para el Anexo 1: "2026-230" (15/09).
 *
 * El Anexo 1 mostraba el id interno del crédito, pero el maestro del área
 * lleva SU numeración —"Nro. 2026-142" en la plantilla— que es la que citan
 * en sus registros y ante la notaría.
 *
 * NO es un contador que solo avanza: el número se toma de los que están EN
 * USO. Si un anexo se anula, su número vuelve al ruedo y lo hereda el
 * siguiente que se emita, así la serie no deja huecos (pedido de Antony,
 * 15/09). Anular es definitivo —no hay des-anular— así que reutilizar no
 * puede producir dos documentos vivos con el mismo número.
 *
 * El piso vive en `correlativos` (tipo "AnexoLegal:2026" = 229, el último que
 * el área usó a mano): el sistema nunca emite por debajo de él. La serie es
 * por año y en 2027 arranca sola desde su propio piso.
 */
class CorrelativoAnexo
{
    /** Número que se asignaría, SIN consumirlo (para la vista previa). */
    public static function proximo(?int $anio = null): string
    {
        $anio ??= (int) now()->year;

        return self::formatear($anio, self::primerLibre($anio));
    }

    /**
     * Asigna el siguiente. Va con bloqueo porque dos personas emitiendo a la
     * vez no pueden llevarse el mismo: el documento se firma y se registra,
     * así que un duplicado se arrastra hacia afuera.
     *
     * Debe llamarse DENTRO de la transacción que emite el documento; el
     * número queda "tomado" cuando esa transacción guarda el documento con su
     * columna `correlativo`.
     */
    public static function siguiente(?int $anio = null): string
    {
        $anio ??= (int) now()->year;

        // La fila del piso hace de cerrojo: serializa la asignación aunque no
        // se modifique. Se crea si el año aún no la tiene.
        $tipo = self::tipo($anio);
        if (! DB::table('correlativos')->where('tipo', $tipo)->exists()) {
            DB::table('correlativos')->insertOrIgnore([
                'tipo' => $tipo, 'correl' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('correlativos')->where('tipo', $tipo)->lockForUpdate()->first();

        return self::formatear($anio, self::primerLibre($anio));
    }

    /** El menor número por encima del piso que ningún anexo VIVO esté usando. */
    private static function primerLibre(int $anio): int
    {
        $enUso = self::enUso($anio);

        $n = self::piso($anio) + 1;
        while (in_array($n, $enUso, true)) {
            $n++;
        }

        return $n;
    }

    /**
     * Números tomados por anexos 1 vivos del año. Los anulados NO cuentan:
     * su número se reutiliza.
     *
     * @return list<int>
     */
    private static function enUso(int $anio): array
    {
        return DB::table('documentos_cliente')
            ->where('tipo', 'anexo1')
            ->where('estado', '!=', 'anulado')
            ->whereNotNull('correlativo')
            ->where('correlativo', 'like', $anio.'-%')
            ->pluck('correlativo')
            ->map(fn (string $c) => (int) substr($c, strlen((string) $anio) + 1))
            ->filter(fn (int $n) => $n > 0)
            ->values()->all();
    }

    /** Último número que el área usó a mano antes del sistema. */
    private static function piso(int $anio): int
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
