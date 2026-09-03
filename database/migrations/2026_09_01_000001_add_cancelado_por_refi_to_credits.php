<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `cancelado_por_refi` = el flag `refi` del legacy, migrado 1:1.
 *
 * El legacy distingue dos cosas que nuestro `refinanciado` FUSIONA:
 *   - refi=1        → el crédito fue CANCELADO POR una refinanciación
 *   - cod_rem='REF' → el crédito NACIÓ DE una refinanciación
 *
 * El reporte de caja 1 del legacy aplica su fórmula de settlement solo con
 * refi=1. Nosotros ramificábamos con `refinanciado` (la fusión), así que un
 * crédito nacido de refi que se cancelaba de un pago real caía en la rama
 * equivocada y su capital desaparecía del reporte — en agosto/2026 eso
 * restó S/ 178,102.40 (créditos 29275 y 29279, renovaciones del 24-25/08)
 * frente al "Capital T." del legacy. Decisión del negocio (01/09): el
 * reporte se homologa al legacy.
 *
 * `refinanciado` NO cambia de semántica: lo siguen usando badges, reportes
 * de desembolsos y el resto del sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->boolean('cancelado_por_refi')->default(false)->after('refinanciado');
        });
    }

    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('cancelado_por_refi');
        });
    }
};
