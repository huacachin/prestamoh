<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actuaciones de un expediente judicial: resoluciones (el PJ las numera en
 * letras: "CUATRO", "QUINCE" — se conserva el texto), escritos de ambas
 * partes, notificaciones y oficios. El timeline del Show reemplaza las
 * columnas Resolución/Seguimiento del Excel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actuaciones_judiciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_judiciales')->cascadeOnDelete();
            $table->string('tipo', 25); // resolucion|escrito_demandante|escrito_demandado|notificacion|oficio|otro
            $table->string('numero', 40)->nullable();
            $table->date('fecha');
            $table->string('sumilla', 500);
            $table->text('detalle')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expediente_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actuaciones_judiciales');
    }
};
