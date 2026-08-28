<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `nacionalidad` es el único dato común que la guía del área legal exige del
 * deudor y que no tenía columna: Clients\Create la declaraba como propiedad y
 * la pintaba readonly, pero el insert nunca la escribía, así que
 * GeneradorContrato la hardcodeaba a 'PERUANO' — un cliente con carné de
 * extranjería salía peruano en el contrato.
 *
 * Se guarda SIEMPRE en masculino ('PERUANO', 'VENEZOLANO'): la cláusula de
 * datos la flexiona según el sexo del deudor. Nullable para no tocar las
 * filas migradas del legacy (los contratos son solo para clientes nuevos y
 * el guard de emisión cubre el resto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('nacionalidad', 50)->nullable()->default('PERUANO')->after('sexo');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('nacionalidad');
        });
    }
};
