<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plazos y vencimientos de un expediente judicial (estructura calcada de
 * compromisos_pago para que la campana funcione igual). Hoy los vencimientos
 * viven en texto libre dentro del estado del Excel ("OFICIO ENTREGADO VENCE
 * 09/03/2026") y nadie recibe alerta; aquí son datos: la campana legal los
 * levanta mientras cumplido_at sea NULL.
 *
 * OJO refresh de BD: tabla NUEVA — lista de preservación del runbook
 * (docs/INSTALACION.md §8.6b), junto a expedientes_judiciales y
 * actuaciones_judiciales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plazos_judiciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_judiciales')->cascadeOnDelete();
            $table->foreignId('actuacion_id')->nullable()->constrained('actuaciones_judiciales')->nullOnDelete();
            $table->string('descripcion');
            $table->date('fecha_vencimiento')->index();
            $table->timestamp('cumplido_at')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plazos_judiciales');
    }
};
