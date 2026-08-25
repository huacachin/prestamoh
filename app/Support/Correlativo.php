<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Correlativos atómicos sobre la tabla `correlativos` (tipo único → correl).
 * A diferencia del patrón legacy (leer en mount() y actualizar al guardar,
 * que tiene carrera bajo concurrencia y ya produjo expedientes duplicados,
 * ver docs/PENDIENTES.md), aquí la lectura y el avance ocurren en UNA
 * transacción con lockForUpdate.
 *
 * Los flujos existentes (tipos 'Cliente' y 'Credito') NO se migran aquí a
 * propósito — este helper nace con el Área Legal (tipo 'Contrato') y queda
 * disponible para migrarlos después.
 */
class Correlativo
{
    public static function siguiente(string $tipo): int
    {
        return DB::transaction(function () use ($tipo) {
            $fila = DB::table('correlativos')->where('tipo', $tipo)->lockForUpdate()->first();

            if (! $fila) {
                DB::table('correlativos')->insert([
                    'tipo' => $tipo, 'correl' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return 1;
            }

            $siguiente = (int) $fila->correl + 1;
            DB::table('correlativos')->where('tipo', $tipo)
                ->update(['correl' => $siguiente, 'updated_at' => now()]);

            return $siguiente;
        });
    }
}
