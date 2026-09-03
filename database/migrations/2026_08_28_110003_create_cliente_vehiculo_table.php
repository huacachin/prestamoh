<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copropiedad de vehículos (tramo D, 28/08).
 *
 * `vehiculos.client_id` sigue siendo el propietario principal y NO se toca:
 * este pivote agrega a los COPROPIETARIOS. Sin él, un vehículo no podía
 * pertenecer a dos clientes (placa UNIQUE + client_id único) y los modelos
 * a.3.x / b.3.x con el vehículo compartido entre los dos deudores no se
 * podían emitir — el wizard rechazaba el vehículo del codeudor.
 *
 * Lectura SIEMPRE con fallback: sin filas aquí, el único dueño es
 * vehiculos.client_id. Cero backfill.
 *
 * OJO refresh de BD: tabla NUEVA — va en la lista de preservación del
 * runbook (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_vehiculo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('rol', 20)->default('copropietario');
            $table->timestamps();

            $table->unique(['vehiculo_id', 'client_id']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_vehiculo');
    }
};
