<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el correlativo del Anexo 1 del área legal (15/09).
 *
 * El área numera sus anexos "2026-NNN" y va por el 229: el próximo que emita
 * el sistema debe ser el 2026-230. Sin esta siembra el sistema arrancaría en
 * 2026-1 y chocaría con los que ya están firmados y registrados.
 *
 * `insertOrIgnore` para que correrla dos veces no reinicie la cuenta.
 */
return new class extends Migration
{
    private const TIPO = 'AnexoLegal:2026';

    private const ULTIMO_USADO = 229;

    public function up(): void
    {
        DB::table('correlativos')->insertOrIgnore([
            'tipo' => self::TIPO,
            'correl' => self::ULTIMO_USADO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Solo se borra si nadie emitió después: si la cuenta avanzó, el
        // número ya salió impreso en documentos firmados y no se toca.
        DB::table('correlativos')
            ->where('tipo', self::TIPO)
            ->where('correl', self::ULTIMO_USADO)
            ->delete();
    }
};
