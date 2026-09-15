<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve el piso del correlativo 2026 a 229 (15/09).
 *
 * La primera versión del correlativo INCREMENTABA esta fila al emitir. Con
 * ella se emitió el Anexo 1 v5 del crédito #29388, que tomó el 2026-230 y
 * movió el piso a 230; lo anularon tres minutos después, pero el piso se
 * quedó arriba y el siguiente habría salido 2026-231, saltándose un número.
 *
 * Desde el cambio siguiente el piso ya no se mueve: es una constante, el
 * último número que el área numeró a mano. Para 2026 es 229, así que se
 * restituye. Los números ocupados salen ahora de los anexos VIVOS, no de esta
 * fila, así que bajarla no puede repetir ninguno.
 */
return new class extends Migration
{
    private const TIPO = 'AnexoLegal:2026';

    private const PISO = 229;

    public function up(): void
    {
        DB::table('correlativos')
            ->where('tipo', self::TIPO)
            ->where('correl', '>', self::PISO)
            ->update(['correl' => self::PISO, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // El piso es una constante del área: no hay a qué volver.
    }
};
