<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * incomes.date y expenses.date deben aceptar NULL.
 *
 * Motivo: los registros legacy con fecha inválida (0000-00-00) deben quedar
 * con fecha NULL — NO con la fecha de hoy. En prod con sql_mode STRICT un
 * insert de NULL en columna NOT NULL falla; esto lo previene.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['incomes', 'expenses'] as $table) {
            if (Schema::hasColumn($table, 'date')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->date('date')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // No revertimos a NOT NULL: podría haber filas con date NULL legítimas.
    }
};
