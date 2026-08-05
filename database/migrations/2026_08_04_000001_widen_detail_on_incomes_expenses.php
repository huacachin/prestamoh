<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El campo "detalle/motivo" de caja (incomes.detail / expenses.detail) quedó en
 * varchar(255), pero el input del formulario (maxlength=500) y la validación
 * (max:500) permiten hasta 500 caracteres. Con MySQL en modo estricto, escribir
 * 256–500 caracteres pasaba la validación pero el INSERT fallaba con "Data too
 * long for column 'detail'" (SQLSTATE 22001), y el error se perdía en un flash
 * que Livewire no renderiza sin redirección → el usuario no veía nada.
 *
 * Se pasa a TEXT para que la columna nunca sea el límite (la validación max:500
 * sigue acotando la entrada). Casos reales: notas de "Notificación de cobranza"
 * que listan varios expedientes + detalle de movilidad superan los 255.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->text('detail')->nullable()->change();
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->text('detail')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Volver a varchar(255) truncaría datos ya guardados (>255) y
        // re-introduciría el bug. Se deja como no-op consciente.
    }
};
