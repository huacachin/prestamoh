<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlace caja1 -> caja3 (espejo contable de movimientos "Fijos").
 *
 * Legacy: entrada3.nroentrada = entrada.identrada ; ingreso3.nroentrada = ingreso.identrada.
 * En Laravel guardamos parent_id en la fila caja=3 apuntando al id de la fila caja=1,
 * para poder sincronizar (editar/eliminar) la copia espejo como hace el legacy.
 *
 * Los registros históricos migrados NO tienen este enlace (parent_id NULL); el reporte
 * cash-statistics suma por caja=3 y no lo necesita. Solo aplica a movimientos nuevos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('incomes', 'parent_id')) {
            Schema::table('incomes', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->index()->after('caja');
            });
        }
        if (! Schema::hasColumn('expenses', 'parent_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->index()->after('caja');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('incomes', 'parent_id')) {
            Schema::table('incomes', function (Blueprint $table) {
                $table->dropColumn('parent_id');
            });
        }
        if (Schema::hasColumn('expenses', 'parent_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('parent_id');
            });
        }
    }
};
