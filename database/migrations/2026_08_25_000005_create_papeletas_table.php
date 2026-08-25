<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Papeletas de tránsito de la flota (reemplaza las hojas Pap.1/Pap.2 del
 * Excel, donde la misma papeleta se digitaba hasta en 6 hojas de 3 archivos).
 * Una fila por papeleta/acta, con su N° como clave natural (unique por
 * entidad) y el responsable de pago que el área ya clasifica a mano
 * (S/ 34,961 recaen en conductores sin trazabilidad).
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papeletas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehiculo_id')->constrained('vehiculos');
            $table->string('entidad', 15); // SAT|ATU|SAT_CALLAO|SUTRAN
            $table->string('nro_papeleta', 30);
            $table->string('codigo_falta', 10)->nullable();
            $table->smallInteger('puntos')->nullable();
            $table->date('fecha_infraccion')->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->string('responsable_pago', 15)->nullable(); // propietario|empresa|prop_empresa|conductor
            $table->string('conductor_nombre')->nullable();
            $table->string('conductor_documento', 15)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->boolean('requiere_revision')->default(false);
            $table->text('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['entidad', 'nro_papeleta']);
            $table->index(['estado', 'fecha_infraccion']);
            $table->index('vehiculo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeletas');
    }
};
