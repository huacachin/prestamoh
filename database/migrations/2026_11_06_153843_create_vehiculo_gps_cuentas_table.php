<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta de la plataforma del GPS de cada vehículo (06/11/2026, Trello "Agregar una opción
 * en la cochera para registrar la cuenta del GPS"; Antony: una por placa, con un botón que
 * abre un modal al costado de "Agregar ubicación"). La contraseña va cifrada con APP_KEY.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_gps_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->unique()->constrained('vehiculos')->cascadeOnDelete();
            $table->string('aplicativo', 100);
            $table->string('enlace', 500)->nullable();
            $table->string('usuario', 150);
            $table->text('contrasena'); // cast 'encrypted': el texto cifrado pasa de 150 caracteres
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculo_gps_cuentas');
    }
};
