<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehículo del cliente (pedido 26/08): la ficha técnica se captura de forma
 * OPCIONAL al registrar el cliente. Tabla propia y no columnas en clients
 * porque un cliente puede cambiar de vehículo y la placa debe ser única en
 * el sistema. Todos los campos técnicos son opcionales salvo la placa, que
 * es la clave del registro (sin placa no se crea el vehículo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('placa', 10)->unique();
            $table->string('marca', 50)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('nro_motor', 30)->nullable();
            $table->string('nro_serie', 30)->nullable();
            $table->string('categoria', 30)->nullable();
            $table->string('anio_modelo', 10)->nullable(); // a veces dual: "2017/2018"
            $table->string('carroceria', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('combustible', 30)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
