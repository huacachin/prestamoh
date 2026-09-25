<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes de GPS de los vehículos en garantía (26/09/2026): en la pestaña
 * GPS del cliente, debajo de Casa, se registran reportes de ubicación de sus
 * vehículos: uno o varios puntos con etiqueta y horario de estadía. El texto
 * para WhatsApp se genera desde estos datos. Las fotos van en
 * vehiculo_gps_reporte_fotos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_gps_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->string('placa', 12); // se guarda aparte: el reporte sobrevive al vehículo
            $table->dateTime('fecha'); // fecha Y hora del reporte
            $table->string('inicio_desde', 5)->nullable(); // HH:MM
            $table->string('inicio_hasta', 5)->nullable();
            $table->string('fin_desde', 5)->nullable();
            $table->string('fin_hasta', 5)->nullable();
            $table->string('domicilio_direccion')->nullable();
            $table->string('domicilio_link', 500)->nullable();
            $table->json('puntos'); // [{etiqueta, estadia_desde, estadia_hasta, direccion, link}]
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_gps_reportes');
    }
};
