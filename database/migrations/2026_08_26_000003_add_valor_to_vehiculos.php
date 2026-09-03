<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valor del vehículo (opcional): el Anexo 1 y la cláusula de valor del
 * contrato lo consignan. No estaba entre los 10 campos pedidos para la ficha,
 * por eso llega aparte y nullable — se puede completar al generar el documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->decimal('valor', 12, 2)->nullable()->after('combustible');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn('valor');
        });
    }
};
