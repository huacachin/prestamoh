<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expedientes judiciales del Área Legal (reemplaza el Excel "A. Registro de
 * seguimiento. Expedientes Judiciales" de ~100 procesos). Cada CUADERNO es
 * una fila: el principal y su cautelar comparten número base del PJ pero
 * difieren en el dígito de cuaderno (04388-2024-0-... / 04388-2024-1-...);
 * el cautelar cuelga del principal vía expediente_padre_id.
 *
 *  - via: mecanismo de recupero (captura vehicular / embargo por inscripción /
 *    descuento por planilla) — en el Excel eran HOJAS; condonado/cancelado
 *    son ESTADOS, no vías.
 *  - estado: catálogos distintos por cuaderno (ver modelo).
 *  - exp_interno: código interno del crédito/cliente (columna "Exp." del
 *    Excel, correlativo de clients.expediente) — clave de matching del import.
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expedientes_judiciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('credit_id')->nullable()->constrained('credits')->nullOnDelete();
            $table->foreignId('garantia_id')->nullable()->constrained('garantias')->nullOnDelete();
            $table->string('exp_interno', 10)->nullable()->index();
            $table->string('nro_expediente', 35)->unique();
            $table->string('cuaderno', 10)->default('principal'); // principal|cautelar
            $table->foreignId('expediente_padre_id')->nullable()->constrained('expedientes_judiciales')->nullOnDelete();
            $table->string('juzgado', 150)->nullable();
            $table->string('distrito_judicial', 60)->nullable();
            $table->string('materia', 100)->nullable();
            $table->string('proceso', 40)->nullable();  // Ejecutivo | Único de ejecución | Sumarísimo
            $table->string('juez', 120)->nullable();
            $table->string('secretario', 120)->nullable();
            $table->string('via', 25)->nullable();          // captura_vehicular|inscripcion|planilla|otra
            $table->string('forma_medida', 20)->nullable(); // cautelar: secuestro|inscripcion
            $table->string('bien_descripcion')->nullable(); // cautelar: "VEHÍCULO placa X" / "CASA"
            $table->decimal('monto_pretension', 12, 2)->nullable();
            $table->string('estado', 25)->default('en_tramite');
            $table->foreignId('asesor_responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->boolean('requiere_revision')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['cuaderno', 'estado']);
            $table->index('asesor_responsable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes_judiciales');
    }
};
