<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vencimientos documentarios del vehículo (SOAT, revisión técnica y
 * habilitación ATU) — el Excel de flota los lleva en columnas sin ninguna
 * alerta (hay un SOAT anotado "VENCIDO" a mano). La campana legal los
 * levanta para la flota propia activa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->date('soat_vence')->nullable()->after('valor');
            $table->date('revision_tecnica_vence')->nullable()->after('soat_vence');
            $table->date('habilitacion_atu_vence')->nullable()->after('revision_tecnica_vence');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn(['soat_vence', 'revision_tecnica_vence', 'habilitacion_atu_vence']);
        });
    }
};
