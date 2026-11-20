<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehículo que garantiza un préstamo, cuando no es uno recién registrado para él (20/11/2026, Antony:
 * "un cliente puede prendar el mismo vehículo si ya ha pagado parte de su préstamo"). El paso a paso
 * del expediente es de cada préstamo: un préstamo nuevo vuelve a pedir el vehículo, y aquí queda dicho
 * que es el mismo de antes. También guarda que la cuenta del GPS de esa placa se confirmó para ESTE
 * préstamo, aunque ya estuviera registrada de uno anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained('credits')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            // false = la fila solo confirma el GPS de un vehículo que ya era de este préstamo.
            $table->boolean('prendado')->default(true);
            $table->timestamp('gps_confirmado_at')->nullable();
            $table->foreignId('gps_confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['credit_id', 'vehiculo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_vehiculo');
    }
};
