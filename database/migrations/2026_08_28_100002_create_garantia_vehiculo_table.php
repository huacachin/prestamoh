<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot garantía ↔ vehículo (el contrato SIGM admite 1 o 2 bienes). Los datos
 * de "bien futuro" (acta de transferencia con kardex ante notario, aún sin
 * inscripción registral) son de la RELACIÓN — el mismo vehículo puede ser bien
 * futuro en una garantía y bien presente en la siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garantia_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('garantia_id')->constrained('garantias')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos');
            $table->boolean('es_bien_futuro')->default(false);
            $table->string('acta_notarial', 60)->nullable();  // bien futuro
            $table->string('kardex', 20)->nullable();          // ej. 0373-2026
            $table->string('notario', 120)->nullable();
            $table->date('fecha_acta')->nullable();
            $table->tinyInteger('orden')->default(1);
            $table->timestamps();

            $table->unique(['garantia_id', 'vehiculo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garantia_vehiculo');
    }
};
