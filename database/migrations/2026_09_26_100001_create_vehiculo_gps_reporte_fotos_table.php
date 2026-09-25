<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fotos adjuntas a cada reporte de GPS de vehículo (26/09/2026). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_gps_reporte_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('vehiculo_gps_reportes')->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('thumb_path', 500)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_gps_reporte_fotos');
    }
};
