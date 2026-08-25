<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehículos (Área Legal). Entidad propia y no un campo del cliente porque:
 *  - un vehículo en garantía puede cambiar de dueño (adjudicación, venta),
 *  - la flota propia de la empresa (papeletas, fase posterior) no tiene
 *    cliente ni crédito asociado → client_id nullable + propietario_tipo.
 * Los campos calcan la ficha técnica que exige el contrato SIGM (placa,
 * motor, serie, categoría, carrocería, combustible...).
 *
 * OJO refresh de BD: tabla NUEVA, no viene del legacy — va en la lista de
 * preservación del runbook (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('propietario_tipo', 20)->default('cliente'); // cliente|empresa|tercero
            $table->string('propietario_nombre')->nullable();           // cuando tipo = tercero
            $table->string('propietario_documento', 15)->nullable();
            $table->string('placa', 10)->unique();
            $table->string('marca', 50)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('nro_motor', 30)->nullable();
            $table->string('nro_serie', 30)->nullable()->index();
            $table->string('categoria', 30)->nullable();
            $table->smallInteger('anio')->nullable();
            $table->string('carroceria', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('combustible', 30)->nullable();
            $table->string('partida_registral', 20)->nullable();
            $table->decimal('valor', 12, 2)->nullable();
            $table->string('estado', 20)->default('activo'); // activo|vendido|adjudicado|baja
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['propietario_tipo', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
